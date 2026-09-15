<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use ZipArchive;

class BranchenAuswertungController extends Controller
{
    public function index()
    {
        $rows = $this->reportRows();
        $groups = $rows->groupBy('strBezeichnung');
        $mode = session('frontend_mode', 'classic');

        return view($mode.'.branchen-auswertung.index', compact('rows', 'groups'));
    }

    public function export()
    {
        $rows = $this->exportRows();
        $headers = ['Branche', 'Kunden-ID', 'Kunde', 'Telefax', 'Branchencode'];
        $data = $rows->map(fn ($r) => [
            $r->strBezeichnung,
            (int) $r->intID,
            $r->strName,
            $r->strTelefax,
            $r->strCode,
        ])->all();

        $tmp = tempnam(sys_get_temp_dir(), 'branchen_');
        $xlsx = $tmp.'.xlsx';
        @unlink($tmp);
        $this->writeXlsx($xlsx, $headers, $data);

        return response()->download(
            $xlsx,
            'BranchenExport_'.now()->format('Y-m-d').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    private function reportRows()
    {
        return DB::connection('sqlsrv_topsnetdb_safe')->table('tblKunde as k')
            ->join('tblKundenBranchen as kb', 'k.intID', '=', 'kb.intKundeID')
            ->join('tblBranchen as b', 'b.strCode', '=', 'kb.strBranchenCode')
            ->select('b.strBezeichnung', 'kb.intKundeID', 'k.strName', 'k.strStrasse', 'k.strOrt', 'k.strPLZ', 'k.strTelefon', 'b.strCode')
            ->orderBy('b.strBezeichnung')->orderBy('k.strName')->get();
    }

    private function exportRows()
    {
        return DB::connection('sqlsrv_topsnetdb_safe')->table('tblKunde as k')
            ->join('tblKundenBranchen as kb', 'k.intID', '=', 'kb.intKundeID')
            ->join('tblBranchen as b', 'b.strCode', '=', 'kb.strBranchenCode')
            ->select('b.strBezeichnung', 'k.intID', 'k.strName', 'k.strTelefax', 'b.strCode')
            ->orderBy('b.strBezeichnung')->orderBy('k.strName')->get();
    }

    private function writeXlsx(string $path, array $headers, array $rows): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'XLSX-Datei konnte nicht erstellt werden.');
        }

        $sheetRows = [$this->xlsxRow(1, $headers, true)];
        foreach ($rows as $i => $row) {
            $sheetRows[] = $this->xlsxRow($i + 2, $row, false);
        }
        $lastRow = max(1, count($rows) + 1);

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols><col min="1" max="1" width="34" customWidth="1"/><col min="2" max="2" width="13" customWidth="1"/><col min="3" max="3" width="38" customWidth="1"/><col min="4" max="4" width="24" customWidth="1"/><col min="5" max="5" width="14" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData><autoFilter ref="A1:E'.$lastRow.'"/></worksheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="BranchenExport" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
    }

    private function xlsxRow(int $rowNum, array $values, bool $header): string
    {
        $xml = '<row r="'.$rowNum.'">';
        foreach ($values as $i => $value) {
            $ref = chr(65 + $i).$rowNum;
            if (!$header && $i === 1) {
                $xml .= '<c r="'.$ref.'"><v>'.((int) $value).'</v></c>';
                continue;
            }
            $style = $header ? ' s="1"' : '';
            $xml .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
        }
        return $xml.'</row>';
    }
}
