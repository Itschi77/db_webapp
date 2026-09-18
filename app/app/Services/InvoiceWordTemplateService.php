<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class InvoiceWordTemplateService
{
    public function build(
        array $document,
        int $invoiceNumber,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $isPreview = false,
    ): string {
        $template = resource_path('invoicing/Rechnung-4.docx');
        if (! is_file($template)) {
            throw new RuntimeException('Die Word-Rechnungsvorlage fehlt.');
        }

        $directory = storage_path('app/invoice-docx');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Temporäres DOCX-Verzeichnis konnte nicht angelegt werden.');
        }

        $path = $directory.'/invoice-'.uniqid('', true).'.docx';
        if (! copy($template, $path)) {
            throw new RuntimeException('Die Word-Rechnungsvorlage konnte nicht kopiert werden.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Die Word-Rechnungsvorlage konnte nicht geöffnet werden.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                throw new RuntimeException('Die Word-Rechnungsvorlage enthält kein Hauptdokument.');
            }

            $run = $document['testRun'];
            $order = $document['order'];
            $address = $run['address'];
            $customer = $run['customer'];

            $fields = [
                'intKID' => '00-'.str_pad((string) $order->intKID, 6, '0', STR_PAD_LEFT),
                'intAufNr' => $this->formatOrderNumber($order),
                'strBuchungskonto' => (string) ($customer->strDatevKundenKonto ?? ''),
                'strEmail' => (string) ($address->strEmail ?? ''),
                'VATIDRECIPIENT' => trim((string) ($address->strUStIdNr ?? '')) ?: 'Nicht angegeben',
                'txtRechnungsdatum' => $run['invoiceDate']->format('d.m.Y'),
                'txtEmpfName' => $this->recipientName($address),
                'txtEmpfStrasse' => (string) ($address->strStrasse ?? ''),
                'txtEmpfOrt' => trim((string) ($address->strPLZ ?? '').' '.(string) ($address->strOrt ?? '')),
                'txtRechnung' => ($isPreview ? 'VORSCHAU – ' : '').(string) ($run['fulfillment']['documentType'] ?? 'Rechnung'),
                'txtRechnungsnummer' => (string) $invoiceNumber,
                'txtZahlungsbedingung' => (string) ($run['paymentText'] ?: ($run['payment']->strBezeichnung ?? '')),
                'txtSkonto1' => $this->skontoText($run['skonto'], 1),
                'txtSkonto2' => $this->skontoText($run['skonto'], 2),
                'txtSkonto3' => $this->skontoText($run['skonto'], 3),
                'txtRuecklastschrift' => (($run['fulfillment']['bankDebit'] ?? false) && (float) $run['gross'] > 0)
                    ? 'Im Fall einer Rücklastschrift ist die volle Summe zuzüglich einer Bearbeitungsgebühr von 13,- € netto auf eines unserer Konten zu überweisen.'
                    : '',
            ];

            foreach ($fields as $name => $value) {
                $xml = $this->replaceLegacyField($xml, $name, $value);
            }

            $xml = $this->replaceLineRows($xml, $run['documentRows']);
            $xml = $this->replaceTaxRows($xml, $run['taxByRate']);
            $xml = str_replace('__SUM_NET__', $this->xmlText($this->money((float) $run['net'])), $xml);
            $xml = str_replace('__GROSS__', $this->xmlText($this->money((float) $run['gross'])), $xml);
            $xml = $this->replaceMultilineToken($xml, '__INVOICE_NOTE__', (string) ($run['invoiceNote'] ?? ''));

            foreach (['__LINE_', '__TAX_', '__SUM_NET__', '__GROSS__', '__INVOICE_NOTE__'] as $marker) {
                if (str_contains($xml, $marker)) {
                    throw new RuntimeException('Die Word-Vorlage enthält nach dem Befüllen noch technische Platzhalter.');
                }
            }

            $zip->addFromString('word/document.xml', $xml);
        } finally {
            $zip->close();
        }

        return $path;
    }

    private function replaceLegacyField(string $xml, string $name, string $value): string
    {
        $namePos = strpos($xml, 'w:name w:val="'.$name.'"');
        if ($namePos === false) {
            throw new RuntimeException('Word-Feld '.$name.' fehlt in der Rechnungsvorlage.');
        }

        $separator = strpos($xml, 'w:fldCharType="separate"', $namePos);
        if ($separator === false) {
            throw new RuntimeException('Word-Feld '.$name.' ist unvollständig.');
        }

        $resultStart = strpos($xml, '</w:r>', $separator);
        if ($resultStart === false) {
            throw new RuntimeException('Word-Feld '.$name.' hat keinen Ergebnisbereich.');
        }
        $resultStart += strlen('</w:r>');

        $fieldEnd = strpos($xml, 'w:fldCharType="end"', $resultStart);
        if ($fieldEnd === false) {
            throw new RuntimeException('Word-Feld '.$name.' hat kein Ende.');
        }

        $between = substr($xml, $resultStart, $fieldEnd - $resultStart);
        if (! preg_match_all('~<w:r(?:\\s[^>]*)?>~', $between, $runMatches, PREG_OFFSET_CAPTURE) || empty($runMatches[0])) {
            throw new RuntimeException('Word-Feld '.$name.' konnte nicht ersetzt werden.');
        }
        $lastRun = end($runMatches[0]);
        $resultEnd = $resultStart + $lastRun[1];

        $resultXml = substr($xml, $resultStart, $resultEnd - $resultStart);
        $runProperties = '';
        if (preg_match('~<w:rPr>.*?</w:rPr>~s', $resultXml, $match)) {
            $runProperties = $match[0];
        }

        $replacement = '<w:r>'.$runProperties.$this->textRuns($value).'</w:r>';

        return substr($xml, 0, $resultStart).$replacement.substr($xml, $resultEnd);
    }

    private function replaceLineRows(string $xml, Collection $rows): string
    {
        [$prototype, $start, $length] = $this->prototypeRow($xml, '__LINE_POSITION__');
        $rendered = '';

        foreach ($rows->values() as $index => $row) {
            $line = $prototype;
            $replacements = [
                '__LINE_POSITION__' => (string) ($index + 1),
                '__LINE_ARTICLE__' => !empty($row->articleNumber) ? '('.trim((string) $row->articleNumber).')' : '',
                '__LINE_TAX__' => number_format((float) $row->taxRate, 0, ',', '.').'%',
                '__LINE_DESCRIPTION__' => (string) $row->description,
                '__LINE_QUANTITY__' => $this->singleLine((string) ($row->quantityLabel ?: '1')),
                '__LINE_NET__' => $this->money((float) $row->net),
            ];
            foreach ($replacements as $token => $value) {
                $line = $token === '__LINE_DESCRIPTION__'
                    ? $this->replaceMultilineToken($line, $token, $value)
                    : str_replace($token, $this->xmlText($value), $line);
            }
            $rendered .= $line;
        }

        return substr($xml, 0, $start).$rendered.substr($xml, $start + $length);
    }

    private function replaceTaxRows(string $xml, Collection $taxByRate): string
    {
        [$prototype, $start, $length] = $this->prototypeRow($xml, '__TAX_LABEL__');
        $rendered = '';

        foreach ($taxByRate as $rate => $amount) {
            $line = str_replace(
                ['__TAX_LABEL__', '__TAX_TOTAL__'],
                [
                    $this->xmlText('zzgl. '.number_format((float) $rate, 0, ',', '.').'% MwSt.'),
                    $this->xmlText($this->money((float) $amount)),
                ],
                $prototype,
            );
            $rendered .= $line;
        }

        return substr($xml, 0, $start).$rendered.substr($xml, $start + $length);
    }

    private function prototypeRow(string $xml, string $token): array
    {
        $quoted = preg_quote($token, '~');
        if (! preg_match('~<(?P<prefix>[A-Za-z0-9]+):tr\b[^>]*>(?:(?!</\k<prefix>:tr>).)*'.$quoted.'.*?</\k<prefix>:tr>~s', $xml, $match, PREG_OFFSET_CAPTURE)) {
            throw new RuntimeException('Zeilenprototyp '.$token.' fehlt in der Word-Vorlage.');
        }

        return [$match[0][0], $match[0][1], strlen($match[0][0])];
    }

    private function replaceMultilineToken(string $xml, string $token, string $value): string
    {
        $quoted = preg_quote($token, '~');
        $count = 0;
        $result = preg_replace_callback(
            '~<(?P<prefix>[A-Za-z0-9]+):t(?P<attrs>[^>]*)>'.$quoted.'</\\k<prefix>:t>~',
            function (array $match) use ($value) {
                $prefix = $match['prefix'];
                $attrs = $match['attrs'];
                $parts = preg_split('/\\R/u', $value) ?: [''];
                $rendered = '';
                foreach ($parts as $index => $part) {
                    if ($index > 0) {
                        $rendered .= '<'.$prefix.':br/>';
                    }
                    $rendered .= '<'.$prefix.':t'.$attrs.' xml:space="preserve">'.$this->xmlText($part).'</'.$prefix.':t>';
                }
                return $rendered;
            },
            $xml,
            1,
            $count,
        );

        if ($result === null || $count !== 1) {
            throw new RuntimeException('Mehrzeiliger Word-Platzhalter '.$token.' konnte nicht ersetzt werden.');
        }

        return $result;
    }

    private function textRuns(string $value): string
    {
        $parts = preg_split('/\R/u', $value) ?: [''];
        $xml = '';
        foreach ($parts as $index => $part) {
            if ($index > 0) {
                $xml .= '<w:br/>';
            }
            $xml .= '<w:t xml:space="preserve">'.$this->xmlText($part).'</w:t>';
        }
        return $xml;
    }

    private function xmlText(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function singleLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', '.').' €';
    }

    private function recipientName(?object $address): string
    {
        if (! $address) {
            return 'Rechnungsanschrift fehlt';
        }

        $name = trim((string) $address->strName);
        $attention = trim((string) ($address->strZuHaenden ?? ''));

        return $attention !== '' ? $name."\n".$attention : $name;
    }

    private function formatOrderNumber(object $order): string
    {
        $number = str_pad((string) $order->intAufNr, 6, '0', STR_PAD_LEFT);
        if (! empty($order->datErfassungsdatum)) {
            return CarbonImmutable::parse($order->datErfassungsdatum)->format('Ymd').'-'.$number;
        }

        return $number;
    }

    private function skontoText(Collection $skonto, int $level): string
    {
        $entry = $skonto->firstWhere('level', $level);
        if (! $entry) {
            return '';
        }

        return number_format((float) $entry['percent'], 2, ',', '.')
            .' % Skonto bis '.$entry['date']->format('d.m.Y')
            .' (Zahlbetrag '.$this->money((float) $entry['gross']).')';
    }
}
