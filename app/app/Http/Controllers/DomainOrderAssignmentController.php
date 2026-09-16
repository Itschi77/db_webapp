<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DomainOrderAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $customerFilter=trim((string)$request->query('kunde',''));
        $domainFilter=trim((string)$request->query('domain',''));
        $selectedDomainId=(int)$request->query('domain_id',0);

        $accounting=DB::connection('sqlsrv_accountings');
        $linkedIds=$accounting->table('tblAnbindungen')->where('intTyp',6)->pluck('intAnbindungReferenz')->map(fn($x)=>(int)$x)->all();

        $dq=DB::connection('sqlsrv_domains')->table('tblDomains')
            ->select('intID','strDomainname','datRegistriertAm','UMSTELLUNGintKundenID')
            ->whereNotNull('UMSTELLUNGintKundenID');
        if($domainFilter!=='') $dq->where('strDomainname','like','%'.$domainFilter.'%');
        if($customerFilter!=='' && ctype_digit($customerFilter)) $dq->where('UMSTELLUNGintKundenID',(int)$customerFilter);
        $domains=$dq->orderBy('strDomainname')->get();
        if($linkedIds){ $linked=array_fill_keys($linkedIds,true); $domains=$domains->reject(fn($d)=>isset($linked[(int)$d->intID]))->values(); }
        $domains=$domains->take(1000);

        $customerIds=$domains->pluck('UMSTELLUNGintKundenID')->map(fn($x)=>(int)$x)->unique()->values()->all();
        $customers=Kunde::whereIn('intID',$customerIds)->get(['intID','strName'])->keyBy('intID');
        foreach($domains as $d) $d->kunde=$customers->get((int)$d->UMSTELLUNGintKundenID);

        $selected=$selectedDomainId ? $domains->firstWhere('intID',$selectedDomainId) : null;
        $orders=collect(); $positions=collect();
        if($selected){
            $kid=(int)$selected->UMSTELLUNGintKundenID;
            $orders=$accounting->table('tblAuftrag')->where('intKID',$kid)->orderBy('datErfassungsdatum')->get(['intAufNr','strBeschreibung','datErfassungsdatum']);
            $positions=$accounting->table('tblAuftragPos as p')->join('tblAuftrag as a','a.intAufNr','=','p.intAufNr')
                ->where('a.intKID',$kid)->where('p.intStaffelTyp',5)
                ->orderBy('p.intID')->get(['p.intID','p.strBeschreibung','p.intAufNr','p.datFakturierAb']);
        }

        $view=session('frontend_mode','classic')==='modern'?'modern.domain-order.index':'classic.domain-order.index';
        return view($view,compact('domains','selected','orders','positions','customerFilter','domainFilter'));
    }

    public function assign(Request $request)
    {
        $v=$request->validate(['domain_id'=>'required|integer','position_id'=>'required|integer']);
        $domain=DB::connection('sqlsrv_domains')->table('tblDomains')->where('intID',(int)$v['domain_id'])->first();
        if(!$domain || !$domain->UMSTELLUNGintKundenID) throw ValidationException::withMessages(['domain_id'=>'Domain nicht gefunden oder keinem Kunden zugeordnet.']);

        $accounting=DB::connection('sqlsrv_accountings');
        if($accounting->table('tblAnbindungen')->where('intTyp',6)->where('intAnbindungReferenz',(int)$domain->intID)->exists())
            throw ValidationException::withMessages(['domain_id'=>'Für diese Domain existiert bereits eine Domain-Accounting-Anbindung.']);

        $position=$accounting->table('tblAuftragPos as p')->join('tblAuftrag as a','a.intAufNr','=','p.intAufNr')
            ->where('p.intID',(int)$v['position_id'])->where('a.intKID',(int)$domain->UMSTELLUNGintKundenID)->where('p.intStaffelTyp',5)
            ->select('p.intID')->first();
        if(!$position) throw ValidationException::withMessages(['position_id'=>'Die gewählte Auftragsposition gehört nicht zum Domain-Kunden oder ist keine Domain-Konditionsposition.']);

        $registered=$domain->datRegistriertAm ? date('Y-m-d\TH:i:s',strtotime($domain->datRegistriertAm)) : date('Y-m-d\TH:i:s');
        $row=$accounting->selectOne("INSERT INTO dbo.tblAnbindungen (intKID,intTyp,intAnbindungReferenz,boolAbrechenbar,dateAbrechenbarStart,dateAbrechenbarEnde,strKopieRechnungsinfo,intAuftragsPos,rowguid) OUTPUT INSERTED.intID AS intID VALUES (NULL,6,?,1,CONVERT(datetime,?,126),DATEFROMPARTS(2029,12,31),?,?,?)",[
            (int)$domain->intID,$registered,trim((string)$domain->strDomainname),(int)$position->intID,(string)Str::uuid()
        ]);

        return redirect()->route('domain-order.index',['domain_id'=>(int)$domain->intID])
            ->with('status','Domain wurde der Auftragsposition zugeordnet. Anbindung '.$row->intID.' wurde angelegt.');
    }
}
