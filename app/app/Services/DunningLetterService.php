<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DunningLetterService
{
    public function render(int $invoiceId, int $stage, float $additionalFee = 0): string
    {
        if (!in_array($stage,[1,2,3],true)) {
            throw new RuntimeException('Ungültige Mahnstufe.');
        }
        $row = DB::connection('sqlsrv_accountings')->table('tblRechnung as r')
            ->join('tblAuftrag as a','a.intAufNr','=','r.intAufNr')
            ->leftJoin('tblRechnungsanschrift as ra', function($join){
                $join->on('ra.intID','=','a.intAnschriftID')->on('ra.intKID','=','a.intKID');
            })
            ->where('r.intID',$invoiceId)
            ->select('r.*','a.intKID','ra.strName as addressName','ra.strZuHaenden','ra.strStrasse','ra.strPLZ','ra.strOrt')
            ->first();
        if (!$row) throw new RuntimeException('Rechnung nicht gefunden.');

        $principal = max(0, round(
            (float)($row->fRechnungsbetrag ?? 0)
            - (float)($row->fBezahlterBetrag ?? 0)
            - (float)($row->fGutschrift ?? 0)
            - (float)($row->fVerlustabschreibungsBetrag ?? 0), 2
        ));
        $fees = round((float)($row->fMahngebührenAufgelaufen ?? 0) + max(0,$additionalFee),2);
        $interest = round((float)($row->fVerzugszinsenAufgelaufen ?? 0),2);
        $total = round($principal + $fees + $interest,2);
        $deadline = CarbonImmutable::now('Europe/Berlin')->startOfDay()
            ->addDays((int)config('dunning.payment_deadline_days',7));

        return Pdf::loadView('mahnwesen.letter', [
            'r'=>$row, 'stage'=>$stage, 'principal'=>$principal, 'fees'=>$fees,
            'interest'=>$interest, 'total'=>$total, 'deadline'=>$deadline,
            'seller'=>config('invoicing.einvoice'),
        ])->setPaper('a4')->output();
    }
}
