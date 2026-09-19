<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceCustomerConsistencyService
{
    public function __construct(
        private InvoiceAddressPlausibilityService $addressPlausibility,
    ) {}

    public function issues(CarbonImmutable $now): array
    {
        $orders = $this->activeInvoiceOrders($now);
        if ($orders->isEmpty()) {
            return [];
        }

        $customerIds = $orders->pluck('intKID')->map(fn ($id) => (int) $id)->unique()->values();
        $customers = collect();

        foreach ($customerIds->chunk(1000) as $chunk) {
            $rows = DB::connection('sqlsrv_topsnetdb_safe')
                ->table('tblKunde')
                ->whereIn('intID', $chunk->all())
                ->get(['intID', 'strName', 'strDatevKundenKonto']);
            $customers = $customers->concat($rows);
        }

        $customers = $customers->keyBy(fn ($row) => (int) $row->intID);
        $issues = collect();

        foreach ($orders as $order) {
            $customerId = (int) $order->intKID;
            $customer = $customers->get($customerId);
            $label = trim((string) ($customer?->strName ?? $order->invoiceName ?? ''));
            $label = $label !== '' ? $label : 'Kunde '.$customerId;

            if (!$customer) {
                $issues->push($this->issue(
                    $order,
                    $label,
                    'Kundenstamm',
                    'Kundennummer '.$customerId.' fehlt in topsnetdb_safe.dbo.tblKunde.'
                ));
            }

            if ($order->addressID === null) {
                $issues->push($this->issue(
                    $order,
                    $label,
                    'Zuordnung',
                    'Rechnungsanschrift fehlt.'
                ));
                continue;
            }

            if ((int) $order->addressKID !== $customerId) {
                $issues->push($this->issue(
                    $order,
                    $label,
                    'Zuordnung',
                    'Rechnungsanschrift gehört zu Kundennummer '.$order->addressKID
                        .' statt '.$customerId.'.'
                ));
            }

            foreach ($this->addressPlausibility->issues((object) [
                'strPLZ' => $order->addressPostalCode,
                'strOrt' => $order->addressCity,
            ]) as $addressIssue) {
                $issues->push($this->issue($order, $label, 'Rechnungsanschrift', $addressIssue));
            }
        }

        return $issues->values()->all();
    }

    private function activeInvoiceOrders(CarbonImmutable $now): Collection
    {
        return DB::connection('sqlsrv_accountings')
            ->table('tblAuftrag as a')
            ->leftJoin('tblRechnungsanschrift as ra', 'ra.intID', '=', 'a.intAnschriftID')
            ->where('a.boolRechnungstool', 1)
            ->where(function ($builder) {
                $builder->whereNull('a.boolSponsoring')
                    ->orWhere('a.boolSponsoring', 0);
            })
            ->where(function ($builder) use ($now) {
                $builder->whereNull('a.datStorniereAb')
                    ->orWhereRaw(
                        'a.datStorniereAb > DATEFROMPARTS(?, ?, ?)',
                        [$now->year, $now->month, $now->day],
                    );
            })
            ->get([
                'a.intAufNr',
                'a.intKID',
                'a.intAnschriftID',
                'ra.intID as addressID',
                'ra.intKID as addressKID',
                'ra.strName as invoiceName',
                'ra.strPLZ as addressPostalCode',
                'ra.strOrt as addressCity',
            ]);
    }

    private function issue(object $order, string $customer, string $category, string $message): array
    {
        return [
            'order' => (int) $order->intAufNr,
            'customer' => $customer,
            'category' => $category,
            'issue' => $message,
        ];
    }
}
