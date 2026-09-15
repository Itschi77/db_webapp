<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatevController extends Controller
{
    public function index()
    {
        $mode = session('frontend_mode', 'classic');
        return view($mode.'.datev.index');
    }

    public function rechnungen(Request $request)
    {
        $variant = $request->get('variante', 'ausfuehrlich');
        if (!in_array($variant, ['ausfuehrlich', 'kurz'], true)) abort(404);

        $von = $request->get('von', '2024-01-01');
        $bis = $request->get('bis', '2024-01-31');
        try {
            $vonDate = Carbon::createFromFormat('Y-m-d', $von)->startOfDay();
            $bisDate = Carbon::createFromFormat('Y-m-d', $bis)->startOfDay();
        } catch (\Throwable) {
            return back()->withErrors(['datum' => 'Bitte gültige Datumswerte im Format JJJJ-MM-TT verwenden.']);
        }
        if ($bisDate->lt($vonDate)) return back()->withErrors(['datum' => 'Das Bis-Datum muss nach dem Von-Datum liegen.']);

        $rows = DB::connection('sqlsrv_accountings')->table('tblAuftragPosBerechnet as b')
            ->join('tblRechnung as r', 'r.intID', '=', 'b.intRechnungIntID')
            ->join('tblAuftragPos as p', 'p.intID', '=', 'b.intAufPosID')
            ->leftJoin('tblDatevBezeichnungen as d', 'd.intID', '=', 'b.intDatevID')
            ->whereRaw('r.datRechnungsDatum >= DATEFROMPARTS(?,?,?) AND r.datRechnungsDatum < DATEADD(day,1,DATEFROMPARTS(?,?,?))', [
                $vonDate->year,$vonDate->month,$vonDate->day,$bisDate->year,$bisDate->month,$bisDate->day,
            ])
            ->select('r.intID as rechnungID','r.intRechNr','r.datRechnungsDatum','b.intAufPosID','b.intKopieKundenID','b.strKopieBeschreibung','b.fBetrag','b.fSteuern','p.fRabattInProzent','d.strDatevKontierung','d.stDatevBezeichnung')
            ->orderBy('r.intRechNr')->orderBy('d.strDatevKontierung')->orderBy('b.intAufPosID')->get();

        $kunden = Kunde::whereIn('intID', $rows->pluck('intKopieKundenID')->filter()->unique())->get()->keyBy('intID');
        foreach ($rows as $row) {
            $kunde = $kunden->get((int)$row->intKopieKundenID);
            $row->kundeName = $kunde?->strName;
            $row->datevKunde = $kunde?->strDatevKundenKonto ?: 'SOFORT KUNDENKONTO NACHTRAGEN!';
            $row->datevProdukt = $row->strDatevKontierung ?: 'NICHT VORHANDEN';
            $row->datevProduktBezeichnung = $row->strDatevKontierung ? $row->stDatevBezeichnung : 'SOFORT DATEV PRODUKT-KONTIERUNG NACHTRAGEN!';
            $faktor = 1 - (((float)$row->fRabattInProzent) / 100);
            $row->betragR = (float)$row->fBetrag * $faktor;
            $row->steuernR = (float)$row->fSteuern * $faktor;
            $row->bruttoR = $row->betragR + $row->steuernR;
        }

        $mode = session('frontend_mode', 'classic');
        return view($mode.'.datev.rechnungen', compact('rows','variant','von','bis'));
    }

    public function produkte(Request $request)
    {
        [$rows,$von,$bis] = $this->datevRows($request);
        $groups = $rows->groupBy(fn($r) => $r->datevProdukt.'|'.$r->datevProduktBezeichnung)->map(function($items){
            return (object)[
                'konto'=>$items->first()->datevProdukt,
                'bezeichnung'=>$items->first()->datevProduktBezeichnung,
                'anzahl'=>$items->count(),
                'netto'=>$items->sum('betragR'),
                'steuer'=>$items->sum('steuernR'),
                'brutto'=>$items->sum('bruttoR'),
            ];
        })->values()->sortBy('konto')->values();
        $mode = session('frontend_mode', 'classic');
        return view($mode.'.datev.produkte', compact('groups','von','bis'));
    }

    public function kunden(Request $request)
    {
        [$rows,$von,$bis] = $this->datevRows($request);
        $groups = $rows->groupBy(fn($r) => $r->datevKunde.'|'.$r->intKopieKundenID)->map(function($items){
            return (object)[
                'konto'=>$items->first()->datevKunde,
                'kundeID'=>$items->first()->intKopieKundenID,
                'name'=>$items->first()->kundeName,
                'netto'=>$items->sum('betragR'),
                'steuer'=>$items->sum('steuernR'),
                'brutto'=>$items->sum('bruttoR'),
            ];
        })->values()->sortBy('konto')->values();
        $mode = session('frontend_mode', 'classic');
        return view($mode.'.datev.kunden', compact('groups','von','bis'));
    }

    public function kundenOhneDatev()
    {
        $ids = DB::connection('sqlsrv_accountings')->table('tblAuftrag')
            ->where('boolPapierrechnung','<>',0)
            ->where(function($q){ $q->whereNull('datStorniereAb')->orWhereRaw('datStorniereAb > DATEFROMPARTS(2000,12,31)'); })
            ->pluck('intKID')->unique()->values();
        $kunden = Kunde::whereIn('intID',$ids)->whereNull('strDatevKundenKonto')->orderBy('strName')->get();
        $mode = session('frontend_mode', 'classic');
        return view($mode.'.datev.kunden-ohne', compact('kunden'));
    }

    public function kundenkonten()
    {
        $kunden = Kunde::whereNotNull('strDatevKundenKonto')->whereRaw('LEN(strDatevKundenKonto) > 0')->orderBy('strDatevKundenKonto')->get();
        $mode = session('frontend_mode', 'classic');
        return view($mode.'.datev.kundenkonten', compact('kunden'));
    }

    private function datevRows(Request $request): array
    {
        $von = $request->get('von', '2024-01-01');
        $bis = $request->get('bis', '2024-01-31');
        try {
            $vonDate = Carbon::createFromFormat('Y-m-d', $von)->startOfDay();
            $bisDate = Carbon::createFromFormat('Y-m-d', $bis)->startOfDay();
        } catch (\Throwable) { abort(422, 'Ungültiger Datumsbereich.'); }
        abort_if($bisDate->lt($vonDate),422,'Das Bis-Datum muss nach dem Von-Datum liegen.');
        $rows = DB::connection('sqlsrv_accountings')->table('tblAuftragPosBerechnet as b')
            ->join('tblRechnung as r', 'r.intID', '=', 'b.intRechnungIntID')
            ->join('tblAuftragPos as p', 'p.intID', '=', 'b.intAufPosID')
            ->leftJoin('tblDatevBezeichnungen as d', 'd.intID', '=', 'b.intDatevID')
            ->whereRaw('r.datRechnungsDatum >= DATEFROMPARTS(?,?,?) AND r.datRechnungsDatum < DATEADD(day,1,DATEFROMPARTS(?,?,?))', [$vonDate->year,$vonDate->month,$vonDate->day,$bisDate->year,$bisDate->month,$bisDate->day])
            ->select('r.intRechNr','r.datRechnungsDatum','b.intAufPosID','b.intKopieKundenID','b.strKopieBeschreibung','b.fBetrag','b.fSteuern','p.fRabattInProzent','d.strDatevKontierung','d.stDatevBezeichnung')->get();
        $kunden = Kunde::whereIn('intID',$rows->pluck('intKopieKundenID')->filter()->unique())->get()->keyBy('intID');
        foreach($rows as $row){
            $kunde=$kunden->get((int)$row->intKopieKundenID);
            $row->kundeName=$kunde?->strName;
            $row->datevKunde=$kunde?->strDatevKundenKonto ?: 'SOFORT KUNDENKONTO NACHTRAGEN!';
            $row->datevProdukt=$row->strDatevKontierung ?: 'NICHT VORHANDEN';
            $row->datevProduktBezeichnung=$row->strDatevKontierung ? $row->stDatevBezeichnung : 'SOFORT DATEV PRODUKT-KONTIERUNG NACHTRAGEN!';
            $faktor=1-(((float)$row->fRabattInProzent)/100);
            $row->betragR=(float)$row->fBetrag*$faktor; $row->steuernR=(float)$row->fSteuern*$faktor; $row->bruttoR=$row->betragR+$row->steuernR;
        }
        return [$rows,$von,$bis];
    }
}
