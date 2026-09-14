<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use App\Models\Rechnungsanschrift;
use Illuminate\Http\Request;

class RechnungsanschriftController extends Controller
{
    public function index(int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);
        $anschriften = Rechnungsanschrift::where('intKID', $kunde->intID)->orderBy('intID')->get();
        $mode = session('frontend_mode', 'classic');
        return view($mode . '.rechnungsanschriften.index', compact('kunde', 'anschriften'));
    }

    public function create(int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);
        $anschrift = new Rechnungsanschrift([
            'intKID' => $kunde->intID,
            'strName' => $kunde->strName,
            'strZuHaenden' => $kunde->strZuHaenden,
            'strStrasse' => $kunde->strStrasse,
            'strOrt' => $kunde->strOrt,
            'strPLZ' => $kunde->strPLZ,
            'strEmail' => $kunde->strEmail,
            'boolErstlastschrift' => 0,
            'boolSEPA' => 0,
            'boolXRechnung' => 0,
            'strLieferantenId' => '',
            'strLeitwegId' => '',
        ]);
        $mode = session('frontend_mode', 'classic');
        return view($mode . '.rechnungsanschriften.form', compact('kunde', 'anschrift'));
    }

    public function store(Request $request, int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);
        $data = $request->validate($this->rules());
        $this->checkboxes($request, $data);
        $data += ['intKID' => $kunde->intID, 'strLieferantenId' => '', 'strLeitwegId' => ''];
        Rechnungsanschrift::create($data);
        return redirect()->route('kunden.rechnungsanschriften.index', $kunde->intID)
            ->with('success', 'Rechnungsanschrift wurde angelegt.');
    }

    public function edit(int $kunde, int $anschrift)
    {
        $kunde = Kunde::findOrFail($kunde);
        $anschrift = Rechnungsanschrift::where('intKID', $kunde->intID)->findOrFail($anschrift);
        $mode = session('frontend_mode', 'classic');
        return view($mode . '.rechnungsanschriften.form', compact('kunde', 'anschrift'));
    }

    public function update(Request $request, int $kunde, int $anschrift)
    {
        $kunde = Kunde::findOrFail($kunde);
        $anschrift = Rechnungsanschrift::where('intKID', $kunde->intID)->findOrFail($anschrift);
        $data = $request->validate($this->rules());
        $this->checkboxes($request, $data);
        $anschrift->fill($data)->save();
        return redirect()->route('kunden.rechnungsanschriften.index', $kunde->intID)
            ->with('success', 'Rechnungsanschrift wurde gespeichert.');
    }

    private function rules(): array
    {
        return [
            'strName' => ['required', 'string', 'max:100'],
            'strZuHaenden' => ['nullable', 'string', 'max:100'],
            'strStrasse' => ['nullable', 'string', 'max:100'],
            'strOrt' => ['nullable', 'string', 'max:100'],
            'strPLZ' => ['nullable', 'string', 'max:100'],
            'strInhaber' => ['nullable', 'string', 'max:100'],
            'strKontoNr' => ['nullable', 'string', 'max:20'],
            'strBLZ' => ['nullable', 'string', 'max:50'],
            'strInstitut' => ['nullable', 'string', 'max:100'],
            'strBIC' => ['nullable', 'string', 'max:11'],
            'strIBAN' => ['nullable', 'string', 'max:34'],
            'strKundenreferenz' => ['nullable', 'string', 'max:140'],
            'strEmail' => ['nullable', 'string', 'max:255'],
            'strUStIdNr' => ['nullable', 'string', 'max:255'],
            'boolErstlastschrift' => ['nullable', 'boolean'],
            'boolSEPA' => ['nullable', 'boolean'],
            'boolXRechnung' => ['nullable', 'boolean'],
            'strLieferantenId' => ['nullable', 'string', 'max:50'],
            'strLeitwegId' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function checkboxes(Request $request, array &$data): void
    {
        foreach (['boolErstlastschrift', 'boolSEPA', 'boolXRechnung'] as $field) {
            $data[$field] = $request->boolean($field) ? 1 : 0;
        }
        $data['strLieferantenId'] = $data['strLieferantenId'] ?? '';
        $data['strLeitwegId'] = $data['strLeitwegId'] ?? '';
    }
}
