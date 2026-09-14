<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use App\Models\Zahlungsbedingung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RechnungController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::connection('sqlsrv_accountings')->table('tblRechnung as r')
            ->join('tblAuftrag as a','r.intAufNr','=','a.intAufNr')
            ->select('r.intID','r.intRechNr','r.intAufNr','r.datRechnungsDatum','r.datFaelligkeitsDatum',
                'r.boolBezahlt','r.datBezahlDatum','r.fRechnungsbetrag','r.fBezahlterBetrag','r.intMahnstufe',
                'r.bolRechnungStrittig','r.strKundenNameAufRechnung','a.intKID');

        $status = $request->get('status','all');
        if ($status === 'open') $query->where('r.boolBezahlt',0);
        if ($status === 'paid') $query->where('r.boolBezahlt','<>',0);
        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($b) use ($q) {
                $b->where('r.strKundenNameAufRechnung','like',"%{$q}%");
                if (ctype_digit($q)) {
                    $b->orWhere('r.intRechNr',(int)$q)->orWhere('r.intAufNr',(int)$q)->orWhere('a.intKID',(int)$q);
                }
            });
        }

        $rechnungen = $query->orderByDesc('r.datRechnungsDatum')->orderByDesc('r.intRechNr')->paginate(100)->withQueryString();
        $kunden = Kunde::whereIn('intID', collect($rechnungen->items())->pluck('intKID')->unique())->get()->keyBy('intID');
        $mode = session('frontend_mode','classic');
        return view($mode.'.rechnungen.index', compact('rechnungen','kunden','status'));
    }

    public function show(int $rechnung)
    {
        $c = DB::connection('sqlsrv_accountings');
        $rechnung = $c->table('tblRechnung')->where('intID',$rechnung)->first();
        abort_unless($rechnung,404);
        $auftrag = $c->table('tblAuftrag')->where('intAufNr',$rechnung->intAufNr)->first();
        $kunde = $auftrag ? Kunde::find($auftrag->intKID) : null;
        $zahlungsbedingung = ($auftrag && $auftrag->intZahlungsbedingungID)
            ? Zahlungsbedingung::find($auftrag->intZahlungsbedingungID)
            : null;
        $mode = session('frontend_mode','classic');
        return view($mode.'.rechnungen.show', compact('rechnung','auftrag','kunde','zahlungsbedingung'));
    }
    public function file(int $rechnung)
    {
        $record = DB::connection('sqlsrv_accountings')->table('tblRechnung')->where('intID', $rechnung)->first();
        abort_unless($record && $record->strPfadZurRechnung, 404);

        $slash = chr(92);
        $unc = str_replace('/', $slash, trim((string) $record->strPfadZurRechnung));
        $prefix = $slash . $slash . 'midas' . $slash . 'bh' . $slash;
        abort_unless(str_starts_with(strtolower($unc), strtolower($prefix)), 403);

        $relative = substr($unc, strlen($prefix));
        $relative = str_replace($slash, '/', $relative);
        $candidate = '/mnt/midas-bh/' . ltrim($relative, '/');
        $base = realpath('/mnt/midas-bh');
        $path = realpath($candidate);

        abort_unless($base && $path && str_starts_with($path, $base . DIRECTORY_SEPARATOR) && is_file($path) && is_readable($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="' . addslashes(basename($path)) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

}
