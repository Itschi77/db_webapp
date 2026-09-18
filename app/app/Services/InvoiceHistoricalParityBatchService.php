<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvoiceHistoricalParityBatchService
{
    public function __construct(
        private InvoiceHistoricalParityService $comparisonService,
    ) {}

    public function run(): array
    {
        $definitions = [
            'fixed' => ['label' => 'Festpreis', 'apply' => fn (Builder $q) => $q->where('p.intStaffelTyp', 0)],
            'discount' => ['label' => 'Rabatt', 'apply' => fn (Builder $q) => $q->where('p.fRabattInProzent', '<>', 0)],
            'multiple' => ['label' => 'Mehrere Positionen', 'multiple' => true],
            'accounting' => ['label' => 'Accounting / Staffel', 'apply' => fn (Builder $q) => $q->whereBetween('p.intStaffelTyp', [1, 7])->where('p.intStaffelTyp', '<>', 5)],
            'domain' => ['label' => 'Domain', 'apply' => fn (Builder $q) => $q->where(function (Builder $inner) {
                $inner->where('p.intStaffelTyp', 5)->orWhere('a.boolDomainrechnung', 1);
            })],
            'prepayment' => ['label' => 'Vorausberechnung', 'apply' => fn (Builder $q) => $q->where(function (Builder $inner) {
                $inner->where('a.boolVoraus', 1)->orWhereNotNull('p.datVorberechnenBis');
            })],
        ];

        $rows = collect();
        $usedInvoiceIds = [];
        foreach ($definitions as $key => $definition) {
            $candidate = ($definition['multiple'] ?? false)
                ? $this->multiplePositionCandidate($usedInvoiceIds)
                : $this->candidate($definition['apply'], $usedInvoiceIds);
            if (! $candidate) {
                $rows->push((object) [
                    'key' => $key,
                    'category' => $definition['label'],
                    'status' => 'missing',
                    'statusLabel' => 'Keine geeignete Altrechnung gefunden',
                    'invoice' => null,
                    'comparison' => null,
                    'error' => null,
                ]);
                continue;
            }

            $usedInvoiceIds[] = (int) $candidate->intID;
            try {
                $comparison = $this->comparisonService->compare((int) $candidate->intRechNr);
                $error = $comparison['error'] ?? null;
                $matches = ! $error && ($comparison['matches'] ?? false);
                $rows->push((object) [
                    'key' => $key,
                    'category' => $definition['label'],
                    'status' => $error ? 'error' : ($matches ? 'matches' : 'differs'),
                    'statusLabel' => $error ? 'Nicht vergleichbar' : ($matches ? 'Übereinstimmung' : 'Abweichung'),
                    'invoice' => $candidate,
                    'comparison' => $error ? null : $comparison,
                    'error' => $error,
                ]);
            } catch (Throwable $e) {
                report($e);
                $rows->push((object) [
                    'key' => $key,
                    'category' => $definition['label'],
                    'status' => 'error',
                    'statusLabel' => 'Technischer Fehler',
                    'invoice' => $candidate,
                    'comparison' => null,
                    'error' => 'Der Vergleich konnte technisch nicht abgeschlossen werden.',
                ]);
            }
        }

        return [
            'generatedAt' => now(),
            'rows' => $rows,
            'matchCount' => $rows->where('status', 'matches')->count(),
            'differenceCount' => $rows->where('status', 'differs')->count(),
            'errorCount' => $rows->whereIn('status', ['error', 'missing'])->count(),
            'complete' => $rows->every(fn ($row) => $row->status === 'matches'),
        ];
    }
    private function baseQuery(array $excludedIds): Builder
    {
        $query = DB::connection('sqlsrv_accountings')
            ->table('tblRechnung as r')
            ->join('tblAuftragPosBerechnet as b', 'b.intRechnungIntID', '=', 'r.intID')
            ->join('tblAuftragPos as p', 'p.intID', '=', 'b.intAufPosID')
            ->join('tblAuftrag as a', 'a.intAufNr', '=', 'r.intAufNr')
            ->where('r.intRechNr', '>', 0)
            ->whereNotNull('b.BerechnetZum');

        if ($excludedIds !== []) {
            $query->whereNotIn('r.intID', $excludedIds);
        }

        return $query;
    }

    private function candidate(callable $apply, array $excludedIds): ?object
    {
        $query = $this->baseQuery($excludedIds);
        $apply($query);

        return $query
            ->select(['r.intID', 'r.intRechNr', 'r.intAufNr', 'r.datRechnungsDatum'])
            ->distinct()
            ->orderByDesc('r.datRechnungsDatum')
            ->orderByDesc('r.intID')
            ->first();
    }

    private function multiplePositionCandidate(array $excludedIds): ?object
    {
        return $this->baseQuery($excludedIds)
            ->select(['r.intID', 'r.intRechNr', 'r.intAufNr', 'r.datRechnungsDatum'])
            ->groupBy('r.intID', 'r.intRechNr', 'r.intAufNr', 'r.datRechnungsDatum')
            ->havingRaw('COUNT(DISTINCT b.intAufPosID) > 1')
            ->orderByDesc('r.datRechnungsDatum')
            ->orderByDesc('r.intID')
            ->first();
    }
}
