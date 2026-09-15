<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SmsZugangController extends Controller
{
    private array $columns = [
        'intSMSZugaengeID','strSMSAccountNummer','intKundenNr','datErstelltAm','strErstelltVon','strBemerkung','strRechnungsinfo',
        'strGWCorporateName','strGWCorporateDepartmentName','strGWRegistrationName','boolGWAllowNewAccounts','strGWAdditionalInformation',
        'boolGWUseIPRestriction','boolIsCustomerAccount','strGWSingleAccountUserName','strGWSingleAccountEmail','strGWSingleAccountOriginator','strGWSingleAccountTYPE',
    ];

    public function index(Request $request)
    {
        $q = trim((string)$request->query('q',''));
        $query = DB::connection('sqlsrv_accountings')->table('tblSMSZugaenge')->select($this->columns)->orderBy('intSMSZugaengeID');
        if ($q !== '') {
            $query->where(function($x) use ($q) {
                $x->where('strSMSAccountNummer','like','%'.$q.'%')->orWhere('strBemerkung','like','%'.$q.'%')->orWhere('strGWCorporateName','like','%'.$q.'%');
                if (ctype_digit($q)) $x->orWhere('intSMSZugaengeID',(int)$q)->orWhere('intKundenNr',(int)$q);
            });
        }
        $zugaenge = $query->get();
        return view(session('frontend_mode','classic').'.sms-zugaenge.index', compact('zugaenge','q'));
    }

    public function create() { return $this->form(null); }

    public function edit(int $smsZugang) { return $this->form($this->find($smsZugang)); }

    public function store(Request $request)
    {
        $data = $this->data($request, true);
        $data['datErstelltAm'] = now();
        $data['strErstelltVon'] = 'webapp';
        $id = DB::connection('sqlsrv_accountings')->table('tblSMSZugaenge')->insertGetId($data,'intSMSZugaengeID');
        return redirect()->route('sms-zugaenge.edit',$id)->with('status','SMS-Zugang angelegt.');
    }

    public function update(Request $request, int $smsZugang)
    {
        $this->find($smsZugang);
        DB::connection('sqlsrv_accountings')->table('tblSMSZugaenge')->where('intSMSZugaengeID',$smsZugang)->update($this->data($request, false));
        return redirect()->route('sms-zugaenge.edit',$smsZugang)->with('status','SMS-Zugang gespeichert.');
    }

    private function form(?object $zugang)
    {
        $hasSmsPassword = false; $hasRegistrationPassword = false;
        if ($zugang) {
            $flags = DB::connection('sqlsrv_accountings')->table('tblSMSZugaenge')->where('intSMSZugaengeID',$zugang->intSMSZugaengeID)
                ->selectRaw("CASE WHEN strKennwort IS NULL OR strKennwort='' THEN 0 ELSE 1 END AS sms_pwd, CASE WHEN strGWRegistrationPassword IS NULL OR strGWRegistrationPassword='' THEN 0 ELSE 1 END AS reg_pwd")->first();
            $hasSmsPassword = (bool)$flags->sms_pwd; $hasRegistrationPassword = (bool)$flags->reg_pwd;
        }
        return view(session('frontend_mode','classic').'.sms-zugaenge.form', compact('zugang','hasSmsPassword','hasRegistrationPassword'));
    }

    private function find(int $id)
    {
        $x = DB::connection('sqlsrv_accountings')->table('tblSMSZugaenge')->select($this->columns)->where('intSMSZugaengeID',$id)->first();
        abort_unless($x,404); return $x;
    }

    private function data(Request $r, bool $creating): array
    {
        $v = $r->validate([
            'strSMSAccountNummer'=>['required','string','max:20'], 'intKundenNr'=>['nullable','integer','min:0'], 'strBemerkung'=>['nullable','string','max:255'],
            'strRechnungsinfo'=>['nullable','string'], 'strKennwort'=>['nullable','string','max:50'], 'strGWCorporateName'=>['nullable','string','max:50'],
            'strGWCorporateDepartmentName'=>['nullable','string','max:50'], 'strGWRegistrationName'=>['nullable','string','max:50'], 'strGWRegistrationPassword'=>['nullable','string','max:50'],
            'strGWAdditionalInformation'=>['nullable','string','max:255'], 'strGWSingleAccountUserName'=>['nullable','string','max:50'], 'strGWSingleAccountEmail'=>['nullable','string','max:50'],
            'strGWSingleAccountOriginator'=>['nullable','string','max:50'], 'strGWSingleAccountTYPE'=>['nullable','string','max:10'],
        ]);
        foreach (['boolGWAllowNewAccounts','boolGWUseIPRestriction','boolIsCustomerAccount'] as $b) $v[$b] = $r->boolean($b) ? 1 : 0;
        foreach (['strKennwort','strGWRegistrationPassword'] as $pwd) if (!$creating && (($v[$pwd] ?? '') === '')) unset($v[$pwd]);
        foreach ($v as $k=>$val) if ($val === '' && !in_array($k,['strSMSAccountNummer'],true)) $v[$k] = null;
        return $v;
    }
}
