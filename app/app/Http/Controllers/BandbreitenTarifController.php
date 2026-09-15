<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BandbreitenTarifController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q',''));
        $query = DB::connection('sqlsrv_accountings')->table('tblBandbreiteStaffel')->orderBy('strBezeichnung');
        if ($q !== '') {
            $query->where(function($x) use ($q) {
                $x->where('strBezeichnung','like','%'.$q.'%');
                if (ctype_digit($q)) $x->orWhere('intID',(int)$q);
            });
        }
        $tarife = $query->get();
        return view(session('frontend_mode','classic').'.bandbreitentarife.index', compact('tarife','q'));
    }

    public function create() { return $this->form(null); }
    public function edit(int $bandbreitentarif) { return $this->form($this->find($bandbreitentarif)); }

    public function store(Request $request)
    {
        $data = $this->tarifData($request);
        $data['rowguid'] = DB::raw('NEWID()');
        $id = DB::connection('sqlsrv_accountings')->table('tblBandbreiteStaffel')->insertGetId($data,'intID');
        return redirect()->route('bandbreitentarife.edit',$id)->with('status','Bandbreiten-Tarif angelegt.');
    }

    public function update(Request $request, int $bandbreitentarif)
    {
        $this->find($bandbreitentarif);
        DB::connection('sqlsrv_accountings')->table('tblBandbreiteStaffel')->where('intID',$bandbreitentarif)->update($this->tarifData($request));
        return redirect()->route('bandbreitentarife.edit',$bandbreitentarif)->with('status','Bandbreiten-Tarif gespeichert.');
    }

    public function storePreis(Request $request, int $bandbreitentarif)
    {
        $this->find($bandbreitentarif);
        $data = $this->preisData($request) + ['intStaffelGruppenID'=>$bandbreitentarif,'rowguid'=>DB::raw('NEWID()')];
        DB::connection('sqlsrv_accountings')->table('tblBandbreiteStaffelPreise')->insert($data);
        return redirect()->route('bandbreitentarife.edit',$bandbreitentarif)->with('status','Preiszeile angelegt.');
    }

    public function updatePreis(Request $request, int $bandbreitentarif, int $preis)
    {
        $this->find($bandbreitentarif);
        $db = DB::connection('sqlsrv_accountings');
        abort_unless($db->table('tblBandbreiteStaffelPreise')->where('intID',$preis)->where('intStaffelGruppenID',$bandbreitentarif)->exists(),404);
        $db->table('tblBandbreiteStaffelPreise')->where('intID',$preis)->update($this->preisData($request));
        return redirect()->route('bandbreitentarife.edit',$bandbreitentarif)->with('status','Preiszeile gespeichert.');
    }

    private function form(?object $tarif)
    {
        $preise = $tarif ? DB::connection('sqlsrv_accountings')->table('tblBandbreiteStaffelPreise')->where('intStaffelGruppenID',$tarif->intID)->orderBy('intMenge')->get() : collect();
        return view(session('frontend_mode','classic').'.bandbreitentarife.form', compact('tarif','preise'));
    }

    private function find(int $id)
    {
        $x=DB::connection('sqlsrv_accountings')->table('tblBandbreiteStaffel')->where('intID',$id)->first();
        abort_unless($x,404);
        return $x;
    }

    private function tarifData(Request $r): array
    {
        return $r->validate(['strBezeichnung'=>['required','string','max:255']]);
    }

    private function preisData(Request $r): array
    {
        return $r->validate(['intMenge'=>['required','integer','min:0'],'fVkPreis'=>['required','numeric']]);
    }
}
