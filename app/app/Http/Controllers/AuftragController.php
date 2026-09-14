<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use App\Models\Rechnungsanschrift;
use App\Models\Zahlungsbedingung;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuftragController extends Controller
{
    public function all(Request $request)
    {
        $query = DB::connection('sqlsrv_accountings')->table('tblAuftrag as a');
        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where(function ($builder) use ($q) {
                $builder->where('a.strBeschreibung', 'like', "%{$q}%");
                if (ctype_digit($q)) $builder->orWhere('a.intAufNr', (int)$q)->orWhere('a.intKID', (int)$q);
            });
        }
        $auftraege = $query->orderByDesc('a.intAufNr')->paginate(100)->withQueryString();
        $kunden = Kunde::whereIn('intID', collect($auftraege->items())->pluck('intKID')->unique()->values())->get()->keyBy('intID');
        $mode = session('frontend_mode', 'classic');
        return view($mode.'.auftraege.all', compact('auftraege','kunden'));
    }

    public function index(int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);
        $auftraege = DB::connection('sqlsrv_accountings')->table('tblAuftrag')->where('intKID',$kunde->intID)->orderByDesc('intAufNr')->get();
        $mode = session('frontend_mode', 'classic');
        return view($mode.'.auftraege.index', compact('kunde','auftraege'));
    }

    public function show(int $kunde, int $auftrag)
    {
        [$kunde,$auftrag] = $this->findOrder($kunde,$auftrag);
        $positionen = DB::connection('sqlsrv_accountings')->table('tblAuftragPos')->where('intAufNr',$auftrag->intAufNr)->orderBy('intID')->get();
        $rechnungsanschrift = $auftrag->intAnschriftID ? Rechnungsanschrift::find($auftrag->intAnschriftID) : null;
        $zahlungsbedingung = $auftrag->intZahlungsbedingungID ? Zahlungsbedingung::find($auftrag->intZahlungsbedingungID) : null;
        $letztesRechnungsdatum = DB::connection('sqlsrv_accountings')->table('tblRechnung')->where('intAufNr',$auftrag->intAufNr)->max('datRechnungsDatum');

        $ticketSubject = trim($kunde->strName.' : '.($auftrag->strBeschreibung ?? ''));
        $ticketBody = trim((string)($auftrag->strBeschreibung ?? ''))."\r\n\r\n";
        $ticketBody .= 'Auftragsnummer: '.$auftrag->intAufNr."\r\n";
        $ticketBody .= 'Kundennummer: '.$kunde->intID."\r\n\r\n";
        $ticketBody .= "Accountingfähige AuftragPOS\r\n---------------------------------------------------------------------------------------------------------------\r\n";
        foreach ($positionen->where('boolIstAnbindung', 1) as $p) {
            $ticketBody .= $p->intID.'  '.str_replace(["\r","\n"], ' ', (string)$p->strBeschreibung)."\r\n";
        }
        $ticketBody .= "\r\nAuftragPOS\r\n---------------------------------------------------------------------------------------------------------------\r\n";
        foreach ($positionen->where('boolIstAnbindung', 0) as $p) {
            $ticketBody .= $p->intID.'  '.str_replace(["\r","\n"], ' ', (string)$p->strBeschreibung)."\r\n";
        }
        $ticketMailto = 'mailto:helpdesk@tops.net?subject='.rawurlencode($ticketSubject).'&body='.rawurlencode($ticketBody);

        $mode = session('frontend_mode', 'classic');
        return view($mode.'.auftraege.show', compact('kunde','auftrag','positionen','rechnungsanschrift','zahlungsbedingung','letztesRechnungsdatum','ticketMailto'));
    }

    public function create(int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);
        $auftrag = (object)['datErfassungsdatum'=>date('Y-m-d'),'datFakturierAb'=>date('Y-m-d'),'boolPapierrechnung'=>1,'boolEmailRechnung'=>0,'boolLastschriftErzeugen'=>0,'boolDauerlastschrift'=>0,'boolRechnungstool'=>0,'boolVoraus'=>0,'boolDomainrechnung'=>0,'boolSponsoring'=>0,'boolEingefroren'=>0,'intSkonto1Tage'=>0,'intSkonto2Tage'=>0,'intSkonto3Tage'=>0,'dezSkonto1Prozent'=>0,'dezSkonto2Prozent'=>0,'dezSkonto3Prozent'=>0];
        return $this->orderForm($kunde,$auftrag,null);
    }

    public function edit(int $kunde, int $auftrag)
    {
        [$kunde,$auftrag] = $this->findOrder($kunde,$auftrag);
        return $this->orderForm($kunde,$auftrag,$auftrag->intAufNr);
    }

    public function store(Request $request, int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);
        $data = $this->validateOrder($request,$kunde->intID);
        $data += ['strAngenommenVon'=>'WEBAPP','rowguid'=>(string)Str::uuid()];
        $id = DB::connection('sqlsrv_accountings')->table('tblAuftrag')->insertGetId($data,'intAufNr');
        return redirect()->route('kunden.auftraege.show',[$kunde->intID,$id])->with('status','Auftrag angelegt.');
    }

    public function update(Request $request, int $kunde, int $auftrag)
    {
        [$kunde,$existing] = $this->findOrder($kunde,$auftrag);
        $data = $this->validateOrder($request,$kunde->intID);
        DB::connection('sqlsrv_accountings')->table('tblAuftrag')->where('intAufNr',$existing->intAufNr)->update($data);
        return redirect()->route('kunden.auftraege.show',[$kunde->intID,$existing->intAufNr])->with('status','Auftrag gespeichert.');
    }

    public function createPosition(int $kunde, int $auftrag)
    {
        [$kunde,$auftrag] = $this->findOrder($kunde,$auftrag);
        $position = (object)['intMenge'=>1,'datFakturierAb'=>date('Y-m-d'),'datFakturierBis'=>'2029-12-31','fRabattInProzent'=>0,'intMwstsatz'=>19,'datMindeslaufzeitEnde'=>date('Y-m-d'),'datWiedervorlageVertrieb'=>date('Y-m-d',strtotime('-90 days')),'txtWiedervorlageVertrieb'=>''];
        return $this->positionForm($kunde,$auftrag,$position,null);
    }

    public function editPosition(int $kunde, int $auftrag, int $position)
    {
        [$kunde,$auftrag] = $this->findOrder($kunde,$auftrag);
        $position = DB::connection('sqlsrv_accountings')->table('tblAuftragPos')->where('intAufNr',$auftrag->intAufNr)->where('intID',$position)->first();
        abort_unless($position,404);
        return $this->positionForm($kunde,$auftrag,$position,$position->intID);
    }

    public function storePosition(Request $request, int $kunde, int $auftrag)
    {
        [$kunde,$auftrag] = $this->findOrder($kunde,$auftrag);
        $data = $this->validatePosition($request,$auftrag->intAufNr,true);
        $now = now();
        $data += ['intAufNr'=>$auftrag->intAufNr,'rowguid'=>(string)Str::uuid(),'strErstelltVon'=>'WEBAPP','datErstelltAm'=>$now,'datMindeslaufzeitEnde'=>$request->input('datMindeslaufzeitEnde',$now),'datWiedervorlageVertrieb'=>$request->input('datWiedervorlageVertrieb',$now->copy()->subDays(90)),'txtWiedervorlageVertrieb'=>$request->input('txtWiedervorlageVertrieb','')];
        DB::connection('sqlsrv_accountings')->table('tblAuftragPos')->insert($data);
        return redirect()->route('kunden.auftraege.show',[$kunde->intID,$auftrag->intAufNr])->with('status','Auftragsposition angelegt.');
    }

    public function updatePosition(Request $request, int $kunde, int $auftrag, int $position)
    {
        [$kunde,$auftrag] = $this->findOrder($kunde,$auftrag);
        $exists = DB::connection('sqlsrv_accountings')->table('tblAuftragPos')->where('intAufNr',$auftrag->intAufNr)->where('intID',$position)->exists();
        abort_unless($exists,404);
        $data = $this->validatePosition($request,$auftrag->intAufNr,false);
        DB::connection('sqlsrv_accountings')->table('tblAuftragPos')->where('intID',$position)->update($data);
        return redirect()->route('kunden.auftraege.show',[$kunde->intID,$auftrag->intAufNr])->with('status','Auftragsposition gespeichert.');
    }

    private function orderForm(Kunde $kunde, object $auftrag, ?int $id)
    {
        $anschriften = Rechnungsanschrift::where('intKID',$kunde->intID)->orderBy('strName')->get();
        $zahlungsbedingungen = Zahlungsbedingung::orderBy('strBezeichnung')->get();
        $mode = session('frontend_mode','classic');
        return view($mode.'.auftraege.form', compact('kunde','auftrag','id','anschriften','zahlungsbedingungen'));
    }

    private function positionForm(Kunde $kunde, object $auftrag, object $position, ?int $id)
    {
        $produkte = DB::connection('sqlsrv_accountings')->table('tblProdukt')->where('boolProduktInaktiv',0)->orderBy('strKuerzel')->get();
        $mode = session('frontend_mode','classic');
        return view($mode.'.auftraege.position-form', compact('kunde','auftrag','position','id','produkte'));
    }

    private function validateOrder(Request $request, int $kid): array
    {
        $v=$request->validate(['strBeschreibung'=>'nullable|string|max:255','datErfassungsdatum'=>'required|date','datFakturierAb'=>'required|date','datStorniereAb'=>'nullable|date','intAnschriftID'=>'required|integer','intZahlungsbedingungID'=>'nullable|integer','strAbrechnungshinweis'=>'nullable|string|max:255','intSkonto1Tage'=>'required|integer|min:0','intSkonto2Tage'=>'required|integer|min:0','intSkonto3Tage'=>'required|integer|min:0','dezSkonto1Prozent'=>'required|numeric|min:0','dezSkonto2Prozent'=>'required|numeric|min:0','dezSkonto3Prozent'=>'required|numeric|min:0']);
        abort_unless(Rechnungsanschrift::where('intID',$v['intAnschriftID'])->where('intKID',$kid)->exists(),422);
        $v['intKID']=$kid;
        foreach(['boolPapierrechnung','boolEmailRechnung','boolLastschriftErzeugen','boolDauerlastschrift','boolRechnungstool','boolVoraus','boolDomainrechnung','boolSponsoring'] as $f) $v[$f]=$request->boolean($f)?1:0;
        $v['boolEingefroren']=$request->boolean('boolEingefroren')?1:0;
        if ($v['boolVoraus']) $v['boolRechnungstool']=1;
        $v['intRechnungsEmpfaenger']=null;
        return $v;
    }

    private function validatePosition(Request $request, int $aufnr, bool $creating): array
    {
        $v=$request->validate(['intProduktID'=>'required|integer','intMenge'=>'required|numeric','strBeschreibung'=>'required|string|max:8000','fEndpreis'=>'required|numeric','datFakturierAb'=>'nullable|date','datFakturierBis'=>'nullable|date','fRabattInProzent'=>'required|numeric','datMindeslaufzeitEnde'=>'required|date','datWiedervorlageVertrieb'=>'required|date','txtWiedervorlageVertrieb'=>'nullable|string|max:8000']);
        $produkt=DB::connection('sqlsrv_accountings')->table('tblProdukt')->where('intID',$v['intProduktID'])->where('boolProduktInaktiv',0)->first();
        abort_unless($produkt,422);
        $v += ['intStaffelgruppe'=>$produkt->intStaffelgruppeID,'intMwstsatz'=>19,'intStaffelTyp'=>$produkt->intStaffeltyp,'intAbrechnungsArt'=>$produkt->intAbrechnungsArt,'intMengenSchluessel'=>$produkt->intMengenSchluessel,'intProduktGruppe'=>$produkt->intProduktGruppe,'boolIstAnbindung'=>$produkt->boolIstAnbindung,'strKuerzel'=>$produkt->strKuerzel,'intDatevBezeichnungsID'=>$produkt->intDatevBezeichnungsID,'txtInfo'=>null,'datVorberechnenBis'=>null,'fDomainRabattEinrichtungProzent'=>null,'fDomainRabattRegulaer'=>null];
        $v['txtWiedervorlageVertrieb']=$v['txtWiedervorlageVertrieb'] ?? '';
        return $v;
    }

    private function findOrder(int $kunde, int $auftrag): array
    {
        $kunde=Kunde::findOrFail($kunde);
        $auftrag=DB::connection('sqlsrv_accountings')->table('tblAuftrag')->where('intKID',$kunde->intID)->where('intAufNr',$auftrag)->first();
        abort_unless($auftrag,404);
        return [$kunde,$auftrag];
    }
}
