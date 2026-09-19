<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use horstoeko\zugferd\ZugferdDocumentPdfReader;
use horstoeko\zugferd\ZugferdDocumentValidator;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\ZugferdXsdValidator;
use RuntimeException;

class InvoiceEInvoiceService
{
    public function __construct(
        private InvoiceXRechnungValidatorService $xrechnungValidator,
        private InvoicePdfAValidatorService $pdfAValidator,
    ) {}

    public const FORMAT_XRECHNUNG = 'xrechnung';
    public const FORMAT_ZUGFERD = 'zugferd';

    public function readiness(array $document, string $format): array
    {
        $run = $document['testRun'];
        $address = $run['address'];
        $issues = collect();

        if (!in_array($format, [self::FORMAT_XRECHNUNG, self::FORMAT_ZUGFERD], true)) {
            $issues->push('Unbekanntes E-Rechnungsformat.');
        }
        if (($run['status'] ?? null) !== 'ready') {
            $issues->push('Der Auftrag ist im Rechnungstestlauf nicht freigegeben.');
        }
        $rows = $run['documentRows'] ?? collect();
        $payableRows = $rows->filter(fn ($row) => abs((float) $row->net) > 0.00001);
        if ($payableRows->isEmpty()) {
            $issues->push('Eine E-Rechnung benötigt mindestens eine abrechenbare Position.');
        }
        foreach ($payableRows as $row) {
            if ((float) $row->net < 0) {
                $issues->push('Negative Dokumentzeilen sind für die E-Rechnung noch nicht als EN16931-Rabatt modelliert: Position #'.$row->positionId.'.');
            }
            if ((float) $row->taxRate <= 0) {
                $issues->push('Position #'.$row->positionId.' hat keinen positiven USt.-Satz; Steuerbefreiungsgrund ist nicht eindeutig hinterlegt.');
            }
        }
        if (!$address) {
            $issues->push('Rechnungsanschrift fehlt.');
        } else {
            if (trim((string) ($address->strEmail ?? '')) === '') {
                $issues->push('Elektronische Empfängeradresse fehlt.');
            }
            if ($format === self::FORMAT_XRECHNUNG && trim((string) ($address->strLeitwegId ?? '')) === '') {
                $issues->push('Leitweg-ID/Käuferreferenz fehlt für XRechnung.');
            }
            if (($run['fulfillment']['bankDebit'] ?? false) && trim((string) ($address->strIBAN ?? '')) === '') {
                $issues->push('IBAN des Zahlers fehlt für den Lastschrifteinzug.');
            }
            if ($format === self::FORMAT_XRECHNUNG && ($run['fulfillment']['bankDebit'] ?? false)) {
                $issues->push('XRechnung mit Lastschrift ist noch nicht freigegeben: Mandatsreferenz und Gläubiger-ID sind im aktuellen Datenbestand nicht eindeutig hinterlegt.');
            }
        }

        return [
            'ready' => $issues->isEmpty(),
            'issues' => $issues,
            'format' => $format,
            'version' => $format === self::FORMAT_XRECHNUNG
                ? config('invoicing.einvoice.xrechnung_version')
                : config('invoicing.einvoice.zugferd_version'),
        ];
    }

    public function buildXml(
        array $document,
        int $invoiceNumber,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $format,
    ): array {
        $readiness = $this->readiness($document, $format);
        if (!$readiness['ready']) {
            throw new RuntimeException($readiness['issues']->implode(' '));
        }

        $profile = $format === self::FORMAT_XRECHNUNG
            ? ZugferdProfiles::PROFILE_XRECHNUNG_3
            : ZugferdProfiles::PROFILE_EN16931;

        $builder = ZugferdDocumentBuilder::createNew($profile);
        $this->fillDocument($builder, $document, $invoiceNumber, $from, $to, $format);
        $xml = $builder->getContent();
        $validation = $this->validateBuilder($builder);
        if ($format === self::FORMAT_XRECHNUNG) {
            $validation['kosit'] = $this->xrechnungValidator->validate($xml);
        }

        return [
            'xml' => $xml,
            'builder' => $builder,
            'format' => $format,
            'version' => $readiness['version'],
            'validation' => $validation,
        ];
    }

    public function mergeZugferdPdf(string $pdf, string $xml): string
    {
        $merger = new ZugferdDocumentPdfMerger($xml, $pdf);
        $merger->setPdfAConformanceLevelToUnicode();
        $merger->setAttachmentRelationshipTypeToAlternative();
        $merger->generateDocument();
        $merged = $merger->downloadString();
        $embeddedXml = ZugferdDocumentPdfReader::getXmlFromContent($merged);
        if (!hash_equals(hash('sha256', $xml), hash('sha256', $embeddedXml))) {
            throw new RuntimeException('Das eingebettete ZUGFeRD-XML stimmt nicht mit den validierten Rechnungsdaten überein.');
        }
        return $merged;
    }

    public function validateZugferdPdf(string $pdf): array
    {
        return $this->pdfAValidator->validate($pdf);
    }

    private function fillDocument(
        ZugferdDocumentBuilder $builder,
        array $document,
        int $invoiceNumber,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $format,
    ): void {
        $run = $document['testRun'];
        $order = $document['order'];
        $address = $run['address'];
        $seller = config('invoicing.einvoice');

        $builder->setDocumentInformation(
            (string) $invoiceNumber,
            '380',
            $run['invoiceDate'],
            'EUR',
            'Rechnung'
        );
        $builder->setDocumentBillingPeriod($from, $to, 'Leistungszeitraum');

        if (trim((string) ($run['invoiceNote'] ?? '')) !== '') {
            $builder->addDocumentNote((string) $run['invoiceNote']);
        }
        if (trim((string) ($run['order']?->strAbrechnungshinweis ?? '')) !== '') {
            $builder->addDocumentNote((string) $run['order']->strAbrechnungshinweis);
        }

        $sellerId = trim((string) ($address->strLieferantenId ?? ''));
        $builder->setDocumentSeller($seller['seller_name'], $sellerId !== '' ? $sellerId : null);
        $builder->setDocumentSellerAddress(
            $seller['seller_street'],
            null,
            null,
            $seller['seller_postcode'],
            $seller['seller_city'],
            $seller['seller_country']
        );
        $builder->addDocumentSellerVATRegistrationNumber($seller['seller_vat_id']);
        $builder->addDocumentSellerTaxNumber($seller['seller_tax_number']);
        $builder->setDocumentSellerContact(
            $seller['seller_contact'],
            null,
            $seller['seller_phone'],
            null,
            $seller['seller_email']
        );
        $builder->setDocumentSellerCommunication('EM', $seller['seller_email']);

        $buyerId = '00-'.str_pad((string) $order->intKID, 6, '0', STR_PAD_LEFT);
        $builder->setDocumentBuyer((string) $address->strName, $buyerId);
        $builder->setDocumentBuyerAddress(
            (string) $address->strStrasse,
            null,
            null,
            (string) $address->strPLZ,
            (string) $address->strOrt,
            'DE'
        );
        if (trim((string) ($address->strUStIdNr ?? '')) !== '') {
            $builder->addDocumentBuyerVATRegistrationNumber((string) $address->strUStIdNr);
        }
        if (trim((string) ($address->strZuHaenden ?? '')) !== '') {
            $builder->setDocumentBuyerContact((string) $address->strZuHaenden, null, null, null, (string) $address->strEmail);
        }
        $builder->setDocumentBuyerCommunication('EM', (string) $address->strEmail);

        $buyerReference = $format === self::FORMAT_XRECHNUNG
            ? trim((string) $address->strLeitwegId)
            : trim((string) ($address->strKundenreferenz ?? ''));
        if ($buyerReference !== '') {
            $builder->setDocumentBuyerReference($buyerReference);
        }
        $builder->setDocumentBuyerOrderReferencedDocument((string) $order->intAufNr);

        $rows = $run['documentRows']
            ->filter(fn ($row) => abs((float) $row->net) > 0.00001)
            ->values();
        foreach ($rows as $index => $row) {
            $net = round((float) $row->net, 2);
            $taxRate = round((float) $row->taxRate, 2);
            $builder->addNewPosition((string) ($index + 1));
            $builder->setDocumentPositionProductDetails(
                mb_substr(trim((string) $row->description), 0, 100),
                (string) $row->description
            );
            $builder->setDocumentPositionNetPrice($net, 1, 'C62');
            $builder->setDocumentPositionQuantity(1, 'C62');
            $builder->addDocumentPositionTax('S', 'VAT', $taxRate);
            $builder->setDocumentPositionLineSummation($net);
        }

        $taxBases = $rows->groupBy(fn ($row) => number_format((float) $row->taxRate, 2, '.', ''))
            ->map(fn ($group) => round($group->sum(fn ($row) => (float) $row->net), 2));

        foreach ($taxBases as $rate => $basis) {
            $rateFloat = (float) $rate;
            $taxAmount = round($basis * $rateFloat / 100, 2);
            $builder->addDocumentTaxSimple('S', 'VAT', $basis, $taxAmount, $rateFloat);
        }

        $net = round((float) $run['net'], 2);
        $tax = round((float) $run['tax'], 2);
        $gross = round((float) $run['gross'], 2);
        $builder->setDocumentSummation($gross, $gross, $net, 0, 0, $net, $tax, 0, 0);

        if ($run['fulfillment']['bankDebit'] ?? false) {
            $builder->addDocumentPaymentMeanToDirectDebit((string) $address->strIBAN);
        } else {
            $builder->addDocumentPaymentMeanToCreditTransfer(
                $seller['seller_iban'],
                $seller['seller_name'],
                null,
                $seller['seller_bic'],
                (string) $invoiceNumber
            );
        }

        $paymentText = trim((string) ($run['paymentText'] ?? ''));
        if ($format === self::FORMAT_XRECHNUNG) {
            $builder->addDocumentPaymentTermXRechnung(
                $paymentText !== '' ? $paymentText : 'Zahlung gemäß Vereinbarung',
                $run['skonto']->pluck('days')->all(),
                $run['skonto']->pluck('percent')->all(),
                $run['skonto']->map(fn ($s) => round((float) $s['gross'], 2))->all(),
                $run['dueDate']
            );
        } else {
            $builder->addDocumentPaymentTerm(
                $paymentText !== '' ? $paymentText : null,
                $run['dueDate']
            );
        }
    }

    private function validateBuilder(ZugferdDocumentBuilder $builder): array
    {
        $xsd = new ZugferdXsdValidator($builder);
        $xsd->validate();

        $semanticErrors = [];
        try {
            $semantic = new ZugferdDocumentValidator($builder);
            $violations = $semantic->validateDocument();
            foreach ($violations as $violation) {
                $semanticErrors[] = $violation->getPropertyPath().': '.$violation->getMessage();
            }
        } catch (\Throwable $e) {
            $semanticErrors[] = 'Interne EN16931-Prüfung konnte nicht ausgeführt werden: '.$e->getMessage();
        }

        return [
            'xsd_valid' => $xsd->hasNoValidationErrors(),
            'xsd_errors' => $xsd->validationErrors(),
            'semantic_valid' => $semanticErrors === [],
            'semantic_errors' => $semanticErrors,
        ];
    }
}
