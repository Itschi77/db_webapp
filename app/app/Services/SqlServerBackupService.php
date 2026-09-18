<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SqlServerBackupService
{
    private const DATABASES = [
        'accountings' => 'sqlsrv_accountings',
        'domains' => 'sqlsrv_domains',
        'topsnetdb_safe' => 'sqlsrv_topsnetdb_safe',
    ];

    public function status(): array
    {
        $databases = [];

        foreach (self::DATABASES as $database => $connection) {
            try {
                $row = DB::connection($connection)->selectOne(
                    "SELECT
                        DB_NAME() AS db,
                        CAST(SERVERPROPERTY('InstanceDefaultBackupPath') AS nvarchar(4000)) AS backup_path,
                        CAST(SUM(size) * 8.0 / 1024 AS decimal(12,1)) AS size_mb,
                        HAS_PERMS_BY_NAME(DB_NAME(), 'DATABASE', 'BACKUP DATABASE') AS can_backup
                     FROM sys.database_files"
                );

                $history = DB::connection($connection)->selectOne(
                    "SELECT TOP 1
                        backup_finish_date,
                        backup_size,
                        compressed_backup_size
                     FROM msdb.dbo.backupset
                     WHERE database_name = ? AND type = 'D'
                     ORDER BY backup_finish_date DESC",
                    [$database]
                );

                $databases[$database] = [
                    'database' => $database,
                    'size_mb' => (float) ($row->size_mb ?? 0),
                    'backup_path' => (string) ($row->backup_path ?? ''),
                    'can_backup' => (bool) ($row->can_backup ?? false),
                    'connection_ok' => true,
                    'last_backup_at' => $history->backup_finish_date ?? null,
                    'last_backup_bytes' => (int) ($history->backup_size ?? 0),
                    'last_compressed_bytes' => (int) ($history->compressed_backup_size ?? 0),
                    'error' => null,
                ];
            } catch (Throwable $e) {
                $databases[$database] = [
                    'database' => $database,
                    'size_mb' => 0,
                    'backup_path' => '',
                    'can_backup' => false,
                    'connection_ok' => false,
                    'last_backup_at' => null,
                    'last_backup_bytes' => 0,
                    'last_compressed_bytes' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        }

        foreach ($databases as &$db) {
            $db['ready'] = $db['connection_ok'] && $db['can_backup'] && $db['backup_path'] !== '';
        }
        unset($db);

        return [
            'server' => 'CARDEA',
            'databases' => $databases,
            'ready' => collect($databases)->every(fn (array $db) => $db['ready']),
        ];
    }

    public function recentBackups(int $limit = 20): array
    {
        $connection = DB::connection('sqlsrv_accountings');

        $rows = $connection->select(
            "SELECT TOP {$limit}
                bs.database_name,
                bs.name AS backup_name,
                bs.backup_start_date,
                bs.backup_finish_date,
                bs.backup_size,
                bs.compressed_backup_size,
                bs.is_copy_only,
                bmf.physical_device_name
             FROM msdb.dbo.backupset bs
             INNER JOIN msdb.dbo.backupmediafamily bmf
                 ON bmf.media_set_id = bs.media_set_id
             WHERE bs.database_name IN ('accountings','domains','topsnetdb_safe')
               AND bs.type = 'D'
             ORDER BY bs.backup_finish_date DESC"
        );

        return array_map(fn ($row) => [
            'database' => (string) $row->database_name,
            'name' => (string) ($row->backup_name ?? ''),
            'started_at' => $row->backup_start_date,
            'completed_at' => $row->backup_finish_date,
            'bytes' => (int) ($row->backup_size ?? 0),
            'compressed_bytes' => (int) ($row->compressed_backup_size ?? 0),
            'copy_only' => (bool) ($row->is_copy_only ?? false),
            'path' => (string) ($row->physical_device_name ?? ''),
        ], $rows);
    }

    public function create(string $database, string $actor): array
    {
        if (! isset(self::DATABASES[$database])) {
            throw new RuntimeException('Unbekannte Datenbank für Backup.');
        }

        $connectionName = self::DATABASES[$database];
        $status = $this->status()['databases'][$database];

        if (! $status['connection_ok']) {
            throw new RuntimeException('SQL-Verbindung fehlgeschlagen: '.$status['error']);
        }
        if (! $status['can_backup']) {
            throw new RuntimeException('Dem SQL-Login janus_connect fehlt BACKUP DATABASE auf '.$database.'.');
        }
        if ($status['backup_path'] === '') {
            throw new RuntimeException('CARDEA liefert keinen Standard-Backupordner.');
        }

        $stamp = CarbonImmutable::now(config('app.timezone'))->format('Ymd_His');
        $serverRoot = rtrim(str_replace('/', '\\', $status['backup_path']), '\\');
        $serverFile = $serverRoot.'\\DB-Webapp_'.$database.'_'.$stamp.'.bak';
        $backupName = 'DB-Webapp manual backup | '.$database.' | '.$actor.' | '.$stamp;

        try {
            $sql = "BACKUP DATABASE [{$database}]
                    TO DISK = N'".$this->sqlLiteral($serverFile)."'
                    WITH COPY_ONLY, INIT, COMPRESSION, CHECKSUM,
                    NAME = N'".$this->sqlLiteral($backupName)."'";

            $this->executeBackupSql($connectionName, $sql);

            $completed = DB::connection($connectionName)->selectOne(
                "SELECT TOP 1
                    bs.backup_start_date,
                    bs.backup_finish_date,
                    bs.backup_size,
                    bs.compressed_backup_size,
                    bmf.physical_device_name
                 FROM msdb.dbo.backupset bs
                 INNER JOIN msdb.dbo.backupmediafamily bmf
                     ON bmf.media_set_id = bs.media_set_id
                 WHERE bs.database_name = ?
                   AND bs.type = 'D'
                   AND bs.name = ?
                 ORDER BY bs.backup_finish_date DESC",
                [$database, $backupName]
            );

            if (! $completed) {
                throw new RuntimeException('SQL Server meldete nach dem Backup keinen passenden Eintrag in msdb.');
            }

            $result = [
                'database' => $database,
                'actor' => $actor,
                'backup_name' => $backupName,
                'path' => (string) $completed->physical_device_name,
                'started_at' => $completed->backup_start_date,
                'completed_at' => $completed->backup_finish_date,
                'bytes' => (int) ($completed->backup_size ?? 0),
                'compressed_bytes' => (int) ($completed->compressed_backup_size ?? 0),
            ];

            Log::notice('Manual SQL Server backup completed on CARDEA', $result);

            return $result;
        } catch (Throwable $e) {
            Log::error('Manual SQL Server backup failed on CARDEA', [
                'database' => $database,
                'actor' => $actor,
                'target' => $serverFile,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


    private function executeBackupSql(string $connectionName, string $sql): void
    {
        sqlsrv_configure('WarningsReturnAsErrors', 0);
        $config = config('database.connections.'.$connectionName);
        $server = (string) $config['host'].(! empty($config['port']) ? ','.$config['port'] : '');
        $conn = sqlsrv_connect($server, [
            'Database' => (string) ($config['database'] ?? 'master'),
            'UID' => (string) $config['username'],
            'PWD' => (string) $config['password'],
            'Encrypt' => true,
            'TrustServerCertificate' => true,
            'ReturnDatesAsStrings' => true,
        ]);

        if ($conn === false) {
            throw new RuntimeException('SQLSRV-Verbindung für das Backup fehlgeschlagen: '.$this->sqlsrvErrors());
        }

        try {
            $stmt = sqlsrv_query($conn, $sql, [], ['QueryTimeout' => 0]);
            if ($stmt === false) {
                throw new RuntimeException('BACKUP DATABASE fehlgeschlagen: '.$this->sqlsrvErrors());
            }

            try {
                while (true) {
                    $next = sqlsrv_next_result($stmt);
                    if ($next === null) {
                        break;
                    }
                    if ($next === false) {
                        throw new RuntimeException('Fehler beim Abschluss von BACKUP DATABASE: '.$this->sqlsrvErrors());
                    }
                }
            } finally {
                sqlsrv_free_stmt($stmt);
            }
        } finally {
            sqlsrv_close($conn);
        }
    }

    private function sqlsrvErrors(): string
    {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL) ?: [];
        return implode(' | ', array_map(
            fn (array $error) => ($error['SQLSTATE'] ?? '').' '.($error['code'] ?? '').' '.($error['message'] ?? ''),
            $errors
        ));
    }

    private function sqlLiteral(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
