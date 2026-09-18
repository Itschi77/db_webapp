<?php

namespace Tests\Feature;

use App\Services\InvoiceEInvoiceService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class InvoiceEInvoiceServiceTest extends TestCase
{
    public function test_zugferd_en16931_xml_is_valid(): void
    {
        $service = app(InvoiceEInvoiceService::class);
        $document = $this->document();

        $result = $service->buildXml(
            $document,
            2026001999,
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-31'),
            InvoiceEInvoiceService::FORMAT_ZUGFERD,
        );

        $this->assertTrue($result['validation']['xsd_valid']);
        $this->assertTrue($result['validation']['semantic_valid']);
        $this->assertStringContainsString('2026001999', $result['xml']);
        $this->assertStringContainsString('tops.net GmbH', $result['xml']);
        $this->assertStringContainsString('Testkunde GmbH', $result['xml']);
    }

    public function test_xrechnung_requires_leitweg_id(): void
    {
        $service = app(InvoiceEInvoiceService::class);
        $document = $this->document();
        $document['testRun']['address']->strLeitwegId = '';

        $readiness = $service->readiness(
            $document,
            InvoiceEInvoiceService::FORMAT_XRECHNUNG,
        );

        $this->assertFalse($readiness['ready']);
        $this->assertTrue(
            $readiness['issues']->contains(fn ($issue) => str_contains($issue, 'Leitweg-ID'))
        );
    }

    public function test_negative_invoice_row_is_blocked_for_einvoice(): void
    {
        $service = app(InvoiceEInvoiceService::class);
        $document = $this->document();
        $document['testRun']['documentRows']->push((object) [
            'positionId' => 2,
            'kind' => 'Rabatt',
            'description' => 'Unmodellierter Rabatt',
            'net' => -10.0,
            'taxRate' => 19.0,
            'tax' => -1.9,
        ]);

        $readiness = $service->readiness(
            $document,
            InvoiceEInvoiceService::FORMAT_ZUGFERD,
        );

        $this->assertFalse($readiness['ready']);
        $this->assertTrue(
            $readiness['issues']->contains(fn ($issue) => str_contains($issue, 'Negative Dokumentzeilen'))
        );
    }

    private function document(): array
    {
        $order = (object) [
            'intKID' => 6384,
            'intAufNr' => 5772,
            'strAbrechnungshinweis' => null,
        ];

        return [
            'order' => $order,
            'testRun' => [
                'status' => 'ready',
                'order' => $order,
                'address' => (object) [
                    'strName' => 'Testkunde GmbH',
                    'strStrasse' => 'Teststraße 1',
                    'strPLZ' => '53111',
                    'strOrt' => 'Bonn',
                    'strEmail' => 'rechnung@example.test',
                    'strUStIdNr' => 'DE123456789',
                    'strZuHaenden' => 'Herr Test',
                    'strLeitwegId' => '992-01497-46',
                    'strLieferantenId' => '',
                    'strKundenreferenz' => '',
                    'strIBAN' => 'DE02120300000000202051',
                ],
                'invoiceDate' => CarbonImmutable::parse('2026-08-31'),
                'fulfillment' => ['bankDebit' => false],
                'documentRows' => collect([
                    (object) [
                        'positionId' => 1,
                        'kind' => 'Leistung',
                        'description' => 'Testleistung E-Rechnung',
                        'net' => 100.0,
                        'taxRate' => 19.0,
                        'tax' => 19.0,
                    ],
                ]),
                'net' => 100.0,
                'tax' => 19.0,
                'gross' => 119.0,
                'invoiceNote' => '',
                'paymentText' => 'Zahlbar bis 14.09.2026.',
                'dueDate' => CarbonImmutable::parse('2026-09-14'),
                'skonto' => collect(),
            ],
        ];
    }
}
