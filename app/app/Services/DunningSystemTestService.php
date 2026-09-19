<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DunningSystemTestService
{
    public function __construct(
        private DunningService $dunning,
        private DunningLetterService $letters,
        private DunningWriteService $writes,
    ) {}

    public function run(): array
    {
        $started = microtime(true);
        $overview = $this->dunning->overview(['status' => 'overdue']);

        $stage1 = $this->stageCase(1);
        $stage2 = $this->stageCase(2);
        $stage3 = $this->stageCase(3);
        $disputed = $this->disputedCase();
        $followup = $this->followupCase();
        $installment = $this->installmentCase();
        $locked = $this->lockedCase();

        $pdf = $this->pdfCase($stage3['invoiceId'] ?? $stage2['invoiceId'] ?? $stage1['invoiceId'] ?? null,
            $stage3['stage'] ?? $stage2['stage'] ?? $stage1['stage'] ?? null);

        $guard = $this->writeGuardCase($stage1['invoiceId'] ?? null);

        $cases = collect([$stage1, $stage2, $stage3, $disputed, $followup, $installment, $locked, $pdf, $guard]);
        $failed = $cases->where('status', 'failed')->count();
        $missing = $cases->where('status', 'missing')->count();

        return [
            'generatedAt' => CarbonImmutable::now('Europe/Berlin'),
            'summary' => [
                'open' => (int) ($overview['summary']->open_count ?? 0),
                'overdue' => (int) ($overview['summary']->overdue_count ?? 0),
                'disputed' => (int) ($overview['summary']->disputed_count ?? 0),
                'followup' => (int) ($overview['summary']->followup_count ?? 0),
                'overdueAmount' => (float) ($overview['summary']->overdue_amount ?? 0),
            ],
            'cases' => $cases,
            'passedCases' => $cases->where('status', 'passed')->count(),
            'failedCases' => $failed,
            'missingCases' => $missing,
            'complete' => $failed === 0 && $missing === 0,
            'writesEnabled' => (bool) config('dunning.writes_enabled'),
            'durationMs' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private function stageCase(int $stage): array
    {
        $current = $stage - 1;
        $data = $this->dunning->overview(['status' => 'overdue', 'stage' => (string) $current]);
        $row = $data['invoices']->getCollection()
            ->first(fn ($invoice) => (int) ($invoice->nextStage ?? 0) === $stage
                && (float) ($invoice->openPrincipal ?? 0) > 0);

        if (! $row) {
            return $this->caseResult(
                'mahnstufe_'.$stage,
                'Mahnstufe '.$stage,
                'missing',
                'Kein aktuell fälliger realer Fall gefunden.',
            );
        }

        $expectedTotal = round(
            (float) $row->openPrincipal
            + (float) ($row->fMahngebührenAufgelaufen ?? 0)
            + (float) ($row->fVerzugszinsenAufgelaufen ?? 0),
            2
        );
        $mathOk = abs($expectedTotal - (float) $row->totalOpen) < 0.005;

        return $this->caseResult(
            'mahnstufe_'.$stage,
            'Mahnstufe '.$stage,
            $mathOk ? 'passed' : 'failed',
            sprintf(
                'Rechnung %s ist seit %d Tagen überfällig. Offen %.2f €, Gebühren %.2f €, Zinsen %.2f €, Gesamt %.2f €. %s',
                $row->intRechNr,
                (int) $row->daysOverdue,
                (float) $row->openPrincipal,
                (float) ($row->fMahngebührenAufgelaufen ?? 0),
                (float) ($row->fVerzugszinsenAufgelaufen ?? 0),
                (float) $row->totalOpen,
                $row->nextStageReason,
            ),
            (int) $row->intID,
            (int) $row->intRechNr,
            (int) $row->intKID,
            $stage,
        );
    }

    private function disputedCase(): array
    {
        $data = $this->dunning->overview(['status' => 'disputed']);
        $row = $data['invoices']->getCollection()->first();

        if (! $row) {
            return $this->caseResult('strittig', 'Strittige Rechnung', 'missing', 'Kein realer strittiger Fall vorhanden.');
        }

        $ok = (bool) $row->bolRechnungStrittig && $row->nextStage === null;

        return $this->caseResult(
            'strittig',
            'Strittige Rechnung',
            $ok ? 'passed' : 'failed',
            sprintf(
                'Rechnung %s wird nicht automatisch eskaliert. Grund: %s',
                $row->intRechNr,
                trim((string) ($row->strRechnungStrittigGrund ?? '')) ?: 'kein Grundtext hinterlegt',
            ),
            (int) $row->intID,
            (int) $row->intRechNr,
            (int) $row->intKID,
        );
    }

    private function followupCase(): array
    {
        $data = $this->dunning->overview(['status' => 'followup']);
        $row = $data['invoices']->getCollection()->first();

        if (! $row) {
            return $this->caseResult('wiedervorlage', 'Wiedervorlage', 'missing', 'Keine fällige Wiedervorlage vorhanden.');
        }

        $followup = CarbonImmutable::parse($row->datRechnungStrittigWiedervorlage)->startOfDay();
        $ok = $followup->lte(CarbonImmutable::now('Europe/Berlin')->startOfDay())
            && (bool) $row->bolRechnungStrittig;

        return $this->caseResult(
            'wiedervorlage',
            'Wiedervorlage',
            $ok ? 'passed' : 'failed',
            'Rechnung '.$row->intRechNr.' steht seit '.$followup->format('d.m.Y').' zur Wiedervorlage an.',
            (int) $row->intID,
            (int) $row->intRechNr,
            (int) $row->intKID,
        );
    }

    private function installmentCase(): array
    {
        $row = DB::connection('sqlsrv_accountings')->table('tblRechnung as r')
            ->join('tblAuftrag as a', 'a.intAufNr', '=', 'r.intAufNr')
            ->whereRaw('ISNULL(r.boolBezahlt,0)=0')
            ->whereRaw('ISNULL(r.bolRatenzahlung,0)=1')
            ->select('r.*', 'a.intKID')
            ->orderByDesc('r.intRechNr')
            ->first();

        if (! $row) {
            return $this->caseResult('ratenzahlung', 'Ratenzahlung', 'missing', 'Keine offene Ratenzahlungsrechnung vorhanden.');
        }

        $row = $this->dunning->decorate($row);
        $ok = $row->nextStage === null
            && str_contains((string) $row->nextStageReason, 'Ratenzahlung');

        return $this->caseResult(
            'ratenzahlung',
            'Ratenzahlung',
            $ok ? 'passed' : 'failed',
            'Rechnung '.$row->intRechNr.' wird wegen hinterlegter Ratenzahlung nicht automatisch eskaliert.',
            (int) $row->intID,
            (int) $row->intRechNr,
            (int) $row->intKID,
        );
    }

    private function lockedCase(): array
    {
        $row = DB::connection('sqlsrv_accountings')->table('tblRechnung as r')
            ->join('tblAuftrag as a', 'a.intAufNr', '=', 'r.intAufNr')
            ->whereRaw('ISNULL(r.boolBezahlt,0)=0')
            ->whereNotNull('r.datKundeGesperrtAm')
            ->where(function ($q) {
                $q->whereNull('r.datKundensperrungAufgehobenAm')
                    ->orWhereColumn('r.datKundeGesperrtAm', '>', 'r.datKundensperrungAufgehobenAm');
            })
            ->select('r.*', 'a.intKID')
            ->orderByDesc('r.datKundeGesperrtAm')
            ->first();

        if (! $row) {
            return $this->caseResult('kundensperre', 'Kundensperre', 'missing', 'Keine aktive reale Kundensperre vorhanden.');
        }

        $row = $this->dunning->decorate($row);

        return $this->caseResult(
            'kundensperre',
            'Kundensperre',
            $row->isLocked ? 'passed' : 'failed',
            'Kunde '.$row->intKID.' ist seit '.CarbonImmutable::parse($row->datKundeGesperrtAm)->format('d.m.Y').' gesperrt.',
            (int) $row->intID,
            (int) $row->intRechNr,
            (int) $row->intKID,
        );
    }

    private function pdfCase(?int $invoiceId, ?int $stage): array
    {
        if (! $invoiceId || ! $stage) {
            return $this->caseResult('mahnschreiben', 'Mahnschreiben PDF', 'missing', 'Kein geeigneter Mahnfall für die PDF-Prüfung vorhanden.');
        }

        try {
            $fee = (float) config('dunning.fees.'.$stage, 0);
            $pdf = $this->letters->render($invoiceId, $stage, $fee);
            $valid = str_starts_with($pdf, '%PDF-') && strlen($pdf) > 1000;

            return $this->caseResult(
                'mahnschreiben',
                'Mahnschreiben PDF',
                $valid ? 'passed' : 'failed',
                sprintf('Mahnvorschau für Stufe %d erfolgreich gerendert, %.1f KB.', $stage, strlen($pdf) / 1024),
                $invoiceId,
                null,
                null,
                $stage,
            );
        } catch (Throwable $e) {
            return $this->caseResult('mahnschreiben', 'Mahnschreiben PDF', 'failed', 'PDF-Fehler: '.$e->getMessage(), $invoiceId);
        }
    }

    private function writeGuardCase(?int $invoiceId): array
    {
        if ((bool) config('dunning.writes_enabled')) {
            return $this->caseResult(
                'schreibschutz',
                'Produktiver Schreibschutz',
                'failed',
                'DUNNING_WRITES_ENABLED ist aktiv; der read-only Endtest darf so nicht ausgeführt werden.',
                $invoiceId,
            );
        }

        if (! $invoiceId) {
            return $this->caseResult('schreibschutz', 'Produktiver Schreibschutz', 'missing', 'Kein Testfall für den Guard vorhanden.');
        }

        try {
            $this->writes->applyReminder($invoiceId, 1, 0, 'admin-systemtest');
        } catch (RuntimeException $e) {
            $ok = str_contains($e->getMessage(), 'serverseitig deaktiviert');
            return $this->caseResult(
                'schreibschutz',
                'Produktiver Schreibschutz',
                $ok ? 'passed' : 'failed',
                $ok
                    ? 'DUNNING_WRITES_ENABLED=false blockiert den Schreibvorgang vor jedem SQL-Write.'
                    : 'Der Schreibvorgang wurde zwar abgebrochen, aber nicht durch den erwarteten Server-Guard: '.$e->getMessage(),
                $invoiceId,
            );
        }

        return $this->caseResult(
            'schreibschutz',
            'Produktiver Schreibschutz',
            'failed',
            'Der Test-Schreibaufruf wurde unerwartet nicht blockiert.',
            $invoiceId,
        );
    }

    private function caseResult(
        string $key,
        string $label,
        string $status,
        string $message,
        ?int $invoiceId = null,
        ?int $invoiceNumber = null,
        ?int $customerNumber = null,
        ?int $stage = null,
    ): array {
        return compact('key', 'label', 'status', 'message', 'invoiceId', 'invoiceNumber', 'customerNumber', 'stage');
    }
}
