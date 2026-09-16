<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DialinController extends Controller
{
    private const SAFE_COLUMNS = [
        'intID','strLogin','strrechnungsinfo','strIP','bInaktiviertesDialin','dateDialinDisabled',
        'intMaxKanaele','intMaxMehrfachLogins','boolCallback','intSessionTimeout','intMaxIdle',
        'bKundeIstInternetProfAbonnent','rowguid',
    ];

    public function index(Request $request)
    {
        $q=trim((string)$request->query('q',''));
        $query=DB::connection('sqlsrv_accountings')->table('tblAnbindungDialin')->select(self::SAFE_COLUMNS)->orderByDesc('intID');
        $this->applySearch($query,$q);
        if(session('frontend_mode','classic')==='modern'){
            $dialins=$query->paginate(100)->withQueryString();
            return view('modern.dialins.index',compact('dialins','q'));
        }
        $ids=(clone $query)->pluck('intID')->map(fn($v)=>(int)$v)->all();
        if(!$ids) return view('classic.dialins.empty',compact('q'));
        $rid=(int)$request->query('rid',0);$index=$rid?array_search($rid,$ids,true):false;if($index===false)$index=0;
        $dialin=$this->findSafe($ids[$index]);
        $nav=['first'=>$ids[0],'prev'=>$ids[$index-1]??null,'next'=>$ids[$index+1]??null,'last'=>$ids[count($ids)-1],'index'=>$index+1,'total'=>count($ids),'query'=>$q!==''?['q'=>$q]:[]];
        return $this->formView($dialin,$nav,$q);
    }

    public function create(){return $this->formView($this->blank(),null,'');}
    public function edit(int $dialin){$d=$this->findSafe($dialin);abort_unless($d,404);return $this->formView($d,null,'');}

    public function store(Request $request)
    {
        $data=$this->validatedData($request,true);$data['rowguid']=DB::raw('NEWID()');
        $id=DB::connection('sqlsrv_accountings')->table('tblAnbindungDialin')->insertGetId($data,'intID');
        return session('frontend_mode','classic')==='modern'?redirect()->route('dialins.edit',$id)->with('status','Dialin angelegt.'):redirect()->route('dialins.index',['rid'=>$id])->with('status','Dialin angelegt.');
    }

    public function update(Request $request,int $dialin)
    {
        $c=DB::connection('sqlsrv_accountings');abort_unless($c->table('tblAnbindungDialin')->where('intID',$dialin)->exists(),404);
        $c->table('tblAnbindungDialin')->where('intID',$dialin)->update($this->validatedData($request,false));
        return session('frontend_mode','classic')==='modern'?redirect()->route('dialins.edit',$dialin)->with('status','Dialin gespeichert.'):redirect()->route('dialins.index',['rid'=>$dialin])->with('status','Dialin gespeichert.');
    }

    public function storeNumber(Request $request,int $dialin)
    {
        abort_unless(DB::connection('sqlsrv_accountings')->table('tblAnbindungDialin')->where('intID',$dialin)->exists(),404);
        $v=$request->validate(['intEinwahlnummerID'=>['required','integer','min:1'],'datBeginn'=>['required','date'],'datEnde'=>['required','date']]);
        $v['intDID']=$dialin;$v['rowguid']=DB::raw('NEWID()');
        DB::connection('sqlsrv_accountings')->table('tblAnbindungDialinEinwahlnummern')->insert($v);
        return back()->with('status','Einwahlnummer-Zuordnung angelegt.');
    }

    public function updateNumber(Request $request,int $dialin,int $nummer)
    {
        $c=DB::connection('sqlsrv_accountings');abort_unless($c->table('tblAnbindungDialinEinwahlnummern')->where('intID',$nummer)->where('intDID',$dialin)->exists(),404);
        $v=$request->validate(['intEinwahlnummerID'=>['required','integer','min:1'],'datBeginn'=>['required','date'],'datEnde'=>['required','date']]);
        $c->table('tblAnbindungDialinEinwahlnummern')->where('intID',$nummer)->where('intDID',$dialin)->update($v);
        return back()->with('status','Einwahlnummer-Zuordnung gespeichert.');
    }

    private function formView(object $dialin,?array $nav,string $q)
    {
        $numbers=$dialin->intID?DB::connection('sqlsrv_accountings')->table('tblAnbindungDialinEinwahlnummern')->select('intID','intDID','intEinwahlnummerID','datBeginn','datEnde')->where('intDID',$dialin->intID)->orderBy('datBeginn')->get():collect();
        $numberLabels=[1=>'9598100'];
        return view(session('frontend_mode','classic').'.dialins.form',compact('dialin','nav','q','numbers','numberLabels'));
    }

    private function findSafe(int $id): ?object
    {
        return DB::connection('sqlsrv_accountings')->table('tblAnbindungDialin')->select(self::SAFE_COLUMNS)->where('intID',$id)->first();
    }

    private function blank(): object
    {
        return (object)['intID'=>null,'strLogin'=>'','strrechnungsinfo'=>'','strIP'=>'255.255.255.254','bInaktiviertesDialin'=>0,'dateDialinDisabled'=>null,'intMaxKanaele'=>null,'intMaxMehrfachLogins'=>null,'boolCallback'=>0,'intSessionTimeout'=>0,'intMaxIdle'=>0,'bKundeIstInternetProfAbonnent'=>0];
    }

    private function validatedData(Request $request,bool $creating): array
    {
        $rules=['strLogin'=>['required','string','max:255'],'strrechnungsinfo'=>['required','string','max:8000'],'strIP'=>['required','string','max:32'],'dateDialinDisabled'=>['nullable','date'],'intMaxKanaele'=>['nullable','integer'],'intMaxMehrfachLogins'=>['nullable','integer'],'intSessionTimeout'=>['nullable','integer'],'intMaxIdle'=>['nullable','integer'],'strKennwort'=>[$creating?'required':'nullable','string','max:50']];
        $v=$request->validate($rules);
        $password=$v['strKennwort']??'';unset($v['strKennwort']);if($creating||$password!=='')$v['strKennwort']=$password;
        $v['bInaktiviertesDialin']=$request->boolean('bInaktiviertesDialin')?1:0;
        $v['boolCallback']=$request->boolean('boolCallback')?-1:0;
        $v['bKundeIstInternetProfAbonnent']=$request->boolean('bKundeIstInternetProfAbonnent')?1:0;
        foreach(['intMaxKanaele','intMaxMehrfachLogins','intSessionTimeout','intMaxIdle'] as $k)$v[$k]=$v[$k]??null;
        $v['dateDialinDisabled']=$v['dateDialinDisabled']??null;
        return $v;
    }

    private function applySearch($query,string $q): void
    {
        if($q==='')return;$query->where(function($x)use($q){$like='%'.$q.'%';$x->where('strLogin','like',$like)->orWhere('strrechnungsinfo','like',$like)->orWhere('strIP','like',$like);if(ctype_digit($q))$x->orWhere('intID',(int)$q);});
    }
}
