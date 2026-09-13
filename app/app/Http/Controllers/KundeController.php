<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use Illuminate\Http\Request;

class KundeController extends Controller
{
    public function index(Request $request)
    {
        $query = Kunde::query();

        if ($request->filled('q')) {
            $q = trim($request->q);

            $query->where(function ($builder) use ($q) {
                $builder
                    ->where('strName', 'like', "%{$q}%")
                    ->orWhere('strOrt', 'like', "%{$q}%")
                    ->orWhere('strPLZ', 'like', "%{$q}%")
                    ->orWhere('strEmail', 'like', "%{$q}%")
                    ->orWhere('strTelefon', 'like', "%{$q}%")
                    ->orWhere('strKuerzel', 'like', "%{$q}%");

                if (ctype_digit($q)) {
                    $builder->orWhere('intID', '=', (int) $q);
                }
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'aktiv') {
                $query->where('boolAktiverKunde', 1);
            }

            if ($request->status === 'inaktiv') {
                $query->where(function ($builder) {
                    $builder
                        ->where('boolAktiverKunde', 0)
                        ->orWhereNull('boolAktiverKunde');
                });
            }
        }

        $kunden = $query
            ->orderBy('strName')
            ->paginate(50)
            ->withQueryString();

        $mode = session('frontend_mode', 'classic');

        return view($mode . '.kunden.index', compact('kunden'));
    }

    public function show(int $id)
    {
	$kunde = Kunde::with([
	    'ansprechpartner',
	    'projekte',
	    'zahlungsbedingung',
	])->findOrFail($id);
	
        $mode = session('frontend_mode', 'classic');

        return view($mode . '.kunden.show', compact('kunde'));
    }
    public function edit(int $id)
{
    $kunde = Kunde::with([
        'ansprechpartner',
        'projekte',
        'zahlungsbedingung',
    ])->findOrFail($id);

    $mode = session('frontend_mode', 'classic');

    return view($mode . '.kunden.edit', compact('kunde'));
}

public function update(Request $request, int $id)
{
    $kunde = Kunde::findOrFail($id);

    $validated = $request->validate([
        'strAnrede' => ['nullable', 'string', 'max:10'],
        'boolIstFirma' => ['nullable', 'boolean'],
        'strName' => ['required', 'string', 'max:100'],
        'strZuHaenden' => ['nullable', 'string', 'max:100'],
        'strStrasse' => ['nullable', 'string', 'max:100'],
        'strOrt' => ['nullable', 'string', 'max:100'],
        'strPLZ' => ['nullable', 'string', 'max:15'],
        'strTelefax' => ['nullable', 'string', 'max:50'],
        'strTelefon' => ['nullable', 'string', 'max:50'],
        'strEmail' => ['nullable', 'string', 'max:255'],
        'datGeburtsDatum' => ['nullable', 'date'],
        'boolLastschrift' => ['nullable', 'boolean'],
        'datKundeSeit' => ['nullable', 'date'],
        'strKuerzel' => ['nullable', 'string', 'max:50'],
        'intZahlungsbedingungID' => ['nullable', 'integer'],
        'strAngenommenVon' => ['nullable', 'string', 'max:255'],
        'strDatevKundenKonto' => ['nullable', 'string', 'max:10'],
        'boolAktiverKunde' => ['nullable', 'boolean'],
        'boolInsolventOderBeimRechtsanwalt' => ['nullable', 'boolean'],
        'strGrundInsolventOderRA' => ['nullable', 'string', 'max:50'],
        'txtInfo' => ['nullable', 'string'],
        'strIntranetFolderPath' => ['nullable', 'string', 'max:500'],
        'boolVertriebsnachfrage' => ['nullable', 'boolean'],
        'txtServiceinfo' => ['nullable', 'string'],
        'rahmenvertragda' => ['nullable', 'boolean'],
        'bWEBDNSistErlaubt' => ['nullable', 'boolean'],
        'bolwebfreischaltung' => ['nullable', 'boolean'],
    ]);

    $checkboxes = [
        'boolIstFirma',
        'boolLastschrift',
        'boolAktiverKunde',
        'boolInsolventOderBeimRechtsanwalt',
        'boolVertriebsnachfrage',
        'rahmenvertragda',
        'bWEBDNSistErlaubt',
        'bolwebfreischaltung',
    ];

    foreach ($checkboxes as $field) {
        $validated[$field] = $request->boolean($field) ? 1 : 0;
    }

    $kunde->fill($validated);
    $kunde->save();

    return redirect()
        ->route('kunden.show', $kunde->intID)
        ->with('success', 'Kundendaten wurden gespeichert.');
}
}
