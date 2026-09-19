<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InvoiceWriteService
{
    public function __construct(
        private InvoicePreviewCalculationService $calculator,
        private InvoiceOrderTestRunService $testRun,
        private InvoiceDocumentEditService $documentEdit,
        private InvoiceTemplatePdfService $templatePdf,
        private InvoiceEInvoiceService $einvoice,
        private InvoiceStorageService $storage,
    ) {}

    public function readiness(): array
    {
        $db = DB::connection('sqlsrv_accountings');
        $required = [
            'tblRechnungsNummern' => ['SELECT', 'UPDATE', 'INSERT'],
            'tblRechnung' => ['SELECT', 'INSERT'],
            'tblAuftragPosBerechnet' => ['SELECT', 'INSERT'],
            'tblAccountingKonto' => ['SELECT', 'INSERT', 'DELETE'],
        ];
        $permissions = [];
        foreach ($required as $table => $actions) {
            foreach ($actions as $action) {
                $value = $db->selectOne(
                    "SELECT HAS_PERMS_BY_NAME(?, 'OBJECT', ?) AS allowed",
                    ['dbo.'.$table, $action],
                );
                $permissions[$table][$action] = (bool) ($value->allowed ?? false);
            }
        }

        $storage = $this->storage->readiness();
        return [
            'enabled' => (bool) config('invoicing.writes_enabled'),
            'permissions' => $permissions,
            'permissionsComplete' => collect($permissions)->flatten()->every(fn ($allowed) => $allowed),
            'storageReady' => $storage['ready'],
            'storagePath' => $storage['path'],
            'documentRendererReady' => $this->templatePdf->ready(),
        ];
    }

    public function commit(
        int $orderNumber,
        CarbonImmutable $from,
        CarbonImmutable $to,
        CarbonImmutable $invoiceDate,
        bool $includeAccountings,
        string $actor,
    ): array {
        if (! config('invoicing.writes_enabled')) {
            throw new RuntimeException('Die produktive Schreibfunktion ist serverseitig deaktiviert.');
        }

        $readiness = $this->readiness();
        if (! $readiness['permissionsComplete']) {
            throw new RuntimeException('Die erforderlichen SQL-Schreibrechte sind noch nicht vollständig eingerichtet.');
        }
        if (! $readiness['storageReady']) {
            throw new RuntimeException('Die schreibbare Janus-Rechnungsablage ist nicht einsatzbereit.');
        }
        if (! $readiness['documentRendererReady']) {
            throw new RuntimeException('Die Word-Vorlage oder die PDF-Konvertierung ist nicht einsatzbereit.');
        }

        $db = DB::connection('sqlsrv_accountings');
        $storedRelativePath = null;
        $storedXmlRelativePath = null;
        try {
            $result = $db->transaction(function () use (
                $db, $orderNumber, $from, $to, $invoiceDate, $includeAccountings, &$storedRelativePath, &$storedXmlRelativePath
            ) {
                $order = $db->selectOne(
                    'SELECT * FROM dbo.tblAuftrag WITH (UPDLOCK, HOLDLOCK) WHERE intAufNr = ?',
                    [$orderNumber],
                );
                if (! $order) {
                    throw new RuntimeException('Der Auftrag ist nicht mehr vorhanden.');
                }

                $positions = $db->table('tblAuftragPos')
                    ->where('intAufNr', $orderNumber)
                    ->orderBy('intID')
                    ->get([
                        'intID', 'strBeschreibung', 'intMenge', 'fEndpreis', 'fRabattInProzent',
                        'intMwstsatz', 'intAbrechnungsArt', 'intStaffelTyp', 'intStaffelgruppe',
                        'datFakturierAb', 'datFakturierBis', 'datVorberechnenBis', 'boolIstAnbindung',
                        'intDatevBezeichnungsID',
                    ]);
                $preview = $this->calculator->calculate($order, $positions, $from, $to, $includeAccountings);
                $testRun = $this->testRun->build($order, $preview, $invoiceDate);
                $testRun = $this->documentEdit->apply(
                    $testRun,
                    $this->documentEdit->get($orderNumber),
                );
                if ($testRun['status'] !== 'ready' || $testRun['invoiceRows']->isEmpty()) {
                    throw new RuntimeException('Die erneute Prüfung innerhalb der Transaktion ist nicht fakturierbar.');
                }

                $invoiceNumber = $this->reserveNumber($db, $invoiceDate);
                $invoiceDocument = [
                    'order' => $order,
                    'calculation' => $preview,
                    'testRun' => $testRun,
                ];
                $pdf = $this->templatePdf->render(
                    $invoiceDocument, $invoiceNumber, $from, $to
                );
                $einvoiceFormat = null;
                $einvoiceXmlPath = null;

                if ((bool) ($testRun['address']->boolXRechnung ?? false)) {
                    $xrechnung = $this->einvoice->buildXml(
                        $invoiceDocument,
                        $invoiceNumber,
                        $from,
                        $to,
                        InvoiceEInvoiceService::FORMAT_XRECHNUNG,
                    );
                    $validation = $xrechnung['validation'];
                    if (!($validation['xsd_valid'] ?? false)
                        || !($validation['semantic_valid'] ?? false)
                        || !($validation['kosit']['valid'] ?? false)) {
                        throw new RuntimeException('Die XRechnung hat die Pflichtvalidierung nicht bestanden.');
                    }
                    $storedXml = $this->storage->storeXml(
                        $invoiceNumber, $invoiceDate, $xrechnung['xml']
                    );
                    $storedXmlRelativePath = $storedXml['relativePath'];
                    $einvoiceXmlPath = $storedXml['databasePath'];
                    $einvoiceFormat = InvoiceEInvoiceService::FORMAT_XRECHNUNG;
                } else {
                    $zugferdReadiness = $this->einvoice->readiness(
                        $invoiceDocument, InvoiceEInvoiceService::FORMAT_ZUGFERD
                    );
                    if ($zugferdReadiness['ready']) {
                        $zugferd = $this->einvoice->buildXml(
                            $invoiceDocument,
                            $invoiceNumber,
                            $from,
                            $to,
                            InvoiceEInvoiceService::FORMAT_ZUGFERD,
                        );
                        if (!($zugferd['validation']['xsd_valid'] ?? false)
                            || !($zugferd['validation']['semantic_valid'] ?? false)) {
                            throw new RuntimeException('Die ZUGFeRD-Daten haben die EN16931-Prüfung nicht bestanden.');
                        }
                        $pdf = $this->einvoice->mergeZugferdPdf($pdf, $zugferd['xml']);
                        $pdfValidation = $this->einvoice->validateZugferdPdf($pdf);
                        if (!($pdfValidation['valid'] ?? false)) {
                            throw new RuntimeException('Das ZUGFeRD-PDF hat die PDF/A-3u-Prüfung nicht bestanden.');
                        }
                        $einvoiceFormat = InvoiceEInvoiceService::FORMAT_ZUGFERD;
                    }
                }

                $stored = $this->storage->storePdf($invoiceNumber, $invoiceDate, $pdf);
                $storedRelativePath = $stored['relativePath'];

                $invoiceId = $db->table('tblRechnung')->insertGetId(
                    $this->invoiceValues($testRun, $invoiceNumber, $stored['databasePath']),
                    'intID',
                );
                $this->writeCalculatedRows($db, $testRun['invoiceRows'], $testRun, $invoiceId);
                $this->writeAccountingAccounts($db, $testRun['invoiceRows']);

                return [
                    'invoiceId' => (int) $invoiceId,
                    'invoiceNumber' => $invoiceNumber,
                    'pdfPath' => $stored['databasePath'],
                    'einvoiceFormat' => $einvoiceFormat,
                    'einvoiceXmlPath' => $einvoiceXmlPath,
                    'testRun' => $testRun,
                ];
            }, 1);
        } catch (Throwable $exception) {
            foreach ([$storedRelativePath, $storedXmlRelativePath] as $rollbackPath) {
                if ($rollbackPath === null) {
                    continue;
                }
                try {
                    $this->storage->delete($rollbackPath);
                } catch (Throwable $cleanupException) {
                    Log::error('Invoice rollback could not remove generated document', [
                        'relative_path' => $rollbackPath,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }
            throw $exception;
        }

        Log::notice('Invoice committed', [
            'invoice_id' => $result['invoiceId'],
            'invoice_number' => $result['invoiceNumber'],
            'order_number' => $orderNumber,
            'pdf_path' => $result['pdfPath'],
            'einvoice_format' => $result['einvoiceFormat'],
            'einvoice_xml_path' => $result['einvoiceXmlPath'],
            'actor' => $actor,
        ]);

        return $result;
    }

    private function reserveNumber($db, CarbonImmutable $invoiceDate): int
    {
        $year = $invoiceDate->year;
        $counter = $db->selectOne(
            'SELECT intID, intLfdNr FROM dbo.tblRechnungsNummern WITH (UPDLOCK, HOLDLOCK) WHERE intRechnungsJahr = ?',
            [$year],
        );
        $storedMaximum = (int) ($db->table('tblRechnung')
            ->whereBetween('intRechNr', [$year * 1_000_000, $year * 1_000_000 + 999_999])
            ->max('intRechNr') ?? 0);
        $storedSequence = $storedMaximum > 0 ? $storedMaximum - ($year * 1_000_000) : 0;

        if ($counter && (int) $counter->intLfdNr !== $storedSequence) {
            throw new RuntimeException('Rechnungszähler und vorhandene Rechnungen weichen voneinander ab.');
        }

        $nextSequence = ($counter ? (int) $counter->intLfdNr : 0) + 1;
        if ($nextSequence > 999_999) {
            throw new RuntimeException('Der Rechnungsnummernkreis ist ausgeschöpft.');
        }
        if ($counter) {
            $db->table('tblRechnungsNummern')->where('intID', $counter->intID)->update(['intLfdNr' => $nextSequence]);
        } else {
            $db->table('tblRechnungsNummern')->insert([
                'intRechnungsJahr' => $year,
                'intLfdNr' => $nextSequence,
                'rowguid' => (string) Str::uuid(),
            ]);
        }

        return $year * 1_000_000 + $nextSequence;
    }

    private function invoiceValues(array $run, int $number, string $pdfPath): array
    {
        if (strlen($pdfPath) > 255) {
            throw new RuntimeException('Der erzeugte Rechnungspfad ist länger als das Datenbankfeld.');
        }
        $skonto = $run['skonto']->keyBy('level');
        $values = [
            'intRechNr' => $number,
            'intAufNr' => (int) $run['order']->intAufNr,
            'datRechnungsDatum' => $this->sqlDate($run['invoiceDate']),
            'datFaelligkeitsDatum' => $this->sqlDate($run['dueDate']),
            'boolBezahlt' => 0,
            'fBezahlterBetrag' => 0,
            'fBetrag' => round($run['net'], 2),
            'fSteuer' => round($run['tax'], 2),
            'fRechnungsbetrag' => round($run['gross'], 2),
            'strPfadZurRechnung' => $pdfPath,
            'strKundenNameAufRechnung' => (string) ($run['address']->strName ?: $run['customer']->strName),
            'strKopieAuftragsbeschreibung' => (string) $run['order']->strBeschreibung,
            'bolRatenzahlung' => 0,
            'bAlleMahngebührenBezahlt' => 0,
            'bolRechnungStrittig' => 0,
            'fVerlustabschreibungsBetrag' => 0,
            'rowguid' => (string) Str::uuid(),
        ];
        foreach ([1, 2, 3] as $level) {
            $entry = $skonto->get($level);
            $values['datSkonto'.$level.'Bis'] = $entry ? $this->sqlDate($entry['date']) : null;
            $values['fBetragMitSkonto'.$level] = $entry ? round($entry['net'], 2) : null;
            $values['fSteuerMitSkonto'.$level] = $entry ? round($entry['tax'], 2) : null;
            $values['fRechnungsbetragMitSkonto'.$level] = $entry ? round($entry['gross'], 2) : null;
        }
        return $values;
    }

    private function writeCalculatedRows($db, Collection $rows, array $run, int $invoiceId): void
    {
        foreach ($rows as $row) {
            $base = $row->precalculationEnded
                ? (float) $row->precalculationAdjustment
                : (float) $row->baseNet + (float) $row->precalculationAdjustment;
            $db->table('tblAuftragPosBerechnet')->insert([
                'intAufPosID' => (int) $row->position->intID,
                'fBetrag' => round($base, 8),
                'BerechnetZum' => $this->sqlDate($row->calculationDate),
                'strKopieBeschreibung' => (string) $row->position->strBeschreibung,
                'intKopieKundenID' => (int) $run['order']->intKID,
                'intDatevID' => $row->position->intDatevBezeichnungsID ?: null,
                'fSteuern' => round($base * ((float) $row->taxRate / 100), 8),
                'intRechnungIntID' => $invoiceId,
                'datBerechnetVon' => null,
                'datBerechnetBis' => null,
                'rowguid' => (string) Str::uuid(),
            ]);
        }
    }

    private function writeAccountingAccounts($db, Collection $rows): void
    {
        foreach ($rows as $row) {
            $details = $row->precalculationDetails;
            if ((int) $row->position->intStaffelTyp !== 1 || ! $row->precalculationActive || $row->precalculationEnded || ! $details) {
                continue;
            }
            $target = $details['displayDate'];
            $key = [
                'intAufPosID' => (int) $row->position->intID,
                'intRechnungsMonat' => $target->month,
                'intRechnungsJahr' => $target->year,
            ];
            $db->table('tblAccountingKonto')->where($key)->delete();
            $db->table('tblAccountingKonto')->insert($key + [
                'intMB' => (int) $details['nextAccountTraffic'],
                'rowguid' => (string) Str::uuid(),
            ]);
        }
    }

    private function sqlDate(CarbonImmutable $date): string
    {
        // YYYYMMDD is independent of SQL Server language and DATEFORMAT settings.
        return $date->format('Ymd H:i:s');
    }
}
