<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZeittarifController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = DB::connection('sqlsrv_accountings')->table('tblZeittarife')->orderBy('strTarifname');
        if ($q !== '') {
            $query->where('strTarifname','like','%'.$q.'%');
            if (ctype_digit($q)) $query->orWhere('intID',(int)$q);
        }
        $tarife = $query->get();
        return view(session('frontend_mode','classic').'.zeittarife.index', compact('tarife','q'));
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit(int $zeittarif)
    {
        $db = DB::connection('sqlsrv_accountings');
        $zeittarif = $db->table('tblZeittarife')->where('intID',$zeittarif)->first();
        abort_unless($zeittarif,404);
        return $this->form($zeittarif);
    }

    public function store(Request $request)
    {
        $data = $this->validatedTarif($request);
        $data['rowguid'] = DB::raw('NEWID()');
        $id = DB::connection('sqlsrv_accountings')->table('tblZeittarife')->insertGetId($data,'intID');
        return redirect()->route('zeittarife.edit',$id)->with('status','Zeittarif angelegt.');
    }

    public function update(Request $request, int $zeittarif)
    {
        $db = DB::connection('sqlsrv_accountings');
        abort_unless($db->table('tblZeittarife')->where('intID',$zeittarif)->exists(),404);
        $db->table('tblZeittarife')->where('intID',$zeittarif)->update($this->validatedTarif($request));
        return redirect()->route('zeittarife.edit',$zeittarif)->with('status','Zeittarif gespeichert.');
    }

    public function storeZone(Request $request, int $zeittarif)
    {
        $db = DB::connection('sqlsrv_accountings');
        abort_unless($db->table('tblZeittarife')->where('intID',$zeittarif)->exists(),404);
        $data = $this->validatedZone($request);
        $data['intTarifID'] = $zeittarif;
        $data['rowguid'] = DB::raw('NEWID()');
        $db->table('tblZeittarifeZonen')->insert($data);
        return redirect()->route('zeittarife.edit',$zeittarif)->with('status','Zeitfenster angelegt.');
    }

    public function updateZone(Request $request, int $zeittarif, int $zone)
    {
        $db = DB::connection('sqlsrv_accountings');
        abort_unless($db->table('tblZeittarifeZonen')->where('intID',$zone)->where('intTarifID',$zeittarif)->exists(),404);
        $db->table('tblZeittarifeZonen')->where('intID',$zone)->where('intTarifID',$zeittarif)->update($this->validatedZone($request));
        return redirect()->route('zeittarife.edit',$zeittarif)->with('status','Zeitfenster gespeichert.');
    }

    private function form(?object $zeittarif)
    {
        $zonen = collect();
        if ($zeittarif) {
            $zonen = DB::connection('sqlsrv_accountings')->table('tblZeittarifeZonen')
                ->where('intTarifID',$zeittarif->intID)->orderBy('datBeginn')->orderBy('datEnde')->get();
        }
        return view(session('frontend_mode','classic').'.zeittarife.form', compact('zeittarif','zonen'));
    }

    private function validatedTarif(Request $request): array
    {
        return $request->validate([
            'strTarifname' => ['required','string','max:255'],
            'intFreiSekunden' => ['required','integer','min:0'],
            'intMindestAbnahmeSekunden' => ['required','integer','min:0'],
            'intTaktSekunden' => ['required','integer','min:0'],
        ]);
    }

    private function validatedZone(Request $request): array
    {
        $v = $request->validate([
            'datBeginn' => ['required','date_format:H:i:s'],
            'datEnde' => ['required','date_format:H:i:s'],
            'fMinutenpreis' => ['required','numeric','min:0'],
        ]);
        $v['datBeginn'] = '1899-12-30 '.$v['datBeginn'];
        $v['datEnde'] = '1899-12-30 '.$v['datEnde'];
        return $v;
    }
}
