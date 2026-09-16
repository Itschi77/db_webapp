<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NetzController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q',''));
        $query = DB::connection('sqlsrv_accountings')->table('tblAnbindungNetze')->orderByDesc('intID');
        $this->applySearch($query,$q);

        if (session('frontend_mode','classic') === 'modern') {
            $netze = $query->paginate(100)->withQueryString();
            return view('modern.netze.index',compact('netze','q'));
        }

        $ids = (clone $query)->pluck('intID')->map(fn($v)=>(int)$v)->all();
        if (!$ids) return view('classic.netze.empty',compact('q'));
        $rid=(int)$request->query('rid',0);
        $index=$rid ? array_search($rid,$ids,true) : false;
        if ($index===false) $index=0;
        $id=$ids[$index];
        $netz=DB::connection('sqlsrv_accountings')->table('tblAnbindungNetze')->where('intID',$id)->first();
        $nav=['first'=>$ids[0],'prev'=>$ids[$index-1]??null,'next'=>$ids[$index+1]??null,'last'=>$ids[count($ids)-1],'index'=>$index+1,'total'=>count($ids),'query'=>$q!==''?['q'=>$q]:[]];
        return view('classic.netze.form',compact('netz','nav','q'));
    }

    public function create()
    {
        $netz=(object)['intID'=>null,'strNetzwerk'=>'','intNetzmaske'=>32,'intGatewayRouter'=>null,'intKundenNetz'=>0,'intAccountingEingerichtet'=>0,'intInUse'=>0,'strVerwendung'=>'','strBemerkung'=>'','strStandort'=>'','strrechnungsinfo'=>''];
        return view(session('frontend_mode','classic').'.netze.form',compact('netz')+['nav'=>null,'q'=>'']);
    }

    public function edit(int $netz)
    {
        $netz=DB::connection('sqlsrv_accountings')->table('tblAnbindungNetze')->where('intID',$netz)->first();
        abort_unless($netz,404);
        return view(session('frontend_mode','classic').'.netze.form',compact('netz')+['nav'=>null,'q'=>'']);
    }

    public function store(Request $request)
    {
        $data=$this->validatedData($request);
        $data['rowguid']=DB::raw('NEWID()');
        $id=DB::connection('sqlsrv_accountings')->table('tblAnbindungNetze')->insertGetId($data,'intID');
        return session('frontend_mode','classic')==='modern'
            ? redirect()->route('netze.edit',$id)->with('status','Netz angelegt.')
            : redirect()->route('netze.index',['rid'=>$id])->with('status','Netz angelegt.');
    }

    public function update(Request $request,int $netz)
    {
        $c=DB::connection('sqlsrv_accountings');
        abort_unless($c->table('tblAnbindungNetze')->where('intID',$netz)->exists(),404);
        $c->table('tblAnbindungNetze')->where('intID',$netz)->update($this->validatedData($request));
        return session('frontend_mode','classic')==='modern'
            ? redirect()->route('netze.edit',$netz)->with('status','Netz gespeichert.')
            : redirect()->route('netze.index',['rid'=>$netz])->with('status','Netz gespeichert.');
    }

    private function validatedData(Request $request): array
    {
        $v=$request->validate([
            'strNetzwerk'=>['required','string','max:255'],
            'intNetzmaske'=>['required','integer','between:0,32'],
            'intGatewayRouter'=>['nullable','integer'],
            'strVerwendung'=>['nullable','string','max:255'],
            'strBemerkung'=>['nullable','string','max:255'],
            'strStandort'=>['nullable','string','max:50'],
            'strrechnungsinfo'=>['required','string','max:8000'],
        ]);
        $v['intKundenNetz']=$request->boolean('intKundenNetz')?-1:0;
        $v['intAccountingEingerichtet']=$request->boolean('intAccountingEingerichtet')?-1:0;
        $v['intInUse']=$request->boolean('intInUse')?-1:0;
        return $v;
    }

    private function applySearch($query,string $q): void
    {
        if ($q==='') return;
        $query->where(function($x) use($q){
            $like='%'.$q.'%';
            $x->where('strNetzwerk','like',$like)->orWhere('strVerwendung','like',$like)->orWhere('strBemerkung','like',$like)->orWhere('strStandort','like',$like)->orWhere('strrechnungsinfo','like',$like);
            if (ctype_digit($q)) $x->orWhere('intID',(int)$q)->orWhere('intNetzmaske',(int)$q)->orWhere('intGatewayRouter',(int)$q);
        });
    }
}
