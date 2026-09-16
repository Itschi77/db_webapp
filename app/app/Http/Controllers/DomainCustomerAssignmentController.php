<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DomainCustomerAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $domainSearch = trim((string) $request->query('domain', ''));
        $customerSearch = trim((string) $request->query('kunde', ''));

        $domains = DB::connection('sqlsrv_domains')->table('tblDomains')
            ->select('intID','strDomainname','datRegistriertAm')
            ->whereNull('UMSTELLUNGintKundenID')
            ->when($domainSearch !== '', fn($q) => $q->where('strDomainname','like','%'.$domainSearch.'%'))
            ->orderBy('strDomainname')->get();

        $customers = DB::connection('sqlsrv_topsnetdb_safe')->table('tblKunde')
            ->select('intID','strName')
            ->when($customerSearch !== '', function ($q) use ($customerSearch) {
                $q->where(function ($x) use ($customerSearch) {
                    $x->where('strName','like','%'.$customerSearch.'%');
                    if (ctype_digit($customerSearch)) $x->orWhere('intID',(int)$customerSearch);
                });
            })
            ->orderBy('strName')->paginate(100)->withQueryString();

        $ids = collect($customers->items())->pluck('intID')->map(fn($v)=>(int)$v)->all();
        $latest = collect();
        if ($ids) {
            $latest = DB::connection('sqlsrv_accountings')->table('tblAuftrag as a')
                ->leftJoin('tblAuftragPos as p','a.intAufNr','=','p.intAufNr')
                ->whereIn('a.intKID',$ids)
                ->groupBy('a.intKID')
                ->select('a.intKID',DB::raw('MAX(p.datErstelltAm) AS latest'))
                ->get()->keyBy(fn($r)=>(int)$r->intKID);
        }

        $view = session('frontend_mode','classic') === 'modern'
            ? 'modern.domain-customer.index'
            : 'classic.domain-customer.index';
        return view($view, compact('domains','customers','latest','domainSearch','customerSearch'));
    }

    public function assign(Request $request)
    {
        $v = $request->validate([
            'domain_id'=>['required','integer','min:1'],
            'customer_id'=>['required','integer','min:1'],
            'registered_at'=>['required','date_format:Y-m-d'],
        ]);

        $customerExists = DB::connection('sqlsrv_topsnetdb_safe')->table('tblKunde')->where('intID',(int)$v['customer_id'])->exists();
        if (!$customerExists) throw ValidationException::withMessages(['customer_id'=>'Der ausgewählte Kunde existiert nicht.']);

        [$year,$month,$day] = array_map('intval', explode('-', $v['registered_at']));
        $affected = DB::connection('sqlsrv_domains')->update(
            'UPDATE dbo.tblDomains SET datRegistriertAm = DATEFROMPARTS(?,?,?), UMSTELLUNGintKundenID = ? WHERE intID = ? AND UMSTELLUNGintKundenID IS NULL',
            [$year,$month,$day,(int)$v['customer_id'],(int)$v['domain_id']]
        );
        if (!$affected) throw ValidationException::withMessages(['domain_id'=>'Die Domain ist nicht mehr unzugeordnet oder existiert nicht.']);

        return redirect()->route('domain-customer.index')->with('status','Domain wurde dem Kunden zugeordnet.');
    }
}
