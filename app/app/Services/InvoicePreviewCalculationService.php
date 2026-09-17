<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoicePreviewCalculationService
{
    public function calculate(object $order, Collection $positions, CarbonImmutable $from, CarbonImmutable $to, bool $includeAccountings = false): array
    {
        $db = DB::connection('sqlsrv_accountings');
        $billingIds = $positions->pluck('intAbrechnungsArt')->filter(fn ($id) => $id !== null)->unique()->values();

        $billingTypes = $billingIds->isEmpty()
            ? collect()
            : $db->table('BETAtblAbrechnungsArt')->whereIn('intID', $billingIds)->get(['intID', 'intEinheiten', 'strDimension'])->keyBy('intID');

        $positionIds = $positions->pluck('intID')->values();
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
            $staffelTyp = (int) ($position->intStaffelTyp ?? 0);
            if ($staffelTyp > 0 && !$includeAccountings) {
                $rows->push($this->unsupportedRow($position, 'Accountingposition wird nicht berücksichtigt.', 'accounting_skipped'));
                continue;
            }
            if (!in_array($staffelTyp, [0, 1, 2], true)) {
                $rows->push($this->unsupportedRow($position, 'Diese Staffel-/Accountingart folgt in einem späteren Paritätsschritt.'));
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
                $valuation = $this->valuate($db, $position, $date, $staffelTyp);
                if (!$valuation['ok']) {
                    $rows->push($this->unsupportedRow($position, $valuation['message'], 'accounting_error', (string) $billingType->strDimension, (int) $billingType->intEinheiten));
                    continue;
                }
                $quantity = $valuation['quantity'];
                $quantityLabel = $valuation['quantityLabel'];
                $unitPrice = $valuation['unitPrice'];
                $base = $valuation['base'];
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
                    'quantityLabel' => $quantityLabel,
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

    private function valuate($db, object $position, CarbonImmutable $date, int $staffelTyp): array
    {
        if ($staffelTyp === 0) {
            $quantity = (float) ($position->intMenge ?? 0);
            $unitPrice = round((float) ($position->fEndpreis ?? 0), 2);
            return ['ok' => true, 'quantity' => $quantity, 'quantityLabel' => (string) $quantity, 'unitPrice' => $unitPrice, 'base' => round($quantity * $unitPrice, 2)];
        }

        if ($staffelTyp === 1) {
            $row = $db->selectOne('SELECT TOP 1 SUM(aw.decGesamt) AS Gesamt, sp.intMenge, sp.intvkpreis, sg.strAbrechnungseinheit
                FROM tblAnbindungen an
                RIGHT JOIN tblAuftragPos p ON an.intAuftragsPos = p.intID
                LEFT JOIN tblAnbindungAuswertung aw ON an.intID = aw.intAnbindungID
                INNER JOIN tblStaffelgruppe sg ON p.intStaffelgruppe = sg.intID
                LEFT JOIN tblStaffelpreise sp ON sg.intID = sp.intStaffelgruppeID
                WHERE aw.intJahr = ? AND aw.intMonat = ? AND an.boolAbrechenbar <> 0 AND p.intID = ?
                GROUP BY sp.intMenge, sp.intvkpreis, p.intID, p.intStaffelgruppe, sg.strAbrechnungseinheit
                HAVING sp.intMenge >= SUM(aw.decGesamt)
                ORDER BY sp.intMenge', [$date->year, $date->month, $position->intID]);
            if (!$row) {
                return ['ok' => false, 'message' => 'Staffel-Accounting fehlt oder es existiert keine passende Preisstufe.'];
            }
            $unit = trim((string) ($row->strAbrechnungseinheit ?? ''));
            $quantity = (float) $row->Gesamt;
            $base = round((float) $row->intvkpreis, 2);
            return ['ok' => true, 'quantity' => $quantity, 'quantityLabel' => trim($quantity.' '.$unit), 'unitPrice' => null, 'base' => $base];
        }

        $row = $db->selectOne('SELECT SUM(aw.decGesamt) AS Gesamt, ls.intMengeFrei, ls.floatPreisEinheit, ls.floatBasisPreis, ls.StrAbrechnungseinheit
            FROM tblAnbindungen an
            RIGHT JOIN tblAuftragPos p ON an.intAuftragsPos = p.intID
            LEFT JOIN tblAnbindungAuswertung aw ON an.intID = aw.intAnbindungID
            INNER JOIN tblLinearStaffel ls ON p.intStaffelgruppe = ls.intID
            WHERE aw.intMonat = ? AND aw.intJahr = ? AND an.boolAbrechenbar <> 0 AND p.intID = ?
            GROUP BY ls.intMengeFrei, ls.floatPreisEinheit, p.intID, p.intStaffelgruppe, ls.StrAbrechnungseinheit, ls.floatBasisPreis', [$date->month, $date->year, $position->intID]);
        if (!$row) {
            return ['ok' => false, 'message' => 'Linearstaffel-Accounting fehlt.'];
        }
        $quantity = (float) $row->Gesamt;
        $used = (int) round($quantity, 0, PHP_ROUND_HALF_EVEN);
        $free = (int) $row->intMengeFrei;
        $basePrice = (int) round((float) $row->floatBasisPreis, 0, PHP_ROUND_HALF_EVEN);
        $over = $used - $free;
        $base = $over <= 0 ? round($basePrice, 2) : round($over * (float) $row->floatPreisEinheit, 2) + $basePrice;
        $unit = trim((string) ($row->StrAbrechnungseinheit ?? ''));
        return ['ok' => true, 'quantity' => $quantity, 'quantityLabel' => trim($used.' '.$unit), 'unitPrice' => $over > 0 ? (float) $row->floatPreisEinheit : null, 'base' => $base];
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
            'quantityLabel' => (string) ($position->intMenge ?? ''),
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
