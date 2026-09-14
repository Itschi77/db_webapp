<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnbindungController extends Controller
{
    private const TYPES = [
        1 => 'IP-Netz-Accounting',
        2 => 'Port-Accounting',
        3 => 'Dialin-Accounting',
        4 => 'Fremdaccounting',
        5 => 'Dialin-Zeitabrechnung',
        6 => 'Domain-Accounting',
        7 => 'SMS-Service',
    ];

    public function index(int $kunde, int $auftrag, int $position)
    {
        [$kunde,$auftrag,$position] = $this->context($kunde,$auftrag,$position);
        $anbindungen = DB::connection('sqlsrv_accountings')->table('tblAnbindungen')
            ->where('intAuftragsPos',$position->intID)->orderBy('intID')->get();
        foreach ($anbindungen as $a) $a->referenzInfo = $this->referenceInfo((int)$a->intTyp,(int)$a->intAnbindungReferenz);
        $types = self::TYPES;
        $mode = session('frontend_mode','classic');
        return view($mode.'.anbindungen.index',compact('kunde','auftrag','position','anbindungen','types'));
    }

    public function create(int $kunde, int $auftrag, int $position)
    {
        [$kunde,$auftrag,$position] = $this->context($kunde,$auftrag,$position);
        $anbindung=(object)['intTyp'=>1,'intAnbindungReferenz'=>0,'boolAbrechenbar'=>1,'dateAbrechenbarStart'=>date('Y-m-d'),'dateAbrechenbarEnde'=>'2029-12-31','strKopieRechnungsinfo'=>''];
        return $this->form($kunde,$auftrag,$position,$anbindung,null);
    }

    public function store(Request $request, int $kunde, int $auftrag, int $position)
    {
        [$kunde,$auftrag,$position] = $this->context($kunde,$auftrag,$position);
        $data=$this->validateData($request,$kunde->intID,$position->intID,true);
        $data['rowguid']=(string)Str::uuid();
        $id=DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->insertGetId($data,'intID');
        return redirect()->route('kunden.auftraege.positionen.anbindungen.edit',[$kunde->intID,$auftrag->intAufNr,$position->intID,$id])->with('status','Anbindung angelegt.');
    }

    public function edit(int $kunde, int $auftrag, int $position, int $anbindung)
    {
        [$kunde,$auftrag,$position] = $this->context($kunde,$auftrag,$position);
        $anbindung=DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->where('intID',$anbindung)->where('intAuftragsPos',$position->intID)->first();
        abort_unless($anbindung,404);
        return $this->form($kunde,$auftrag,$position,$anbindung,$anbindung->intID);
    }

    public function update(Request $request, int $kunde, int $auftrag, int $position, int $anbindung)
    {
        [$kunde,$auftrag,$position] = $this->context($kunde,$auftrag,$position);
        $existing=DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->where('intID',$anbindung)->where('intAuftragsPos',$position->intID)->first();
        abort_unless($existing,404);
        $data=$this->validateData($request,$kunde->intID,$position->intID,false,(int)$existing->intTyp);
        DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->where('intID',$existing->intID)->update($data);
        return redirect()->route('kunden.auftraege.positionen.anbindungen.edit',[$kunde->intID,$auftrag->intAufNr,$position->intID,$existing->intID])->with('status','Anbindung gespeichert.');
    }

    private function form($kunde,$auftrag,$position,$anbindung,?int $id)
    {
        $types=self::TYPES;
        $referenzInfo=$id ? $this->referenceInfo((int)$anbindung->intTyp,(int)$anbindung->intAnbindungReferenz) : null;
        $mode=session('frontend_mode','classic');
        return view($mode.'.anbindungen.form',compact('kunde','auftrag','position','anbindung','id','types','referenzInfo'));
    }

    private function validateData(Request $request,int $kid,int $positionId,bool $creating,?int $existingType=null): array
    {
        $allowed=$creating ? [1,2,3,5,6] : array_keys(self::TYPES);
        $v=$request->validate([
            'intTyp'=>['required','integer',function($a,$v,$fail) use($allowed){if(!in_array((int)$v,$allowed,true))$fail('Diese Accounting-Art kann hier nicht neu angelegt werden.');}],
            'intAnbindungReferenz'=>'required|integer|min:0','dateAbrechenbarStart'=>'required|date','dateAbrechenbarEnde'=>'required|date|after_or_equal:dateAbrechenbarStart','strKopieRechnungsinfo'=>'nullable|string|max:8000'
        ]);
        $type=(int)$v['intTyp'];
        if (!$this->referenceExists($type,(int)$v['intAnbindungReferenz'],$kid) && !in_array($type,[4,7],true)) abort(422,'Die angegebene Referenz existiert für diese Accounting-Art nicht.');
        $v['boolAbrechenbar']=$request->boolean('boolAbrechenbar')?1:0;
        $v['intKID']=$kid;
        $v['intAuftragsPos']=$positionId;
        $v['strKopieRechnungsinfo']=$v['strKopieRechnungsinfo'] ?: ($this->referenceInfo($type,(int)$v['intAnbindungReferenz'])['rechnung'] ?? null);
        return $v;
    }

    private function referenceExists(int $type,int $id,int $kid): bool
    {
        $c=DB::connection('sqlsrv_accountings');
        return match($type){
            1 => $c->table('tblAnbindungNetze')->where('intID',$id)->exists(),
            2 => $c->table('tblPort')->where('intid',$id)->exists(),
            3,5 => $c->table('tblAnbindungDialin')->where('intID',$id)->exists(),
            6 => $c->table('tblDomains')->where('intID',$id)->where('intKID',$kid)->exists(),
            default => true,
        };
    }

    private function referenceInfo(int $type,int $id): ?array
    {
        $c=DB::connection('sqlsrv_accountings');
        $r=match($type){
            1 => $c->table('tblAnbindungNetze')->where('intID',$id)->first(),
            2 => $c->table('tblPort')->where('intid',$id)->first(),
            3,5 => $c->table('tblAnbindungDialin')->select('intID','strLogin','strIP','bInaktiviertesDialin','strrechnungsinfo')->where('intID',$id)->first(),
            6 => $c->table('tblDomains')->select('intID','intKID','strDomainname','intStatus','datDeaktiviertAm')->where('intID',$id)->first(),
            default => null,
        };
        if(!$r) return null;
        return match($type){
            1 => ['title'=>trim($r->strNetzwerk).'/'.$r->intNetzmaske,'detail'=>trim(($r->strVerwendung??'').' '.($r->strStandort??'')),'rechnung'=>$r->strrechnungsinfo],
            2 => ['title'=>$r->strPortDescription,'detail'=>'Router '.$r->strRouterIP,'rechnung'=>$r->strrechnungsinfo],
            3,5 => ['title'=>'Dialin '.$r->strLogin,'detail'=>'IP '.$r->strIP.($r->bInaktiviertesDialin?' · inaktiv':''),'rechnung'=>$r->strrechnungsinfo],
            6 => ['title'=>$r->strDomainname,'detail'=>$r->datDeaktiviertAm?'deaktiviert':'Domain','rechnung'=>$r->strDomainname],
        };
    }

    private function context(int $kunde,int $auftrag,int $position): array
    {
        $kunde=Kunde::findOrFail($kunde);
        $auftrag=DB::connection('sqlsrv_accountings')->table('tblAuftrag')->where('intAufNr',$auftrag)->where('intKID',$kunde->intID)->first();
        abort_unless($auftrag,404);
        $position=DB::connection('sqlsrv_accountings')->table('tblAuftragPos')->where('intID',$position)->where('intAufNr',$auftrag->intAufNr)->first();
        abort_unless($position,404);
        return [$kunde,$auftrag,$position];
    }
}
