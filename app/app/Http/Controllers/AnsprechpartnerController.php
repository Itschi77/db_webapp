<?php

namespace App\Http\Controllers;

use App\Models\Ansprechpartner;
use App\Models\Kunde;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnsprechpartnerController extends Controller
{
    public function create(int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);
        $ansprechpartner = new Ansprechpartner();
        $mode = session('frontend_mode', 'classic');
        return view($mode . '.ansprechpartner.form', compact('kunde', 'ansprechpartner'));
    }

    public function store(Request $request, int $kunde)
    {
        $kunde = Kunde::findOrFail($kunde);
        $data = $request->validate($this->rules());
        $data += ['intKID' => $kunde->intID, 'rowguid' => (string) Str::uuid()];
        Ansprechpartner::create($data);
        return redirect()->route('kunden.show', $kunde->intID)->with('success', 'Ansprechpartner wurde angelegt.');
    }

    public function edit(int $kunde, int $ansprechpartner)
    {
        $kunde = Kunde::findOrFail($kunde);
        $ansprechpartner = Ansprechpartner::where('intKID', $kunde->intID)->findOrFail($ansprechpartner);
        $mode = session('frontend_mode', 'classic');
        return view($mode . '.ansprechpartner.form', compact('kunde', 'ansprechpartner'));
    }

    public function update(Request $request, int $kunde, int $ansprechpartner)
    {
        $kunde = Kunde::findOrFail($kunde);
        $ansprechpartner = Ansprechpartner::where('intKID', $kunde->intID)->findOrFail($ansprechpartner);
        $ansprechpartner->fill($request->validate($this->rules()))->save();
        return redirect()->route('kunden.show', $kunde->intID)->with('success', 'Ansprechpartner wurde gespeichert.');
    }

    private function rules(): array
    {
        return [
            'strAnrede' => ['nullable', 'string', 'max:100'],
            'strTitel' => ['nullable', 'string', 'max:100'],
            'strVorname' => ['nullable', 'string', 'max:100'],
            'strName' => ['required', 'string', 'max:100'],
            'strFunktion' => ['nullable', 'string', 'max:100'],
            'strtel1' => ['nullable', 'string', 'max:50'],
            'strtel2' => ['nullable', 'string', 'max:50'],
            'strtelmobil1' => ['nullable', 'string', 'max:50'],
            'strtelmobil2' => ['nullable', 'string', 'max:50'],
            'strdurchwahl' => ['nullable', 'string', 'max:50'],
            'stremail1' => ['nullable', 'string', 'max:50'],
            'stremail2' => ['nullable', 'string', 'max:50'],
            'strtelefax' => ['nullable', 'string', 'max:50'],
        ];
    }
}
