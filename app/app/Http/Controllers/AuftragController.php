<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use App\Models\Rechnungsanschrift;
use App\Models\Zahlungsbedingung;
use Illuminate\Support\Facades\DB;

class AuftragController extends Controller
{
    public function index(int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);
        $auftraege = DB::connection('sqlsrv_accountings')->table('tblAuftrag')
            ->where('intKID', $kunde->intID)
            ->orderByDesc('intAufNr')
            ->get();

        $mode = session('frontend_mode', 'classic');
        return view($mode . '.auftraege.index', compact('kunde', 'auftraege'));
    }

    public function show(int $kunde, int $auftrag)
    {
        $kunde = Kunde::findOrFail($kunde);
        $auftrag = DB::connection('sqlsrv_accountings')->table('tblAuftrag')
            ->where('intKID', $kunde->intID)
            ->where('intAufNr', $auftrag)
            ->first();

        abort_unless($auftrag, 404);

        $positionen = DB::connection('sqlsrv_accountings')->table('tblAuftragPos')
            ->where('intAufNr', $auftrag->intAufNr)
            ->orderBy('intID')
            ->get();

        $rechnungsanschrift = $auftrag->intAnschriftID
            ? Rechnungsanschrift::find($auftrag->intAnschriftID)
            : null;
        $zahlungsbedingung = $auftrag->intZahlungsbedingungID
            ? Zahlungsbedingung::find($auftrag->intZahlungsbedingungID)
            : null;
        $letztesRechnungsdatum = DB::connection('sqlsrv_accountings')->table('tblRechnung')
            ->where('intAufNr', $auftrag->intAufNr)
            ->max('datRechnungsDatum');

        $mode = session('frontend_mode', 'classic');
        return view($mode . '.auftraege.show', compact(
            'kunde', 'auftrag', 'positionen', 'rechnungsanschrift',
            'zahlungsbedingung', 'letztesRechnungsdatum'
        ));
    }
}
