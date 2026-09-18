<?php

use App\Services\InvoiceConsistencyCheckService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('invoice:check-consistency', function (
    InvoiceConsistencyCheckService $service
) {
    $report = $service->run();

    $this->info(
        'Rechnungsprüfung abgeschlossen: '.count($report['issues']).' Auffälligkeiten.'
    );
})->purpose('Prüft die Rechnungsdaten rein lesend auf aktuelle Inkonsistenzen');

Schedule::command('invoice:check-consistency')
    ->dailyAt('08:00')
    ->timezone('Europe/Berlin')
    ->withoutOverlapping();
