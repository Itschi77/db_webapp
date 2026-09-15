<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DomainkonditionController extends Controller
{
    private const INTERVALL = [4=>'Tage',5=>'Wochen',6=>'Monate',7=>'Jahre'];
    private const ENTHALTEN = [4=>'Tage',5=>'Monate',6=>'Wochen',7=>'Jahre'];

    public function index(Request $request)
    {
        $q=trim((string)$request->query('q',''));
        $query=DB::connection('sqlsrv_domains')->table('tblDomainKonditionen')->orderBy('strKonditionsName');
        if($q!=='') $query->where(function($x) use($q){$x->where('strKonditionsName','like','%'.$q.'%'); if(ctype_digit($q)) $x->orWhere('intID',(int)$q);});
        $konditionen=$query->get();
        return view(session('frontend_mode','classic').'.domainkonditionen.index',compact('konditionen','q'));
    }

    public function create(){ return $this->form(null); }
    public function edit(int $domainkondition){ return $this->form($this->find($domainkondition)); }

    public function store(Request $request)
    {
        $data=$this->data($request);
        $id=DB::connection('sqlsrv_domains')->table('tblDomainKonditionen')->insertGetId($data,'intID');
        return redirect()->route('domainkonditionen.edit',$id)->with('status','Domainkondition angelegt.');
    }

    public function update(Request $request,int $domainkondition)
    {
        $this->find($domainkondition);
        DB::connection('sqlsrv_domains')->table('tblDomainKonditionen')->where('intID',$domainkondition)->update($this->data($request));
        return redirect()->route('domainkonditionen.edit',$domainkondition)->with('status','Domainkondition gespeichert.');
    }

    private function find(int $id)
    {
        $x=DB::connection('sqlsrv_domains')->table('tblDomainKonditionen')->where('intID',$id)->first(); abort_unless($x,404); return $x;
    }

    private function form(?object $kondition)
    {
        $intervall=self::INTERVALL; $enthalten=self::ENTHALTEN;
        return view(session('frontend_mode','classic').'.domainkonditionen.form',compact('kondition','intervall','enthalten'));
    }

    private function data(Request $r): array
    {
        $v=$r->validate([
            'strKonditionsName'=>['required','string','max:255'],
            'intR_AbrechnungEinheit'=>['required','integer','min:1'],
            'intR_AbrechnungIntervall'=>['required',Rule::in(array_keys(self::INTERVALL))],
            'fR_IntervallPreis'=>['required','numeric'],
            'intEnthaltenAnzahl'=>['nullable','integer','min:0'],
            'intEnthaltenEinheit'=>['nullable',Rule::in(array_keys(self::ENTHALTEN))],
            'fEinrichtungsPreis'=>['nullable','numeric'],
            'strEinrichtungRechnungsInfo'=>['nullable','string','max:255'],
        ]);
        $v['boolFuerKonnektierung']=$r->boolean('boolFuerKonnektierung')?-1:0;
        $v['boolFuerSecondary']=$r->boolean('boolFuerSecondary')?-1:0;
        $v['boolVeraltet']=$r->boolean('boolVeraltet')?-1:0;
        $v['intEnthaltenAnzahl']=$v['intEnthaltenAnzahl']??null;
        $v['intEnthaltenEinheit']=$v['intEnthaltenEinheit']??null;
        $v['fEinrichtungsPreis']=$v['fEinrichtungsPreis']??null;
        $v['strEinrichtungRechnungsInfo']=$v['strEinrichtungRechnungsInfo']??null;
        return $v;
    }
}
