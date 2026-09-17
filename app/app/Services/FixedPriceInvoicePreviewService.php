<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FixedPriceInvoicePreviewService
{
    public function calculate(object $order, Collection $positions, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $db = DB::connection('sqlsrv_accountings');
        $normalPositions = $positions->filter(fn ($p) => (int) ($p->intStaffelTyp ?? 0) === 0);
        $billingIds = $normalPositions->pluck('intAbrechnungsArt')->filter(fn ($id) => $id !== null)->unique()->values();

        $billingTypes = $billingIds->isEmpty()
            ? collect()
            : $db->table('BETAtblAbrechnungsArt')->whereIn('intID', $billingIds)->get(['intID', 'intEinheiten', 'strDimension'])->keyBy('intID');

        $positionIds = $normalPositions->pluck('intID')->values();
        $history = $positionIds->isEmpty()
            ? collect()
            : $db->table('tblAuftragPosBerechnet')
                ->whereIn('intAufPosID', $positionIds)
                ->get(['intAufPosID', 'BerechnetZum', 'fBetrag'])
                ->groupBy('intAufPosID');

        $rows = collect();
        $net = 0.0;
        $taxByRate = [];
        $hasConflict = false;
        $frozen = (bool) ($order->boolEingefroren ?? false);

        foreach ($positions as $position) {
            if ((int) ($position->intStaffelTyp ?? 0) !== 0) {
                $rows->push($this->unsupportedRow($position, 'Staffel/Accounting folgt in einem späteren Paritätsschritt.'));
                continue;
            }

            $billingType = $billingTypes->get($position->intAbrechnungsArt);
            if (!$billingType) {
                $rows->push($this->unsupportedRow($position, 'Abrechnungsart konnte nicht aufgelöst werden.'));
                continue;
            }

            if ($frozen) {
                $rows->push($this->unsupportedRow($position, 'Auftrag ist eingefroren und würde vom Alttool nicht fakturiert.', 'frozen'));
                continue;
            }

            $dates = $this->calculationDates($position, $order, $from, $to, (string) $billingType->strDimension, (int) $billingType->intEinheiten);
            if ($dates->isEmpty()) {
                $rows->push($this->unsupportedRow($position, 'Im gewählten Zeitraum ist kein Berechnungstermin fällig.', 'not_due', (string) $billingType->strDimension, (int) $billingType->intEinheiten));
                continue;
            }

            foreach ($dates as $date) {
                $quantity = (float) ($position->intMenge ?? 0);
                $unitPrice = round((float) ($position->fEndpreis ?? 0), 2);
                $base = round($quantity * $unitPrice, 2);
                $discountPercent = (float) ($position->fRabattInProzent ?? 0);
                $discount = $discountPercent > 0 ? -($base * ($discountPercent / 100)) : 0.0;
                $lineNet = $base + $discount;
                $taxRate = (float) ($position->intMwstsatz ?? 0);
                $lineTax = round($base * ($taxRate / 100), 8) + round($discount * ($taxRate / 100), 8);

                $existing = ($history->get($position->intID) ?? collect())->first(function ($item) use ($date) {
                    return CarbonImmutable::parse($item->BerechnetZum)->toDateString() === $date->toDateString();
                });

                $status = 'billable';
                $message = 'Abrechenbar';
                if ($existing) {
                    if (round((float) $existing->fBetrag, 2) === round($base, 2)) {
                        $status = 'already_calculated';
                        $message = 'Bereits mit gleichem Preis berechnet';
                    } else {
                        $status = 'conflict';
                        $message = 'Preisabweichung zu bereits berechnetem Betrag';
                        $hasConflict = true;
                    }
                }

                if ($status === 'billable') {
                    $net += $lineNet;
                    $taxKey = number_format($taxRate, 4, '.', '');
                    $taxByRate[$taxKey] = ($taxByRate[$taxKey] ?? 0.0) + $lineTax;
                }

                $rows->push((object) [
                    'position' => $position,
                    'status' => $status,
                    'statusLabel' => $message,
                    'calculationDate' => $date,
                    'dimension' => strtoupper(trim((string) $billingType->strDimension)),
                    'interval' => max(1, (int) $billingType->intEinheiten),
                    'quantity' => $quantity,
                    'unitPrice' => $unitPrice,
                    'baseNet' => $base,
                    'discountPercent' => $discountPercent,
                    'discount' => $discount,
                    'net' => $lineNet,
                    'taxRate' => $taxRate,
                    'tax' => $lineTax,
                    'existingAmount' => $existing ? (float) $existing->fBetrag : null,
                ]);
            }
        }

        $tax = round(array_sum($taxByRate), 2);
        $netRounded = round($net, 2);

        return [
            'rows' => $rows,
            'net' => $netRounded,
            'tax' => $tax,
            'gross' => round($netRounded + $tax, 2),
            'taxByRate' => collect($taxByRate)->map(fn ($amount) => round($amount, 2)),
            'hasConflict' => $hasConflict,
            'frozen' => $frozen,
        ];
    }

    private function calculationDates(object $position, object $order, CarbonImmutable $from, CarbonImmutable $to, string $dimension, int $interval): Collection
    {
        if (!$position->datFakturierAb) {
            return collect();
        }

        $dimension = strtoupper(trim($dimension));
        $interval = max(1, $interval);
        $original = CarbonImmutable::parse($position->datFakturierAb)->startOfDay();
        $current = $original;
        $end = $to->startOfDay();

        foreach ([$position->datFakturierBis ?? null, $order->datStorniereAb ?? null] as $limit) {
            if ($limit) {
                $limitDate = CarbonImmutable::parse($limit)->startOfDay();
                if ($limitDate->lt($end)) {
                    $end = $limitDate;
                }
            }
        }

        $dates = collect();
        $guard = 0;
        while ($current->lte($end) && $guard++ < 100000) {
            if ($current->gte($from->startOfDay())) {
                $dates->push($current);
            }
            if ($dimension === 'EINMALIG') {
                break;
            }
            $next = $this->advance($original, $current, $dimension, $interval);
            if (!$next || $next->lte($current)) {
                break;
            }
            $current = $next;
        }

        return $dates;
    }

    private function advance(CarbonImmutable $original, CarbonImmutable $current, string $dimension, int $interval): ?CarbonImmutable
    {
        return match ($dimension) {
            'TAG' => $current->addDays($interval),
            'MONAT' => $this->addMonthsAnchored($original, $current, $interval),
            'JAHR' => $this->addYearsCorrected($current, $interval),
            default => null,
        };
    }

    private function addMonthsAnchored(CarbonImmutable $original, CarbonImmutable $current, int $months): CarbonImmutable
    {
        $result = $current;
        for ($i = 0; $i < $months; $i++) {
            $monthStart = $result->startOfMonth()->addMonth();
            $day = min($original->day, $monthStart->daysInMonth);
            $result = $monthStart->day($day);
        }
        return $result;
    }

    private function addYearsCorrected(CarbonImmutable $current, int $years): CarbonImmutable
    {
        $result = $current;
        for ($i = 0; $i < $years; $i++) {
            $targetYear = $result->year + 1;
            $day = min($result->day, CarbonImmutable::create($targetYear, $result->month, 1)->daysInMonth);
            $result = CarbonImmutable::create($targetYear, $result->month, $day);
        }
        return $result;
    }

    private function unsupportedRow(object $position, string $message, string $status = 'unsupported', ?string $dimension = null, ?int $interval = null): object
    {
        return (object) [
            'position' => $position,
            'status' => $status,
            'statusLabel' => $message,
            'calculationDate' => null,
            'dimension' => $dimension,
            'interval' => $interval,
            'quantity' => (float) ($position->intMenge ?? 0),
            'unitPrice' => round((float) ($position->fEndpreis ?? 0), 2),
            'baseNet' => null,
            'discountPercent' => (float) ($position->fRabattInProzent ?? 0),
            'discount' => null,
            'net' => null,
            'taxRate' => (float) ($position->intMwstsatz ?? 0),
            'tax' => null,
            'existingAmount' => null,
        ];
    }
}
