<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceOrderTestRunService
{
    public function __construct(
        private InvoiceAddressPlausibilityService $addressPlausibility,
    ) {}

    public function build(object $orderListRow, array $preview, CarbonImmutable $invoiceDate): array
    {
        $accountings = DB::connection('sqlsrv_accountings');
        $tops = DB::connection('sqlsrv_topsnetdb_safe');

        $order = $accountings->table('tblAuftrag')->where('intAufNr', $orderListRow->intAufNr)->first([
            'intAufNr', 'intKID', 'intAnschriftID', 'intZahlungsbedingungID', 'strBeschreibung',
            'strAbrechnungshinweis', 'boolPapierrechnung', 'boolEmailRechnung', 'boolLastschriftErzeugen',
            'boolDauerlastschrift', 'datStorniereAb',
            'boolEingefroren', 'intSkonto1Tage', 'intSkonto2Tage', 'intSkonto3Tage',
            'dezSkonto1Prozent', 'dezSkonto2Prozent', 'dezSkonto3Prozent',
        ]);

        $issues = collect();
        $warnings = collect();
        if (!$order) {
            $issues->push('Auftrag ist nicht mehr vorhanden.');
            return $this->result('blocked', $issues, $warnings, null, null, null, $preview, $invoiceDate);
        }

        $customer = $tops->table('tblKunde')->where('intID', $order->intKID)->first([
            'intID', 'strName', 'strDatevKundenKonto',
        ]);
        $address = $accountings->table('tblRechnungsanschrift')->where('intID', $order->intAnschriftID)->first([
            'intID', 'intKID', 'strName', 'strZuHaenden', 'strStrasse', 'strPLZ', 'strOrt', 'strEmail',
            'strKontoNr', 'strBLZ', 'strInstitut', 'strBIC', 'strIBAN', 'strUStIdNr', 'strInhaber',
            'strKundenreferenz', 'boolSEPA', 'boolErstlastschrift', 'boolXRechnung',
            'strLieferantenId', 'strLeitwegId',
        ]);
        // Das Alttool liest tblZahlungsbedingung aus accountings, nicht aus der Kundendatenbank.
        $payment = $order->intZahlungsbedingungID
            ? $accountings->table('tblZahlungsbedingung')->where('intID', $order->intZahlungsbedingungID)->first([
                'intID', 'strBezeichnung', 'boolIstBankeinzug', 'boolIstZahlungsziel', 'intAnzahlTage',
            ])
            : null;

        if ((bool) $order->boolEingefroren) {
            $issues->push('Auftrag ist eingefroren.');
        }
        if (!$customer) {
            $issues->push('Kundendatensatz fehlt.');
        } elseif (trim((string) $customer->strDatevKundenKonto) === '') {
            // Das Alttool bricht für diesen Auftrag die Transaktion ab, erzeugt aber keinen harten Gesamtlauffehler.
            $issues->push('DATEV-Kundenkonto fehlt. Das Alttool würde für diesen Auftrag keine Rechnung erzeugen.');
        }
        if (!$address) {
            $issues->push('Rechnungsanschrift fehlt.');
        } elseif ((int) $address->intKID !== (int) $order->intKID) {
            $issues->push('Rechnungsanschrift gehört zu einer anderen Kundennummer. Das Alttool würde diesen Auftrag nicht zur Fakturierung auswählen.');
        } else {
            foreach ($this->addressPlausibility->issues($address) as $addressIssue) {
                $issues->push($addressIssue);
            }
        }
        if (!$payment) {
            $issues->push('Zahlungsbedingung fehlt.');
        } elseif ((bool) $payment->boolIstBankeinzug && $address && !$this->hasBankDetails($address)) {
            // Der historische Konsistenzcheck prüft nur auf einen vorhandenen Datensatz. Für die Webvorschau
            // weisen wir zusätzlich auf leere Bankdaten hin, blockieren deswegen aber nicht eigenmächtig.
            $warnings->push('Bankeinzug ist gesetzt, die Rechnungsanschrift enthält aber keine vollständige Bankverbindung. Das Alttool prüft diesen Inhalt an dieser Stelle nicht streng.');
        }

        $fatalStatuses = collect($preview['rows'])->whereIn('status', ['conflict', 'accounting_error', 'unsupported', 'frozen']);
        foreach ($fatalStatuses as $row) {
            $issues->push('Position #'.$row->position->intID.': '.$row->statusLabel);
        }

        $invoiceRows = collect($preview['rows'])
            ->filter(fn ($row) => in_array($row->status, ['billable', 'precalculation_end'], true))
            ->values();

        $datevByPosition = collect();
        if ($invoiceRows->isNotEmpty()) {
            $positionIds = $invoiceRows->pluck('position.intID')->unique()->values();
            $datevByPosition = $accountings->table('tblAuftragPos as p')
                ->leftJoin('tblDatevBezeichnungen as d', 'd.intID', '=', 'p.intDatevBezeichnungsID')
                ->whereIn('p.intID', $positionIds)
                ->get(['p.intID', 'd.strDatevKontierung'])
                ->keyBy('intID');
            foreach ($positionIds as $positionId) {
                if (trim((string) ($datevByPosition->get($positionId)?->strDatevKontierung ?? '')) === '') {
                    $issues->push('Position #'.$positionId.' hat keine DATEV-Produktkontierung.');
                }
            }
        }

        $documentRows = $this->documentRows($invoiceRows, $datevByPosition);
        $status = $issues->isNotEmpty() ? 'blocked' : ($invoiceRows->isEmpty() ? 'nothing_to_invoice' : 'ready');
        $dueDate = $payment ? $invoiceDate->addDays((int) $payment->intAnzahlTage) : null;
        $paymentText = $this->paymentText($payment, $preview, $dueDate, $address);
        $skonto = $this->skonto($order, $preview, $invoiceDate);
        $fulfillment = $this->fulfillmentPlan($order, $address, $payment, $preview, $dueDate, $skonto);
        foreach ($fulfillment['issues'] as $issue) {
            $issues->push($issue);
        }
        foreach ($fulfillment['warnings'] as $warning) {
            $warnings->push($warning);
        }
        $status = $issues->isNotEmpty() ? 'blocked' : ($invoiceRows->isEmpty() ? 'nothing_to_invoice' : 'ready');

        return $this->result(
            $status, $issues, $warnings, $customer, $address, $payment, $preview, $invoiceDate,
            $dueDate, $invoiceRows, $documentRows, $skonto, $order, $paymentText, $fulfillment
        );
    }

    private function documentRows(Collection $invoiceRows, Collection $datevByPosition): Collection
    {
        $rows = collect();
        foreach ($invoiceRows as $row) {
            $datev = trim((string) ($datevByPosition->get($row->position->intID)?->strDatevKontierung ?? ''));
            $details = $row->precalculationDetails;

            if ($details && ($details['tierChanged'] ?? false)) {
                $rows->push($this->documentRow($row, 'Erstattung der vorausbezahlten Staffel bis '.($details['previousTierLimit'] ?? '–').' MB', -((float) ($details['previousPrice'] ?? 0)), 'Korrektur', $datev));
                $rows->push($this->documentRow($row, $row->position->strBeschreibung.' · tatsächliche Staffel bis '.($details['currentTierLimit'] ?? '–').' MB', (float) ($details['currentPrice'] ?? 0), 'Nachberechnung', $datev));
            }

            if (!$row->precalculationEnded) {
                $description = (string) $row->position->strBeschreibung;
                if ($row->precalculationActive && $row->displayDate) {
                    $description .= ' · Vorausberechnung ab '.$row->displayDate->format('d.m.Y');
                }
                $rows->push($this->documentRow($row, $description, (float) $row->baseNet, 'Leistung', $datev));
            } elseif (!$details || !($details['tierChanged'] ?? false)) {
                $rows->push($this->documentRow($row, 'Vorberechnung endet; keine weitere Vorausberechnung für '.$row->position->strBeschreibung, 0.0, 'Hinweis', $datev));
            }

            if ((float) $row->discount != 0.0) {
                $rows->push($this->documentRow(
                    $row,
                    number_format((float) $row->discountPercent, 2, ',', '.').' % Rabatt für diese Auftragsposition',
                    (float) $row->discount,
                    'Rabatt',
                    $datev
                ));
            }
        }
        return $rows;
    }

    private function documentRow(object $source, string $description, float $net, string $kind, string $datev): object
    {
        return (object) [
            'positionId' => (int) $source->position->intID,
            'kind' => $kind,
            'description' => $description,
            'quantityLabel' => $source->quantityLabel,
            'datev' => $datev,
            'net' => round($net, 8),
            'taxRate' => (float) $source->taxRate,
            'tax' => round($net * ((float) $source->taxRate / 100), 8),
        ];
    }

    private function skonto(object $order, array $preview, CarbonImmutable $invoiceDate): Collection
    {
        return collect([1, 2, 3])->map(function (int $level) use ($order, $preview, $invoiceDate) {
            $days = (int) ($order->{'intSkonto'.$level.'Tage'} ?? 0);
            $percent = (float) ($order->{'dezSkonto'.$level.'Prozent'} ?? 0);
            if ($days <= 0 || $percent <= 0 || (float) $preview['net'] <= 0) {
                return null;
            }
            $factor = 1 - ($percent / 100);
            $net = (float) $preview['net'] * $factor;
            $tax = (float) $preview['tax'] * $factor;
            return [
                'level' => $level,
                'days' => $days,
                'percent' => $percent,
                'date' => $invoiceDate->addDays($days),
                'net' => $net,
                'tax' => $tax,
                'gross' => $net + $tax,
                'discountAmount' => (float) $preview['gross'] - ($net + $tax),
            ];
        })->filter()->values();
    }

    private function paymentText(?object $payment, array $preview, ?CarbonImmutable $dueDate, ?object $address): string
    {
        if (!$payment) {
            return '';
        }
        $net = (float) ($preview['net'] ?? 0);
        if ($net < 0) {
            return 'Storno/Teilstorno; der Erstattungsbetrag ist bei zukünftigen Zahlungen zu berücksichtigen.';
        }
        if ($net == 0.0) {
            return 'Nullrechnung; sie dient in der Regel nur zur Information.';
        }
        if ((bool) $payment->boolIstBankeinzug) {
            $iban = trim((string) ($address->strIBAN ?? ''));
            $masked = $iban !== '' ? $this->maskIban($iban) : 'nicht hinterlegt';
            return 'Bankeinzug zum '.($dueDate?->format('d.m.Y') ?? '–').' · IBAN '.$masked;
        }
        if ((bool) $payment->boolIstZahlungsziel && $dueDate) {
            return 'Zahlbar ohne Abzug bis zum '.$dueDate->format('d.m.Y').'.';
        }
        return trim((string) $payment->strBezeichnung);
    }

    private function maskIban(string $iban): string
    {
        $compact = preg_replace('/\s+/', '', $iban) ?? $iban;
        if (strlen($compact) <= 8) {
            return '****';
        }
        return substr($compact, 0, 6).str_repeat('*', max(4, strlen($compact) - 8)).substr($compact, -2);
    }

    private function result(
        string $status, $issues, $warnings, $customer, $address, $payment, array $preview,
        CarbonImmutable $invoiceDate, ?CarbonImmutable $dueDate = null, $invoiceRows = null,
        $documentRows = null, $skonto = null, $order = null, string $paymentText = '', ?array $fulfillment = null
    ): array {
        return [
            'status' => $status,
            'statusLabel' => match ($status) {
                'ready' => 'Testlauf vollständig und fakturierbar',
                'nothing_to_invoice' => 'Testlauf vollständig, aber nichts neu zu fakturieren',
                default => 'Testlauf blockiert',
            },
            'issues' => collect($issues)->unique()->values(),
            'warnings' => collect($warnings)->unique()->values(),
            'customer' => $customer,
            'address' => $address,
            'payment' => $payment,
            'order' => $order,
            'invoiceDate' => $invoiceDate,
            'dueDate' => $dueDate,
            'invoiceRows' => $invoiceRows ?? collect(),
            'documentRows' => $documentRows ?? collect(),
            'skonto' => $skonto ?? collect(),
            'paymentText' => $paymentText,
            'fulfillment' => $fulfillment ?? $this->emptyFulfillment(),
            'net' => (float) ($preview['net'] ?? 0),
            'tax' => (float) ($preview['tax'] ?? 0),
            'gross' => (float) ($preview['gross'] ?? 0),
            'taxByRate' => $preview['taxByRate'] ?? collect(),
        ];
    }

    private function fulfillmentPlan(
        object $order,
        ?object $address,
        ?object $payment,
        array $preview,
        ?CarbonImmutable $dueDate,
        Collection $skonto,
    ): array {
        $issues = collect();
        $warnings = collect();
        $net = (float) ($preview['net'] ?? 0);
        $gross = (float) ($preview['gross'] ?? 0);
        $paper = (bool) $order->boolPapierrechnung;
        $email = (bool) $order->boolEmailRechnung;
        $emailAddress = trim((string) ($address?->strEmail ?? ''));

        if (! $paper && ! $email) {
            $issues->push('Es ist weder Papier- noch E-Mail-Versand aktiviert.');
        }
        if ($email && ! filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            $issues->push('E-Mail-Versand ist aktiviert, aber die Rechnungsanschrift enthält keine gültige E-Mail-Adresse.');
        }

        $bankDebit = (bool) ($payment?->boolIstBankeinzug ?? false);
        if ($bankDebit && ! (bool) $order->boolLastschriftErzeugen) {
            $warnings->push('Die Zahlungsbedingung ist Bankeinzug, das Auftragsmerkmal „Lastschrift erzeugen“ ist jedoch nicht gesetzt.');
        }
        $sepa = $bankDebit && (bool) ($address?->boolSEPA ?? false) && $gross > 0;
        if ($bankDebit && $gross > 0 && ! (bool) ($address?->boolSEPA ?? false)) {
            $issues->push('Bankeinzug ist vorgesehen, aber SEPA ist an der Rechnungsanschrift nicht aktiviert.');
        }
        if ($sepa && ! $this->hasBankDetails($address)) {
            $issues->push('Für den SEPA-Einzug fehlen IBAN oder vollständige Kontodaten.');
        }
        if ($sepa && trim((string) ($address?->strInhaber ?? '')) === '') {
            $warnings->push('Für den SEPA-Einzug ist kein abweichender Kontoinhaber hinterlegt; verwendet wird der Rechnungsempfänger.');
        }

        $sequence = null;
        if ($sepa) {
            if (! (bool) $order->boolDauerlastschrift) {
                $sequence = 'OOFF';
            } elseif ((bool) ($address?->boolErstlastschrift ?? false)) {
                $sequence = 'FRST';
            } elseif ($order->datStorniereAb && $dueDate && CarbonImmutable::parse($order->datStorniereAb)->lte($dueDate)) {
                $sequence = 'FNAL';
            } else {
                $sequence = 'RCUR';
            }
        }

        $debitAmount = $gross;
        $firstSkonto = $skonto->sortBy('level')->first();
        if ($sepa && $firstSkonto) {
            $debitAmount = (float) $firstSkonto['gross'];
        }

        return [
            'documentType' => $net < 0 ? 'Storno/Teilstorno' : ($net == 0.0 ? 'Nullrechnung' : 'Rechnung'),
            'paper' => $paper,
            'email' => $email,
            'emailAddress' => $emailAddress,
            'bankDebit' => $bankDebit,
            'sepa' => $sepa,
            'sepaSequence' => $sequence,
            'debitAmount' => round(max(0, $debitAmount), 2),
            'dueDate' => $dueDate,
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }

    private function emptyFulfillment(): array
    {
        return [
            'documentType' => 'Rechnung', 'paper' => false, 'email' => false, 'emailAddress' => '',
            'bankDebit' => false, 'sepa' => false, 'sepaSequence' => null, 'debitAmount' => 0.0,
            'dueDate' => null, 'issues' => collect(), 'warnings' => collect(),
        ];
    }

    private function hasBankDetails(?object $address): bool
    {
        if (!$address) {
            return false;
        }
        return trim((string) ($address->strIBAN ?? '')) !== ''
            || (trim((string) ($address->strKontoNr ?? '')) !== '' && trim((string) ($address->strBLZ ?? '')) !== '');
    }
}
