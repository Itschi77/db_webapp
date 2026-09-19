<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentationPdfExportTest extends TestCase
{
    public function test_all_documentation_exports_return_pdf_files(): void
    {
        foreach (['migration', 'handbook', 'invoice-handbook', 'sql-wiki', 'test-protocol'] as $document) {
            $response = $this->get(route('documentation.pdf', ['document' => $document]));

            $response->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        }
    }
}
