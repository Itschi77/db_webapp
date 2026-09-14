<?php

namespace App\Http\Controllers;

use App\Models\Branche;
use App\Models\Kunde;
use App\Models\KundenBranche;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BrancheController extends Controller
{
    public function edit(int $id)
    {
        $kunde = Kunde::findOrFail($id);
        $branchen = Branche::orderBy('strBezeichnung')->get();
        $selected = KundenBranche::where('intKundeID', $id)->pluck('strBranchenCode')->all();
        $mode = session('frontend_mode', 'classic');

        return view($mode . '.kunden.branchen', compact('kunde', 'branchen', 'selected'));
    }

    public function update(Request $request, int $id)
    {
        $kunde = Kunde::findOrFail($id);
        $codes = $request->validate([
            'branchen' => ['nullable', 'array'],
            'branchen.*' => ['string', 'max:10'],
        ])['branchen'] ?? [];

        $valid = Branche::whereIn('strCode', $codes)->pluck('strCode')->all();
        $conn = DB::connection('sqlsrv_topsnetdb_safe');

        $conn->transaction(function () use ($id, $valid) {
            KundenBranche::where('intKundeID', $id)->delete();
            foreach ($valid as $code) {
                KundenBranche::create([
                    'intKundeID' => $id,
                    'strBranchenCode' => $code,
                    'rowguid' => (string) Str::uuid(),
                ]);
            }
        });

        return redirect()->route('kunden.show', $kunde->intID)
            ->with('success', 'Branchen wurden gespeichert.');
    }
}
