<?php

namespace App\Jobs;

use App\Services\SqlServerBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSqlServerBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400;
    public int $tries = 1;

    public function __construct(public string $database, public string $actor)
    {
        $this->onConnection('database');
        $this->onQueue('backups');
    }

    public function handle(SqlServerBackupService $service): void
    {
        $service->create($this->database, $this->actor);
    }
}
