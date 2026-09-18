<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class InvoiceNumberSimulationService
{
    public function simulate(CarbonInterface $invoiceDate): array
    {
        $year = (int) $invoiceDate->format('Y');
        $first = $year * 1_000_000;
        $last = $first + 999_999;
        $db = DB::connection('sqlsrv_accountings');

        $stats = $db->table('tblRechnung')
            ->whereBetween('intRechNr', [$first, $last])
            ->selectRaw(
                'MAX(intRechNr) AS current_number, COUNT(*) AS invoice_count, '.
                'COUNT(DISTINCT intRechNr) AS distinct_count'
            )
            ->first();

        $current = $stats?->current_number !== null
            ? (int) $stats->current_number
            : null;
        $next = $current === null ? $first + 1 : $current + 1;
        $duplicateCount = max(
            0,
            (int) ($stats?->invoice_count ?? 0) - (int) ($stats?->distinct_count ?? 0),
        );
        $dateMismatchCount = (int) $db->table('tblRechnung')
            ->whereYear('datRechnungsDatum', $year)
            ->where('intRechNr', '>', 0)
            ->whereNotBetween('intRechNr', [$first, $last])
            ->count();

        $warnings = [];
        if ($duplicateCount > 0) {
            $warnings[] = "{$duplicateCount} doppelte Rechnungsnummer(n) im Jahr {$year}.";
        }
        if ($dateMismatchCount > 0) {
            $warnings[] = "{$dateMismatchCount} Rechnung(en) mit Datum {$year}, aber abweichendem Nummernpräfix.";
        }
        if ($next > $last) {
            $warnings[] = "Der sechsstellige Nummernbereich für {$year} ist ausgeschöpft.";
        }
        if ($year < (int) now()->format('Y')) {
            $warnings[] = 'Das gewählte Rechnungsdatum liegt in einem abgeschlossenen Kalenderjahr.';
        }

        return [
            'year' => $year,
            'current' => $current,
            'next' => $next,
            'invoiceCount' => (int) ($stats?->invoice_count ?? 0),
            'duplicateCount' => $duplicateCount,
            'dateMismatchCount' => $dateMismatchCount,
            'warnings' => $warnings,
            'canSimulate' => $next <= $last && $duplicateCount === 0,
        ];
    }
}
