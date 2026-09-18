<?php

return [
    /*
     * Productive writes stay disabled until the migration has been approved.
     * Enabling this flag only exposes the guarded commit action; every write is
     * still recalculated and validated inside one SQL Server transaction.
     */
    'writes_enabled' => env('INVOICE_WRITES_ENABLED', false),
    'confirmation_phrase' => env('INVOICE_WRITE_CONFIRMATION', 'RECHNUNG VERBINDLICH ERZEUGEN'),
    'einvoice' => [
        'seller_name' => 'tops.net GmbH & Co. KG',
        'seller_street' => 'Holtorfer Strasse 35',
        'seller_postcode' => '53229',
        'seller_city' => 'Bonn',
        'seller_country' => 'DE',
        'seller_vat_id' => 'DE182607448',
        'seller_tax_number' => '5206/5809/0145',
        'seller_email' => 'info@tops.net',
        'seller_phone' => '+49 228 9771 0',
        'seller_contact' => 'Rechnungswesen',
        'seller_iban' => 'DE88370501980032900649',
        'seller_bic' => 'COLSDE33XXX',
        'seller_bank_name' => 'Sparkasse KölnBonn',
        'xrechnung_version' => '3.0.2',
        'zugferd_version' => '2.5.2',
    ],
];
