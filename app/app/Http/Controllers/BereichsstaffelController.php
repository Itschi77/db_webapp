<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BereichsstaffelController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q',''));
        $query = DB::connection('sqlsrv_accountings')->table('tblBereichsStaffel')->orderBy('strBezeichnung');
        if ($q !== '') {
            $query->where(function($x) use ($q) {
                $x->where('strBezeichnung','like','%'.$q.'%')->orWhere('strAbrechnungseinheit','like','%'.$q.'%');
                if (ctype_digit($q)) $x->orWhere('intID',(int)$q);
            });
        }
        $staffeln = $query->get();
        return view(session('frontend_mode','classic').'.bereichsstaffeln.index', compact('staffeln','q'));
    }

    public function create() { return $this->form(null); }

    public function edit(int $bereichsstaffel) { return $this->form($this->find($bereichsstaffel)); }

    public function store(Request $request)
    {
        $data = $this->groupData($request);
        $data['rowguid'] = DB::raw('NEWID()');
        $id = DB::connection('sqlsrv_accountings')->table('tblBereichsStaffel')->insertGetId($data,'intID');
        return redirect()->route('bereichsstaffeln.edit',$id)->with('status','Bereichsstaffel angelegt.');
    }

    public function update(Request $request, int $bereichsstaffel)
    {
        $this->find($bereichsstaffel);
        DB::connection('sqlsrv_accountings')->table('tblBereichsStaffel')->where('intID',$bereichsstaffel)->update($this->groupData($request));
        return redirect()->route('bereichsstaffeln.edit',$bereichsstaffel)->with('status','Bereichsstaffel gespeichert.');
    }

    public function storePreis(Request $request, int $bereichsstaffel)
    {
        $this->find($bereichsstaffel);
        $data = $this->priceData($request) + ['intStaffelGruppenID'=>$bereichsstaffel,'rowguid'=>DB::raw('NEWID()')];
        DB::connection('sqlsrv_accountings')->table('tblBereichsStaffelPreise')->insert($data);
        return redirect()->route('bereichsstaffeln.edit',$bereichsstaffel)->with('status','Bereich angelegt.');
    }

    public function updatePreis(Request $request, int $bereichsstaffel, int $preis)
    {
        $this->find($bereichsstaffel);
        $db = DB::connection('sqlsrv_accountings');
        abort_unless($db->table('tblBereichsStaffelPreise')->where('intID',$preis)->where('intStaffelGruppenID',$bereichsstaffel)->exists(),404);
        $db->table('tblBereichsStaffelPreise')->where('intID',$preis)->update($this->priceData($request));
        return redirect()->route('bereichsstaffeln.edit',$bereichsstaffel)->with('status','Bereich gespeichert.');
    }

    private function form(?object $staffel)
    {
        $preise = $staffel ? DB::connection('sqlsrv_accountings')->table('tblBereichsStaffelPreise')->where('intStaffelGruppenID',$staffel->intID)->orderBy('intMengeAb')->orderBy('intMengeBis')->get() : collect();
        return view(session('frontend_mode','classic').'.bereichsstaffeln.form', compact('staffel','preise'));
    }

    private function find(int $id)
    {
        $x = DB::connection('sqlsrv_accountings')->table('tblBereichsStaffel')->where('intID',$id)->first();
        abort_unless($x,404);
        return $x;
    }

    private function groupData(Request $r): array
    {
        return $r->validate(['strBezeichnung'=>['required','string','max:255'],'strAbrechnungseinheit'=>['required','string','max:10']]);
    }

    private function priceData(Request $r): array
    {
        $v = $r->validate([
            'fGrundgebuehr'=>['required','numeric'], 'fBereichsGrundgebuehr'=>['required','numeric'], 'fStueckpreis'=>['required','numeric'],
            'intMengeAb'=>['required','integer','min:0'], 'intMengeBis'=>['required','integer','min:0'],
        ]);
        if ((int)$v['intMengeBis'] < (int)$v['intMengeAb']) abort(422,'Menge bis muss größer oder gleich Menge ab sein.');
        return $v;
    }
}
