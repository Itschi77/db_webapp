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
            if (!in_array($staffelTyp, [0, 1, 2, 3, 4, 5, 6, 7], true)) {
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

        if ($staffelTyp === 2) {
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

        if ($staffelTyp === 3) {
            return $this->valuateTimeTariff($db, $position, $date);
        }
        if (in_array($staffelTyp, [4, 7], true)) {
            return $this->valuateBandwidth($db, $position, $date, $staffelTyp);
        }
        if ($staffelTyp === 5) {
            return $this->valuateDomain($db, $position, $date);
        }
        if ($staffelTyp === 6) {
            return $this->valuateRangeTier($db, $position, $date);
        }

        return ['ok' => false, 'message' => 'Unbekannte Bewertungsart.'];
    }

    private function valuateRangeTier($db, object $position, CarbonImmutable $date): array
    {
        $row = $db->selectOne('SELECT SUM(aw.decGesamt) AS Gesamt,
                bsp.intMengeAb, bsp.intMengeBis, bsp.fGrundgebuehr, bsp.fBereichsGrundgebuehr,
                bsp.fStueckPreis, bs.strAbrechnungseinheit
            FROM tblAnbindungen an
            RIGHT JOIN tblAuftragPos p ON an.intAuftragsPos = p.intID
            LEFT JOIN tblAnbindungAuswertung aw ON an.intID = aw.intAnbindungID
            INNER JOIN tblBereichsStaffel bs ON p.intStaffelgruppe = bs.intID
            LEFT JOIN tblBereichsStaffelPreise bsp ON bs.intID = bsp.intStaffelGruppenID
            WHERE aw.intJahr = ? AND aw.intMonat = ? AND an.boolAbrechenbar <> 0 AND p.intID = ?
            GROUP BY bsp.intMengeAb, bsp.intMengeBis, bsp.fGrundgebuehr, bsp.fBereichsGrundgebuehr,
                bsp.fStueckPreis, p.intID, p.intStaffelgruppe, bs.strAbrechnungseinheit
            HAVING bsp.intMengeAb <= SUM(aw.decGesamt) AND bsp.intMengeBis >= SUM(aw.decGesamt)
            ORDER BY bsp.intMengeAb', [$date->year, $date->month, $position->intID]);

        if (!$row) {
            return ['ok' => false, 'message' => 'Bereichsstaffel-Accounting fehlt oder kein passender Bereich vorhanden.'];
        }

        $traffic = (int) round((float) $row->Gesamt, 0, PHP_ROUND_HALF_EVEN);
        $included = (int) $row->intMengeAb;
        $singleCount = $traffic - $included;
        $baseFee = (float) $row->fGrundgebuehr;
        $rangeFee = (float) $row->fBereichsGrundgebuehr;
        $piecePrice = (float) $row->fStueckPreis;
        $unit = trim((string) ($row->strAbrechnungseinheit ?? ''));
        $base = $baseFee + $rangeFee + ($piecePrice * $singleCount);
        $label = trim($traffic.' '.$unit).': ';
        if ($baseFee != 0.0) {
            $label .= 'Grundgebühr '.number_format($baseFee, 2, ',', '.').' €, ';
        }
        if ($rangeFee != 0.0) {
            $label .= $included.' '.$unit.' '.number_format($rangeFee, 2, ',', '.').' €, ';
        }
        $label .= $singleCount.' '.$unit.' × '.number_format($piecePrice, 5, ',', '.').' €';

        return [
            'ok' => true,
            'quantity' => $traffic,
            'quantityLabel' => $label,
            'unitPrice' => $piecePrice,
            'base' => $base,
        ];
    }

    private function valuateDomain($db, object $position, CarbonImmutable $date): array
    {
        $domainsDb = DB::connection('sqlsrv_domains');
        $condition = $domainsDb->table('tblDomainKonditionen')
            ->where('intID', $position->intStaffelgruppe)
            ->first([
                'intID', 'strKonditionsName', 'intR_AbrechnungEinheit', 'intR_AbrechnungIntervall',
                'fR_IntervallPreis', 'intEnthaltenAnzahl', 'intEnthaltenEinheit',
                'fEinrichtungsPreis', 'strEinrichtungRechnungsInfo',
            ]);
        if (!$condition) {
            return ['ok' => false, 'message' => 'Domain-Abrechnungskonditionen fehlen.'];
        }

        $discount = $domainsDb->table('tblDomainKonditionenRabatte')
            ->where('intAuftragsPosID', $position->intID)
            ->first(['fRabattEinrichtung', 'fRabattRegulaer']);
        $setupDiscount = (float) ($discount->fRabattEinrichtung ?? 0);
        $regularDiscount = (float) ($discount->fRabattRegulaer ?? 0);

        $monthStart = $date->startOfMonth();
        $monthEnd = $date->endOfMonth();
        $bindings = $db->table('tblAnbindungen')
            ->where('intAuftragsPos', $position->intID)
            ->where('intTyp', 6)
            ->where('boolAbrechenbar', '<>', 0)
            ->whereRaw('dateAbrechenbarStart < DATEADD(month, 1, DATEFROMPARTS(?, ?, 1))', [$date->year, $date->month])
            ->whereRaw('dateAbrechenbarEnde >= DATEFROMPARTS(?, ?, 1)', [$date->year, $date->month])
            ->get(['intAnbindungReferenz']);
        if ($bindings->isEmpty()) {
            return ['ok' => true, 'quantity' => 0, 'quantityLabel' => 'Keine Domain in diesem Monat abzurechnen', 'unitPrice' => null, 'base' => 0.0];
        }

        $domainMap = $domainsDb->table('tblDomains')
            ->whereIn('intID', $bindings->pluck('intAnbindungReferenz'))
            ->get(['intID', 'strDomainklartextname', 'datRegistriertAm'])
            ->keyBy('intID');
        if ($domainMap->count() !== $bindings->count()) {
            return ['ok' => false, 'message' => 'Mindestens eine aktive Domain-Anbindung verweist auf keinen vorhandenen Domain-Datensatz.'];
        }
        $domainRows = $bindings->map(fn ($binding) => $domainMap->get($binding->intAnbindungReferenz))
            ->sortBy(fn ($domain) => trim((string) $domain->strDomainklartextname))
            ->values();

        $setup = [];
        $special = [];
        $regular = [];
        foreach ($domainRows as $domain) {
            if (!$domain->datRegistriertAm) {
                continue;
            }
            $registered = CarbonImmutable::parse($domain->datRegistriertAm);
            $name = trim((string) $domain->strDomainklartextname);
            $specialCount = max(0, (int) ($condition->intEnthaltenAnzahl ?? 0));
            $specialUnit = (int) ($condition->intEnthaltenEinheit ?? 0);

            $specialPeriod = null;
            if ($specialCount > 0) {
                $specialPeriod = $this->domainPeriod($registered, $specialUnit, $specialCount);
                if (!$specialPeriod) {
                    return ['ok' => false, 'message' => 'Besonderes Domain-Abrechnungsintervall wird noch nicht unterstützt.'];
                }
            }
            $regularStart = $specialPeriod ? $specialPeriod['end']->addSecond() : $registered;

            $regularInterval = (int) $condition->intR_AbrechnungIntervall;
            $regularCount = max(1, (int) $condition->intR_AbrechnungEinheit);
            $period = $this->findDomainPeriodForMonth($regularStart, $regularInterval, $regularCount, $monthStart);
            if ($period === false) {
                return ['ok' => false, 'message' => 'Reguläres Domain-Abrechnungsintervall wird noch nicht unterstützt.'];
            }

            $first = $specialPeriod ?: $this->domainPeriod($regularStart, $regularInterval, $regularCount);
            if ($first && $first['start']->startOfMonth()->equalTo($monthStart)) {
                $setup[] = ['name' => $name, 'start' => $first['start'], 'end' => $first['end']];
            }

            if ($specialPeriod && $specialPeriod['start']->startOfMonth()->equalTo($monthStart)) {
                $special[] = ['name' => $name, 'start' => $specialPeriod['start'], 'end' => $specialPeriod['end']];
            } elseif ($period && $period['start']->startOfMonth()->equalTo($monthStart)) {
                $regular[] = ['name' => $name, 'start' => $period['start'], 'end' => $period['end']];
            }
        }

        if (!$setup && !$special && !$regular) {
            return ['ok' => true, 'quantity' => 0, 'quantityLabel' => 'In diesem Monat ist keine Domainposition fällig', 'unitPrice' => null, 'base' => 0.0];
        }

        $setupPrice = 0.0;
        if ($setup && (float) ($condition->fEinrichtungsPreis ?? 0) > 0) {
            foreach ($setup as $_) {
                $setupPrice += round((float) $condition->fEinrichtungsPreis * (1 - ($setupDiscount / 100)), 2, PHP_ROUND_HALF_EVEN);
            }
        }
        $regularPrice = 0.0;
        foreach ($regular as $_) {
            $regularPrice += round((float) $condition->fR_IntervallPreis * (1 - ($regularDiscount / 100)), 2, PHP_ROUND_HALF_EVEN);
        }
        // Das Alttool bepreist die besondere Anfangsphase über die Einrichtungsgebühr.
        // clDomainsAusnahme verhindert in diesem Monat lediglich die reguläre Berechnung.
        $base = $setupPrice + $regularPrice;
        $parts = [];
        if ($setup) {
            $parts[] = count($setup).' Einrichtung';
        }
        if ($special) {
            $parts[] = count($special).' Anfangsphase';
        }
        if ($regular) {
            $parts[] = count($regular).' regulär';
        }
        $names = collect(array_merge($setup, $regular, $special))->pluck('name')->filter()->unique()->values();
        $label = implode(', ', $parts);
        if ($names->isNotEmpty()) {
            $label .= ' · '.$names->join(', ');
        }

        return [
            'ok' => true,
            'quantity' => count($setup) + count($regular),
            'quantityLabel' => $label,
            'unitPrice' => null,
            'base' => $base,
        ];
    }

    private function findDomainPeriodForMonth(CarbonImmutable $start, int $interval, int $count, CarbonImmutable $monthStart): array|false|null
    {
        $current = $start;
        for ($guard = 0; $guard < 10000; $guard++) {
            $period = $this->domainPeriod($current, $interval, $count);
            if (!$period) {
                return false;
            }
            if ($period['start']->startOfMonth()->equalTo($monthStart)) {
                return $period;
            }
            if ($period['start']->startOfMonth()->gt($monthStart)) {
                return null;
            }
            $current = $period['end']->addSecond();
        }
        return null;
    }

    private function domainPeriod(CarbonImmutable $start, int $interval, int $count): ?array
    {
        $count = max(1, $count);
        $end = match ($interval) {
            4 => $start->addDays($count - 1)->endOfDay(),
            5 => $start->addWeeks($count)->subDay()->endOfDay(),
            6 => $start->addMonthsNoOverflow($count - 1)->endOfMonth()->endOfDay(),
            7 => $start->addYearsNoOverflow($count)->subMonthNoOverflow()->endOfMonth()->endOfDay(),
            default => null,
        };
        return $end ? ['start' => $start, 'end' => $end] : null;
    }

    private function valuateBandwidth($db, object $position, CarbonImmutable $date, int $staffelTyp): array
    {
        $traffic = $db->selectOne('SELECT SUM(aw.decMBIn) AS mbIn, SUM(aw.decMBOut) AS mbOut
            FROM tblAnbindungAuswertung aw
            INNER JOIN tblAnbindungen an ON aw.intAnbindungID = an.intID
            WHERE an.intAuftragsPos = ? AND aw.intMonat = ? AND aw.intJahr = ?',
            [$position->intID, $date->month, $date->year]);

        if (!$traffic || ($traffic->mbIn === null && $traffic->mbOut === null)) {
            return ['ok' => false, 'message' => 'Bandbreiten-Accounting fehlt.'];
        }

        $mbIn = (int) round((float) ($traffic->mbIn ?? 0), 0, PHP_ROUND_HALF_EVEN);
        $mbOut = (int) round((float) ($traffic->mbOut ?? 0), 0, PHP_ROUND_HALF_EVEN);
        $ratedMb = $staffelTyp === 4 ? max($mbIn, $mbOut) : $mbIn + $mbOut;
        $days = $date->daysInMonth;
        $kbit = round((((($ratedMb * 1024) * 8) / $days) / 24) / 60 / 60, 2, PHP_ROUND_HALF_EVEN);

        $tier = $db->table('tblBandbreiteStaffelPreise')
            ->where('intStaffelGruppenID', $position->intStaffelgruppe)
            ->whereRaw('CAST(intMenge AS float) >= CAST(? AS float)', [$kbit])
            ->orderBy('intMenge')
            ->first(['intMenge', 'fVKPreis']);
        if (!$tier) {
            return ['ok' => false, 'message' => 'Für die ermittelte Bandbreite existiert keine passende Preisstufe.'];
        }

        $mode = $staffelTyp === 4 ? 'MAX(In/Out)' : 'SUM(In+Out)';
        return [
            'ok' => true,
            'quantity' => $kbit,
            'quantityLabel' => $kbit.' kBit/Sek von '.(int) $tier->intMenge.' kBit/Sek · '.$mode,
            'unitPrice' => null,
            'base' => round((float) $tier->fVKPreis, 2),
        ];
    }

    private function valuateTimeTariff($db, object $position, CarbonImmutable $date): array
    {
        $binding = $db->table('tblAnbindungen')
            ->where('intAuftragsPos', $position->intID)
            ->where('boolAbrechenbar', '<>', 0)
            ->first(['intID', 'intTyp', 'intAnbindungReferenz']);
        if (!$binding) {
            return ['ok' => false, 'message' => 'Dialin-Anbindung zur Auftragsposition fehlt.'];
        }

        $dialin = $db->table('tblAnbindungDialin')->where('intID', $binding->intAnbindungReferenz)->first(['intID']);
        if (!$dialin || !in_array((int) $binding->intTyp, [3, 5], true)) {
            return ['ok' => false, 'message' => 'Dialin-Referenz der Anbindung ist nicht konsistent.'];
        }

        $isTimeTariff = (int) $binding->intTyp === 5;
        $freeSeconds = 0;
        $minimumSeconds = 0;
        $tickSeconds = 60;
        $zones = collect();
        if ($isTimeTariff) {
            $tariff = $db->table('tblZeitTarife')->where('intID', $position->intStaffelgruppe)
                ->first(['intID', 'intFreiSekunden', 'intMindestAbnahmeSekunden', 'intTaktSekunden']);
            if (!$tariff) {
                return ['ok' => false, 'message' => 'Zeittarif der Auftragsposition fehlt.'];
            }
            $freeSeconds = (int) $tariff->intFreiSekunden;
            $minimumSeconds = (int) $tariff->intMindestAbnahmeSekunden;
            $tickSeconds = max(1, (int) $tariff->intTaktSekunden);
            $zones = $db->table('tblZeittarifeZonen')->where('intTarifID', $tariff->intID)
                ->orderBy('datBeginn')->get(['datBeginn', 'datEnde', 'fMinutenpreis']);
            if ($zones->isEmpty()) {
                return ['ok' => false, 'message' => 'Zeitzonen des Zeittarifs fehlen.'];
            }
        }

        $start = $date->startOfMonth();
        $end = $start->addMonth();
        $connections = $db->table('tblAnbindungenDialinWerte')
            ->where('intDialinID', $dialin->intID)
            ->whereRaw('datEnd >= DATEFROMPARTS(?, ?, 1)', [$start->year, $start->month])
            ->whereRaw('datEnd <= DATEFROMPARTS(?, ?, 1)', [$end->year, $end->month])
            ->orderBy('datBegin')
            ->get(['datBegin', 'datEnd', 'fNettoPreis', 'strCalledStationNummer', 'intVerbindungsdauerInSec']);

        $sum = 0.0;
        $seconds = 0;
        foreach ($connections as $connection) {
            $duration = (int) ($connection->intVerbindungsdauerInSec ?? 0);
            $seconds += $duration;
            if ((float) ($connection->fNettoPreis ?? 0) > 0) {
                $sum += (float) $connection->fNettoPreis;
                $freeSeconds -= $duration;
                continue;
            }
            if (!$isTimeTariff || trim((string) $connection->strCalledStationNummer) !== '9598100') {
                $freeSeconds -= $duration;
                continue;
            }
            $connStart = CarbonImmutable::parse($connection->datBegin);
            $connEnd = CarbonImmutable::parse($connection->datEnd);
            if ($connStart->diffInSeconds($connEnd) < $minimumSeconds) {
                $connEnd = $connStart->addSeconds($minimumSeconds);
            }
            $price = $this->calculateConnectionPrice($connStart, $connEnd, $zones, $tickSeconds, $freeSeconds);
            if ($price === null) {
                return ['ok' => false, 'message' => 'Eine Dialin-Verbindung konnte keiner Zeitzone zugeordnet werden.'];
            }
            $sum += round($price, 3, PHP_ROUND_HALF_EVEN);
        }

        $sum = round(round($sum, 4, PHP_ROUND_HALF_EVEN), 2, PHP_ROUND_HALF_EVEN);
        return [
            'ok' => true,
            'quantity' => $seconds,
            'quantityLabel' => 'EVN unter https://kunden.tops.net',
            'unitPrice' => null,
            'base' => $sum,
        ];
    }

    private function calculateConnectionPrice(CarbonImmutable $start, CarbonImmutable $end, Collection $zones, int $tickSeconds, int &$freeSeconds): ?float
    {
        $price = 0.0;
        $cursor = $start;
        $guard = 0;
        while ($cursor->lt($end) && $guard++ < 10000) {
            $secondsOfDay = ($cursor->hour * 3600) + ($cursor->minute * 60) + $cursor->second;
            $zone = $zones->first(function ($z) use ($secondsOfDay) {
                $zs = CarbonImmutable::parse($z->datBeginn);
                $ze = CarbonImmutable::parse($z->datEnde);
                $startSec = ($zs->hour * 3600) + ($zs->minute * 60) + $zs->second;
                $endSec = ($ze->hour * 3600) + ($ze->minute * 60) + $ze->second;
                return $secondsOfDay >= $startSec && $secondsOfDay <= $endSec;
            });
            if (!$zone) {
                return null;
            }
            $zoneEndTime = CarbonImmutable::parse($zone->datEnde);
            $zoneEnd = $cursor->setTime($zoneEndTime->hour, $zoneEndTime->minute, $zoneEndTime->second)->addSecond();
            if ($zoneEnd->lte($cursor)) {
                $zoneEnd = $zoneEnd->addDay();
            }
            $segmentEnd = $end->lt($zoneEnd) ? $end : $zoneEnd->subSecond();
            // Das VB-Alttool behandelt das Enddatum inklusiv und addiert vor DateDiff eine Sekunde.
            $actualSeconds = max(0, (int) $cursor->diffInSeconds($segmentEnd, false) + 1);
            $roundedSeconds = (int) ceil($actualSeconds / $tickSeconds) * $tickSeconds;

            if ($freeSeconds <= 0) {
                $billableSeconds = $roundedSeconds;
            } elseif ($freeSeconds >= $roundedSeconds) {
                $freeSeconds -= $roundedSeconds;
                $billableSeconds = 0;
            } else {
                $billableSeconds = $roundedSeconds - $freeSeconds;
                $billableSeconds = (int) ceil($billableSeconds / $tickSeconds) * $tickSeconds;
                $freeSeconds = 0;
            }
            $price += $billableSeconds * ((float) $zone->fMinutenpreis / 60);
            // Auch dies entspricht dem Altcode: Weiter geht es am auf Takt gerundeten Ende.
            $cursor = $cursor->addSeconds($roundedSeconds);
        }
        return $price;
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
