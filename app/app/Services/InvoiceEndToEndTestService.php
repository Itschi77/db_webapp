<?php

namespace App\Services;

use App\Models\AdminConnectionProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class InvoiceEndToEndTestService
{
    public function __construct(
        private InvoiceDocumentPreviewService $documentPreview,
        private InvoiceTemplatePdfService $pdfRenderer,
        private InvoiceStorageService $storage,
    ) {}

    public function run(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $invoiceDate): array
    {
        $started = microtime(true);

        $cases = collect([
            $this->testCase('festpreis', 'Normale Festpreisrechnung', $from, $to, $invoiceDate, false),
            $this->testCase('voraus', 'Im Voraus', $from, $to, $invoiceDate, false),
            $this->testCase('staffel', 'Staffel / Accounting', $from, $to, $invoiceDate, true),
            $this->testCase('domain', 'Domainrechnung', $from, $to, $invoiceDate, true),
            $this->testCase('mehrere_positionen', 'Mehrere Positionen', $from, $to, $invoiceDate, true),
            $this->testCase('rabatt', 'Rabatt', $from, $to, $invoiceDate, false),
        ]);

        $systemChecks = collect($this->systemChecks());
        $failedCases = $cases->where('status', 'failed')->count();
        $missingCases = $cases->where('status', 'missing')->count();
        $failedSystem = $systemChecks->where('status', 'failed')->count();

        return [
            'generatedAt' => CarbonImmutable::now(),
            'from' => $from,
            'to' => $to,
            'invoiceDate' => $invoiceDate,
            'cases' => $cases,
            'systemChecks' => $systemChecks,
            'passedCases' => $cases->where('status', 'passed')->count(),
            'missingCases' => $missingCases,
            'failedCases' => $failedCases,
            'failedSystemChecks' => $failedSystem,
            'complete' => $failedCases === 0 && $missingCases === 0 && $failedSystem === 0,
            'durationMs' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private function testCase(
        string $key,
        string $label,
        CarbonImmutable $from,
        CarbonImmutable $to,
        CarbonImmutable $invoiceDate,
        bool $includeAccountings,
    ): array {
        $candidates = $this->candidateQuery($key, $from, $to)
            ->limit(150)
            ->pluck('a.intAufNr');

        if ($candidates->isEmpty()) {
            return $this->caseResult($key, $label, 'missing', null, null, 'Kein passender Auftrag im gewählten Zeitraum gefunden.');
        }

        $lastIssue = null;
        foreach ($candidates as $orderNumber) {
            try {
                $document = $this->documentPreview->build(
                    (int) $orderNumber,
                    $from,
                    $to,
                    $invoiceDate,
                    $includeAccountings,
                );

                $run = $document['testRun'];
                if (($run['status'] ?? null) !== 'ready' || $run['documentRows']->isEmpty()) {
                    $lastIssue = $run['issues']->first()
                        ?? $run['warnings']->first()
                        ?? 'Auftrag ist im Testzeitraum nicht fakturierbar.';
                    continue;
                }
                if ($key === 'mehrere_positionen' && $run['documentRows']->count() < 2) {
                    $lastIssue = 'Der Auftrag enthält mehrere Positionen, erzeugt im Testzeitraum aber weniger als zwei Dokumentzeilen.';
                    continue;
                }
                if ($key === 'rabatt' && ! $run['documentRows']->contains(fn ($row) => $row->kind === 'Rabatt')) {
                    $lastIssue = 'Ein Rabatt ist im Auftrag hinterlegt, erscheint im Testzeitraum aber nicht als Rabattzeile im Dokument.';
                    continue;
                }

                $pdf = $this->pdfRenderer->render(
                    $document,
                    2099999999,
                    $from,
                    $to,
                    true,
                );
                $pdfValid = str_starts_with($pdf, '%PDF-') && strlen($pdf) > 1000;
                if (! $pdfValid) {
                    return $this->caseResult(
                        $key,
                        $label,
                        'failed',
                        (int) $orderNumber,
                        (int) $document['order']->intKID,
                        'Die PDF-Vorschau wurde erzeugt, ist aber strukturell ungültig.',
                    );
                }

                return $this->caseResult(
                    $key,
                    $label,
                    'passed',
                    (int) $orderNumber,
                    (int) $document['order']->intKID,
                    sprintf(
                        '%d Dokumentzeile(n), %.2f € brutto, PDF %.1f KB.',
                        $run['documentRows']->count(),
                        (float) $run['gross'],
                        strlen($pdf) / 1024,
                    ),
                );
            } catch (Throwable $e) {
                report($e);
                $lastIssue = 'Technischer Fehler: '.$e->getMessage();
            }
        }

        return $this->caseResult(
            $key,
            $label,
            'failed',
            (int) $candidates->first(),
            null,
            $lastIssue ?: 'Keiner der gefundenen Kandidaten war vollständig fakturierbar.',
        );
    }

    private function candidateQuery(string $key, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $db = DB::connection('sqlsrv_accountings');
        $query = $db->table('tblAuftrag as a')
            ->where('a.boolRechnungstool', 1)
            ->where(function ($q) {
                $q->whereNull('a.boolSponsoring')->orWhere('a.boolSponsoring', 0);
            })
            ->whereRaw('a.datFakturierAb < DATEADD(day, 1, DATEFROMPARTS(?, ?, ?))', [$to->year, $to->month, $to->day])
            ->where(function ($q) use ($from) {
                $q->whereNull('a.datStorniereAb')
                    ->orWhereRaw('a.datStorniereAb > DATEFROMPARTS(?, ?, ?)', [$from->year, $from->month, $from->day]);
            })
            ->orderByDesc('a.intAufNr');

        return match ($key) {
            'voraus' => $query->where('a.boolVoraus', 1),
            'domain' => $query->where('a.boolVoraus', 0)->where('a.boolDomainrechnung', 1),
            'staffel' => $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('tblAuftragPos as p')
                    ->whereColumn('p.intAufNr', 'a.intAufNr')
                    ->where(function ($p) {
                        $p->where('p.intStaffelTyp', '>', 0)
                            ->orWhere('p.boolIstAnbindung', 1);
                    });
            }),
            'mehrere_positionen' => $query->whereRaw(
                '(SELECT COUNT(*) FROM dbo.tblAuftragPos p WHERE p.intAufNr = a.intAufNr) > 1'
            ),
            'rabatt' => $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('tblAuftragPos as p')
                    ->whereColumn('p.intAufNr', 'a.intAufNr')
                    ->where('p.fRabattInProzent', '>', 0);
            }),
            default => $query
                ->where('a.boolVoraus', 0)
                ->where(function ($q) {
                    $q->whereNull('a.boolDomainrechnung')->orWhere('a.boolDomainrechnung', 0);
                })
                ->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('tblAuftragPos as p')
                        ->whereColumn('p.intAufNr', 'a.intAufNr')
                        ->where(function ($p) {
                            $p->whereNull('p.intStaffelTyp')->orWhere('p.intStaffelTyp', 0);
                        })
                        ->where(function ($p) {
                            $p->whereNull('p.boolIstAnbindung')->orWhere('p.boolIstAnbindung', 0);
                        });
                }),
        };
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
            'label' => 'Rechnungsablage',
            'status' => $storageOk ? 'passed' : 'failed',
            'message' => $storageOk
                ? 'Schreibziel ist ausschließlich '.('\\\\'.'janus\\Rechnungen').' ('.$storage['path'].').'
                : 'Die aktive Rechnungsablage zeigt nicht eindeutig auf '.('\\\\'.'janus\\Rechnungen').'.',
        ];

        $midas = AdminConnectionProfile::where('key', 'storage.midas')->first();
        $midasReadOnly = $midas
            && ($midas->options['mode'] ?? null) === 'read-only';
        $checks[] = [
            'label' => 'MIDAS-Schreibschutz',
            'status' => $midasReadOnly ? 'passed' : 'failed',
            'message' => $midasReadOnly
                ? 'storage.midas ist ausschließlich read-only.'
                : 'storage.midas ist nicht eindeutig als read-only konfiguriert.',
        ];

        $writesDisabled = ! (bool) config('invoicing.writes_enabled');
        $checks[] = [
            'label' => 'Produktiver Schreibschutz',
            'status' => $writesDisabled ? 'passed' : 'failed',
            'message' => $writesDisabled
                ? 'INVOICE_WRITES_ENABLED ist weiterhin deaktiviert.'
                : 'INVOICE_WRITES_ENABLED ist bereits aktiv. Endtest ist damit nicht mehr rein lesend abgesichert.',
        ];

        $rendererReady = $this->pdfRenderer->ready();
        $checks[] = [
            'label' => 'PDF-Renderer',
            'status' => $rendererReady ? 'passed' : 'failed',
            'message' => $rendererReady
                ? 'Word-Vorlage und LibreOffice-Konvertierung sind verfügbar.'
                : 'Word-Vorlage oder LibreOffice-Konvertierung ist nicht einsatzbereit.',
        ];

        return $checks;
    }

    private function caseResult(
        string $key,
        string $label,
        string $status,
        ?int $orderNumber,
        ?int $customerNumber,
        string $message,
    ): array {
        return compact('key', 'label', 'status', 'orderNumber', 'customerNumber', 'message');
    }
}
