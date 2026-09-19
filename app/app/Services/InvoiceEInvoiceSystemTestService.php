<?php

namespace App\Services;

use App\Models\AdminConnectionProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvoiceEInvoiceSystemTestService
{
    public function __construct(
        private InvoiceDocumentPreviewService $preview,
        private InvoiceTemplatePdfService $pdfRenderer,
        private InvoiceEInvoiceService $einvoice,
        private InvoiceStorageService $storage,
    ) {}

    public function run(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $invoiceDate): array
    {
        $started = microtime(true);

        $zugferd = $this->testZugferd($from, $to, $invoiceDate);
        $xrechnung = $this->testXRechnung($from, $to, $invoiceDate);
        $systemChecks = collect($this->systemChecks());

        return [
            'generatedAt' => CarbonImmutable::now(),
            'from' => $from,
            'to' => $to,
            'invoiceDate' => $invoiceDate,
            'zugferd' => $zugferd,
            'xrechnung' => $xrechnung,
            'systemChecks' => $systemChecks,
            'complete' => $zugferd['status'] === 'passed'
                && $xrechnung['status'] === 'passed'
                && $systemChecks->every(fn (array $check) => $check['status'] === 'passed'),
            'durationMs' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private function testZugferd(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $invoiceDate): array
    {
        foreach ($this->candidateOrders($from, $to, false) as $orderNumber) {
            try {
                $document = $this->preview->build((int) $orderNumber, $from, $to, $invoiceDate, true);
                $readiness = $this->einvoice->readiness($document, InvoiceEInvoiceService::FORMAT_ZUGFERD);
                if (! $readiness['ready']) {
                    continue;
                }

                $xml = $this->einvoice->buildXml(
                    $document,
                    2099999901,
                    $from,
                    $to,
                    InvoiceEInvoiceService::FORMAT_ZUGFERD,
                );
                $visual = $this->pdfRenderer->render($document, 2099999901, $from, $to, true);
                $pdf = $this->einvoice->mergeZugferdPdf($visual, $xml['xml']);
                $pdfValidation = $this->einvoice->validateZugferdPdf($pdf);

                $xmlValid = ($xml['validation']['xsd_valid'] ?? false)
                    && ($xml['validation']['semantic_valid'] ?? false);
                $pdfValid = ($pdfValidation['executed'] ?? false)
                    && ($pdfValidation['valid'] ?? false);

                return [
                    'status' => $xmlValid && $pdfValid ? 'passed' : 'failed',
                    'orderNumber' => (int) $orderNumber,
                    'customerNumber' => (int) $document['order']->intKID,
                    'gross' => (float) $document['testRun']['gross'],
                    'xmlBytes' => strlen($xml['xml']),
                    'pdfBytes' => strlen($pdf),
                    'xsdValid' => (bool) ($xml['validation']['xsd_valid'] ?? false),
                    'semanticValid' => (bool) ($xml['validation']['semantic_valid'] ?? false),
                    'pdfaExecuted' => (bool) ($pdfValidation['executed'] ?? false),
                    'pdfaValid' => (bool) ($pdfValidation['valid'] ?? false),
                    'pdfaVersion' => $pdfValidation['version'] ?? null,
                    'pdfaProfile' => $pdfValidation['profile'] ?? null,
                    'errors' => array_values(array_filter(array_merge(
                        $xml['validation']['xsd_errors'] ?? [],
                        $xml['validation']['semantic_errors'] ?? [],
                        $pdfValidation['errors'] ?? [],
                    ))),
                ];
            } catch (Throwable $e) {
                report($e);
            }
        }

        return [
            'status' => 'failed',
            'orderNumber' => null,
            'customerNumber' => null,
            'gross' => 0.0,
            'xmlBytes' => 0,
            'pdfBytes' => 0,
            'xsdValid' => false,
            'semanticValid' => false,
            'pdfaExecuted' => false,
            'pdfaValid' => false,
            'pdfaVersion' => null,
            'pdfaProfile' => null,
            'errors' => ['Kein vollständig fakturierbarer ZUGFeRD-Testfall im gewählten Zeitraum gefunden.'],
        ];
    }

    private function testXRechnung(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $invoiceDate): array
    {
        $last = null;

        foreach ($this->candidateOrders($from, $to, true) as $orderNumber) {
            try {
                $document = $this->preview->build((int) $orderNumber, $from, $to, $invoiceDate, true);
                $address = $document['testRun']['address'];
                $readiness = $this->einvoice->readiness($document, InvoiceEInvoiceService::FORMAT_XRECHNUNG);

                $base = [
                    'orderNumber' => (int) $orderNumber,
                    'customerNumber' => (int) $document['order']->intKID,
                    'buyer' => (string) ($address->strName ?? ''),
                    'leitwegId' => (string) ($address->strLeitwegId ?? ''),
                    'email' => (string) ($address->strEmail ?? ''),
                    'bankDebit' => (bool) ($document['testRun']['fulfillment']['bankDebit'] ?? false),
                    'gross' => (float) $document['testRun']['gross'],
                ];

                if (! $readiness['ready']) {
                    $last = $base + [
                        'status' => 'failed',
                        'xsdValid' => false,
                        'semanticValid' => false,
                        'kositExecuted' => false,
                        'kositValid' => false,
                        'errors' => $readiness['issues']->values()->all(),
                    ];
                    if ($base['bankDebit'] && $readiness['issues']->contains(fn ($issue) => str_contains($issue, 'Mandatsreferenz'))) {
                        return $last;
                    }
                    continue;
                }

                $xml = $this->einvoice->buildXml(
                    $document,
                    2099999902,
                    $from,
                    $to,
                    InvoiceEInvoiceService::FORMAT_XRECHNUNG,
                );
                $validation = $xml['validation'];
                $valid = ($validation['xsd_valid'] ?? false)
                    && ($validation['semantic_valid'] ?? false)
                    && ($validation['kosit']['executed'] ?? false)
                    && ($validation['kosit']['valid'] ?? false);

                $result = $base + [
                    'status' => $valid ? 'passed' : 'failed',
                    'xmlBytes' => strlen($xml['xml']),
                    'xsdValid' => (bool) ($validation['xsd_valid'] ?? false),
                    'semanticValid' => (bool) ($validation['semantic_valid'] ?? false),
                    'kositExecuted' => (bool) ($validation['kosit']['executed'] ?? false),
                    'kositValid' => (bool) ($validation['kosit']['valid'] ?? false),
                    'errors' => array_values(array_filter(array_merge(
                        $validation['xsd_errors'] ?? [],
                        $validation['semantic_errors'] ?? [],
                        $validation['kosit']['errors'] ?? [],
                    ))),
                ];

                return $result;
            } catch (Throwable $e) {
                report($e);
                $last = [
                    'status' => 'failed',
                    'orderNumber' => (int) $orderNumber,
                    'customerNumber' => null,
                    'buyer' => '',
                    'leitwegId' => '',
                    'email' => '',
                    'bankDebit' => false,
                    'gross' => 0.0,
                    'xsdValid' => false,
                    'semanticValid' => false,
                    'kositExecuted' => false,
                    'kositValid' => false,
                    'errors' => ['Technischer Fehler: '.$e->getMessage()],
                ];
            }
        }

        return $last ?? [
            'status' => 'missing',
            'orderNumber' => null,
            'customerNumber' => null,
            'buyer' => '',
            'leitwegId' => '',
            'email' => '',
            'bankDebit' => false,
            'gross' => 0.0,
            'xsdValid' => false,
            'semanticValid' => false,
            'kositExecuted' => false,
            'kositValid' => false,
            'errors' => ['Kein Auftrag mit Leitweg-ID im gewählten Zeitraum gefunden.'],
        ];
    }

    private function candidateOrders(CarbonImmutable $from, CarbonImmutable $to, bool $requireLeitweg): array
    {
        $query = DB::connection('sqlsrv_accountings')->table('tblAuftrag as a')
            ->join('tblRechnungsanschrift as ra', function ($join) {
                $join->on('ra.intID', '=', 'a.intAnschriftID')
                    ->on('ra.intKID', '=', 'a.intKID');
            })
            ->where('a.boolRechnungstool', 1)
            ->where(function ($q) {
                $q->whereNull('a.boolSponsoring')->orWhere('a.boolSponsoring', 0);
            })
            ->whereRaw('a.datFakturierAb < DATEADD(day, 1, DATEFROMPARTS(?, ?, ?))', [$to->year, $to->month, $to->day])
            ->where(function ($q) use ($from) {
                $q->whereNull('a.datStorniereAb')
                    ->orWhereRaw('a.datStorniereAb > DATEFROMPARTS(?, ?, ?)', [$from->year, $from->month, $from->day]);
            });

        if ($requireLeitweg) {
            $query->whereNotNull('ra.strLeitwegId')
                ->whereRaw("LTRIM(RTRIM(ra.strLeitwegId)) <> ''");
        }

        return $query->orderByDesc('a.intAufNr')
            ->limit(150)
            ->pluck('a.intAufNr')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function systemChecks(): array
    {
        $checks = [];

        $storage = $this->storage->readiness();
        $profile = AdminConnectionProfile::where('key', InvoiceStorageService::PROFILE_KEY)->first();
        $unc = (string) ($profile?->options['unc_root'] ?? '');
        $storageOk = ($storage['ready'] ?? false)
            && strcasecmp(ltrim(rtrim($unc, '\\/'), '\\/'), 'janus\\Rechnungen') === 0;
        $checks[] = [
            'label' => 'E-Rechnungs-Ablage',
            'status' => $storageOk ? 'passed' : 'failed',
            'message' => $storageOk
                ? 'Produktives Ziel ist '.('\\\\'.'janus\\Rechnungen').' ('.$storage['path'].').'
                : 'Die Rechnungsablage zeigt nicht eindeutig auf '.('\\\\'.'janus\\Rechnungen').'.',
        ];

        $midas = AdminConnectionProfile::where('key', 'storage.midas')->first();
        $midasReadOnly = $midas && ($midas->options['mode'] ?? null) === 'read-only';
        $checks[] = [
            'label' => 'MIDAS-Schreibschutz',
            'status' => $midasReadOnly ? 'passed' : 'failed',
            'message' => $midasReadOnly
                ? 'MIDAS ist weiterhin ausschließlich read-only.'
                : 'MIDAS ist nicht eindeutig read-only konfiguriert.',
        ];

        $checks[] = [
            'label' => 'Testablauf',
            'status' => 'passed',
            'message' => 'XML und PDF werden nur temporär erzeugt und nicht in der Rechnungsablage gespeichert.',
        ];

        return $checks;
    }
}
