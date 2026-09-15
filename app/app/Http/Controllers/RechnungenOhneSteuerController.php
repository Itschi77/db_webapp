<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RechnungenOhneSteuerController extends Controller
{
    public function index(Request $request)
    {
        $ab = $request->input('ab', now()->startOfYear()->format('Y-m-d'));
        $validated = validator(['ab' => $ab], [
            'ab' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        $date = Carbon::createFromFormat('Y-m-d', $validated['ab']);

        $rows = DB::connection('sqlsrv_accountings')->table('tblRechnung as r')
            ->whereRaw('r.datRechnungsDatum >= DATEFROMPARTS(?,?,?)', [
                $date->year, $date->month, $date->day,
            ])
            ->where('r.fBetrag', '>', 0)
            ->where('r.fSteuer', '=', 0)
            ->select(
                'r.intRechNr',
                'r.datRechnungsDatum',
                'r.fBetrag',
                'r.fRechnungsbetrag',
                'r.fSteuer',
                'r.strKundenNameAufRechnung'
            )
            ->orderBy('r.datRechnungsDatum')
            ->orderBy('r.intRechNr')
            ->get();

        $mode = session('frontend_mode', 'classic');
        return view($mode.'.rechnungen-ohne-steuer.index', [
            'rows' => $rows,
            'ab' => $validated['ab'],
        ]);
    }
}
