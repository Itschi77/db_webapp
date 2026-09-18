<?php

namespace App\Services;

use horstoeko\zugferd\ZugferdPdfValidator;

class InvoicePdfAValidatorService
{
    public function validate(string $pdf): array
    {
        $base = storage_path('app/verapdf');
        if (!is_dir($base) && !@mkdir($base, 0775, true) && !is_dir($base)) {
            return $this->failure('veraPDF-Verzeichnis konnte nicht angelegt werden.');
        }

        $executable = is_file('/opt/verapdf/verapdf') && is_executable('/opt/verapdf/verapdf')
            ? '/opt/verapdf/verapdf'
            : $this->findExecutable($base);
        if ($executable === null) {
            $bootstrap = ZugferdPdfValidator::fromContent($pdf)
                ->setBaseDirectory($base)
                ->setValidatorDownloadUrl('https://software.verapdf.org/releases/verapdf-installer.zip')
                ->setValidatorRuleset(ZugferdPdfValidator::RULESET_PDF_A_3U)
                ->disableCleanup();
            try {
                // Die Bibliothek übernimmt nur Download/Installation. Ihre Auswertung
                // unterstützt das aktuelle veraPDF-1.30-JSON noch nicht vollständig.
                $bootstrap->validate();
            } catch (\Throwable) {
                // Direkt danach prüfen wir, ob die Installation trotzdem vorliegt.
            }
            $executable = $this->findExecutable($base);
        }

        if ($executable === null) {
            return $this->failure('veraPDF konnte nicht installiert oder gefunden werden.');
        }

        $dir = storage_path('app/pdfa-validation/'.uniqid('', true));
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            return $this->failure('Temporäres PDF/A-Prüfverzeichnis konnte nicht angelegt werden.');
        }
        $pdfFile = $dir.'/invoice.pdf';
        file_put_contents($pdfFile, $pdf);

        $command = sprintf(
            '%s --format json --flavour 3u %s 2>&1',
            escapeshellarg($executable),
            escapeshellarg($pdfFile),
        );
        exec($command, $output, $exitCode);
        $this->removeDirectory($dir);

        $json = json_decode(implode("\n", $output), true);
        if (!is_array($json)) {
            return $this->failure('veraPDF hat keinen lesbaren JSON-Prüfbericht geliefert.', $exitCode);
        }

        $job = $json['report']['jobs'][0] ?? null;
        $results = $job['validationResult'] ?? null;
        if (isset($results['compliant'])) {
            $results = [$results];
        }
        if (!is_array($results) || $results === []) {
            return $this->failure('veraPDF-Prüfbericht enthält kein Validierungsergebnis.', $exitCode);
        }

        $errors = [];
        $warnings = [];
        $valid = true;
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }
            if (!($result['compliant'] ?? false)) {
                $valid = false;
                $details = $result['details'] ?? [];
                $errors[] = sprintf(
                    '%s: %s failed rules / %s failed checks. %s',
                    $result['profileName'] ?? 'PDF/A',
                    $details['failedRules'] ?? '?',
                    $details['failedChecks'] ?? '?',
                    $result['statement'] ?? ''
                );
            }
            if (($result['jobEndStatus'] ?? 'normal') !== 'normal') {
                $valid = false;
                $errors[] = 'veraPDF-Jobstatus: '.($result['jobEndStatus'] ?? 'unbekannt');
            }
        }

        return [
            'executed' => true,
            'valid' => $valid,
            'errors' => array_values(array_unique($errors)),
            'warnings' => $warnings,
            'exit_code' => $exitCode,
            'version' => $json['report']['buildInformation']['releaseDetails'][0]['version'] ?? null,
            'profile' => $results[0]['profileName'] ?? null,
        ];
    }

    private function findExecutable(string $base): ?string
    {
        $candidates = glob($base.'/verapdf-*/verapdf') ?: [];
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function failure(string $message, ?int $exitCode = null): array
    {
        return [
            'executed' => false,
            'valid' => false,
            'errors' => [$message],
            'warnings' => [],
            'exit_code' => $exitCode,
        ];
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory.'/'.$item;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}
