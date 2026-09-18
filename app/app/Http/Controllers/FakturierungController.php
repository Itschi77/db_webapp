<?php

namespace App\Http\Controllers;

use App\Models\Kunde;
use App\Services\InvoiceBatchTestRunService;
use App\Services\InvoiceConsistencyCheckService;
use App\Services\InvoiceDocumentEditService;
use App\Services\InvoiceDocumentPreviewService;
use App\Services\InvoiceHistoricalParityBatchService;
use App\Services\InvoiceHistoricalParityService;
use App\Services\InvoiceNumberSimulationService;
use App\Services\InvoiceOrderTestRunService;
use App\Services\InvoicePreviewCalculationService;
use App\Services\InvoiceTemplatePdfService;
use App\Services\InvoiceWriteService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

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
            'accountings' => ['nullable', 'in:0,1'],
            'rechnungsdatum' => ['nullable', 'date'],
            'lauf' => ['nullable', 'in:auto,kunde,gesamt,auftrag'],
            'ansicht' => ['nullable', 'in:classic,modern'],
            'vergleich' => ['nullable', 'integer', 'min:1'],
            'anzeige' => ['nullable', 'in:abrechenbar,alle'],
            'paritaetslauf' => ['nullable', 'in:1'],
        ]);

        $today = CarbonImmutable::today();
        $previousMonth = $today->subMonthNoOverflow();
        $von = CarbonImmutable::parse($request->input('von', $previousMonth->startOfMonth()->toDateString()))->startOfDay();
        $bis = CarbonImmutable::parse($request->input('bis', $previousMonth->endOfMonth()->toDateString()))->endOfDay();
        $art = $request->input('art', 'nachtraeglich');
        $q = trim((string) $request->input('q', ''));
        $auftragsnr = (int) $request->input('auftragsnr', 0);
        $kundennr = (int) $request->input('kundennr', 0);
        $accountings = $request->input('accountings') === '1';
        $rechnungsdatum = CarbonImmutable::parse($request->input('rechnungsdatum', $bis->toDateString()))->startOfDay();
        $lauf = $request->input('lauf');
        $anzeige = $request->input('anzeige', 'abrechenbar');
        $mode = $request->input('ansicht', session('frontend_mode', 'classic'));

        $db = DB::connection('sqlsrv_accountings');
        $latestInvoice = $db->table('tblRechnung')
            ->selectRaw('intAufNr, MAX(datRechnungsDatum) AS letztesRechnungsdatum')
            ->groupBy('intAufNr');

        $query = $db->table('tblAuftrag as a')
            ->join('tblRechnungsanschrift as ra', function ($join) {
                $join->on('ra.intID', '=', 'a.intAnschriftID')
                    ->on('ra.intKID', '=', 'a.intKID');
            })
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

        // Ungefilterte Kandidaten des gewählten Zeitraums/Abrechnungstyps für Kunden- und Gesamttestlauf.
        $runBaseQuery = clone $query;

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
        $alleAuftraegeCount = $auftraege->count();
        $orderFilterHint = null;
        if ($auftragsnr > 0 && $alleAuftraegeCount === 0) {
            $rawOrder = $db->table('tblAuftrag')->where('intAufNr', $auftragsnr)
                ->first(['intAufNr', 'boolVoraus', 'boolDomainrechnung']);
            if ($rawOrder) {
                $expectedArt = (bool) $rawOrder->boolVoraus
                    ? 'voraus'
                    : ((bool) $rawOrder->boolDomainrechnung ? 'domain' : 'nachtraeglich');
                if ($expectedArt !== $art) {
                    $labels = ['nachtraeglich' => 'Nachträglich', 'voraus' => 'Im Voraus', 'domain' => 'Domains'];
                    $orderFilterHint = 'Auftrag #'.$auftragsnr.' gehört zur Abrechnungsart „'.$labels[$expectedArt].'“ und wird deshalb mit „'.$labels[$art].'“ nicht angezeigt.';
                }
            }
        }
        $abrechenbareIds = collect();

        if ($mode === 'modern' && $auftraege->isNotEmpty()) {
            $classificationKey = 'invoice.billable-orders.'.sha1(json_encode([
                $von->toDateString(), $bis->toDateString(), $rechnungsdatum->toDateString(),
                $art, $accountings, $auftraege->pluck('intAufNr')->all(),
            ]));
            $abrechenbareIds = collect(Cache::remember(
                $classificationKey,
                now()->addMinutes(5),
                function () use ($auftraege, $von, $bis, $rechnungsdatum, $accountings) {
                    return app(InvoiceBatchTestRunService::class)
                        ->build($auftraege, $von, $bis, $rechnungsdatum, $accountings, 'liste')['rows']
                        ->filter(fn ($row) => $row->status === 'ready' && $row->documentRows > 0)
                        ->pluck('order.intAufNr')
                        ->values()
                        ->all();
                },
            ));
            if ($anzeige === 'abrechenbar') {
                $auftraege = $auftraege
                    ->filter(fn ($order) => $abrechenbareIds->contains((int) $order->intAufNr))
                    ->values();
            }
        }

        $abrechenbareAuftraegeCount = $abrechenbareIds->count();

        $batchTestRun = null;
        $batchRunError = null;
        if ($lauf) {
            $resolvedScope = $lauf === 'auto'
                ? ($auftragsnr > 0 ? 'auftrag' : ($kundennr > 0 ? 'kunde' : 'gesamt'))
                : $lauf;
            $batchQuery = clone $runBaseQuery;
            if ($resolvedScope === 'kunde') {
                if ($kundennr <= 0) {
                    $batchRunError = 'Für einen Kunden-Testlauf muss eine Kundennummer angegeben werden.';
                } else {
                    $batchQuery->where('a.intKID', $kundennr);
                }
            } elseif ($resolvedScope === 'auftrag') {
                if ($auftragsnr <= 0) {
                    $batchRunError = 'Für einen Auftrags-Testlauf muss eine Auftragsnummer angegeben werden.';
                } else {
                    $batchQuery->where('a.intAufNr', $auftragsnr);
                }
            }
            if (! $batchRunError) {
                $batchOrders = $batchQuery->orderBy('a.intKID')->orderBy('a.intAufNr')->get();
                $batchTestRun = app(InvoiceBatchTestRunService::class)
                    ->build($batchOrders, $von, $bis, $rechnungsdatum, $accountings, $resolvedScope);
            }
        }

        $kunden = Kunde::whereIn('intID', $auftraege->pluck('intKID')->unique()->values())
            ->get(['intID', 'strName'])
            ->keyBy('intID');

        $selected = null;
        $positionen = collect();
        $berechnet = collect();
        $calculationPreview = null;
        $calculationError = null;
        $orderTestRun = null;
        if ($request->filled('auftrag')) {
            $selectedOrderNumber = (int) $request->integer('auftrag');
            $selected = $auftraege->firstWhere('intAufNr', $selectedOrderNumber);

            // A consistency-report link must open its order even when the order is
            // outside the currently selected billing type, period, or list filters.
            if (! $selected) {
                $selected = $db->table('tblAuftrag as a')
                    ->leftJoin('tblRechnungsanschrift as ra', 'ra.intID', '=', 'a.intAnschriftID')
                    ->leftJoinSub($latestInvoice, 'lr', fn ($join) => $join->on('lr.intAufNr', '=', 'a.intAufNr'))
                    ->where('a.intAufNr', $selectedOrderNumber)
                    ->first([
                        'a.intAufNr', 'a.intKID', 'a.datFakturierAb', 'a.datStorniereAb',
                        'a.strBeschreibung', 'a.boolEmailRechnung', 'a.strAbrechnungshinweis',
                        'a.boolVoraus', 'a.boolDomainrechnung', 'a.boolEingefroren',
                        'ra.strEmail as rechnungEmail', 'lr.letztesRechnungsdatum',
                    ]);

                if ($selected && ! $kunden->has($selected->intKID)) {
                    $selectedCustomer = Kunde::find($selected->intKID, ['intID', 'strName']);
                    if ($selectedCustomer) {
                        $kunden->put($selectedCustomer->intID, $selectedCustomer);
                    }
                }
            }

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
                    $calculationPreview = app(InvoicePreviewCalculationService::class)
                        ->calculate($selected, $positionen, $von, $bis, $accountings);
                    $orderTestRun = app(InvoiceOrderTestRunService::class)
                        ->build($selected, $calculationPreview, $rechnungsdatum);
                    $draft = app(InvoiceDocumentEditService::class)->get((int) $selected->intAufNr);
                    if ($draft) {
                        try {
                            $orderTestRun = app(InvoiceDocumentEditService::class)->apply($orderTestRun, $draft);
                        } catch (RuntimeException $exception) {
                            session()->forget('invoice_document_draft.'.(int) $selected->intAufNr);
                            $orderTestRun['warnings']->push($exception->getMessage().' Der Bearbeitungsstand wurde verworfen.');
                        }
                    }
                } catch (QueryException $e) {
                    $calculationError = str_contains($e->getMessage(), 'BETAtblAbrechnungsArt')
                        ? 'Für die Intervallberechnung fehlt dem Webapp-SQL-Benutzer noch SELECT auf BETAtblAbrechnungsArt.'
                        : 'Die lesende Berechnungsvorschau konnte nicht ausgeführt werden.';
                }
            }
        }

        $manualReviewReport = Cache::get(
            InvoiceConsistencyCheckService::CACHE_KEY,
            [
                'generated_at' => null,
                'period_from' => null,
                'period_to' => null,
                'issues' => [],
            ],
        );
        $manualReviewIssues = collect($manualReviewReport['issues']);

        $parityIdentifier = $request->integer('vergleich');
        $parityComparison = $parityIdentifier
            ? app(InvoiceHistoricalParityService::class)->compare($parityIdentifier)
            : null;
        $parityBatch = $request->input('paritaetslauf') === '1'
            ? app(InvoiceHistoricalParityBatchService::class)->run()
            : null;

        $invoiceNumberSimulation = app(InvoiceNumberSimulationService::class)
            ->simulate($rechnungsdatum);
        $invoiceWriteReadiness = app(InvoiceWriteService::class)->readiness();

        return view($mode.'.fakturierung.index', [
            'adUsername' => $request->attributes->get('ad_username'),
            'auftraege' => $auftraege,
            'kunden' => $kunden,
            'selected' => $selected,
            'positionen' => $positionen,
            'berechnet' => $berechnet,
            'calculationPreview' => $calculationPreview,
            'calculationError' => $calculationError,
            'orderTestRun' => $orderTestRun,
            'batchTestRun' => $batchTestRun,
            'batchRunError' => $batchRunError,
            'orderFilterHint' => $orderFilterHint,
            'manualReviewIssues' => $manualReviewIssues,
            'manualReviewReport' => $manualReviewReport,
            'parityIdentifier' => $parityIdentifier,
            'parityComparison' => $parityComparison,
            'parityBatch' => $parityBatch,
            'invoiceNumberSimulation' => $invoiceNumberSimulation,
            'invoiceWriteReadiness' => $invoiceWriteReadiness,
            'von' => $von->toDateString(),
            'bis' => $bis->toDateString(),
            'art' => $art,
            'q' => $q,
            'auftragsnr' => $auftragsnr,
            'kundennr' => $kundennr,
            'accountings' => $accountings,
            'rechnungsdatum' => $rechnungsdatum->toDateString(),
            'lauf' => $lauf,
            'anzeige' => $anzeige,
            'alleAuftraegeCount' => $alleAuftraegeCount,
            'abrechenbareAuftraegeCount' => $abrechenbareAuftraegeCount,
        ]);
    }

    public function commitInvoice(Request $request, InvoiceWriteService $writer)
    {
        $validated = $request->validate([
            'auftrag' => ['required', 'integer', 'min:1'],
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
            'rechnungsdatum' => ['required', 'date'],
            'accountings' => ['nullable', 'in:0,1'],
            'bestaetigung' => ['required', 'string'],
        ]);

        if (! hash_equals((string) config('invoicing.confirmation_phrase'), $validated['bestaetigung'])) {
            return back()->withErrors(['bestaetigung' => 'Der Bestätigungstext stimmt nicht.'])->withInput();
        }

        try {
            $result = $writer->commit(
                (int) $validated['auftrag'],
                CarbonImmutable::parse($validated['von'])->startOfDay(),
                CarbonImmutable::parse($validated['bis'])->endOfDay(),
                CarbonImmutable::parse($validated['rechnungsdatum'])->startOfDay(),
                ($validated['accountings'] ?? '0') === '1',
                (string) $request->attributes->get('ad_username', 'unbekannt'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['rechnung' => $exception->getMessage()])->withInput();
        } catch (Throwable $exception) {
            report($exception);
            return back()->withErrors(['rechnung' => 'Der Schreibvorgang wurde vollständig zurückgerollt. Details stehen im Anwendungslog.'])->withInput();
        }

        return redirect()->route('rechnungen.show', $result['invoiceId'])
            ->with('status', 'Rechnung '.$result['invoiceNumber'].' wurde transaktionssicher erzeugt.');
    }

    public function saveDocumentEdit(
        Request $request,
        InvoiceDocumentPreviewService $previewService,
        InvoiceDocumentEditService $editService,
    ) {
        $validated = $request->validate([
            'auftrag' => ['required', 'integer', 'min:1'],
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
            'rechnungsdatum' => ['required', 'date'],
            'accountings' => ['nullable', 'in:0,1'],
            'invoice_note' => ['nullable', 'string', 'max:2000'],
            'descriptions' => ['nullable', 'array'],
            'descriptions.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $from = CarbonImmutable::parse($validated['von'])->startOfDay();
        $to = CarbonImmutable::parse($validated['bis'])->endOfDay();
        $invoiceDate = CarbonImmutable::parse($validated['rechnungsdatum'])->startOfDay();
        $document = $previewService->build(
            (int) $validated['auftrag'],
            $from,
            $to,
            $invoiceDate,
            ($validated['accountings'] ?? '0') === '1',
        );

        try {
            $draft = $editService->save(
                (int) $validated['auftrag'],
                $document['testRun'],
                $validated,
                (string) $request->attributes->get('ad_username', 'unbekannt'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['document_edit' => $exception->getMessage()])->withInput();
        }

        return back()->with(
            'status',
            'Bearbeitungsstand bestätigt und protokolliert · '
            .CarbonImmutable::parse($draft['confirmed_at'])->timezone('Europe/Berlin')->format('d.m.Y H:i').' Uhr.'
        );
    }

    public function clearDocumentEdit(Request $request, InvoiceDocumentEditService $editService)
    {
        $validated = $request->validate([
            'auftrag' => ['required', 'integer', 'min:1'],
        ]);

        $editService->clear(
            (int) $validated['auftrag'],
            (string) $request->attributes->get('ad_username', 'unbekannt'),
        );

        return back()->with('status', 'Bearbeitungsstand wurde verworfen.');
    }

    public function documentPreview(
        Request $request,
        InvoiceDocumentPreviewService $previewService,
        InvoiceDocumentEditService $editService,
        InvoiceTemplatePdfService $templatePdf,
    )
    {
        $validated = $request->validate([
            'auftrag' => ['required', 'integer', 'min:1'],
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
            'rechnungsdatum' => ['required', 'date'],
            'accountings' => ['nullable', 'in:0,1'],
        ]);

        $from = CarbonImmutable::parse($validated['von'])->startOfDay();
        $to = CarbonImmutable::parse($validated['bis'])->endOfDay();
        $invoiceDate = CarbonImmutable::parse($validated['rechnungsdatum'])->startOfDay();
        $document = $previewService->build(
            (int) $validated['auftrag'],
            $from,
            $to,
            $invoiceDate,
            ($validated['accountings'] ?? '0') === '1',
        );
        $document['testRun'] = $editService->apply(
            $document['testRun'],
            $editService->get((int) $validated['auftrag']),
        );
        $numberSimulation = app(InvoiceNumberSimulationService::class)->simulate($invoiceDate);
        $pdf = $templatePdf->render(
            $document,
            (int) $numberSimulation['next'],
            $from,
            $to,
            true,
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Rechnungsvorschau-Auftrag-'.$validated['auftrag'].'.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
