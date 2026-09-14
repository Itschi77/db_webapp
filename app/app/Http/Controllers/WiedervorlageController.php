<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WiedervorlageController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::connection('sqlsrv_accountings')->table('tblAuftrag as a')
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('tblAuftragPos as p')
                  ->whereColumn('p.intAufNr','a.intAufNr')
                  ->whereRaw('CAST(p.txtInfo AS nvarchar(max)) LIKE ?', ['WV']);
            })
            ->select('a.intAufNr','a.intKID','a.strBeschreibung','a.datErfassungsdatum','a.datFakturierAb','a.boolEingefroren');

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($b) use ($q) {
                $b->where('a.strBeschreibung','like',"%{$q}%");
                if (ctype_digit($q)) {
                    $b->orWhere('a.intAufNr',(int)$q)->orWhere('a.intKID',(int)$q);
                }
            });
        }

        $auftraege = $query->orderByDesc('a.intAufNr')->paginate(100)->withQueryString();
        $kunden = Kunde::whereIn('intID', collect($auftraege->items())->pluck('intKID')->unique())->get()->keyBy('intID');
        $mode = session('frontend_mode','classic');
        return view($mode.'.wiedervorlagen.index', compact('auftraege','kunden'));
    }
}
