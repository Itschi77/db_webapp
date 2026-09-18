<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class InvoiceDocumentPreviewService
{
    public function build(
        int $orderNumber,
        CarbonImmutable $from,
        CarbonImmutable $to,
        CarbonImmutable $invoiceDate,
        bool $withAccountings,
    ): array {
        $db = DB::connection('sqlsrv_accountings');
        $latestInvoice = $db->table('tblRechnung')
            ->selectRaw('intAufNr, MAX(datRechnungsDatum) AS letztesRechnungsdatum')
            ->groupBy('intAufNr');

        $order = $db->table('tblAuftrag as a')
            ->leftJoin('tblRechnungsanschrift as ra', function ($join) {
                $join->on('ra.intID', '=', 'a.intAnschriftID')
                    ->on('ra.intKID', '=', 'a.intKID');
            })
            ->leftJoinSub($latestInvoice, 'lr', fn ($join) => $join->on('lr.intAufNr', '=', 'a.intAufNr'))
            ->where('a.intAufNr', $orderNumber)
            ->first([
                'a.intAufNr', 'a.intKID', 'a.datErfassungsdatum', 'a.datFakturierAb', 'a.datStorniereAb',
                'a.strBeschreibung', 'a.boolEmailRechnung', 'a.strAbrechnungshinweis',
                'a.boolVoraus', 'a.boolDomainrechnung', 'a.boolEingefroren',
                'ra.strEmail as rechnungEmail', 'lr.letztesRechnungsdatum',
            ]);

        if (! $order) {
            throw (new ModelNotFoundException())->setModel('Auftrag', [$orderNumber]);
        }

        $positions = $db->table('tblAuftragPos')
            ->where('intAufNr', $orderNumber)
            ->orderBy('intID')
            ->get([
                'intID', 'strBeschreibung', 'intMenge', 'fEndpreis', 'fRabattInProzent',
                'intMwstsatz', 'intAbrechnungsArt', 'intStaffelTyp', 'intStaffelgruppe',
                'datFakturierAb', 'datFakturierBis', 'datVorberechnenBis', 'boolIstAnbindung',
            ]);

        $calculation = app(InvoicePreviewCalculationService::class)
            ->calculate($order, $positions, $from, $to, $withAccountings);
        $testRun = app(InvoiceOrderTestRunService::class)
            ->build($order, $calculation, $invoiceDate);

        return [
            'order' => $order,
            'calculation' => $calculation,
            'testRun' => $testRun,
        ];
    }
}
