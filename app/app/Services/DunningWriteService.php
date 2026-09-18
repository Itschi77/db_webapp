<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DunningWriteService
{
    public function __construct(private DunningService $dunning) {}

    public function applyReminder(int $invoiceId, int $stage, float $additionalFee, string $actor): void
    {
        $this->guard();
        if (!in_array($stage, [1,2,3], true)) {
            throw new RuntimeException('Ungültige Mahnstufe.');
        }
        if ($additionalFee < 0 || $additionalFee > 1000) {
            throw new RuntimeException('Ungültige zusätzliche Mahngebühr.');
        }

        $db = DB::connection('sqlsrv_accountings');
        $db->transaction(function () use ($db, $invoiceId, $stage, $additionalFee) {
            $row = $db->table('tblRechnung')->where('intID', $invoiceId)->lockForUpdate()->first();
            if (!$row) {
                throw new RuntimeException('Rechnung nicht gefunden.');
            }
            if ((int) ($row->boolBezahlt ?? 0) !== 0) {
                throw new RuntimeException('Bezahlte Rechnungen können nicht gemahnt werden.');
            }
            if ((bool) ($row->bolRechnungStrittig ?? false)) {
                throw new RuntimeException('Strittige Rechnungen können nicht gemahnt werden.');
            }
            $current = (int) ($row->intMahnstufe ?? 0);
            if ($stage !== $current + 1 || $stage > 3) {
                throw new RuntimeException('Mahnstufen müssen in der Reihenfolge 1 bis 3 gebucht werden.');
            }
            $checked = $this->dunning->decorate($row);
            if ((int) ($checked->nextStage ?? 0) !== $stage) {
                throw new RuntimeException('Diese Mahnstufe ist nach den hinterlegten Fristen noch nicht fällig.');
            }

            $dateField = 'datMahnung'.$stage.'Am';
            $db->table('tblRechnung')->where('intID', $invoiceId)->update([
                'intMahnstufe' => $stage,
                $dateField => CarbonImmutable::now('Europe/Berlin')->startOfDay(),
                'fMahngebührenAufgelaufen' => round((float) ($row->fMahngebührenAufgelaufen ?? 0) + $additionalFee, 2),
            ]);
        });

        Log::notice('Dunning reminder applied', compact('invoiceId','stage','additionalFee','actor'));
    }

    public function markDisputed(int $invoiceId, string $reason, CarbonImmutable $followup, string $actor): void
    {
        $this->guard();
        DB::connection('sqlsrv_accountings')->table('tblRechnung')->where('intID', $invoiceId)->update([
            'bolRechnungStrittig' => 1,
            'strRechnungStrittigGrund' => $reason,
            'datRechnungStrittigWiedervorlage' => $followup->startOfDay(),
        ]);
        Log::notice('Invoice marked disputed', compact('invoiceId','reason','followup','actor'));
    }

    public function clearDisputed(int $invoiceId, string $actor): void
    {
        $this->guard();
        DB::connection('sqlsrv_accountings')->table('tblRechnung')->where('intID', $invoiceId)->update([
            'bolRechnungStrittig' => 0,
            'strRechnungStrittigGrund' => null,
            'datRechnungStrittigWiedervorlage' => null,
        ]);
        Log::notice('Invoice dispute cleared', compact('invoiceId','actor'));
    }

    public function lockCustomer(int $customerId, string $actor): int
    {
        $this->guard();
        $db = DB::connection('sqlsrv_accountings');
        $ids = $db->table('tblRechnung as r')
            ->join('tblAuftrag as a','a.intAufNr','=','r.intAufNr')
            ->where('a.intKID',$customerId)->whereRaw('ISNULL(r.boolBezahlt,0)=0')
            ->pluck('r.intID');
        $count = $db->table('tblRechnung')->whereIn('intID',$ids)->update([
            'datKundeGesperrtAm' => CarbonImmutable::now('Europe/Berlin')->startOfDay(),
            'datKundensperrungAufgehobenAm' => null,
        ]);
        Log::notice('Customer dunning lock set', compact('customerId','count','actor'));
        return $count;
    }

    public function unlockCustomer(int $customerId, string $actor): int
    {
        $this->guard();
        $db = DB::connection('sqlsrv_accountings');
        $ids = $db->table('tblRechnung as r')
            ->join('tblAuftrag as a','a.intAufNr','=','r.intAufNr')
            ->where('a.intKID',$customerId)->whereRaw('ISNULL(r.boolBezahlt,0)=0')
            ->pluck('r.intID');
        $count = $db->table('tblRechnung')->whereIn('intID',$ids)->update([
            'datKundensperrungAufgehobenAm' => CarbonImmutable::now('Europe/Berlin')->startOfDay(),
        ]);
        Log::notice('Customer dunning lock cleared', compact('customerId','count','actor'));
        return $count;
    }

    private function guard(): void
    {
        if (!config('dunning.writes_enabled')) {
            throw new RuntimeException('Produktive Mahnwesen-Schreibvorgänge sind serverseitig deaktiviert.');
        }
    }
}
