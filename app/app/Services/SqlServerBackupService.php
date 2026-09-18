<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SqlServerBackupService
{
    private const BACKUP_ROOT = '/mnt/backups';

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
                        HAS_PERMS_BY_NAME(DB_NAME(), 'DATABASE', 'BACKUP DATABASE') AS can_backup,
                        HAS_PERMS_BY_NAME(NULL, NULL, 'ADMINISTER BULK OPERATIONS') AS can_bulk
                     FROM sys.database_files"
                );
                $history = DB::connection($connection)->selectOne(
                    "SELECT TOP 1 backup_finish_date, backup_size, compressed_backup_size
                     FROM msdb.dbo.backupset
                     WHERE database_name = ? AND type = 'D'
                     ORDER BY backup_finish_date DESC",
                    [$database]
                );
                $estimatedBytes = (int) ($history->backup_size ?? round((float) ($row->size_mb ?? 0) * 1048576));

                $databases[$database] = [
                    'database' => $database,
                    'size_mb' => (float) ($row->size_mb ?? 0),
                    'estimated_bytes' => $estimatedBytes,
                    'last_backup_at' => $history->backup_finish_date ?? null,
                    'backup_path' => (string) ($row->backup_path ?? ''),
                    'can_backup' => (bool) ($row->can_backup ?? false),
                    'can_bulk' => (bool) ($row->can_bulk ?? false),
                    'connection_ok' => true,
                    'stripe_count' => $this->stripeCount((float) ($row->size_mb ?? 0)),
                    'error' => null,
                ];
            } catch (Throwable $e) {
                $databases[$database] = [
                    'database' => $database,
                    'size_mb' => 0,
                    'estimated_bytes' => 0,
                    'last_backup_at' => null,
                    'backup_path' => '',
                    'can_backup' => false,
                    'can_bulk' => false,
                    'connection_ok' => false,
                    'stripe_count' => 1,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $rootReady = is_dir(self::BACKUP_ROOT) && is_writable(self::BACKUP_ROOT);
        $freeBytes = $rootReady ? (disk_free_space(self::BACKUP_ROOT) ?: 0) : 0;
        foreach ($databases as &$db) {
            $requiredBytes = (int) ceil(($db['estimated_bytes'] ?? 0) * 1.05);
            $db['space_ok'] = $rootReady && $requiredBytes > 0 && $freeBytes >= $requiredBytes;
            $db['ready'] = $db['connection_ok'] && $db['can_backup'] && $db['can_bulk']
                && $db['backup_path'] !== '' && $db['space_ok'];
        }
        unset($db);

        return [
            'root' => self::BACKUP_ROOT,
            'unc' => '\\janus\Backups',
            'root_ready' => $rootReady,
            'free_bytes' => $freeBytes,
            'databases' => $databases,
            'ready' => $rootReady && collect($databases)->every(fn (array $db) => $db['ready']),
        ];
    }

    public function recentBackups(int $limit = 20): array
    {
        if (! is_dir(self::BACKUP_ROOT)) {
            return [];
        }

        $entries = [];
        foreach (glob(self::BACKUP_ROOT.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $manifestPath = $directory.'/manifest.json';
            if (! is_file($manifestPath)) {
                continue;
            }
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (! is_array($manifest)) {
                continue;
            }
            $entries[] = [
                'directory' => basename($directory),
                'database' => $manifest['database'] ?? 'unbekannt',
                'created_at' => $manifest['created_at'] ?? null,
                'stripe_count' => $manifest['stripe_count'] ?? 0,
                'bytes' => $manifest['bytes'] ?? 0,
                'status' => $manifest['status'] ?? 'unknown',
            ];
        }

        usort($entries, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return array_slice($entries, 0, $limit);
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
        if (! $status['can_bulk']) {
            throw new RuntimeException('Die serverweite Berechtigung zum Lesen der erzeugten Backup-Dateien fehlt.');
        }
        if ($status['backup_path'] === '') {
            throw new RuntimeException('CARDEA liefert keinen Standard-Backupordner.');
        }
        if (! ($status['space_ok'] ?? false)) {
            throw new RuntimeException('Auf der Janus-Backupablage steht für diese Datenbank nicht genügend freier Speicher zur Verfügung.');
        }
        if (! is_dir(self::BACKUP_ROOT) || ! is_writable(self::BACKUP_ROOT)) {
            throw new RuntimeException('Die Janus-Backupablage ist nicht schreibbar.');
        }

        $stripeCount = $status['stripe_count'];
        $stamp = CarbonImmutable::now()->format('Y-m-d_H-i-s');
        $folderName = $stamp.'_'.$database;
        $targetDir = self::BACKUP_ROOT.'/'.$folderName;
        if (! mkdir($targetDir, 0777, true) && ! is_dir($targetDir)) {
            throw new RuntimeException('Der Zielordner für das Backup konnte nicht angelegt werden.');
        }
        @chmod($targetDir, 0777);

        $serverRoot = rtrim(str_replace('/', '\\', $status['backup_path']), '\\');
        $serverFiles = [];
        $targetFiles = [];

        for ($i = 1; $i <= $stripeCount; $i++) {
            $suffix = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $count = str_pad((string) $stripeCount, 2, '0', STR_PAD_LEFT);
            $serverFiles[] = $serverRoot.'\\JANUS_'.$database.'_'.$suffix.'_of_'.$count.'.bak';
            $targetFiles[] = $targetDir.'/'.$database.'_'.$suffix.'_of_'.$count.'.bak';
        }

        $manifest = [
            'database' => $database,
            'created_at' => CarbonImmutable::now()->toIso8601String(),
            'actor' => $actor,
            'source_server' => 'CARDEA',
            'source_backup_path' => $status['backup_path'],
            'stripe_count' => $stripeCount,
            'status' => 'running',
            'files' => array_map('basename', $targetFiles),
            'bytes' => 0,
        ];
        $this->writeManifest($targetDir, $manifest);

        try {
            $devices = implode(', ', array_map(
                fn (string $path) => "DISK = N'".$this->sqlLiteral($path)."'",
                $serverFiles
            ));
            $name = 'Janus manual backup '.$database.' '.$stamp;
            $sql = "BACKUP DATABASE [{$database}] TO {$devices}
                    WITH COPY_ONLY, INIT, COMPRESSION, CHECKSUM,
                    NAME = N'".$this->sqlLiteral($name)."', STATS = 10";

            DB::connection($connectionName)->unprepared($sql);

            $bytes = 0;
            foreach ($serverFiles as $index => $serverFile) {
                $bytes += $this->streamServerFile(
                    $connectionName,
                    $serverFile,
                    $targetFiles[$index],
                );
            }

            $manifest['status'] = 'ok';
            $manifest['bytes'] = $bytes;
            $manifest['completed_at'] = CarbonImmutable::now()->toIso8601String();
            $this->writeManifest($targetDir, $manifest);

            Log::notice('Manual SQL Server backup completed', [
                'database' => $database,
                'folder' => $folderName,
                'stripes' => $stripeCount,
                'bytes' => $bytes,
                'actor' => $actor,
            ]);

            return $manifest + ['folder' => $folderName];
        } catch (Throwable $e) {
            $manifest['status'] = 'error';
            $manifest['error'] = $e->getMessage();
            $manifest['completed_at'] = CarbonImmutable::now()->toIso8601String();
            $this->writeManifest($targetDir, $manifest);
            Log::error('Manual SQL Server backup failed', [
                'database' => $database,
                'folder' => $folderName,
                'actor' => $actor,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function streamServerFile(string $connectionName, string $serverFile, string $targetFile): int
    {
        $config = config('database.connections.'.$connectionName);
        $server = (string) $config['host'].(!empty($config['port']) ? ','.$config['port'] : '');
        $options = [
            'Database' => (string) ($config['database'] ?? 'master'),
            'UID' => (string) $config['username'],
            'PWD' => (string) $config['password'],
            'Encrypt' => true,
            'TrustServerCertificate' => true,
            'ReturnDatesAsStrings' => true,
        ];

        $conn = sqlsrv_connect($server, $options);
        if ($conn === false) {
            throw new RuntimeException('SQLSRV-Verbindung zum Lesen der Backup-Datei fehlgeschlagen: '.$this->sqlsrvErrors());
        }

        try {
            $query = "SELECT BulkColumn FROM OPENROWSET(BULK N'".$this->sqlLiteral($serverFile)."', SINGLE_BLOB) AS BackupFile";
            $stmt = sqlsrv_query($conn, $query);
            if ($stmt === false || ! sqlsrv_fetch($stmt)) {
                throw new RuntimeException('Backup-Datei konnte nicht vom SQL Server gelesen werden: '.$this->sqlsrvErrors());
            }

            $stream = sqlsrv_get_field($stmt, 0, SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY));
            if (! is_resource($stream)) {
                throw new RuntimeException('SQL Server lieferte keinen binären Datenstrom für das Backup.');
            }

            $partial = $targetFile.'.partial';
            $out = fopen($partial, 'wb');
            if ($out === false) {
                throw new RuntimeException('Temporäre Backup-Datei auf Janus konnte nicht geöffnet werden.');
            }
            try {
                $copied = stream_copy_to_stream($stream, $out);
                if ($copied === false) {
                    throw new RuntimeException('Backup-Datenstrom konnte nicht vollständig auf Janus geschrieben werden.');
                }
            } finally {
                fclose($out);
                fclose($stream);
            }

            if (! rename($partial, $targetFile)) {
                @unlink($partial);
                throw new RuntimeException('Temporäre Backup-Datei konnte nicht finalisiert werden.');
            }
            @chmod($targetFile, 0666);

            return filesize($targetFile) ?: 0;
        } finally {
            sqlsrv_close($conn);
        }
    }

    private function stripeCount(float $sizeMb): int
    {
        if ($sizeMb <= 1500) {
            return 1;
        }

        return min(64, max(2, (int) ceil($sizeMb / 1500)));
    }

    private function writeManifest(string $targetDir, array $manifest): void
    {
        file_put_contents(
            $targetDir.'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
        @chmod($targetDir.'/manifest.json', 0666);
    }

    private function sqlLiteral(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    private function sqlsrvErrors(): string
    {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL) ?: [];
        return implode(' | ', array_map(
            fn (array $error) => ($error['SQLSTATE'] ?? '').' '.($error['code'] ?? '').' '.($error['message'] ?? ''),
            $errors
        ));
    }
}
