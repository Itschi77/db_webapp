<?php

namespace Tests\Feature;

use App\Services\InvoiceAddressPlausibilityService;
use Tests\TestCase;

class InvoiceAddressPlausibilityServiceTest extends TestCase
{
    public function test_normal_german_address_is_accepted(): void
    {
        $issues = app(InvoiceAddressPlausibilityService::class)->issues((object) [
            'strPLZ' => '53579',
            'strOrt' => 'Erpel',
        ]);

        $this->assertTrue($issues->isEmpty());
    }

    public function test_obviously_swapped_postal_code_and_city_are_blocked(): void
    {
        $issues = app(InvoiceAddressPlausibilityService::class)->issues((object) [
            'strPLZ' => 'Erpel',
            'strOrt' => '53579',
        ]);

        $this->assertCount(1, $issues);
        $this->assertStringContainsString('vertauscht', $issues->first());
    }

    public function test_alphanumeric_foreign_postal_code_is_not_blocked(): void
    {
        $issues = app(InvoiceAddressPlausibilityService::class)->issues((object) [
            'strPLZ' => 'SW1A 1AA',
            'strOrt' => 'London',
        ]);

        $this->assertTrue($issues->isEmpty());
    }
}
