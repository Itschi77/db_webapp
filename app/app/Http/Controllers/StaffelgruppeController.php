<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffelgruppeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q',''));
        $query = DB::connection('sqlsrv_accountings')->table('tblStaffelgruppe')->orderBy('strBezeichnung');
        if ($q !== '') {
            $query->where(function($x) use ($q) {
                $x->where('strBezeichnung','like','%'.$q.'%')->orWhere('strAbrechnungseinheit','like','%'.$q.'%');
                if (ctype_digit($q)) $x->orWhere('intID',(int)$q);
            });
        }
        $staffelgruppen = $query->get();
        return view(session('frontend_mode','classic').'.staffelgruppen.index', compact('staffelgruppen','q'));
    }

    public function create() { return $this->form(null); }

    public function edit(int $staffelgruppe)
    {
        $sg = $this->find($staffelgruppe);
        return $this->form($sg);
    }

    public function store(Request $request)
    {
        $data = $this->groupData($request);
        $data['rowguid'] = DB::raw('NEWID()');
        $id = DB::connection('sqlsrv_accountings')->table('tblStaffelgruppe')->insertGetId($data,'intID');
        return redirect()->route('staffelgruppen.edit',$id)->with('status','Staffelgruppe angelegt.');
    }

    public function update(Request $request, int $staffelgruppe)
    {
        $this->find($staffelgruppe);
        DB::connection('sqlsrv_accountings')->table('tblStaffelgruppe')->where('intID',$staffelgruppe)->update($this->groupData($request));
        return redirect()->route('staffelgruppen.edit',$staffelgruppe)->with('status','Staffelgruppe gespeichert.');
    }

    public function storePreis(Request $request, int $staffelgruppe)
    {
        $this->find($staffelgruppe);
        $data = $this->priceData($request) + ['intStaffelgruppeID'=>$staffelgruppe,'rowguid'=>DB::raw('NEWID()')];
        DB::connection('sqlsrv_accountings')->table('tblStaffelpreise')->insert($data);
        return redirect()->route('staffelgruppen.edit',$staffelgruppe)->with('status','Preisstufe angelegt.');
    }

    public function updatePreis(Request $request, int $staffelgruppe, int $preis)
    {
        $this->find($staffelgruppe);
        $exists = DB::connection('sqlsrv_accountings')->table('tblStaffelpreise')->where('intID',$preis)->where('intStaffelgruppeID',$staffelgruppe)->exists();
        abort_unless($exists,404);
        DB::connection('sqlsrv_accountings')->table('tblStaffelpreise')->where('intID',$preis)->update($this->priceData($request));
        return redirect()->route('staffelgruppen.edit',$staffelgruppe)->with('status','Preisstufe gespeichert.');
    }

    public function rechner(int $staffelgruppe)
    {
        $sg = $this->find($staffelgruppe);
        $preise = $this->prices($staffelgruppe);
        return view(session('frontend_mode','classic').'.staffelgruppen.rechner', compact('sg','preise'));
    }

    public function runRechner(Request $request, int $staffelgruppe)
    {
        $this->find($staffelgruppe);
        $v = $request->validate([
            'startwert'=>['required','integer','min:0'], 'endwert'=>['required','integer','min:1'],
            'schrittweite'=>['required','integer','min:1'], 'schrittpreis'=>['required','numeric'], 'startpreis'=>['required','numeric'],
        ]);
        if ($v['endwert'] <= $v['startwert']) return back()->withErrors(['endwert'=>'Endwert muss größer als Startwert sein.'])->withInput();
        $count = (int)ceil(($v['endwert'] - $v['startwert']) / $v['schrittweite']);
        if ($count > 10000) return back()->withErrors(['schrittweite'=>'Die Eingaben würden mehr als 10.000 Preisstufen erzeugen.'])->withInput();
        $rows=[]; $i=(int)$v['startwert']; $preis=(float)$v['startpreis'];
        while ($i < (int)$v['endwert']) {
            $i += (int)$v['schrittweite'];
            $preis += (float)$v['schrittpreis'];
            $rows[]=['intMenge'=>$i,'intvkpreis'=>$preis,'intStaffelgruppeID'=>$staffelgruppe,'rowguid'=>DB::raw('NEWID()')];
        }
        DB::connection('sqlsrv_accountings')->transaction(function() use ($rows) {
            foreach (array_chunk($rows,200) as $chunk) DB::connection('sqlsrv_accountings')->table('tblStaffelpreise')->insert($chunk);
        });
        return redirect()->route('staffelgruppen.rechner',$staffelgruppe)->with('status',count($rows).' Preisstufen erzeugt.');
    }

    private function form(?object $sg)
    {
        $preise = $sg ? $this->prices($sg->intID) : collect();
        return view(session('frontend_mode','classic').'.staffelgruppen.form', compact('sg','preise'));
    }
    private function prices(int $id) { return DB::connection('sqlsrv_accountings')->table('tblStaffelpreise')->where('intStaffelgruppeID',$id)->orderBy('intMenge')->get(); }
    private function find(int $id) { $x=DB::connection('sqlsrv_accountings')->table('tblStaffelgruppe')->where('intID',$id)->first(); abort_unless($x,404); return $x; }
    private function groupData(Request $r): array { return $r->validate(['strBezeichnung'=>['required','string','max:255'],'strAbrechnungseinheit'=>['required','string','max:10']]); }
    private function priceData(Request $r): array { return $r->validate(['intMenge'=>['required','integer','min:0'],'intvkpreis'=>['required','numeric']]); }
}
