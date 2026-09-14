<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WiedervorlageController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::connection('sqlsrv_accountings')->table('tblAuftragPos as p')
            ->join('tblAuftrag as a', 'p.intAufNr', '=', 'a.intAufNr')
            ->select('p.intID','p.intAufNr','p.strBeschreibung','p.datWiedervorlageVertrieb',
                'p.txtWiedervorlageVertrieb','p.datMindeslaufzeitEnde','a.intKID','a.strBeschreibung as auftragBeschreibung');

        $status = $request->get('status', 'due');
        $query->whereRaw('p.datWiedervorlageVertrieb > DATEFROMPARTS(1980,1,2)');
        if ($status === 'due') $query->whereRaw('p.datWiedervorlageVertrieb < DATEADD(day,1,CONVERT(date,GETDATE()))');
        if ($status === 'future') $query->whereRaw('p.datWiedervorlageVertrieb >= DATEADD(day,1,CONVERT(date,GETDATE()))');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($b) use ($q) {
                $b->where('p.strBeschreibung','like',"%{$q}%")
                  ->orWhere('a.strBeschreibung','like',"%{$q}%");
                if (ctype_digit($q)) {
                    $b->orWhere('p.intID',(int)$q)->orWhere('p.intAufNr',(int)$q)->orWhere('a.intKID',(int)$q);
                }
            });
        }

        $positionen = $query->orderBy('p.datWiedervorlageVertrieb')->paginate(100)->withQueryString();
        $kunden = Kunde::whereIn('intID', collect($positionen->items())->pluck('intKID')->unique())->get()->keyBy('intID');
        $mode = session('frontend_mode','classic');
        return view($mode.'.wiedervorlagen.index', compact('positionen','kunden','status'));
    }
}
