<?php

namespace Tests\Feature;

use App\Services\InvoiceWriteService;
use Carbon\CarbonImmutable;
use RuntimeException;
use Tests\TestCase;

class InvoiceWriteGuardTest extends TestCase
{
    public function test_invoice_writes_are_blocked_by_default(): void
    {
        config(['invoicing.writes_enabled' => false]);
        $date = CarbonImmutable::parse('2026-09-18');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('serverseitig deaktiviert');

        app(InvoiceWriteService::class)->commit(
            1,
            $date,
            $date,
            $date,
            false,
            'test-user',
        );
    }
}
