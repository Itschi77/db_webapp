<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;

class InvoiceTemplatePdfService
{
    public function __construct(
        private InvoiceWordTemplateService $word,
        private InvoiceOfficePdfService $office,
    ) {}

    public function ready(): bool
    {
        return is_file(resource_path('invoicing/Rechnung-4.docx')) && $this->office->available();
    }

    public function render(
        array $document,
        int $invoiceNumber,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $isPreview = false,
    ): string {
        $docx = $this->word->build($document, $invoiceNumber, $from, $to, $isPreview);
        $pdf = null;

        try {
            $pdf = $this->office->convert($docx);
            $bytes = file_get_contents($pdf);
            if ($bytes === false || $bytes === '') {
                throw new RuntimeException('Die erzeugte PDF-Datei konnte nicht gelesen werden.');
            }
            return $bytes;
        } finally {
            @unlink($docx);
            if ($pdf) {
                @unlink($pdf);
            }
        }
    }
}
