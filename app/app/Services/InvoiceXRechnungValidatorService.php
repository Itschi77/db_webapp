<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use RuntimeException;

class InvoiceXRechnungValidatorService
{
    public function available(): bool
    {
        return is_file('/opt/kosit-xrechnung/validator.jar')
            && is_file('/opt/kosit-xrechnung/config/scenarios.xml')
            && trim((string) shell_exec('command -v java')) !== '';
    }

    public function validate(string $xml): array
    {
        if (! $this->available()) {
            return [
                'executed' => false,
                'valid' => false,
                'errors' => ['KoSIT-Validator ist nicht installiert.'],
                'report_xml' => null,
            ];
        }

        $dir = storage_path('app/xrechnung-validation/'.uniqid('', true));
        if (! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Temporäres XRechnung-Prüfverzeichnis konnte nicht angelegt werden.');
        }

        $invoiceFile = $dir.'/invoice.xml';
        file_put_contents($invoiceFile, $xml);

        $command = sprintf(
            'cd %s && java -jar %s -s %s -r %s -o %s -h %s 2>&1',
            escapeshellarg('/opt/kosit-xrechnung/config'),
            escapeshellarg('/opt/kosit-xrechnung/validator.jar'),
            escapeshellarg('/opt/kosit-xrechnung/config/scenarios.xml'),
            escapeshellarg('/opt/kosit-xrechnung/config'),
            escapeshellarg($dir),
            escapeshellarg($invoiceFile),
        );

        exec($command, $output, $exitCode);
        $reportFile = $dir.'/invoice-report.xml';
        $report = is_file($reportFile) ? file_get_contents($reportFile) : false;

        if ($report === false) {
            $this->removeDirectory($dir);
            return [
                'executed' => true,
                'valid' => false,
                'errors' => ['KoSIT hat keinen XML-Prüfbericht erzeugt.', implode("\n", $output)],
                'report_xml' => null,
                'exit_code' => $exitCode,
            ];
        }

        $dom = new DOMDocument();
        $dom->loadXML($report);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('rep', 'http://www.xoev.de/de/validator/varl/1');

        $validAttr = $xpath->evaluate('string(/*/@valid)');
        $valid = strtolower($validAttr) === 'true';
        $errors = [];
        foreach ($xpath->query('//rep:message[@level="error"] | //rep:message[@level="fatal"]') ?: [] as $message) {
            $text = trim($message->textContent);
            if ($text !== '') {
                $errors[] = $text;
            }
        }

        $result = [
            'executed' => true,
            'valid' => $valid,
            'errors' => array_values(array_unique($errors)),
            'report_xml' => $report,
            'exit_code' => $exitCode,
        ];

        $this->removeDirectory($dir);
        return $result;
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
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
