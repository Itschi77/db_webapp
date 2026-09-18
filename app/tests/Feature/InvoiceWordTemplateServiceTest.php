<?php

namespace Tests\Feature;

use App\Services\InvoiceWordTemplateService;
use Carbon\CarbonImmutable;
use Tests\TestCase;
use ZipArchive;

class InvoiceWordTemplateServiceTest extends TestCase
{
    public function test_template_fills_legacy_fields_and_dynamic_rows(): void
    {
        $document = $this->document();
        $path = app(InvoiceWordTemplateService::class)->build(
            $document,
            2026001998,
            CarbonImmutable::parse('2026-08-01'),
            CarbonImmutable::parse('2026-08-31'),
            true,
        );

        try {
            $zip = new ZipArchive();
            $this->assertTrue($zip->open($path) === true);
            $xml = (string) $zip->getFromName('word/document.xml');
            $zip->close();

            $this->assertStringContainsString('00-006384', $xml);
            $this->assertStringContainsString('20231204-005772', $xml);
            $this->assertStringContainsString('VORSCHAU – Rechnung', $xml);
            $this->assertStringContainsString('Testposition A', $xml);
            $this->assertStringContainsString('zweite Zeile', $xml);
            $this->assertStringContainsString('(A-4711)', $xml);
            $this->assertStringContainsString('Testposition B', $xml);
            $this->assertStringContainsString('<w:br', $xml);
            $this->assertStringContainsString('<w:tblHeader', $xml);
            $this->assertGreaterThanOrEqual(5, substr_count($xml, '<w:cantSplit'));
            $this->assertStringContainsString('100,00 €', $xml);
            $this->assertStringContainsString('50,00 €', $xml);
            $this->assertStringContainsString('zzgl. 19% MwSt.', $xml);
            $this->assertStringContainsString('178,50 €', $xml);
            $this->assertStringNotContainsString('__LINE_', $xml);
            $this->assertStringNotContainsString('__TAX_', $xml);
        } finally {
            @unlink($path);
        }
    }

    private function document(): array
    {
        $address = (object) [
            'strName' => 'Testkunde GmbH',
            'strZuHaenden' => 'Herr Test',
            'strStrasse' => 'Teststraße 1',
            'strPLZ' => '53111',
            'strOrt' => 'Bonn',
            'strEmail' => 'rechnung@example.test',
            'strUStIdNr' => 'DE123456789',
        ];

        return [
            'order' => (object) [
                'intKID' => 6384,
                'intAufNr' => 5772,
                'datErfassungsdatum' => '2023-12-04 00:00:00.000',
            ],
            'testRun' => [
                'address' => $address,
                'customer' => (object) ['strDatevKundenKonto' => '26384'],
                'invoiceDate' => CarbonImmutable::parse('2026-08-31'),
                'fulfillment' => ['documentType' => 'Rechnung', 'bankDebit' => false],
                'paymentText' => 'Zahlbar bis 14.09.2026.',
                'payment' => null,
                'skonto' => collect(),
                'documentRows' => collect([
                    (object) [
                        'positionId' => 1, 'kind' => 'Leistung',
                        'description' => "Testposition A\nzweite Zeile", 'quantityLabel' => '1',
                        'articleNumber' => 'A-4711',
                        'net' => 100.0, 'taxRate' => 19.0, 'tax' => 19.0,
                    ],
                    (object) [
                        'positionId' => 2, 'kind' => 'Leistung',
                        'description' => 'Testposition B', 'quantityLabel' => '2',
                        'net' => 50.0, 'taxRate' => 19.0, 'tax' => 9.5,
                    ],
                ]),
                'net' => 150.0,
                'tax' => 28.5,
                'gross' => 178.5,
                'taxByRate' => collect([19 => 28.5]),
                'invoiceNote' => 'Testhinweis',
            ],
        ];
    }
}
