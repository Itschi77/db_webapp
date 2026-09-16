<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DomainEintragController extends Controller
{
    public function index(Request $request)
    {
        $q=trim((string)$request->query('q',''));
        $c=DB::connection('sqlsrv_domains');
        $query=$c->table('tblAllgemeineDomain as ad')
            ->leftJoin('tblDomains as d','ad.intDomainID','=','d.intID')
            ->leftJoin('tblDomainAuftrag as da','ad.intDomainID','=','da.intID')
            ->select('ad.intID','ad.strTyp','ad.intDomainID','d.strDomainKlartextname',
                DB::raw("CASE WHEN RTRIM(ad.strTyp)='DOMAIN' THEN RTRIM(d.strDomainname) ELSE RTRIM(da.strDomainName) END AS Domainname"))
            ->orderByRaw("CASE WHEN RTRIM(ad.strTyp)='DOMAIN' THEN RTRIM(d.strDomainname) ELSE RTRIM(da.strDomainName) END");
        if($q!=='') $query->where(function($x)use($q){$like='%'.$q.'%';$x->where('d.strDomainname','like',$like)->orWhere('da.strDomainName','like',$like)->orWhere('d.strDomainKlartextname','like',$like);if(ctype_digit($q))$x->orWhere('ad.intID',(int)$q);});
        if(session('frontend_mode','classic')==='modern'){
            $domains=$query->paginate(100)->withQueryString();
            return view('modern.domain-eintraege.index',compact('domains','q'));
        }
        $ids=(clone $query)->pluck('ad.intID')->map(fn($v)=>(int)$v)->all();
        if(!$ids) return view('classic.domain-eintraege.empty',compact('q'));
        $rid=(int)$request->query('rid',0);$idx=$rid?array_search($rid,$ids,true):false;if($idx===false)$idx=0;
        return $this->classicForm($ids[$idx],$ids,$idx,$q);
    }

    public function show(int $domain)
    {
        return session('frontend_mode','classic')==='classic'
            ? redirect()->route('domain-eintraege.index',['rid'=>$domain])
            : $this->modernForm($domain);
    }

    public function store(Request $request,int $domain)
    {
        $this->domain($domain);$v=$this->validateEntry($request);
        $ttl=$this->defaultTtl($domain);
        DB::connection('sqlsrv_domains')->table('tblDomainEintraege')->insert([
            'intIDAllgemeineDomain'=>$domain,'strName'=>$v['strName']?:null,'strTyp'=>strtoupper($v['strTyp']),
            'intTTL'=>$ttl,'strAdresse'=>$v['strAdresse'],'Datum'=>now(),
        ]);
        return back()->with('status','DNS-Eintrag angelegt. Die DNS-Zone wurde noch nicht automatisch neu eingelesen.');
    }

    public function update(Request $request,int $domain,int $entry)
    {
        $this->domain($domain);$v=$this->validateEntry($request);
        $c=DB::connection('sqlsrv_domains');abort_unless($c->table('tblDomainEintraege')->where('intID',$entry)->where('intIDAllgemeineDomain',$domain)->exists(),404);
        $c->table('tblDomainEintraege')->where('intID',$entry)->update(['strName'=>$v['strName']?:null,'strTyp'=>strtoupper($v['strTyp']),'strAdresse'=>$v['strAdresse'],'Datum'=>now()]);
        return back()->with('status','DNS-Eintrag gespeichert. Die DNS-Zone wurde noch nicht automatisch neu eingelesen.');
    }

    public function destroy(int $domain,int $entry)
    {
        $this->domain($domain);$c=DB::connection('sqlsrv_domains');
        $deleted=$c->table('tblDomainEintraege')->where('intID',$entry)->where('intIDAllgemeineDomain',$domain)->delete();abort_unless($deleted,404);
        return back()->with('status','DNS-Eintrag gelöscht. Die DNS-Zone wurde noch nicht automatisch neu eingelesen.');
    }

    private function validateEntry(Request $r):array{return $r->validate(['strName'=>['nullable','string','max:50'],'strTyp'=>['required','string','max:10'],'strAdresse'=>['required','string','max:1520']]);}
    private function defaultTtl(int $domain):int{return 3600;}

    private function domain(int $id):object
    {
        $c=DB::connection('sqlsrv_domains');$r=$c->table('tblAllgemeineDomain as ad')->leftJoin('tblDomains as d','ad.intDomainID','=','d.intID')->leftJoin('tblDomainAuftrag as da','ad.intDomainID','=','da.intID')
            ->select('ad.intID','ad.strTyp','ad.intDomainID','d.strDomainKlartextname',DB::raw("CASE WHEN RTRIM(ad.strTyp)='DOMAIN' THEN RTRIM(d.strDomainname) ELSE RTRIM(da.strDomainName) END AS Domainname"))->where('ad.intID',$id)->first();abort_unless($r,404);return $r;
    }
    private function entries(int $domain){return DB::connection('sqlsrv_domains')->table('tblDomainEintraege')->select('intID','strName','strTyp','intTTL','strAdresse','Datum')->where('intIDAllgemeineDomain',$domain)->orderBy('intID')->get();}
    private function classicForm(int $id,array $ids,int $idx,string $q){$domain=$this->domain($id);$entries=$this->entries($id);$nav=['first'=>$ids[0],'prev'=>$ids[$idx-1]??null,'next'=>$ids[$idx+1]??null,'last'=>$ids[count($ids)-1],'index'=>$idx+1,'total'=>count($ids)];return view('classic.domain-eintraege.form',compact('domain','entries','nav','q'));}
    private function modernForm(int $id){$domain=$this->domain($id);$entries=$this->entries($id);return view('modern.domain-eintraege.form',compact('domain','entries'));}
}
