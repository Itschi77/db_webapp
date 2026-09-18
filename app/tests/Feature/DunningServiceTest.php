<?php

namespace Tests\Feature;

use App\Services\DunningService;
use App\Services\DunningWriteService;
use Carbon\CarbonImmutable;
use RuntimeException;
use Tests\TestCase;

class DunningServiceTest extends TestCase
{
    public function test_overdue_invoice_reaches_first_reminder_after_configured_wait(): void
    {
        config(['dunning.first_reminder_after_due_days' => 7, 'dunning.fees.1' => 0.0]);
        $row = $this->row([
            'datFaelligkeitsDatum' => '2026-09-01 00:00:00',
            'intMahnstufe' => 0,
        ]);

        $result = app(DunningService::class)->decorate(
            $row,
            CarbonImmutable::parse('2026-09-18', 'Europe/Berlin')
        );

        $this->assertTrue($result->isOverdue);
        $this->assertSame(1, $result->nextStage);
        $this->assertSame(17, $result->daysOverdue);
        $this->assertSame(100.0, $result->openPrincipal);
    }

    public function test_disputed_invoice_is_not_automatically_escalated(): void
    {
        $row = $this->row([
            'datFaelligkeitsDatum' => '2026-08-01 00:00:00',
            'bolRechnungStrittig' => 1,
            'datRechnungStrittigWiedervorlage' => '2026-09-18 00:00:00',
        ]);

        $result = app(DunningService::class)->decorate(
            $row,
            CarbonImmutable::parse('2026-09-18', 'Europe/Berlin')
        );

        $this->assertNull($result->nextStage);
        $this->assertStringContainsString('strittig', $result->nextStageReason);
    }

    public function test_installment_invoice_is_not_automatically_escalated(): void
    {
        $row = $this->row([
            'datFaelligkeitsDatum' => '2026-08-01 00:00:00',
            'bolRatenzahlung' => 1,
        ]);

        $result = app(DunningService::class)->decorate(
            $row,
            CarbonImmutable::parse('2026-09-18', 'Europe/Berlin')
        );

        $this->assertNull($result->nextStage);
        $this->assertStringContainsString('Ratenzahlung', $result->nextStageReason);
    }

    public function test_dunning_writes_are_blocked_by_default(): void
    {
        config(['dunning.writes_enabled' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('serverseitig deaktiviert');

        app(DunningWriteService::class)->applyReminder(1, 1, 0, 'test');
    }

    private function row(array $overrides = []): object
    {
        return (object) array_merge([
            'fRechnungsbetrag' => 100.0,
            'fBezahlterBetrag' => 0.0,
            'fGutschrift' => 0.0,
            'fVerlustabschreibungsBetrag' => 0.0,
            'fMahngebührenAufgelaufen' => 0.0,
            'fVerzugszinsenAufgelaufen' => 0.0,
            'datFaelligkeitsDatum' => null,
            'intMahnstufe' => 0,
            'datMahnung1Am' => null,
            'datMahnung2Am' => null,
            'datMahnung3Am' => null,
            'bolRechnungStrittig' => 0,
            'strRechnungStrittigGrund' => null,
            'datRechnungStrittigWiedervorlage' => null,
            'bolRatenzahlung' => 0,
            'datKundeGesperrtAm' => null,
            'datKundensperrungAufgehobenAm' => null,
        ], $overrides);
    }
}
