<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class DocumentationController extends Controller
{
    private const DOCUMENTS = [
        'migration' => [
            'title' => 'Technische Dokumentation',
            'file' => 'MIGRATION.md',
            'filename' => 'Technische-Dokumentation.pdf',
        ],
        'handbook' => [
            'title' => 'Benutzerhandbuch',
            'file' => 'HANDBUCH.md',
            'filename' => 'Benutzerhandbuch.pdf',
        ],
        'invoice-handbook' => [
            'title' => 'Handbuch Rechnungstool',
            'file' => 'RECHNUNGSTOOL_HANDBUCH.md',
            'filename' => 'Handbuch-Rechnungstool.pdf',
        ],
        'sql-wiki' => [
            'title' => 'SQL-Statement-Wiki',
            'file' => 'SQL_WIKI.md',
            'filename' => 'SQL-Statement-Wiki.pdf',
        ],
        'test-protocol' => [
            'title' => 'Testprotokoll & Fehleranalyse',
            'file' => 'TESTPROTOKOLL.md',
            'filename' => 'Testprotokoll-Fehleranalyse.pdf',
        ],
    ];

    public function migration()
    {
        return $this->render('migration');
    }

    public function handbook()
    {
        return $this->render('handbook');
    }

    public function invoiceHandbook()
    {
        return $this->render('invoice-handbook');
    }

    public function sqlWiki()
    {
        return $this->render('sql-wiki');
    }

    public function testProtocol()
    {
        return $this->render('test-protocol');
    }

    public function pdf(string $document)
    {
        abort_unless(isset(self::DOCUMENTS[$document]), 404);

        $definition = self::DOCUMENTS[$document];
        $content = $this->html($definition['file']);

        return Pdf::loadView('documentation.pdf', [
            'title' => $definition['title'],
            'content' => $content,
            'generatedAt' => now(),
        ])
            ->setPaper('a4', 'portrait')
            ->download($definition['filename']);
    }

    private function render(string $document)
    {
        abort_unless(isset(self::DOCUMENTS[$document]), 404);

        $definition = self::DOCUMENTS[$document];
        $title = $definition['title'];
        $content = $this->html($definition['file']);
        $pdfUrl = route('documentation.pdf', ['document' => $document]);
        $mode = session('frontend_mode', 'classic');

        return view($mode.'.documentation.show', compact('title', 'content', 'pdfUrl'));
    }

    private function html(string $file): string
    {
        $markdown = file_get_contents(base_path('docs/'.$file));

        return Str::markdown($markdown, ['html_input' => 'strip']);
    }
}
