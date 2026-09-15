<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FremdaccountingController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->integer('monat') ?: null;
        $year = $request->integer('jahr') ?: null;
        if ($month !== null && ($month < 1 || $month > 12)) abort(422, 'Monat muss zwischen 1 und 12 liegen.');
        if ($year !== null && ($year < 1900 || $year > 2100)) abort(422, 'Jahr ist ungültig.');

        $c = DB::connection('sqlsrv_accountings');
        $perioden = $c->table('tblAnbindungAuswertung as x')
            ->join('tblAnbindungen as a', 'a.intID', '=', 'x.intAnbindungID')
            ->where('a.intTyp', 4)
            ->select('x.intJahr', 'x.intMonat')
            ->distinct()->orderByDesc('x.intJahr')->orderByDesc('x.intMonat')->get();

        $auswertungen = collect();
        $kunden = collect();
        if ($month !== null && $year !== null) {
            $auswertungen = $c->table('tblAnbindungen as a')
                ->join('tblAnbindungAuswertung as x', 'a.intID', '=', 'x.intAnbindungID')
                ->leftJoin('tblAuftragPos as p', 'a.intAuftragsPos', '=', 'p.intID')
                ->leftJoin('tblAuftrag as o', 'p.intAufNr', '=', 'o.intAufNr')
                ->where('a.intTyp', 4)->where('x.intMonat', $month)->where('x.intJahr', $year)
                ->select('x.intID as auswertungID','x.intAnbindungID','x.decMBin','x.decMBout','x.intMonat','x.intJahr','x.decGesamt','x.strrechnungsinfo',
                    'a.intKID','a.intAuftragsPos','p.intAufNr','o.strBeschreibung as auftragBeschreibung')
                ->orderBy('a.intKID')->orderBy('x.intAnbindungID')->get();
            $kunden = Kunde::whereIn('intID', $auswertungen->pluck('intKID')->filter()->unique())->get()->keyBy('intID');
        }

        $mode = session('frontend_mode', 'classic');
        return view($mode.'.fremdaccounting.index', compact('auswertungen','kunden','perioden','month','year'));
    }
}
