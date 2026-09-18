<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
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

        $storedCurrent = $stats?->current_number !== null
            ? (int) $stats->current_number
            : null;
        $counter = null;
        $counterReadable = true;
        try {
            $counter = $db->table('tblRechnungsNummern')
                ->where('intRechnungsJahr', $year)
                ->value('intLfdNr');
            $counter = $counter !== null ? (int) $counter : null;
        } catch (QueryException) {
            $counterReadable = false;
        }

        $counterCurrent = $counter === null
            ? null
            : ($counter >= $first ? $counter : $first + $counter);
        $current = $counterCurrent ?? $storedCurrent;
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
        if (! $counterReadable) {
            $warnings[] = 'Die Zählertabelle tblRechnungsNummern ist für janus_connect noch nicht lesbar; ersatzweise wird MAX(intRechNr) verwendet.';
        } elseif ($counterCurrent !== null && $storedCurrent !== null && $counterCurrent !== $storedCurrent) {
            $warnings[] = 'Zählertabelle und höchste gespeicherte Rechnungsnummer weichen voneinander ab.';
        }
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
            'source' => $counterCurrent !== null ? 'tblRechnungsNummern' : 'tblRechnung (Fallback)',
            'counterReadable' => $counterReadable,
            'invoiceCount' => (int) ($stats?->invoice_count ?? 0),
            'duplicateCount' => $duplicateCount,
            'dateMismatchCount' => $dateMismatchCount,
            'warnings' => $warnings,
            'canSimulate' => $next <= $last && $duplicateCount === 0,
        ];
    }
}
