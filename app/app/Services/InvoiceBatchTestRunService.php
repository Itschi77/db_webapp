<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvoiceBatchTestRunService
{
    public function __construct(
        private InvoicePreviewCalculationService $calculationService,
        private InvoiceOrderTestRunService $orderTestRunService,
    ) {}

    public function build(Collection $orders, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $invoiceDate, bool $includeAccountings, string $scope): array
    {
        $db = DB::connection('sqlsrv_accountings');
        $rows = collect();
        $net = 0.0;
        $tax = 0.0;
        $gross = 0.0;
        $taxByRate = [];

        foreach ($orders as $order) {
            try {
                $positions = $db->table('tblAuftragPos')
                    ->where('intAufNr', $order->intAufNr)
                    ->orderBy('intID')
                    ->get([
                        'intID', 'strBeschreibung', 'intMenge', 'fEndpreis', 'fRabattInProzent',
                        'intMwstsatz', 'intAbrechnungsArt', 'intStaffelTyp', 'intStaffelgruppe',
                        'datFakturierAb', 'datFakturierBis', 'datVorberechnenBis', 'boolIstAnbindung',
                    ]);

                $preview = $this->calculationService->calculate($order, $positions, $from, $to, $includeAccountings);
                $run = $this->orderTestRunService->build($order, $preview, $invoiceDate);

                if ($run['status'] === 'ready') {
                    $net += (float) $run['net'];
                    $tax += (float) $run['tax'];
                    $gross += (float) $run['gross'];
                    foreach ($run['taxByRate'] as $rate => $amount) {
                        $key = (string) $rate;
                        $taxByRate[$key] = ($taxByRate[$key] ?? 0.0) + (float) $amount;
                    }
                }

                $rows->push((object) [
                    'order' => $order,
                    'status' => $run['status'],
                    'statusLabel' => $run['statusLabel'],
                    'customerName' => $run['customer']->strName ?? $run['address']->strName ?? ('Kunde '.$order->intKID),
                    'net' => (float) $run['net'],
                    'tax' => (float) $run['tax'],
                    'gross' => (float) $run['gross'],
                    'documentRows' => $run['documentRows']->count(),
                    'issues' => $run['issues'],
                    'warnings' => $run['warnings'],
                ]);
            } catch (Throwable $e) {
                report($e);
                $rows->push((object) [
                    'order' => $order,
                    'status' => 'blocked',
                    'statusLabel' => 'Testlauf technisch fehlgeschlagen',
                    'customerName' => 'Kunde '.$order->intKID,
                    'net' => 0.0,
                    'tax' => 0.0,
                    'gross' => 0.0,
                    'documentRows' => 0,
                    'issues' => collect(['Technischer Fehler bei der lesenden Berechnung dieses Auftrags.']),
                    'warnings' => collect(),
                ]);
            }
        }

        $counts = $rows->countBy('status');
        return [
            'scope' => $scope,
            'scopeLabel' => match ($scope) {
                'kunde' => 'Kunden-Testlauf',
                'auftrag' => 'Auftrags-Testlauf',
                default => 'Gesamtlauf',
            },
            'rows' => $rows,
            'orderCount' => $rows->count(),
            'customerCount' => $orders->pluck('intKID')->unique()->count(),
            'readyCount' => (int) ($counts['ready'] ?? 0),
            'nothingCount' => (int) ($counts['nothing_to_invoice'] ?? 0),
            'blockedCount' => (int) ($counts['blocked'] ?? 0),
            'warningCount' => $rows->sum(fn ($row) => $row->warnings->count()),
            'documentRowCount' => $rows->where('status', 'ready')->sum('documentRows'),
            'net' => round($net, 2),
            'tax' => round($tax, 2),
            'gross' => round($gross, 2),
            'taxByRate' => collect($taxByRate)->map(fn ($amount) => round($amount, 2)),
            'invoiceDate' => $invoiceDate,
        ];
    }
}
