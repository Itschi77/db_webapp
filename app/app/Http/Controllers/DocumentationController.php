<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;

class DocumentationController extends Controller
{
    public function migration()
    {
        return $this->render('Technische Dokumentation', 'MIGRATION.md');
    }

    public function handbook()
    {
        return $this->render('Benutzerhandbuch', 'HANDBUCH.md');
    }

    public function invoiceHandbook()
    {
        return $this->render('Handbuch Rechnungstool', 'RECHNUNGSTOOL_HANDBUCH.md');
    }

    public function sqlWiki()
    {
        return $this->render('SQL-Statement-Wiki', 'SQL_WIKI.md');
    }

    private function render(string $title, string $file)
    {
        $markdown = file_get_contents(base_path('docs/'.$file));
        $content = Str::markdown($markdown, ['html_input' => 'strip']);
        $mode = session('frontend_mode', 'classic');

        return view($mode.'.documentation.show', compact('title', 'content'));
    }
}
