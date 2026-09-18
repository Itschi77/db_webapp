<?php

namespace Tests\Feature;

use App\Services\InvoiceDocumentEditService;
use Illuminate\Support\Facades\Session;
use RuntimeException;
use Tests\TestCase;

class InvoiceDocumentEditServiceTest extends TestCase
{
    public function test_confirmed_descriptions_are_applied_without_changing_amounts(): void
    {
        Session::start();
        $service = app(InvoiceDocumentEditService::class);
        $run = $this->sampleRun();

        $draft = $service->save(123, $run, [
            'descriptions' => [0 => 'Geänderte Beschreibung'],
            'invoice_note' => 'Zusatztext',
        ], 'tester');

        $edited = $service->apply($run, $draft);

        $this->assertSame('Geänderte Beschreibung', $edited['documentRows'][0]->description);
        $this->assertSame(100.0, $edited['net']);
        $this->assertSame(19.0, $edited['tax']);
        $this->assertSame(119.0, $edited['gross']);
        $this->assertSame('Zusatztext', $edited['invoiceNote']);
    }

    public function test_changed_amount_invalidates_confirmed_draft(): void
    {
        Session::start();
        $service = app(InvoiceDocumentEditService::class);
        $run = $this->sampleRun();
        $draft = $service->save(123, $run, ['descriptions' => []], 'tester');

        $run['gross'] = 120.0;

        $this->expectException(RuntimeException::class);
        $service->apply($run, $draft);
    }

    private function sampleRun(): array
    {
        return [
            'documentRows' => collect([
                (object) [
                    'positionId' => 1,
                    'kind' => 'Leistung',
                    'description' => 'Original',
                    'net' => 100.0,
                    'taxRate' => 19.0,
                    'tax' => 19.0,
                ],
            ]),
            'net' => 100.0,
            'tax' => 19.0,
            'gross' => 119.0,
        ];
    }
}
