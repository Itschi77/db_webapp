<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProduktController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q', ''));
        $query = DB::connection('sqlsrv_accountings')->table('tblProdukt')->orderByDesc('intID');
        if ($q !== '') {
            $query->where(function ($x) use ($q) {
                $x->where('strKuerzel', 'like', '%'.$q.'%')
                  ->orWhere('strBeschreibung', 'like', '%'.$q.'%');
                if (ctype_digit($q)) $x->orWhere('intID', (int)$q);
            });
        }
        $produkte = $query->limit(250)->get();
        return view(session('frontend_mode','classic').'.produkte.index', compact('produkte','q'));
    }

    public function create()
    {
        return $this->form(null);
    }

    public function edit(int $produkt)
    {
        $produkt = DB::connection('sqlsrv_accountings')->table('tblProdukt')->where('intID',$produkt)->first();
        abort_unless($produkt,404);
        return $this->form($produkt);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['rowguid'] = DB::raw('NEWID()');
        $id = DB::connection('sqlsrv_accountings')->table('tblProdukt')->insertGetId($data, 'intID');
        return redirect()->route('produkte.edit',$id)->with('status','Produkt angelegt.');
    }

    public function update(Request $request, int $produkt)
    {
        abort_unless(DB::connection('sqlsrv_accountings')->table('tblProdukt')->where('intID',$produkt)->exists(),404);
        DB::connection('sqlsrv_accountings')->table('tblProdukt')->where('intID',$produkt)->update($this->validatedData($request));
        return redirect()->route('produkte.edit',$produkt)->with('status','Produkt gespeichert.');
    }

    private function form(?object $produkt)
    {
        $a = DB::connection('sqlsrv_accountings');
        $listen = [
            'abrechnungsarten' => $a->table('tblAbrechnungsArt')->orderBy('intID')->get(),
            'mengenschluessel' => $a->table('tblMengeSchluessel')->orderBy('strBezeichnung')->get(),
            'datev' => $a->table('tblDatevBezeichnungen')->orderBy('strDatevKontierung')->get(),
            'produktgruppen' => $a->table('tblProduktGruppe')->orderBy('strBezeichnung')->get(),
            'mwst' => $a->table('tblMwstSchluessel')->orderBy('intID')->get(),
            'staffel1' => $a->table('tblStaffelgruppe')->orderBy('strBezeichnung')->get(),
            'staffel2' => $a->table('tblLinearStaffel')->orderBy('strBezeichnung')->get(),
            'staffel3' => $a->table('tblZeittarife')->orderBy('strTarifname')->get(),
            'staffel6' => $a->table('tblBereichsStaffel')->orderBy('strBezeichnung')->get(),
            'staffel5' => DB::connection('sqlsrv_domains')->table('tblDomainKonditionen')->orderBy('strKonditionsName')->get(),
        ];
        return view(session('frontend_mode','classic').'.produkte.form', compact('produkt','listen'));
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'strKuerzel' => ['required','string','max:255'],
            'strBeschreibung' => ['nullable','string','max:8000'],
            'intAbrechnungsArt' => ['required','integer'],
            'intMengenSchluessel' => ['required','integer'],
            'intProduktGruppe' => ['required','integer'],
            'fPreis' => ['nullable','numeric'],
            'intMwstSchluesselID' => ['required','integer'],
            'intStaffeltyp' => ['required','integer',Rule::in([0,1,2,3,4,5,6])],
            'intStaffelgruppeID' => ['nullable','integer'],
            'intDatevBezeichnungsID' => ['nullable','integer'],
            'fMaxRabattFuerInternenVertrieb' => ['required','numeric'],
            'fMaxRabattFuerExternenVertrieb' => ['required','numeric'],
        ]);

        $a = DB::connection('sqlsrv_accountings');
        abort_unless($a->table('tblAbrechnungsArt')->where('intID',$data['intAbrechnungsArt'])->exists(),422);
        abort_unless($a->table('tblMengeSchluessel')->where('intID',$data['intMengenSchluessel'])->exists(),422);
        abort_unless($a->table('tblProduktGruppe')->where('intID',$data['intProduktGruppe'])->exists(),422);
        abort_unless($a->table('tblMwstSchluessel')->where('intID',$data['intMwstSchluesselID'])->exists(),422);
        if ($data['intDatevBezeichnungsID'] !== null) abort_unless($a->table('tblDatevBezeichnungen')->where('intID',$data['intDatevBezeichnungsID'])->exists(),422);

        $typ = (int)$data['intStaffeltyp'];
        if ($typ === 5 && $request->boolean('domain_type_changed')) $data['intMengenSchluessel'] = 1;
        if (!in_array($typ,[0,4],true) && $data['intStaffelgruppeID'] !== null) {
            $id = $data['intStaffelgruppeID'];
            $exists = match ($typ) {
                1 => $a->table('tblStaffelgruppe')->where('intID',$id)->exists(),
                2 => $a->table('tblLinearStaffel')->where('intID',$id)->exists(),
                3 => $a->table('tblZeittarife')->where('intID',$id)->exists(),
                5 => DB::connection('sqlsrv_domains')->table('tblDomainKonditionen')->where('intID',$id)->exists(),
                6 => $a->table('tblBereichsStaffel')->where('intID',$id)->exists(),
                default => false,
            };
            abort_unless($exists,422);
        }

        $data['strBeschreibung'] = $data['strBeschreibung'] ?? '';
        $data['boolIstAnbindung'] = $request->boolean('boolIstAnbindung') ? 1 : 0;
        $data['boolProduktInaktiv'] = $request->boolean('boolProduktInaktiv') ? 1 : 0;
        return $data;
    }
}
