<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InvoiceHistoricalParityService
{
    public function __construct(
        private InvoicePreviewCalculationService $calculator,
    ) {}

    public function compare(int $identifier): array
    {
        $db = DB::connection('sqlsrv_accountings');
        $invoice = $db->table('tblRechnung')
            ->where('intRechNr', $identifier)
            ->orWhere('intID', $identifier)
            ->orderByRaw('CASE WHEN intRechNr = ? THEN 0 ELSE 1 END', [$identifier])
            ->first();

        if (!$invoice) {
            return ['error' => 'Die Rechnung wurde nicht gefunden.'];
        }

        $stored = $db->table('tblAuftragPosBerechnet as b')
            ->join('tblAuftragPos as p', 'p.intID', '=', 'b.intAufPosID')
            ->where('b.intRechnungIntID', $invoice->intID)
            ->orderBy('b.BerechnetZum')->orderBy('b.intID')
            ->get([                'b.intID', 'b.intAufPosID', 'b.fBetrag', 'b.fSteuern',
                'b.BerechnetZum', 'b.datBerechnetVon', 'b.datBerechnetBis',
                'b.strKopieBeschreibung', 'p.fRabattInProzent',
                'p.intMwstsatz', 'p.strBeschreibung',
            ]);

        if ($stored->isEmpty()) {
            return ['error' => 'Zu dieser Rechnung sind keine historischen Berechnungszeilen gespeichert.'];
        }

        $dates = $stored->pluck('BerechnetZum')->filter()->map(
            fn ($date) => CarbonImmutable::parse($date)->startOfDay()
        );
        $from = $dates->min();
        $to = $dates->max()->endOfDay();

        $order = $db->table('tblAuftrag')->where('intAufNr', $invoice->intAufNr)->first();
        if (!$order) {
            return ['error' => 'Der zugehörige Auftrag wurde nicht gefunden.'];
        }

        $positions = $db->table('tblAuftragPos')
            ->where('intAufNr', $invoice->intAufNr)
            ->orderBy('intID')
            ->get([
                'intID', 'strBeschreibung', 'intMenge', 'fEndpreis', 'fRabattInProzent',
                'intMwstsatz', 'intAbrechnungsArt', 'intStaffelTyp', 'intStaffelgruppe',                'datFakturierAb', 'datFakturierBis', 'datVorberechnenBis', 'boolIstAnbindung',
            ]);

        $preview = $this->calculator->calculate($order, $positions, $from, $to, true, true);
        $storedByKey = $stored->keyBy(fn ($row) => $this->key(
            $row->intAufPosID,
            CarbonImmutable::parse($row->BerechnetZum),
        ));
        $replayedByKey = $preview['rows']
            ->filter(fn ($row) => $row->calculationDate && $row->net !== null)
            ->keyBy(fn ($row) => $this->key($row->position->intID, $row->calculationDate));

        $rows = $stored->map(function ($old) use ($replayedByKey) {
            $discountPercent = (float) ($old->fRabattInProzent ?? 0);
            $oldBase = (float) $old->fBetrag;
            $oldDiscount = round(-($oldBase * ($discountPercent / 100)), 2);
            $oldNet = round($oldBase + $oldDiscount, 2);
            $oldTax = round((float) $old->fSteuern * (1 - ($discountPercent / 100)), 2);
            $new = $replayedByKey->get($this->key(
                $old->intAufPosID,
                CarbonImmutable::parse($old->BerechnetZum),
            ));
            $newNet = $new ? round((float) $new->net, 2) : null;
            $newTax = $new ? round((float) $new->tax, 2) : null;
            $netDifference = $newNet === null ? null : round($newNet - $oldNet, 2);
            $taxDifference = $newTax === null ? null : round($newTax - $oldTax, 2);

            return (object) [
                'stored' => $old,
                'replayed' => $new,
                'discountPercent' => $discountPercent,
                'storedDiscount' => $oldDiscount,
                'storedNet' => $oldNet,
                'storedTax' => $oldTax,
                'netDifference' => $netDifference,
                'taxDifference' => $taxDifference,
                'matches' => $new && abs($netDifference) < 0.01 && abs($taxDifference) < 0.01,
            ];
        });

        $unexpected = $replayedByKey->reject(fn ($row, $key) => $storedByKey->has($key))->values();
        $replayedNet = round((float) $rows->sum(fn ($row) => $row->replayed?->net ?? 0), 2);
        $replayedTax = round((float) $rows->sum(fn ($row) => $row->replayed?->tax ?? 0), 2);
        $replayedGross = round($replayedNet + $replayedTax, 2);
        $storedNet = round((float) $invoice->fBetrag, 2);
        $storedTax = round((float) $invoice->fSteuer, 2);
        $storedGross = round((float) $invoice->fRechnungsbetrag, 2);
        $summaryMatches = abs($replayedNet - $storedNet) < 0.01
            && abs($replayedTax - $storedTax) < 0.01
            && abs($replayedGross - $storedGross) < 0.01;

        return [
            'invoice' => $invoice,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'unexpectedRows' => $unexpected,            'storedNet' => $storedNet,
            'storedTax' => $storedTax,
            'storedGross' => $storedGross,
            'replayedNet' => $replayedNet,
            'replayedTax' => $replayedTax,
            'replayedGross' => $replayedGross,
            'netDifference' => round($replayedNet - $storedNet, 2),
            'taxDifference' => round($replayedTax - $storedTax, 2),
            'grossDifference' => round($replayedGross - $storedGross, 2),
            'matches' => $summaryMatches && $rows->every->matches && $unexpected->isEmpty(),
        ];
    }

    private function key(int $positionId, CarbonImmutable $date): string
    {
        return $positionId.'|'.$date->toDateString();
    }
}
