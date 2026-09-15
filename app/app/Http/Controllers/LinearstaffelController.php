<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LinearstaffelController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = DB::connection('sqlsrv_accountings')->table('tblLinearStaffel')->orderBy('strBezeichnung');
        if ($q !== '') {
            $query->where(function ($x) use ($q) {
                $x->where('strBezeichnung', 'like', '%'.$q.'%')
                  ->orWhere('strAbrechnungseinheit', 'like', '%'.$q.'%');
                if (ctype_digit($q)) $x->orWhere('intID', (int) $q);
            });
        }
        $staffeln = $query->get();
        return view(session('frontend_mode','classic').'.linearstaffeln.index', compact('staffeln','q'));
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit(int $linearstaffel)
    {
        $linearstaffel = DB::connection('sqlsrv_accountings')->table('tblLinearStaffel')->where('intID',$linearstaffel)->first();
        abort_unless($linearstaffel,404);
        return $this->form($linearstaffel);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['rowguid'] = DB::raw('NEWID()');
        $id = DB::connection('sqlsrv_accountings')->table('tblLinearStaffel')->insertGetId($data,'intID');
        return redirect()->route('linearstaffeln.edit',$id)->with('status','Linearstaffel angelegt.');
    }

    public function update(Request $request, int $linearstaffel)
    {
        $db = DB::connection('sqlsrv_accountings');
        abort_unless($db->table('tblLinearStaffel')->where('intID',$linearstaffel)->exists(),404);
        $db->table('tblLinearStaffel')->where('intID',$linearstaffel)->update($this->validated($request));
        return redirect()->route('linearstaffeln.edit',$linearstaffel)->with('status','Linearstaffel gespeichert.');
    }

    private function form(?object $linearstaffel)
    {
        return view(session('frontend_mode','classic').'.linearstaffeln.form', compact('linearstaffel'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'intMengeFrei' => ['required','integer','min:0'],
            'floatPreisEinheit' => ['required','numeric'],
            'strBezeichnung' => ['nullable','string','max:255'],
            'floatBasisPreis' => ['nullable','numeric'],
            'strAbrechnungseinheit' => ['required','string','max:10'],
        ]);
        $data['strBezeichnung'] = $data['strBezeichnung'] ?? null;
        $data['floatBasisPreis'] = $data['floatBasisPreis'] ?? null;
        return $data;
    }
}
