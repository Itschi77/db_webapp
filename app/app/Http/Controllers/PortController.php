<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortController extends Controller
{
    private const DESCRIPTION_CHOICES = [
        'Intel Switch Port','Cisco1601 in Ptzn','Cisco Switch Delfin Port','3Com Switch Stadthaus',
        '3Com Switch Duisdorf Port','Intel Switch Stadthaus Port','Intel Switch 2 Port',
        'Intel Switch (Internet) Port','BGP 3662 Stadthaus','BGP 3662 Delfinarium','Intel 460T Switch Beuel',
    ];

    public function index(Request $request)
    {
        $q=trim((string)$request->query('q',''));
        $query=DB::connection('sqlsrv_accountings')->table('tblPort')->orderByDesc('intid');
        $this->applySearch($query,$q);
        if(session('frontend_mode','classic')==='modern'){
            $ports=$query->paginate(100)->withQueryString();
            return view('modern.ports.index',compact('ports','q'));
        }
        $ids=(clone $query)->pluck('intid')->map(fn($v)=>(int)$v)->all();
        if(!$ids) return view('classic.ports.empty',compact('q'));
        $rid=(int)$request->query('rid',0); $index=$rid?array_search($rid,$ids,true):false; if($index===false)$index=0;
        $port=DB::connection('sqlsrv_accountings')->table('tblPort')->where('intid',$ids[$index])->first();
        $nav=['first'=>$ids[0],'prev'=>$ids[$index-1]??null,'next'=>$ids[$index+1]??null,'last'=>$ids[count($ids)-1],'index'=>$index+1,'total'=>count($ids),'query'=>$q!==''?['q'=>$q]:[]];
        return $this->formView($port,$nav,$q);
    }

    public function create(){ return $this->formView((object)['intid'=>null,'strRouterIP'=>'','strSNMPCommunity'=>'','strMIBVarIN'=>'','strMIBVarOUT'=>'','strPortDescription'=>'','strrechnungsinfo'=>'','decOverrunLimit'=>null,'boolDeaktiviert'=>0,'strMIBVarDESCR'=>'','strIfDescrMust'=>'','strIfDescrCurrent'=>'','dateIfDescrCurrent'=>null],null,''); }
    public function edit(int $port){$p=DB::connection('sqlsrv_accountings')->table('tblPort')->where('intid',$port)->first();abort_unless($p,404);return $this->formView($p,null,'');}

    public function store(Request $request)
    {
        $data=$this->validatedData($request);$data['rowguid']=DB::raw('NEWID()');
        $id=DB::connection('sqlsrv_accountings')->table('tblPort')->insertGetId($data,'intid');
        return session('frontend_mode','classic')==='modern'?redirect()->route('ports.edit',$id)->with('status','Port angelegt.'):redirect()->route('ports.index',['rid'=>$id])->with('status','Port angelegt.');
    }

    public function update(Request $request,int $port)
    {
        $c=DB::connection('sqlsrv_accountings');abort_unless($c->table('tblPort')->where('intid',$port)->exists(),404);
        $c->table('tblPort')->where('intid',$port)->update($this->validatedData($request));
        return session('frontend_mode','classic')==='modern'?redirect()->route('ports.edit',$port)->with('status','Port gespeichert.'):redirect()->route('ports.index',['rid'=>$port])->with('status','Port gespeichert.');
    }

    private function formView(object $port,?array $nav,string $q)
    {
        $descriptionChoices=self::DESCRIPTION_CHOICES;
        $communityChoices=DB::connection('sqlsrv_accountings')->table('tblPort')->select('strSNMPCommunity')->distinct()->whereNotNull('strSNMPCommunity')->where('strSNMPCommunity','<>','')->orderBy('strSNMPCommunity')->pluck('strSNMPCommunity')->all();
        return view(session('frontend_mode','classic').'.ports.form',compact('port','nav','q','descriptionChoices','communityChoices'));
    }

    private function validatedData(Request $request): array
    {
        $v=$request->validate([
            'strRouterIP'=>['required','string','max:255'],'strSNMPCommunity'=>['required','string','max:255'],
            'strMIBVarIN'=>['nullable','string','max:255'],'strMIBVarOUT'=>['nullable','string','max:255'],'strPortDescription'=>['nullable','string','max:255'],
            'strrechnungsinfo'=>['required','string','max:8000'],'decOverrunLimit'=>['nullable','regex:/^\d{1,28}$/'],
            'strMIBVarDESCR'=>['nullable','string','max:255'],'strIfDescrMust'=>['nullable','string','max:255'],
        ]);
        foreach(['strMIBVarIN','strMIBVarOUT','strPortDescription','strMIBVarDESCR','strIfDescrMust'] as $k) $v[$k]=$v[$k]??null;
        $v['decOverrunLimit']=$v['decOverrunLimit']??null;$v['boolDeaktiviert']=$request->boolean('boolDeaktiviert')?-1:0;
        return $v;
    }

    private function applySearch($query,string $q): void
    {
        if($q==='')return; $query->where(function($x)use($q){$like='%'.$q.'%';$x->where('strRouterIP','like',$like)->orWhere('strMIBVarIN','like',$like)->orWhere('strMIBVarOUT','like',$like)->orWhere('strPortDescription','like',$like)->orWhere('strrechnungsinfo','like',$like)->orWhere('strMIBVarDESCR','like',$like)->orWhere('strIfDescrMust','like',$like);if(ctype_digit($q))$x->orWhere('intid',(int)$q);});
    }
}
