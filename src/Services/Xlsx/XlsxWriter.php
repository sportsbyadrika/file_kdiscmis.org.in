<?php
declare(strict_types=1);

namespace App\Services\Xlsx;

/**
 * Minimal, dependency-free .xlsx writer (just enough for our templates):
 * one worksheet, a styled+locked frozen header row, an optional greyed
 * sample row, column widths, and sheet protection. Uses inline strings.
 *
 * Avoids PhpSpreadsheet (no Composer on the target host).
 */
final class XlsxWriter
{
    /**
     * Build an .xlsx workbook in memory and return its bytes.
     *
     * @param string[]            $headers
     * @param array<int,string>   $sampleRow
     * @param array<int,float>    $colWidths  (1-based column => width)
     */
    public static function build(array $headers, array $sampleRow = [], array $colWidths = []): string
    {
        $sheet = self::sheetXml($headers, $sampleRow, $colWidths);

        $files = [
            '[Content_Types].xml'          => self::contentTypes(),
            '_rels/.rels'                  => self::rootRels(),
            'xl/workbook.xml'              => self::workbook(),
            'xl/_rels/workbook.xml.rels'   => self::workbookRels(),
            'xl/styles.xml'                => self::styles(),
            'xl/worksheets/sheet1.xml'     => $sheet,
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    public static function colLetter(int $index): string // 0-based -> A, B, ... AA
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $rem = ($index - 1) % 26;
            $letter = chr(65 + $rem) . $letter;
            $index = intdiv($index - 1, 26);
        }
        return $letter;
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function sheetXml(array $headers, array $sampleRow, array $colWidths): string
    {
        $cols = '';
        if ($colWidths) {
            $cols .= '<cols>';
            foreach ($colWidths as $i => $w) {
                $cols .= '<col min="' . $i . '" max="' . $i . '" width="' . $w . '" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }

        // Header row (style 1 = bold/fill/locked)
        $r1 = '<row r="1">';
        foreach ($headers as $c => $h) {
            $ref = self::colLetter($c) . '1';
            $r1 .= '<c r="' . $ref . '" s="1" t="inlineStr"><is><t xml:space="preserve">' . self::esc($h) . '</t></is></c>';
        }
        $r1 .= '</row>';

        // Sample row (style 2 = grey/unlocked)
        $r2 = '';
        if ($sampleRow) {
            $r2 = '<row r="2">';
            foreach ($headers as $c => $h) {
                $ref = self::colLetter($c) . '2';
                $val = (string) ($sampleRow[$c] ?? '');
                $r2 .= '<c r="' . $ref . '" s="2" t="inlineStr"><is><t xml:space="preserve">' . self::esc($val) . '</t></is></c>';
            }
            $r2 .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . $cols
            . '<sheetData>' . $r1 . $r2 . '</sheetData>'
            . '<sheetProtection sheet="1" objects="1" scenarios="1" selectLockedCells="1" selectUnlockedCells="1"/>'
            . '</worksheet>';
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Template" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }

    private static function styles(): string
    {
        // fonts: 0 normal, 1 bold(white)
        // fills: 0 none, 1 gray125, 2 header(blue), 3 sample(grey)
        // cellXfs: 0 normal(unlocked), 1 header(bold/blue/locked), 2 sample(grey/unlocked)
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F3A5F"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFEFEFEF"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border/></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyProtection="1"><protection locked="0"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1" applyProtection="1"><protection locked="0"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
