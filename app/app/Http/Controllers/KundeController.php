<?php

namespace App\Http\Controllers;

use App\Models\Bankverbindung;
use App\Models\Kunde;
use App\Models\Rechnungsanschrift;
use App\Models\Zahlungsbedingung;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

        $branchen = DB::connection('sqlsrv_topsnetdb_safe')
            ->table('tblKundenBranchen as kb')
            ->join('tblBranchen as b', 'b.strCode', '=', 'kb.strBranchenCode')
            ->where('kb.intKundeID', $kunde->intID)
            ->orderBy('b.strBezeichnung')
            ->get(['b.strCode', 'b.strBezeichnung']);

        $mode = session('frontend_mode', 'classic');
        return view($mode . '.kunden.show', compact('kunde', 'branchen'));
    }

    public function create()
    {
        // Access springt bei "Neuer Kunde" lediglich auf einen leeren Datensatz.
        // Gespeichert wird erst durch "Kunde speichern".
        $kunde = new Kunde([
            'boolAktiverKunde' => 1,
            'boolLastschrift' => 1,
            'rahmenvertragda' => 0,
            'bWEBDNSistErlaubt' => 0,
            'bolwebfreischaltung' => 0,
            'boolInsolventOderBeimRechtsanwalt' => 0,
        ]);

        // Ein noch nicht gespeicherter Kunde hat naturgemaess keine Unterdatensaetze.
        $kunde->setRelation('ansprechpartner', collect());
        $kunde->setRelation('projekte', collect());
        $kunde->setRelation('zahlungsbedingung', null);

        $zahlungsbedingungen = Zahlungsbedingung::orderBy('intID')->get();
        $mode = session('frontend_mode', 'classic');
        return view($mode . '.kunden.create', compact('kunde', 'zahlungsbedingungen'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->customerRules());
        $this->applyCheckboxValues($request, $validated);

        $validated += [
            'strPasswortFuerStatistiken' => '',
            'fOffeneMahngebühren' => 0,
            'intSyncStatus' => 2,
            'rowguid' => (string) Str::uuid(),
            'fJahresUmsatz' => 0,
        ];

        $kunde = Kunde::create($validated);
        $this->ensureStandardRechnungsanschrift($kunde);

        $redirect = redirect()->route('kunden.show', $kunde->intID)
            ->with('success', 'Neuer Kunde wurde angelegt.');

        if (empty($kunde->intZahlungsbedingungID)) {
            $redirect->with('warning', 'Hopla! Da fehlt doch glatt die Zahlungsbedingung!');
        }

        return $redirect;
    }

    public function edit(int $id)
    {
        $kunde = Kunde::with([
            'ansprechpartner',
            'projekte',
            'zahlungsbedingung',
        ])->findOrFail($id);

        $zahlungsbedingungen = Zahlungsbedingung::orderBy('intID')->get();
        $mode = session('frontend_mode', 'classic');
        return view($mode . '.kunden.edit', compact('kunde', 'zahlungsbedingungen'));
    }

    public function update(Request $request, int $id)
    {
        $kunde = Kunde::findOrFail($id);

        $validated = $request->validate($this->customerRules());
        $this->applyCheckboxValues($request, $validated);

        $kunde->fill($validated);
        $kunde->save();
        $this->ensureStandardRechnungsanschrift($kunde);

        $redirect = redirect()->route('kunden.show', $kunde->intID)
            ->with('success', 'Kundendaten wurden gespeichert.');

        if (empty($kunde->intZahlungsbedingungID)) {
            $redirect->with('warning', 'Hopla! Da fehlt doch glatt die Zahlungsbedingung!');
        }

        return $redirect;
    }

    private function ensureStandardRechnungsanschrift(Kunde $kunde): void
    {
        if (Rechnungsanschrift::where('intKID', $kunde->intID)->exists()) {
            return;
        }

        $data = [
            'intKID' => $kunde->intID,
            'strName' => $kunde->strName,
            'strStrasse' => $kunde->strStrasse,
            'strOrt' => $kunde->strOrt,
            'strPLZ' => $kunde->strPLZ,
            'boolErstlastschrift' => 0,
            'boolSEPA' => 0,
            'boolXRechnung' => 0,
            'strLieferantenId' => '',
            'strLeitwegId' => '',
        ];

        // Das Access-Unterformular hat keine feste Sortierung. Bei mehreren
        // Bankverbindungen verwenden wir reproduzierbar den ältesten Datensatz.
        $bankverbindung = Bankverbindung::where('intKID', $kunde->intID)
            ->orderBy('intID')
            ->first();

        if ($bankverbindung && collect([
            $bankverbindung->strInhaber,
            $bankverbindung->strKontoNr,
            $bankverbindung->strBLZ,
            $bankverbindung->strInstitut,
        ])->contains(fn ($value) => trim((string) $value) !== '')) {
            $data += [
                'strInhaber' => $bankverbindung->strInhaber,
                'strKontoNr' => $bankverbindung->strKontoNr,
                'strBLZ' => $bankverbindung->strBLZ,
                'strInstitut' => $bankverbindung->strInstitut,
            ];
        }

        Rechnungsanschrift::create($data);
    }

    private function customerRules(): array
    {
        return [
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
        ];
    }

    private function applyCheckboxValues(Request $request, array &$validated): void
    {
        foreach ([
            'boolIstFirma',
            'boolLastschrift',
            'boolAktiverKunde',
            'boolInsolventOderBeimRechtsanwalt',
            'boolVertriebsnachfrage',
            'rahmenvertragda',
            'bWEBDNSistErlaubt',
            'bolwebfreischaltung',
        ] as $field) {
            $validated[$field] = $request->boolean($field) ? 1 : 0;
        }
    }
}
