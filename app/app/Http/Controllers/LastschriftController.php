<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LastschriftController extends Controller
{
    public function index(Request $request)
    {
        $von = $request->input('von', now()->subDays(30)->format('Y-m-d'));
        $bis = $request->input('bis', now()->format('Y-m-d'));

        $validated = validator(['von' => $von, 'bis' => $bis], [
            'von' => ['required', 'date_format:Y-m-d'],
            'bis' => ['required', 'date_format:Y-m-d', 'after_or_equal:von'],
        ])->validate();

        $rows = $this->eligibleRows($validated['von'], $validated['bis']);
        foreach ($rows as $row) {
            $row->zahlbetrag = $this->zahlbetrag($row);
        }

        $summe = $rows->sum('zahlbetrag');
        $mode = session('frontend_mode', 'classic');

        return view($mode.'.lastschriften.index', [
            'rows' => $rows,
            'summe' => $summe,
            'von' => $validated['von'],
            'bis' => $validated['bis'],
        ]);
    }

    public function markPaid(Request $request)
    {
        $validated = $request->validate([
            'von' => ['required', 'date_format:Y-m-d'],
            'bis' => ['required', 'date_format:Y-m-d', 'after_or_equal:von'],
            'confirm' => ['accepted'],
        ]);

        $db = DB::connection('sqlsrv_accountings');
        $result = $db->transaction(function () use ($validated, $db) {
            $rows = $this->eligibleRows($validated['von'], $validated['bis']);
            $count = 0;
            $summe = 0.0;

            foreach ($rows as $row) {
                $betrag = $this->zahlbetrag($row);
                $updated = $db->table('tblRechnung')
                    ->where('intID', $row->intID)
                    ->where('boolBezahlt', 0)
                    ->update([
                        'datBezahlDatum' => Carbon::parse($row->datFaelligkeitsDatum)->format('Y-m-d'),
                        'fBezahlterBetrag' => $betrag,
                        'boolBezahlt' => 1,
                    ]);

                if ($updated === 1) {
                    $count++;
                    $summe += $betrag;
                }
            }

            return [$count, $summe];
        });

        return redirect()->route('lastschriften.index', [
            'von' => $validated['von'],
            'bis' => $validated['bis'],
        ])->with('success', sprintf(
            '%d Lastschrift(en) wurden als bezahlt markiert. Gesamtsumme: %s €.',
            $result[0], number_format($result[1], 2, ',', '.')
        ));
    }

    private function eligibleRows(string $von, string $bis)
    {
        $from = Carbon::createFromFormat('Y-m-d', $von);
        $to = Carbon::createFromFormat('Y-m-d', $bis);

        return DB::connection('sqlsrv_accountings')->table('tblRechnung as r')
            ->join('tblAuftrag as a', 'a.intAufNr', '=', 'r.intAufNr')
            ->where('r.boolBezahlt', 0)
            ->whereIn('a.intZahlungsbedingungID', [3, 26])
            ->whereRaw('r.datFaelligkeitsDatum >= DATEFROMPARTS(?,?,?) AND r.datFaelligkeitsDatum < DATEADD(day,1,DATEFROMPARTS(?,?,?))', [
                $from->year, $from->month, $from->day,
                $to->year, $to->month, $to->day,
            ])
            ->select(
                'r.intID', 'r.intRechNr', 'r.intAufNr', 'r.strKundenNameAufRechnung',
                'r.datFaelligkeitsDatum', 'r.boolBezahlt', 'r.datBezahlDatum', 'r.fBezahlterBetrag',
                'r.fRechnungsbetrag', 'r.datSkonto1Bis', 'r.datSkonto2Bis', 'r.datSkonto3Bis',
                'r.fRechnungsbetragMitSkonto1', 'r.fRechnungsbetragMitSkonto2', 'r.fRechnungsbetragMitSkonto3',
                'a.intZahlungsbedingungID'
            )
            ->orderBy('r.intID')
            ->get();
    }

    private function zahlbetrag(object $row): float
    {
        $betrag = (float) $row->fRechnungsbetrag;
        $faellig = Carbon::parse($row->datFaelligkeitsDatum)->startOfDay();

        foreach ([1, 2, 3] as $stufe) {
            $betragFeld = 'fRechnungsbetragMitSkonto'.$stufe;
            $bisFeld = 'datSkonto'.$stufe.'Bis';
            if ((float) $row->{$betragFeld} > 0 && $row->{$bisFeld} && Carbon::parse($row->{$bisFeld})->startOfDay()->gte($faellig)) {
                $betrag = (float) $row->{$betragFeld};
            }
        }

        return $betrag;
    }
}
