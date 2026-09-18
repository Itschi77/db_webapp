<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceWriteService
{
    public function __construct(
        private InvoicePreviewCalculationService $calculator,
        private InvoiceOrderTestRunService $testRun,
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

        return [
            'enabled' => (bool) config('invoicing.writes_enabled'),
            'permissions' => $permissions,
            'permissionsComplete' => collect($permissions)->flatten()->every(fn ($allowed) => $allowed),
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

        $db = DB::connection('sqlsrv_accountings');
        $result = $db->transaction(function () use ($db, $orderNumber, $from, $to, $invoiceDate, $includeAccountings) {
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
            if ($testRun['status'] !== 'ready' || $testRun['invoiceRows']->isEmpty()) {
                throw new RuntimeException('Die erneute Prüfung innerhalb der Transaktion ist nicht fakturierbar.');
            }

            $invoiceNumber = $this->reserveNumber($db, $invoiceDate);
            $invoiceId = $db->table('tblRechnung')->insertGetId(
                $this->invoiceValues($testRun, $invoiceNumber),
                'intID',
            );
            $this->writeCalculatedRows($db, $testRun['invoiceRows'], $testRun, $invoiceId);
            $this->writeAccountingAccounts($db, $testRun['invoiceRows']);

            return ['invoiceId' => (int) $invoiceId, 'invoiceNumber' => $invoiceNumber, 'testRun' => $testRun];
        }, 1);

        Log::notice('Invoice committed', [
            'invoice_id' => $result['invoiceId'],
            'invoice_number' => $result['invoiceNumber'],
            'order_number' => $orderNumber,
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

    private function invoiceValues(array $run, int $number): array
    {
        $skonto = $run['skonto']->keyBy('level');
        $values = [
            'intRechNr' => $number,
            'intAufNr' => (int) $run['order']->intAufNr,
            'datRechnungsDatum' => $run['invoiceDate']->toDateTimeString(),
            'datFaelligkeitsDatum' => $run['dueDate']->toDateTimeString(),
            'boolBezahlt' => 0,
            'fBezahlterBetrag' => 0,
            'fBetrag' => round($run['net'], 2),
            'fSteuer' => round($run['tax'], 2),
            'fRechnungsbetrag' => round($run['gross'], 2),
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
            $values['datSkonto'.$level.'Bis'] = $entry ? $entry['date']->toDateTimeString() : null;
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
                'BerechnetZum' => $row->calculationDate->toDateTimeString(),
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
}
