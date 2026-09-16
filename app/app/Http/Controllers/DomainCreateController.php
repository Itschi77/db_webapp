<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DomainCreateController extends Controller
{
    public function create(Request $request)
    {
        $view=session('frontend_mode','classic')==='modern' ? 'modern.domain-create' : 'classic.domain-create';
        return view($view);
    }

    public function store(Request $request)
    {
        $v=$request->validate([
            'domainname'=>['required','string','max:50'],
            'klartextname'=>['nullable','string','max:50'],
            'kundennummer'=>['required','integer','min:1'],
            'authcode'=>['nullable','string','max:50'],
        ]);

        $domain=trim($v['domainname']);
        $klar=trim((string)($v['klartextname'] ?? ''));
        $kid=(int)$v['kundennummer'];
        $auth=(string)($v['authcode'] ?? '');

        $customerExists=DB::connection('sqlsrv_topsnetdb_safe')->table('tblKunde')->where('intID',$kid)->exists();
        if(!$customerExists){
            throw ValidationException::withMessages(['kundennummer'=>'Dieser Kunde existiert nicht in der Kundendatenbank.']);
        }

        $c=DB::connection('sqlsrv_domains');
        if($c->table('tblDomains')->whereRaw('RTRIM(strDomainname)=?',[$domain])->exists()){
            throw ValidationException::withMessages(['domainname'=>'Diese Domain existiert bereits in der Domaindatenbank.']);
        }

        $allgId=$c->transaction(function() use($c,$domain,$klar,$kid,$auth){
            $inserted=$c->selectOne("INSERT INTO dbo.tblDomains (strDomainname,strDomainKlartextname,intBesitzerC,intAdminC,intTechC1,intZoneC1,intDNSServer1,intDNSServer2,datRegistriertAm,intRegistryID,UMSTELLUNGintKundenID,boolIstPaketTeil,strAuthCode,intDNSSEC) OUTPUT INSERTED.intID AS intID VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",[
                $domain,$klar,2,2,2,2,3,13,now(),1,$kid,0,$auth,0
            ]);
            $domainId=(int)$inserted->intID;

            $allg=$c->selectOne("INSERT INTO dbo.tblAllgemeineDomain (strTyp,intDomainID) OUTPUT INSERTED.intID AS intID VALUES ('DOMAIN',?)",[$domainId]);
            $allgId=(int)$allg->intID;

            $fqdn=rtrim($domain,'.').'.';
            $soa='dns.tops.net. guardian.tops.net. ('.now()->format('Ymd').'00 28800 7200 1814400 86400)';
            $rows=[
                [$allgId,$fqdn,'SOA',3600,$soa,now()],
                [$allgId,$fqdn,'NS',3600,'dns.tops.net.',now()],
                [$allgId,$fqdn,'NS',3600,'ns2.tops.net.',now()],
            ];
            foreach($rows as $r){
                $c->table('tblDomainEintraege')->insert([
                    'intIDAllgemeineDomain'=>$r[0],'strName'=>$r[1],'strTyp'=>$r[2],
                    'intTTL'=>$r[3],'strAdresse'=>$r[4],'Datum'=>$r[5],
                ]);
            }
            return $allgId;
        });

        return redirect()->route('domain-eintraege.show',$allgId)
            ->with('status','Domain und Standard-DNS-Einträge wurden angelegt. TTL: 3600. Das DNS-Neueinlesen wurde noch nicht automatisch ausgelöst.');
    }
}
