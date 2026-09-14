<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use Illuminate\Support\Facades\DB;

class OffeneRechnungenController extends Controller
{
    public function index(int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);

        $rechnungen = DB::connection('sqlsrv_accountings')
            ->table('tblRechnung as r')
            ->join('tblAuftrag as a', 'r.intAufNr', '=', 'a.intAufNr')
            ->where('a.intKID', $kunde->intID)
            ->where('r.boolBezahlt', 0)
            ->select([
                'r.intID', 'r.intRechNr', 'r.intAufNr',
                'r.datRechnungsDatum', 'r.datVersendeDatum', 'r.datFaelligkeitsDatum',
                'r.fBetrag', 'r.fSteuer', 'r.fRechnungsbetrag', 'r.fBezahlterBetrag',
                'r.fVerzugszinsenAufgelaufen', 'r.fGutschrift', 'r.intMahnstufe',
                'r.datMahnung1Am', 'r.datMahnung2Am', 'r.datMahnung3Am',
                'r.datKundeGesperrtAm', 'r.datKundensperrungAufgehobenAm',
                'r.strKundenNameAufRechnung', 'r.strKopieAuftragsbeschreibung',
                'r.strPfadZurRechnung', 'r.bolRechnungStrittig', 'r.strRechnungStrittigGrund',
            ])
            ->orderByDesc('r.datRechnungsDatum')
            ->orderByDesc('r.intRechNr')
            ->get();

        $mode = session('frontend_mode', 'classic');
        return view($mode . '.offene-rechnungen.index', compact('kunde', 'rechnungen'));
    }
}
