<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnbindungController extends Controller
{
    private const TYPES = [
        1 => 'Ip-NetzAccounting',
        2 => 'Port-Accounting',
        3 => 'Dialin-Accounting',
        4 => 'Fremd-Accounting',
        5 => 'Zeit-Accounting',
        6 => 'Domainen-Accounting',
        7 => 'SMS-Accounting',
    ];

    public function all(Request $request)
    {
        $q = trim((string)$request->query('q',''));
        $type = (int)$request->query('typ',0);
        $c = DB::connection('sqlsrv_accountings');
        $query = $c->table('tblAnbindungen as an')
            ->join('tblAuftragPos as ap','ap.intID','=','an.intAuftragsPos')
            ->join('tblAuftrag as a','a.intAufNr','=','ap.intAufNr')
            ->select('an.*','ap.intAufNr','a.intKID as auftragKID')
            ->orderByDesc('an.intID');
        if (isset(self::TYPES[$type])) $query->where('an.intTyp',$type);
        $fieldFilter = $this->fieldFilter($request);
        if ($fieldFilter) $this->applyFieldFilter($query,$fieldFilter);
        if ($q !== '') {
            $customerIds = Kunde::query()->where('strName','like','%'.$q.'%')->limit(500)->pluck('intID')->all();
            $query->where(function($x) use($q,$customerIds) {
                $x->where('an.strKopieRechnungsinfo','like','%'.$q.'%');
                if (ctype_digit($q)) {
                    $n=(int)$q;
                    $x->orWhere('an.intID',$n)->orWhere('an.intAnbindungReferenz',$n)->orWhere('an.intAuftragsPos',$n)->orWhere('ap.intAufNr',$n)->orWhere('a.intKID',$n);
                }
                if ($customerIds) $x->orWhereIn('a.intKID',$customerIds);
            });
        }

        if (session('frontend_mode','classic') === 'classic') {
            $total=(clone $query)->reorder()->count();
            if ($total===0) {
                $types=self::TYPES;
                return view('classic.anbindungen.empty',compact('types','q','type','fieldFilter'));
            }
            $rid=(int)$request->query('rid',0);
            $record=$rid ? (clone $query)->reorder()->where('an.intID',$rid)->first() : null;
            if(!$record) $record=(clone $query)->reorder()->orderByDesc('an.intID')->first();
            [$position,$auftrag,$kunde]=$this->positionContext((int)$record->intAuftragsPos);
            $before=(clone $query)->reorder()->where('an.intID','>',$record->intID)->count();
            $prev=(clone $query)->reorder()->where('an.intID','>',$record->intID)->orderBy('an.intID')->value('an.intID');
            $next=(clone $query)->reorder()->where('an.intID','<',$record->intID)->orderByDesc('an.intID')->value('an.intID');
            $first=(clone $query)->reorder()->orderByDesc('an.intID')->value('an.intID');
            $last=(clone $query)->reorder()->orderBy('an.intID')->value('an.intID');
            $nav=['index'=>$before+1,'total'=>$total,'prev'=>$prev,'next'=>$next,'first'=>$first,'last'=>$last,
                'query'=>$request->except('rid')];
            return $this->globalForm($record,(int)$record->intID,compact('position','auftrag','kunde'),$nav,$fieldFilter);
        }

        $anbindungen = $query->paginate(100)->withQueryString();
        $kunden = Kunde::query()->whereIn('intID',$anbindungen->getCollection()->pluck('auftragKID')->filter()->unique()->all())
            ->get(['intID','strName'])->keyBy('intID');
        foreach ($anbindungen as $a) {
            $a->kunde = $kunden->get((int)$a->auftragKID);
            $a->referenzInfo = $this->referenceInfo((int)$a->intTyp,(int)$a->intAnbindungReferenz,(int)$a->intID);
        }
        $types=self::TYPES;
        return view('modern.anbindungen.all',compact('anbindungen','types','q','type','fieldFilter'));
    }

    public function globalCreate(Request $request)
    {
        $anbindung=(object)['intTyp'=>1,'intAnbindungReferenz'=>0,'boolAbrechenbar'=>1,'dateAbrechenbarStart'=>date('Y-m-d 00:00:00'),'dateAbrechenbarEnde'=>'2029-12-31 23:59:59','strKopieRechnungsinfo'=>'','intAuftragsPos'=>''];
        $mode=$request->query('ui')==='modern' ? 'modern' : null;
        return $this->globalForm($anbindung,null,null,null,null,$mode);
    }

    public function globalStore(Request $request)
    {
        [$position,$auftrag,$kunde]=$this->positionContext((int)$request->input('intAuftragsPos'));
        $data=$this->validateData($request,(int)$kunde->intID,(int)$position->intID,true);
        $data['intKID']=null;
        $data['rowguid']=(string)Str::uuid();
        if (!$request->filled('strKopieRechnungsinfo')) $data['strKopieRechnungsinfo']=$position->txtInfo ?? null;
        $id=DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->insertGetId($data,'intID');
        return redirect()->route(session('frontend_mode','classic')==='classic'?'anbindungen.index':'anbindungen.edit',session('frontend_mode','classic')==='classic'?['rid'=>$id]:[$id])->with('status','Anbindung angelegt.');
    }

    public function globalEdit(Request $request,int $anbindung)
    {
        $a=DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->where('intID',$anbindung)->first(); abort_unless($a,404);
        [$position,$auftrag,$kunde]=$this->positionContext((int)$a->intAuftragsPos);
        $mode=$request->query('ui')==='modern' ? 'modern' : null;
        return $this->globalForm($a,$a->intID,compact('position','auftrag','kunde'),null,null,$mode);
    }

    public function globalUpdate(Request $request,int $anbindung)
    {
        $c=DB::connection('sqlsrv_accountings');
        $existing=$c->table('tblAnbindungen')->where('intID',$anbindung)->first(); abort_unless($existing,404);
        [$position,$auftrag,$kunde]=$this->positionContext((int)$request->input('intAuftragsPos'));
        $data=$this->validateData($request,(int)$kunde->intID,(int)$position->intID,false);
        unset($data['intKID']);
        $c->table('tblAnbindungen')->where('intID',$anbindung)->update($data);
        return redirect()->route(session('frontend_mode','classic')==='classic'?'anbindungen.index':'anbindungen.edit',session('frontend_mode','classic')==='classic'?['rid'=>$anbindung]:[$anbindung])->with('status','Anbindung gespeichert.');
    }

    public function index(int $kunde,int $auftrag,int $position)
    {
        [$kunde,$auftrag,$position]=$this->context($kunde,$auftrag,$position);
        $anbindungen=DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->where('intAuftragsPos',$position->intID)->orderBy('intID')->get();
        foreach($anbindungen as $a) $a->referenzInfo=$this->referenceInfo((int)$a->intTyp,(int)$a->intAnbindungReferenz,(int)$a->intID);
        $types=self::TYPES;
        return view(session('frontend_mode','classic').'.anbindungen.index',compact('kunde','auftrag','position','anbindungen','types'));
    }

    public function create(int $kunde,int $auftrag,int $position)
    {
        [$kunde,$auftrag,$position]=$this->context($kunde,$auftrag,$position);
        $anbindung=(object)['intTyp'=>1,'intAnbindungReferenz'=>0,'boolAbrechenbar'=>1,'dateAbrechenbarStart'=>date('Y-m-d 00:00:00'),'dateAbrechenbarEnde'=>'2029-12-31 23:59:59','strKopieRechnungsinfo'=>$position->txtInfo ?? ''];
        return $this->form($kunde,$auftrag,$position,$anbindung,null);
    }

    public function store(Request $request,int $kunde,int $auftrag,int $position)
    {
        [$kunde,$auftrag,$position]=$this->context($kunde,$auftrag,$position);
        $data=$this->validateData($request,(int)$kunde->intID,(int)$position->intID,true);
        $data['rowguid']=(string)Str::uuid();
        $id=DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->insertGetId($data,'intID');
        return redirect()->route('kunden.auftraege.positionen.anbindungen.edit',[$kunde->intID,$auftrag->intAufNr,$position->intID,$id])->with('status','Anbindung angelegt.');
    }

    public function edit(int $kunde,int $auftrag,int $position,int $anbindung)
    {
        [$kunde,$auftrag,$position]=$this->context($kunde,$auftrag,$position);
        $anbindung=DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->where('intID',$anbindung)->where('intAuftragsPos',$position->intID)->first(); abort_unless($anbindung,404);
        return $this->form($kunde,$auftrag,$position,$anbindung,$anbindung->intID);
    }

    public function update(Request $request,int $kunde,int $auftrag,int $position,int $anbindung)
    {
        [$kunde,$auftrag,$position]=$this->context($kunde,$auftrag,$position);
        $existing=DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->where('intID',$anbindung)->where('intAuftragsPos',$position->intID)->first(); abort_unless($existing,404);
        $data=$this->validateData($request,(int)$kunde->intID,(int)$position->intID,false);
        DB::connection('sqlsrv_accountings')->table('tblAnbindungen')->where('intID',$existing->intID)->update($data);
        return redirect()->route('kunden.auftraege.positionen.anbindungen.edit',[$kunde->intID,$auftrag->intAufNr,$position->intID,$existing->intID])->with('status','Anbindung gespeichert.');
    }

    private function globalForm(object $anbindung,?int $id,?array $ctx,?array $nav=null,?array $fieldFilter=null,?string $mode=null)
    {
        $types=self::TYPES; $referenceOptions=$this->referenceOptions($ctx ? (int)$ctx['kunde']->intID : null);
        $referenzInfo=$id ? $this->referenceInfo((int)$anbindung->intTyp,(int)$anbindung->intAnbindungReferenz,$id) : null;
        $position=$ctx['position']??null; $auftrag=$ctx['auftrag']??null; $kunde=$ctx['kunde']??null;
        $frontendMode=$mode ?? session('frontend_mode','classic');
        return view($frontendMode.'.anbindungen.global-form',compact('anbindung','id','types','referenceOptions','referenzInfo','position','auftrag','kunde','nav','fieldFilter'));
    }

    private function form($kunde,$auftrag,$position,$anbindung,?int $id)
    {
        $types=self::TYPES; $referenzInfo=$id ? $this->referenceInfo((int)$anbindung->intTyp,(int)$anbindung->intAnbindungReferenz,$id) : null;
        $referenceOptions=$this->referenceOptions((int)$kunde->intID); $readOnlyAltbestand=false;
        return view(session('frontend_mode','classic').'.anbindungen.form',compact('kunde','auftrag','position','anbindung','id','types','referenzInfo','referenceOptions','readOnlyAltbestand'));
    }

    private function validateData(Request $request,int $kid,int $positionId,bool $creating): array
    {
        $v=$request->validate([
            'intTyp'=>['required','integer','between:1,7'], 'intAnbindungReferenz'=>['required','integer','min:0'],
            'dateAbrechenbarStart'=>['required','date'], 'dateAbrechenbarEnde'=>['required','date'], 'strKopieRechnungsinfo'=>['nullable','string','max:8000']
        ]);
        $type=(int)$v['intTyp']; $ref=(int)$v['intAnbindungReferenz'];
        if ($type!==4 && !$this->referenceExists($type,$ref,$kid)) abort(422,'Die angegebene Referenz existiert für diese Accounting-Art nicht.');
        $start=$this->normalizeDate((string)$v['dateAbrechenbarStart'],false); $end=$this->normalizeDate((string)$v['dateAbrechenbarEnde'],true);
        if (strtotime($end)<strtotime($start)) abort(422,'Das Abrechnungsende darf nicht vor dem Startdatum liegen.');
        return ['intTyp'=>$type,'intAnbindungReferenz'=>$ref,'boolAbrechenbar'=>$request->boolean('boolAbrechenbar')?1:0,
            'dateAbrechenbarStart'=>$start,'dateAbrechenbarEnde'=>$end,'strKopieRechnungsinfo'=>$v['strKopieRechnungsinfo']??null,
            'intKID'=>$kid,'intAuftragsPos'=>$positionId];
    }

    private function fieldFilter(Request $request): ?array
    {
        $field=(string)$request->query('f_field','');
        $op=(string)$request->query('f_op','contains');
        $value=trim((string)$request->query('f_value',''));
        $fields=['id','reference','invoice_info','technical','customer_id','customer_name','order_id','position_id'];
        $ops=['equals','not_equals','starts','not_starts','contains','not_contains','ends','not_ends'];
        if(!in_array($field,$fields,true) || !in_array($op,$ops,true) || $value==='') return null;
        return compact('field','op','value');
    }

    private function applyFieldFilter($query,array $filter): void
    {
        $field=$filter['field']; $op=$filter['op']; $value=$filter['value'];
        if($field==='technical') { $this->applyTechnicalFilter($query,$op,$value); return; }
        if($field==='customer_name') {
            $ids=Kunde::query()->where('strName','like',$this->likeValue($op,$value))->limit(5000)->pluck('intID')->all();
            if(str_starts_with($op,'not_')) {
                if($ids) $query->whereNotIn('a.intKID',$ids);
            } else {
                $ids ? $query->whereIn('a.intKID',$ids) : $query->whereRaw('1=0');
            }
            return;
        }
        $column=match($field){
            'id'=>'an.intID','reference'=>'an.intAnbindungReferenz','invoice_info'=>'an.strKopieRechnungsinfo',
            'customer_id'=>'a.intKID','order_id'=>'ap.intAufNr','position_id'=>'an.intAuftragsPos',
        };
        $expr="CONVERT(NVARCHAR(4000), $column)";
        if($op==='equals') {$query->whereRaw("$expr = ?",[$value]); return;}
        if($op==='not_equals') {$query->whereRaw("ISNULL($expr,'') <> ?",[$value]); return;}
        $not=str_starts_with($op,'not_');
        $query->whereRaw("ISNULL($expr,'') ".($not?'NOT LIKE':'LIKE')." ?",[$this->likeValue($op,$value)]);
    }

    private function applyTechnicalFilter($query,string $op,string $value): void
    {
        $not=str_starts_with($op,'not_');
        $base=str_replace('not_','',$op);
        $cmp=$base==='equals' ? ($not?'<>':'=') : ($not?'NOT LIKE':'LIKE');
        $v=$base==='equals' ? $value : $this->likeValue($base,$value);
        $query->where(function($x) use($cmp,$v,$not){
            $x->where(function($z) use($cmp,$v,$not){$z->where('an.intTyp',1)->whereExists(function($s) use($cmp,$v,$not){$s->selectRaw('1')->from('tblAnbindungNetze as n')->whereColumn('n.intID','an.intAnbindungReferenz');$this->technicalColumns($s,['n.strNetzwerk','n.intNetzmaske','n.strrechnungsinfo'],$cmp,$v,$not);});})
              ->orWhere(function($z) use($cmp,$v,$not){$z->where('an.intTyp',2)->whereExists(function($s) use($cmp,$v,$not){$s->selectRaw('1')->from('tblPort as p')->whereColumn('p.intid','an.intAnbindungReferenz');$this->technicalColumns($s,['p.strRouterIP','p.strMIBVarIN','p.strMIBVarOUT','p.strPortDescription','p.strrechnungsinfo','p.decOverrunLimit'],$cmp,$v,$not);});})
              ->orWhere(function($z) use($cmp,$v,$not){$z->whereIn('an.intTyp',[3,5])->whereExists(function($s) use($cmp,$v,$not){$s->selectRaw('1')->from('tblAnbindungDialin as d')->whereColumn('d.intID','an.intAnbindungReferenz');$this->technicalColumns($s,['d.strLogin','d.strIP','d.strrechnungsinfo'],$cmp,$v,$not);});})
              ->orWhere(function($z) use($cmp,$v,$not){$z->where('an.intTyp',6)->whereExists(function($s) use($cmp,$v,$not){$s->selectRaw('1')->from('tblDomains as dm')->whereColumn('dm.intID','an.intAnbindungReferenz');$this->technicalColumns($s,['dm.strDomainname','dm.datRegistriertAm','dm.strDNS1'],$cmp,$v,$not);});})
              ->orWhere(function($z) use($cmp,$v,$not){$z->where('an.intTyp',7)->whereExists(function($s) use($cmp,$v,$not){$s->selectRaw('1')->from('tblSMSZugaenge as sm')->whereColumn('sm.intSMSZugaengeID','an.intAnbindungReferenz');$this->technicalColumns($s,['sm.strSMSAccountNummer','sm.intKundenNr','sm.strRechnungsinfo'],$cmp,$v,$not);});})
              ->orWhere(function($z) use($cmp,$v,$not){$z->where('an.intTyp',4)->whereExists(function($s) use($cmp,$v,$not){$s->selectRaw('1')->from('tblAnbindungAuswertung as au')->whereColumn('au.intAnbindungID','an.intID');$this->technicalColumns($s,['au.strrechnungsinfo','au.decGesamt','au.intMonat','au.intJahr'],$cmp,$v,$not);});});
        });
    }

    private function technicalColumns($query,array $columns,string $cmp,string $value,bool $not): void
    {
        $query->where(function($w) use($columns,$cmp,$value,$not){
            foreach($columns as $i=>$column){
                $sql="ISNULL(CONVERT(NVARCHAR(4000),$column),'') $cmp ?";
                if($i===0) $w->whereRaw($sql,[$value]);
                elseif($not) $w->whereRaw($sql,[$value]);
                else $w->orWhereRaw($sql,[$value]);
            }
        });
    }

    private function likeValue(string $op,string $value): string
    {
        $base=str_replace(['[','%','_'],['[[]','[%]','[_]'],$value);
        $op=str_replace('not_','',$op);
        return match($op){'starts'=>$base.'%','ends'=>'%'.$base,'contains'=>'%'.$base.'%','equals'=>$base,default=>'%'.$base.'%'};
    }

    private function normalizeDate(string $value,bool $end): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) return $value.($end?' 23:59:59':' 00:00:00');
        return date('Y-m-d H:i:s',strtotime($value));
    }

    private function referenceExists(int $type,int $id,int $kid): bool
    {
        $c=DB::connection('sqlsrv_accountings');
        return match($type){
            1=>$c->table('tblAnbindungNetze')->where('intID',$id)->exists(), 2=>$c->table('tblPort')->where('intid',$id)->exists(),
            3,5=>$c->table('tblAnbindungDialin')->where('intID',$id)->exists(), 6=>$c->table('tblDomains')->where('intID',$id)->where('intKID',$kid)->exists(),
            7=>$c->table('tblSMSZugaenge')->where('intSMSZugaengeID',$id)->exists(), default=>false,
        };
    }

    private function referenceInfo(int $type,int $id,?int $anbindungId=null): ?array
    {
        $c=DB::connection('sqlsrv_accountings');
        if ($type===4) {
            $rows=$c->table('tblAnbindungAuswertung')->select('intID','intAnbindungID','decMBin','decMBout','intMonat','intJahr','decGesamt','strrechnungsinfo','intVerbindungsdauerInSec')->where('intAnbindungID',$anbindungId)->orderByDesc('intJahr')->orderByDesc('intMonat')->limit(24)->get();
            return ['kind'=>'fremd','title'=>'Fremd-Accounting','detail'=>$rows->count().' Auswertungszeile(n), max. 24 angezeigt','rechnung'=>null,'rows'=>$rows];
        }
        $r=match($type){
            1=>$c->table('tblAnbindungNetze')->select('intID','strNetzwerk','intNetzmaske','strrechnungsinfo')->where('intID',$id)->first(),
            2=>$c->table('tblPort')->select('intid','strRouterIP','strMIBVarIN','strMIBVarOUT','strPortDescription','strrechnungsinfo','decOverrunLimit')->where('intid',$id)->first(),
            3,5=>$c->table('tblAnbindungDialin')->select('intID','strLogin','strIP','strrechnungsinfo')->where('intID',$id)->first(),
            6=>$c->table('tblDomains')->select('intID','strDomainname','datRegistriertAm','strDNS1','intStatus','datDeaktiviertAm')->where('intID',$id)->first(),
            7=>$c->table('tblSMSZugaenge')->select('intSMSZugaengeID','strSMSAccountNummer','intKundenNr','strRechnungsinfo','boolIsCustomerAccount')->where('intSMSZugaengeID',$id)->first(),
            default=>null,
        };
        if(!$r) return null;
        return match($type){
            1=>['kind'=>'netz','title'=>trim($r->strNetzwerk).'/'.$r->intNetzmaske,'detail'=>'Netz-ID '.$r->intID,'rechnung'=>$r->strrechnungsinfo,'fields'=>['strNetzwerk'=>$r->strNetzwerk,'intNetzmaske'=>$r->intNetzmaske,'strRechnungsinfo'=>$r->strrechnungsinfo]],
            2=>['kind'=>'port','title'=>trim((string)$r->strPortDescription),'detail'=>'Router '.$r->strRouterIP,'rechnung'=>$r->strrechnungsinfo,'fields'=>['strRouterIP'=>$r->strRouterIP,'strMIBVarIN'=>$r->strMIBVarIN,'strMIBVarOUT'=>$r->strMIBVarOUT,'strPortDescription'=>$r->strPortDescription,'decOverrunLimit'=>$r->decOverrunLimit,'strRechnungsinfo'=>$r->strrechnungsinfo]],
            3,5=>['kind'=>'dialin','title'=>'Dialin '.$r->strLogin,'detail'=>'IP '.$r->strIP,'rechnung'=>$r->strrechnungsinfo,'fields'=>['strLogin'=>$r->strLogin,'strIP'=>$r->strIP,'strRechnungsinfo'=>$r->strrechnungsinfo]],
            6=>['kind'=>'domain','title'=>$r->strDomainname,'detail'=>'Domain','rechnung'=>$r->strDomainname,'fields'=>['strDomainname'=>$r->strDomainname,'strDomainKlartext'=>$r->strDomainname,'dtRegistriertAm'=>$r->datRegistriertAm,'intDNSServer1'=>$r->strDNS1]],
            7=>['kind'=>'sms','title'=>'SMS '.$r->strSMSAccountNummer,'detail'=>($r->boolIsCustomerAccount?'Corporate Account':'Single Account'),'rechnung'=>$r->strRechnungsinfo,'fields'=>['strSMSAccountNummer'=>$r->strSMSAccountNummer,'intKundenNr'=>$r->intKundenNr,'strRechnungsinfo'=>$r->strRechnungsinfo]],
        };
    }

    private function referenceOptions(?int $kid): array
    {
        $c=DB::connection('sqlsrv_accountings');
        $domains=$c->table('tblDomains')->select('intID','strDomainname','datDeaktiviertAm'); if($kid) $domains->where('intKID',$kid); else $domains->whereRaw('1=0');
        return [
            1=>$c->table('tblAnbindungNetze')->select('intID','strNetzwerk','intNetzmaske')->orderBy('strNetzwerk')->get()->map(fn($r)=>['id'=>(int)$r->intID,'label'=>trim($r->strNetzwerk).'/'.$r->intNetzmaske])->all(),
            2=>$c->table('tblPort')->select('intid','strPortDescription','strRouterIP')->orderBy('strPortDescription')->get()->map(fn($r)=>['id'=>(int)$r->intid,'label'=>trim((string)$r->strPortDescription).' · '.$r->strRouterIP])->all(),
            3=>$this->dialinOptions($c), 5=>$this->dialinOptions($c),
            6=>$domains->orderBy('strDomainname')->get()->map(fn($r)=>['id'=>(int)$r->intID,'label'=>$r->strDomainname.($r->datDeaktiviertAm?' · deaktiviert':'')])->all(),
            7=>$c->table('tblSMSZugaenge')->select('intSMSZugaengeID','strSMSAccountNummer','intKundenNr')->orderBy('intSMSZugaengeID')->get()->map(fn($r)=>['id'=>(int)$r->intSMSZugaengeID,'label'=>trim($r->strSMSAccountNummer).' · Kunde '.($r->intKundenNr??'')])->all(),
        ];
    }

    private function dialinOptions($c): array
    {
        return $c->table('tblAnbindungDialin')->select('intID','strLogin','strIP','bInaktiviertesDialin')->orderBy('strLogin')->get()->map(fn($r)=>['id'=>(int)$r->intID,'label'=>$r->strLogin.' · '.$r->strIP.($r->bInaktiviertesDialin?' · inaktiv':'')])->all();
    }

    private function positionContext(int $positionId): array
    {
        $c=DB::connection('sqlsrv_accountings');
        $position=$c->table('tblAuftragPos')->where('intID',$positionId)->first(); abort_unless($position,422,'Auftragsposition nicht gefunden.');
        $auftrag=$c->table('tblAuftrag')->where('intAufNr',$position->intAufNr)->first(); abort_unless($auftrag,422,'Auftrag nicht gefunden.');
        $kunde=Kunde::find($auftrag->intKID);
        if (!$kunde) $kunde=(object)['intID'=>(int)$auftrag->intKID,'strName'=>'(Kunde nicht im aktuellen Kundenbestand)','_missing'=>true];
        return [$position,$auftrag,$kunde];
    }

    private function context(int $kunde,int $auftrag,int $position): array
    {
        $kunde=Kunde::findOrFail($kunde); $auftrag=DB::connection('sqlsrv_accountings')->table('tblAuftrag')->where('intAufNr',$auftrag)->where('intKID',$kunde->intID)->first(); abort_unless($auftrag,404);
        $position=DB::connection('sqlsrv_accountings')->table('tblAuftragPos')->where('intID',$position)->where('intAufNr',$auftrag->intAufNr)->first(); abort_unless($position,404); return [$kunde,$auftrag,$position];
    }
}
