<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use App\Services\FixedPriceInvoicePreviewService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class FakturierungController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'von' => ['nullable', 'date'],
            'bis' => ['nullable', 'date', 'after_or_equal:von'],
            'art' => ['nullable', 'in:nachtraeglich,voraus,domain'],
            'q' => ['nullable', 'string', 'max:100'],
            'auftragsnr' => ['nullable', 'integer', 'min:0'],
            'kundennr' => ['nullable', 'integer', 'min:0'],
            'auftrag' => ['nullable', 'integer', 'min:1'],
        ]);

        $today = CarbonImmutable::today();
        $previousMonth = $today->subMonthNoOverflow();
        $von = CarbonImmutable::parse($request->input('von', $previousMonth->startOfMonth()->toDateString()))->startOfDay();
        $bis = CarbonImmutable::parse($request->input('bis', $previousMonth->endOfMonth()->toDateString()))->endOfDay();
        $art = $request->input('art', 'nachtraeglich');
        $q = trim((string) $request->input('q', ''));
        $auftragsnr = (int) $request->input('auftragsnr', 0);
        $kundennr = (int) $request->input('kundennr', 0);

        $db = DB::connection('sqlsrv_accountings');
        $latestInvoice = $db->table('tblRechnung')
            ->selectRaw('intAufNr, MAX(datRechnungsDatum) AS letztesRechnungsdatum')
            ->groupBy('intAufNr');

        $query = $db->table('tblAuftrag as a')
            ->join('tblRechnungsanschrift as ra', 'ra.intID', '=', 'a.intAnschriftID')
            ->leftJoinSub($latestInvoice, 'lr', fn ($join) => $join->on('lr.intAufNr', '=', 'a.intAufNr'))
            ->select([
                'a.intAufNr', 'a.intKID', 'a.datFakturierAb', 'a.datStorniereAb',
                'a.strBeschreibung', 'a.boolEmailRechnung', 'a.strAbrechnungshinweis',
                'a.boolVoraus', 'a.boolDomainrechnung', 'a.boolEingefroren',
                'ra.strEmail as rechnungEmail', 'lr.letztesRechnungsdatum',
            ])
            ->where('a.boolRechnungstool', 1)
            ->where(function ($builder) {
                $builder->whereNull('a.boolSponsoring')->orWhere('a.boolSponsoring', 0);
            })
            ->whereRaw(
                'a.datFakturierAb < DATEADD(day, 1, DATEFROMPARTS(?, ?, ?))',
                [$bis->year, $bis->month, $bis->day]
            )
            ->where(function ($builder) use ($von) {
                $builder->whereNull('a.datStorniereAb')->orWhereRaw(
                    'a.datStorniereAb > DATEFROMPARTS(?, ?, ?)',
                    [$von->year, $von->month, $von->day]
                );
            });

        match ($art) {
            'voraus' => $query->where('a.boolVoraus', 1),
            'domain' => $query->where('a.boolVoraus', 0)->where('a.boolDomainrechnung', 1),
            default => $query->where('a.boolVoraus', 0)->where(function ($builder) {
                $builder->whereNull('a.boolDomainrechnung')->orWhere('a.boolDomainrechnung', 0);
            }),
        };

        if ($auftragsnr > 0) {
            $query->where('a.intAufNr', $auftragsnr);
        }
        if ($kundennr > 0) {
            $query->where('a.intKID', $kundennr);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('a.strBeschreibung', 'like', "%{$q}%")
                    ->orWhere('ra.strName', 'like', "%{$q}%");
                if (ctype_digit($q)) {
                    $builder->orWhere('a.intAufNr', (int) $q)->orWhere('a.intKID', (int) $q);
                }
            });
        }

        $auftraege = $query->orderBy('a.intKID')->orderBy('a.intAufNr')->limit(500)->get();
        $kunden = Kunde::whereIn('intID', $auftraege->pluck('intKID')->unique()->values())
            ->get(['intID', 'strName'])
            ->keyBy('intID');

        $selected = null;
        $positionen = collect();
        $berechnet = collect();
        $calculationPreview = null;
        $calculationError = null;
        if ($request->filled('auftrag')) {
            $selected = $auftraege->firstWhere('intAufNr', (int) $request->integer('auftrag'));
            if ($selected) {
                $positionen = $db->table('tblAuftragPos')
                    ->where('intAufNr', $selected->intAufNr)
                    ->orderBy('intID')
                    ->get([
                        'intID', 'strBeschreibung', 'intMenge', 'fEndpreis', 'fRabattInProzent',
                        'intMwstsatz', 'intAbrechnungsArt', 'intStaffelTyp', 'intStaffelgruppe',
                        'datFakturierAb', 'datFakturierBis', 'datVorberechnenBis', 'boolIstAnbindung',
                    ]);

                $positionIds = $positionen->pluck('intID');
                if ($positionIds->isNotEmpty()) {
                    $berechnet = $db->table('tblAuftragPosBerechnet')
                        ->whereIn('intAufPosID', $positionIds)
                        ->selectRaw('intAufPosID, COUNT(*) AS anzahl, MAX(BerechnetZum) AS zuletztBerechnet')
                        ->groupBy('intAufPosID')
                        ->get()
                        ->keyBy('intAufPosID');
                }

                try {
                    $calculationPreview = app(FixedPriceInvoicePreviewService::class)
                        ->calculate($selected, $positionen, $von, $bis);
                } catch (QueryException $e) {
                    $calculationError = str_contains($e->getMessage(), 'BETAtblAbrechnungsArt')
                        ? 'Für die Intervallberechnung fehlt dem Webapp-SQL-Benutzer noch SELECT auf BETAtblAbrechnungsArt.'
                        : 'Die lesende Berechnungsvorschau konnte nicht ausgeführt werden.';
                }
            }
        }

        $mode = session('frontend_mode', 'classic');
        return view($mode.'.fakturierung.index', [
            'adUsername' => $request->attributes->get('ad_username'),
            'auftraege' => $auftraege,
            'kunden' => $kunden,
            'selected' => $selected,
            'positionen' => $positionen,
            'berechnet' => $berechnet,
            'calculationPreview' => $calculationPreview,
            'calculationError' => $calculationError,
            'von' => $von->toDateString(),
            'bis' => $bis->toDateString(),
            'art' => $art,
            'q' => $q,
            'auftragsnr' => $auftragsnr,
            'kundennr' => $kundennr,
        ]);
    }
}
