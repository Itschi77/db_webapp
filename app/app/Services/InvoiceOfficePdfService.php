<?php

namespace App\Services;

use RuntimeException;

class InvoiceOfficePdfService
{
    public function available(): bool
    {
        return trim((string) shell_exec('command -v soffice')) !== '';
    }

    public function convert(string $docxPath): string
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException('Die zu konvertierende Word-Rechnung fehlt.');
        }

        $soffice = trim((string) shell_exec('command -v soffice'));
        if (! $this->available() || $soffice === '') {
            throw new RuntimeException('LibreOffice ist für die Word-PDF-Konvertierung nicht installiert.');
        }

        $outDir = dirname($docxPath).'/pdf';
        $profileDir = storage_path('app/libreoffice-profile-'.uniqid('', true));
        if (! is_dir($outDir) && ! mkdir($outDir, 0775, true) && ! is_dir($outDir)) {
            throw new RuntimeException('PDF-Ausgabeverzeichnis konnte nicht angelegt werden.');
        }
        if (! mkdir($profileDir, 0775, true) && ! is_dir($profileDir)) {
            throw new RuntimeException('Temporäres LibreOffice-Profil konnte nicht angelegt werden.');
        }

        $command = sprintf(
            '%s --headless --nologo --nodefault --nofirststartwizard %s --convert-to %s --outdir %s %s 2>&1',
            escapeshellarg($soffice),
            escapeshellarg('-env:UserInstallation=file://'.$profileDir),
            escapeshellarg('pdf:writer_pdf_Export'),
            escapeshellarg($outDir),
            escapeshellarg($docxPath),
        );

        exec($command, $output, $exitCode);
        $this->removeDirectory($profileDir);

        if ($exitCode !== 0) {
            throw new RuntimeException('LibreOffice konnte die Word-Rechnung nicht in PDF umwandeln: '.implode(' ', $output));
        }

        $pdfPath = $outDir.'/'.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';
        if (! is_file($pdfPath) || filesize($pdfPath) === 0) {
            throw new RuntimeException('LibreOffice hat keine PDF-Datei erzeugt.');
        }

        return $pdfPath;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.'/'.$item;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}
