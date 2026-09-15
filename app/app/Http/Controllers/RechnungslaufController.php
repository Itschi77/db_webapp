<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ZipArchive;

class RechnungslaufController extends Controller
{
    public function index()
    {
        $mode = session('frontend_mode', 'classic');
        return view($mode.'.rechnungslauf.index');
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'von' => ['required','date_format:Y-m-d'],
            'bis' => ['required','date_format:Y-m-d','after_or_equal:von'],
        ]);
        $von = Carbon::createFromFormat('Y-m-d', $validated['von'])->startOfDay();
        $bis = Carbon::createFromFormat('Y-m-d', $validated['bis'])->startOfDay();

        $rows = DB::connection('sqlsrv_accountings')->table('tblRechnung as r')
            ->join('tblAuftrag as a', 'r.intAufNr', '=', 'a.intAufNr')
            ->join('tblZahlungsbedingung as z', 'z.intID', '=', 'a.intZahlungsbedingungID')
            ->whereRaw('r.datRechnungsDatum >= DATEFROMPARTS(?,?,?) AND r.datRechnungsDatum < DATEADD(day,1,DATEFROMPARTS(?,?,?))', [
                $von->year,$von->month,$von->day,$bis->year,$bis->month,$bis->day,
            ])
            ->select(
                'r.datRechnungsDatum','r.intRechNr','r.strKundenNameAufRechnung','r.strKopieAuftragsbeschreibung',
                'r.fRechnungsbetrag','r.fRechnungsbetragMitSkonto1','r.datFaelligkeitsDatum','z.boolIstBankeinzug',
                'r.datBezahlDatum','r.fBezahlterBetrag','r.strZahlungskommentar','r.strPfadZurRechnung',
                'r.datForderungsausfallAbgeschriebenAm'
            )->orderBy('r.intRechNr')->get();

        $headers = ['Rechnungsdatum','Rechnungsnummer','Kunde','Auftragsbeschreibung','Betrag','Fälligkeit','Zahlung','LS Einzug am','Bezahlt am','Z-Betrag','Kommentar','Rechnungspfad','Abgeschrieben am'];
        $data = [];
        foreach ($rows as $r) {
            $lastschrift = (bool)$r->boolIstBankeinzug;
            $betrag = $lastschrift && (float)$r->fRechnungsbetragMitSkonto1 > 0
                ? (float)$r->fRechnungsbetragMitSkonto1
                : (float)$r->fRechnungsbetrag;
            $data[] = [
                $r->datRechnungsDatum, (int)$r->intRechNr, $r->strKundenNameAufRechnung, $r->strKopieAuftragsbeschreibung,
                $betrag, $r->datFaelligkeitsDatum, $lastschrift ? 'LS' : 'R', $lastschrift ? $r->datFaelligkeitsDatum : null,
                $r->datBezahlDatum, $r->fBezahlterBetrag === null ? null : (float)$r->fBezahlterBetrag, $r->strZahlungskommentar,
                $r->strPfadZurRechnung ? 'file:'.$r->strPfadZurRechnung : null, $r->datForderungsausfallAbgeschriebenAm,
            ];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rechnungslauf_');
        $xlsx = $tmp.'.xlsx';
        @unlink($tmp);
        $this->writeXlsx($xlsx, $headers, $data);
        $filename = 'Rechnungslauf_'.$von->format('Y-m-d').'_bis_'.$bis->format('Y-m-d').'.xlsx';
        return response()->download($xlsx, $filename, ['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
    }

    private function writeXlsx(string $path, array $headers, array $rows): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) abort(500, 'XLSX-Datei konnte nicht erstellt werden.');
        $sheetRows = [];
        $sheetRows[] = $this->xlsxRow(1, $headers, true);
        foreach ($rows as $i => $row) $sheetRows[] = $this->xlsxRow($i+2, $row, false);
        $lastRow = max(1, count($rows)+1);
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols><col min="1" max="1" width="14" customWidth="1"/><col min="2" max="2" width="16" customWidth="1"/><col min="3" max="3" width="30" customWidth="1"/><col min="4" max="4" width="45" customWidth="1"/><col min="5" max="5" width="14" customWidth="1"/><col min="6" max="6" width="14" customWidth="1"/><col min="7" max="7" width="10" customWidth="1"/><col min="8" max="9" width="14" customWidth="1"/><col min="10" max="10" width="14" customWidth="1"/><col min="11" max="11" width="32" customWidth="1"/><col min="12" max="12" width="55" customWidth="1"/><col min="13" max="13" width="16" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData><autoFilter ref="A1:M'.$lastRow.'"/></worksheet>';
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rechnungslauf" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $this->xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
    }

    private function xlsxRow(int $rowNum, array $values, bool $header): string
    {
        $xml = '<row r="'.$rowNum.'">';
        foreach ($values as $i => $value) {
            $ref = $this->columnName($i+1).$rowNum;
            if ($header) { $xml .= $this->inlineCell($ref, (string)$value, 1); continue; }
            if (in_array($i, [0,5,7,8,12], true) && $value) {
                $dt = Carbon::parse($value); $serial = ($dt->timestamp / 86400) + 25569;
                $xml .= '<c r="'.$ref.'" s="2"><v>'.$serial.'</v></c>'; continue;
            }
            if (in_array($i, [4,9], true) && $value !== null) { $xml .= '<c r="'.$ref.'" s="3"><v>'.((float)$value).'</v></c>'; continue; }
            if ($i === 1 && $value !== null) { $xml .= '<c r="'.$ref.'"><v>'.((int)$value).'</v></c>'; continue; }
            $xml .= $this->inlineCell($ref, $value === null ? '' : (string)$value, $i === 3 || $i === 10 || $i === 11 ? 4 : 0);
        }
        return $xml.'</row>';
    }

    private function inlineCell(string $ref, string $value, int $style=0): string
    {
        return '<c r="'.$ref.'" t="inlineStr"'.($style ? ' s="'.$style.'"' : '').'><is><t xml:space="preserve">'.htmlspecialchars($value, ENT_XML1|ENT_QUOTES, 'UTF-8').'</t></is></c>';
    }

    private function columnName(int $n): string
    {
        $s=''; while($n>0){$n--; $s=chr(65+($n%26)).$s; $n=intdiv($n,26);} return $s;
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><numFmts count="2"><numFmt numFmtId="164" formatCode="dd.mm.yyyy"/><numFmt numFmtId="165" formatCode="#,##0.00 [$€-407]"/></numFmts><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs></styleSheet>';
    }
}
