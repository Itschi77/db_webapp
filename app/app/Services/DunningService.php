<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DunningService
{
    public function overview(array $filters = []): array
    {
        $now = CarbonImmutable::now('Europe/Berlin')->startOfDay();
        $db = DB::connection('sqlsrv_accountings');

        $base = $db->table('tblRechnung as r')
            ->join('tblAuftrag as a', 'a.intAufNr', '=', 'r.intAufNr')
            ->leftJoin('tblRechnungsanschrift as ra', function ($join) {
                $join->on('ra.intID', '=', 'a.intAnschriftID')
                    ->on('ra.intKID', '=', 'a.intKID');
            })
            ->whereRaw('ISNULL(r.boolBezahlt,0)=0')
            ->select([
                'r.intID', 'r.intRechNr', 'r.intAufNr', 'a.intKID',
                'r.datRechnungsDatum', 'r.datFaelligkeitsDatum',
                'r.fRechnungsbetrag', 'r.fBezahlterBetrag', 'r.fGutschrift',
                'r.fVerzugszinsenAufgelaufen', 'r.fMahngebührenAufgelaufen',
                'r.intMahnstufe', 'r.datMahnung1Am', 'r.datMahnung2Am', 'r.datMahnung3Am',
                'r.datKundeGesperrtAm', 'r.datKundensperrungAufgehobenAm',
                'r.bolRechnungStrittig', 'r.strRechnungStrittigGrund',
                'r.datRechnungStrittigWiedervorlage', 'r.bolRatenzahlung',
                'r.fVerlustabschreibungsBetrag', 'r.datForderungsausfallAbgeschriebenAm',
                'r.strKundenNameAufRechnung',
                'ra.strName as addressName', 'ra.strZuHaenden', 'ra.strStrasse',
                'ra.strPLZ', 'ra.strOrt', 'ra.strEmail',
            ]);

        $status = $filters['status'] ?? 'overdue';
        if ($status === 'overdue') {
            $base->whereNotNull('r.datFaelligkeitsDatum')->whereDate('r.datFaelligkeitsDatum', '<', $now->toDateString());
        } elseif ($status === 'due') {
            $base->whereNotNull('r.datFaelligkeitsDatum')->whereDate('r.datFaelligkeitsDatum', '<=', $now->toDateString());
        } elseif ($status === 'disputed') {
            $base->whereRaw('ISNULL(r.bolRechnungStrittig,0)=1');
        } elseif ($status === 'followup') {
            $base->whereRaw('ISNULL(r.bolRechnungStrittig,0)=1')
                ->whereNotNull('r.datRechnungStrittigWiedervorlage')
                ->whereDate('r.datRechnungStrittigWiedervorlage', '<=', $now->toDateString());
        }

        if (array_key_exists('stage', $filters) && $filters['stage'] !== null && $filters['stage'] !== '' && ctype_digit((string) $filters['stage'])) {
            $base->whereRaw('ISNULL(r.intMahnstufe,0)=?', [(int) $filters['stage']]);
        }
        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $base->where(function ($query) use ($q) {
                $query->where('r.strKundenNameAufRechnung', 'like', "%{$q}%")
                    ->orWhere('ra.strName', 'like', "%{$q}%");
                if (ctype_digit($q)) {
                    $query->orWhere('r.intRechNr', (int) $q)
                        ->orWhere('r.intAufNr', (int) $q)
                        ->orWhere('a.intKID', (int) $q);
                }
            });
        }

        /** @var LengthAwarePaginator $invoices */
        $invoices = $base->orderBy('r.datFaelligkeitsDatum')->orderBy('r.intRechNr')
            ->paginate(100)->withQueryString();

        $customerIds = $invoices->getCollection()->pluck('intKID')->filter()->unique()->values();
        $customerLocks = collect();
        if ($customerIds->isNotEmpty()) {
            $customerLocks = $db->table('tblRechnung as lr')
                ->join('tblAuftrag as la', 'la.intAufNr', '=', 'lr.intAufNr')
                ->whereIn('la.intKID', $customerIds)
                ->groupBy('la.intKID')
                ->selectRaw('la.intKID, MAX(lr.datKundeGesperrtAm) AS lockedAt, MAX(lr.datKundensperrungAufgehobenAm) AS unlockedAt')
                ->get()->keyBy('intKID');
        }

        $invoices->setCollection($invoices->getCollection()->map(function ($row) use ($now, $customerLocks) {
            $row = $this->decorate($row, $now);
            if ($lock = $customerLocks->get($row->intKID)) {
                $row->isLocked = $lock->lockedAt
                    && (!$lock->unlockedAt || CarbonImmutable::parse($lock->lockedAt)->gt(CarbonImmutable::parse($lock->unlockedAt)));
            }
            return $row;
        }));

        $summary = $db->table('tblRechnung as r')
            ->join('tblAuftrag as a', 'a.intAufNr', '=', 'r.intAufNr')
            ->whereRaw('ISNULL(r.boolBezahlt,0)=0')
            ->selectRaw(
                'COUNT(*) AS open_count,
                 SUM(CASE WHEN r.datFaelligkeitsDatum < CAST(GETDATE() AS date) THEN 1 ELSE 0 END) AS overdue_count,
                 SUM(CASE WHEN ISNULL(r.bolRechnungStrittig,0)=1 THEN 1 ELSE 0 END) AS disputed_count,
                 SUM(CASE WHEN ISNULL(r.bolRechnungStrittig,0)=1 AND r.datRechnungStrittigWiedervorlage <= CAST(GETDATE() AS date) THEN 1 ELSE 0 END) AS followup_count,
                 SUM(CASE WHEN r.datFaelligkeitsDatum < CAST(GETDATE() AS date) THEN CASE WHEN ISNULL(r.fRechnungsbetrag,0)-ISNULL(r.fBezahlterBetrag,0)-ISNULL(r.fGutschrift,0)-ISNULL(r.fVerlustabschreibungsBetrag,0)>0 THEN ISNULL(r.fRechnungsbetrag,0)-ISNULL(r.fBezahlterBetrag,0)-ISNULL(r.fGutschrift,0)-ISNULL(r.fVerlustabschreibungsBetrag,0) ELSE 0 END ELSE 0 END) AS overdue_amount'
            )->first();

        return [
            'invoices' => $invoices,
            'summary' => $summary,
            'status' => $status,
            'writesEnabled' => (bool) config('dunning.writes_enabled'),
            'now' => $now,
        ];
    }

    public function decorate(object $row, ?CarbonImmutable $now = null): object
    {
        $now ??= CarbonImmutable::now('Europe/Berlin')->startOfDay();
        $gross = (float) ($row->fRechnungsbetrag ?? 0);
        $paid = (float) ($row->fBezahlterBetrag ?? 0);
        $credit = (float) ($row->fGutschrift ?? 0);
        $writtenOff = (float) ($row->fVerlustabschreibungsBetrag ?? 0);
        $principal = max(0, round($gross - $paid - $credit - $writtenOff, 2));

        $row->openPrincipal = $principal;
        $row->totalOpen = round(
            $principal
            + (float) ($row->fMahngebührenAufgelaufen ?? 0)
            + (float) ($row->fVerzugszinsenAufgelaufen ?? 0),
            2
        );

        $due = $row->datFaelligkeitsDatum
            ? CarbonImmutable::parse($row->datFaelligkeitsDatum, 'Europe/Berlin')->startOfDay()
            : null;
        $row->daysOverdue = ($due && $due->lt($now)) ? (int) $due->diffInDays($now) : 0;
        $row->isOverdue = $due?->lt($now) ?? false;
        $row->isLocked = $this->lockedFromRow($row);

        [$stage, $date, $reason] = $this->nextStage($row, $now);
        $row->nextStage = $stage;
        $row->nextStageDate = $date;
        $row->nextStageReason = $reason;
        $row->suggestedFee = $stage ? (float) config("dunning.fees.{$stage}", 0) : 0.0;

        return $row;
    }

    private function nextStage(object $row, CarbonImmutable $now): array
    {
        if ((float) ($row->openPrincipal ?? 0) <= 0) {
            return [null, null, 'Kein offener Hauptbetrag.'];
        }
        if ((bool) ($row->bolRatenzahlung ?? false)) {
            return [null, null, 'Ratenzahlung ist hinterlegt; keine automatische Eskalation.'];
        }
        if ((bool) ($row->bolRechnungStrittig ?? false)) {
            $followup = $row->datRechnungStrittigWiedervorlage
                ? CarbonImmutable::parse($row->datRechnungStrittigWiedervorlage)->startOfDay()
                : null;
            return [null, $followup, 'Rechnung ist als strittig markiert.'];
        }
        if (!$row->datFaelligkeitsDatum) {
            return [null, null, 'Kein Fälligkeitsdatum.'];
        }

        $current = (int) ($row->intMahnstufe ?? 0);
        $due = CarbonImmutable::parse($row->datFaelligkeitsDatum)->startOfDay();

        if ($current <= 0) {
            $date = $due->addDays((int) config('dunning.first_reminder_after_due_days', 7));
            return $now->gte($date)
                ? [1, $date, 'Erste Mahnstufe ist fällig.']
                : [null, $date, 'Wartefrist bis zur ersten Mahnstufe läuft.'];
        }
        if ($current === 1 && $row->datMahnung1Am) {
            $date = CarbonImmutable::parse($row->datMahnung1Am)->startOfDay()
                ->addDays((int) config('dunning.second_reminder_after_days', 14));
            return $now->gte($date)
                ? [2, $date, 'Zweite Mahnstufe ist fällig.']
                : [null, $date, 'Wartefrist nach Mahnung 1 läuft.'];
        }
        if ($current === 2 && $row->datMahnung2Am) {
            $date = CarbonImmutable::parse($row->datMahnung2Am)->startOfDay()
                ->addDays((int) config('dunning.third_reminder_after_days', 14));
            return $now->gte($date)
                ? [3, $date, 'Dritte Mahnstufe ist fällig.']
                : [null, $date, 'Wartefrist nach Mahnung 2 läuft.'];
        }

        return [null, null, $current >= 3 ? 'Höchste automatische Mahnstufe erreicht.' : 'Mahnhistorie ist unvollständig.'];
    }

    private function lockedFromRow(object $row): bool
    {
        if (!$row->datKundeGesperrtAm) {
            return false;
        }
        if (!$row->datKundensperrungAufgehobenAm) {
            return true;
        }
        return CarbonImmutable::parse($row->datKundeGesperrtAm)
            ->gt(CarbonImmutable::parse($row->datKundensperrungAufgehobenAm));
    }
}
