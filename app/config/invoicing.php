<?php

return [
    /*
     * Productive writes stay disabled until the migration has been approved.
     * Enabling this flag only exposes the guarded commit action; every write is
     * still recalculated and validated inside one SQL Server transaction.
     */
    'writes_enabled' => env('INVOICE_WRITES_ENABLED', false),
    'confirmation_phrase' => env('INVOICE_WRITE_CONFIRMATION', 'RECHNUNG VERBINDLICH ERZEUGEN'),
];
