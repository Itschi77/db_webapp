<?php

namespace App\Providers;

use App\Models\AdminConnectionProfile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            if (! Schema::hasTable('admin_connection_profiles')) {
                return;
            }

            foreach (AdminConnectionProfile::where('active', true)->where('last_test_status', 'ok')->get() as $profile) {
                $this->applyAdminConnection($profile);
            }
        } catch (Throwable) {
            // Environment configuration remains the safe fallback if the app DB is unavailable.
        }
    }

    private function applyAdminConnection(AdminConnectionProfile $profile): void
    {
        $databaseMap = [
            'sql.accountings' => 'sqlsrv_accountings',
            'sql.domains' => 'sqlsrv_domains',
            'sql.topsnetdb_safe' => 'sqlsrv_topsnetdb_safe',
        ];

        if (isset($databaseMap[$profile->key]) && $profile->type === 'sqlserver') {
            $connection = $databaseMap[$profile->key];
            config([
                "database.connections.{$connection}.host" => $profile->host,
                "database.connections.{$connection}.port" => $profile->port ?: 1433,
                "database.connections.{$connection}.database" => $profile->database,
                "database.connections.{$connection}.username" => $profile->username,
                "database.connections.{$connection}.password" => $profile->secret,
            ]);
            return;
        }

        if ($profile->key === 'smtp.primary' && $profile->type === 'smtp') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $profile->host,
                'mail.mailers.smtp.port' => $profile->port ?: 25,
                'mail.mailers.smtp.username' => $profile->username,
                'mail.mailers.smtp.password' => $profile->secret,
                'mail.mailers.smtp.scheme' => ($profile->options['tls'] ?? false) ? 'tls' : null,
            ]);
            return;
        }

        if ($profile->key === 'storage.midas' && $profile->type === 'filesystem') {
            config([
                'filesystems.disks.midas.driver' => 'local',
                'filesystems.disks.midas.root' => $profile->host,
                'filesystems.disks.midas.throw' => true,
            ]);
            return;
        }

        config(["services.admin_connections.{$profile->key}" => [
            'type' => $profile->type,
            'host' => $profile->host,
            'port' => $profile->port,
            'database' => $profile->database,
            'username' => $profile->username,
            'secret' => $profile->secret,
            'options' => $profile->options,
        ]]);
    }
}
