<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InvoiceConsistencyCheckService
{
    public const CACHE_KEY = 'invoice_consistency_report';

    public function __construct(
        private InvoiceBatchTestRunService $batchService,
        private InvoiceAccountingHealthCheckService $accountingHealthService,
    ) {}

    public function run(): array
    {
        $now = CarbonImmutable::now('Europe/Berlin');
        $month = $now->subMonthNoOverflow();
        $from = $month->startOfMonth()->startOfDay();
        $to = $month->endOfMonth()->endOfDay();
        $issues = collect($this->structuralIssues($now))
            ->concat($this->accountingHealthService->issues($now));

        foreach (['nachtraeglich', 'voraus', 'domain'] as $type) {
            $orders = $this->candidateQuery($from, $to, $type)->get();
            $result = $this->batchService->build(
                $orders,
                $from,
                $to,
                $to->startOfDay(),
                true,
                'gesamt',
            );

            foreach ($result['rows']->where('status', 'blocked') as $row) {
                foreach ($row->issues as $issue) {
                    $issue = (string) $issue;
                    if (str_contains($issue, 'Auftrag ist eingefroren')) {
                        $issue = 'Auftrag ist eingefroren und wird vom Alttool nicht fakturiert.';
                    }
                    $issues->push([
                        'order' => (int) $row->order->intAufNr,
                        'customer' => trim((string) $row->customerName),
                        'category' => $this->category($issue),
                        'issue' => $issue,
                    ]);
                }
            }
        }

        $report = [
            'generated_at' => $now->toIso8601String(),
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'issues' => $issues
                ->unique(fn (array $issue) => $issue['order'].'|'.$issue['issue'])
                ->sortBy(fn (array $issue) => sprintf(
                    '%d|%010d|%s',
                    $issue['order'] === 0 ? 0 : 1,
                    $issue['order'],
                    $issue['category'],
                ))
                ->values()
                ->all(),
        ];

        Cache::forever(self::CACHE_KEY, $report);

        return $report;
    }

    private function candidateQuery(
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $type,
    ) {
        $query = DB::connection('sqlsrv_accountings')
            ->table('tblAuftrag as a')
            ->join('tblRechnungsanschrift as ra', function ($join) {
                $join->on('ra.intID', '=', 'a.intAnschriftID')
                    ->on('ra.intKID', '=', 'a.intKID');
            })
            ->select([
                'a.intAufNr', 'a.intKID', 'a.datFakturierAb', 'a.datStorniereAb',
                'a.strBeschreibung', 'a.boolEmailRechnung', 'a.strAbrechnungshinweis',
                'a.boolVoraus', 'a.boolDomainrechnung', 'a.boolEingefroren',
                'ra.strEmail as rechnungEmail',
            ])
            ->where('a.boolRechnungstool', 1)
            ->where(function ($builder) {
                $builder->whereNull('a.boolSponsoring')
                    ->orWhere('a.boolSponsoring', 0);
            })
            ->whereRaw(
                'a.datFakturierAb < DATEADD(day, 1, DATEFROMPARTS(?, ?, ?))',
                [$to->year, $to->month, $to->day],
            )
            ->where(function ($builder) use ($from) {
                $builder->whereNull('a.datStorniereAb')
                    ->orWhereRaw(
                        'a.datStorniereAb > DATEFROMPARTS(?, ?, ?)',
                        [$from->year, $from->month, $from->day],
                    );
            });

        return match ($type) {
            'voraus' => $query->where('a.boolVoraus', 1),
            'domain' => $query->where('a.boolVoraus', 0)
                ->where('a.boolDomainrechnung', 1),
            default => $query->where('a.boolVoraus', 0)
                ->where(function ($builder) {
                    $builder->whereNull('a.boolDomainrechnung')
                        ->orWhere('a.boolDomainrechnung', 0);
                }),
        };
    }

    private function structuralIssues(CarbonImmutable $now): array
    {
        $db = DB::connection('sqlsrv_accountings');
        $active = $db->table('tblAuftrag as a')
            ->leftJoin('tblRechnungsanschrift as ra', 'ra.intID', '=', 'a.intAnschriftID')
            ->where('a.boolRechnungstool', 1)
            ->where(function ($builder) {
                $builder->whereNull('a.boolSponsoring')
                    ->orWhere('a.boolSponsoring', 0);
            })
            ->where(function ($builder) use ($now) {
                $builder->whereNull('a.datStorniereAb')
                    ->orWhereRaw(
                        'a.datStorniereAb > DATEFROMPARTS(?, ?, ?)',
                        [$now->year, $now->month, $now->day],
                    );
            });

        return (clone $active)
            ->where(function ($builder) {
                $builder->whereNull('ra.intID')
                    ->orWhereColumn('ra.intKID', '<>', 'a.intKID');
            })
            ->get([
                'a.intAufNr', 'a.intKID', 'ra.strName',
                'ra.intKID as addressKID',
            ])
            ->map(fn ($row) => [
                'order' => (int) $row->intAufNr,
                'customer' => $row->strName ?: 'Kunde '.$row->intKID,
                'category' => 'Zuordnung',
                'issue' => $row->addressKID === null
                    ? 'Rechnungsanschrift fehlt.'
                    : 'Rechnungsanschrift gehört zu einer anderen Kundennummer.',
            ])
            ->all();
    }

    private function category(string $issue): string
    {
        return match (true) {
            str_contains($issue, 'eingefroren') => 'Eingefroren',
            str_contains($issue, 'Domain') => 'Domain-Referenz',
            str_contains($issue, 'Accounting'),
            str_contains($issue, 'Staffel') => 'Accounting',
            str_contains($issue, 'DATEV') => 'DATEV',
            str_contains($issue, 'Rechnungsanschrift'),
            str_contains($issue, 'Kundennummer'),
            str_contains($issue, 'Kundendatensatz') => 'Zuordnung',
            default => 'Technik',
        };
    }
}
