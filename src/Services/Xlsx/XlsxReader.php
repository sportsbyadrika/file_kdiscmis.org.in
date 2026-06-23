<?php
declare(strict_types=1);

namespace App\Services\Xlsx;

/**
 * Minimal, dependency-free .xlsx reader. Returns the first worksheet as an
 * array of rows (each row an array of cell strings, aligned by column index).
 * Resolves shared strings and inline strings; numeric cells are returned as
 * their textual value.
 */
final class XlsxReader
{
    /**
     * @return array<int, array<int, string>>
     * @throws \RuntimeException on a malformed workbook
     */
    public static function rows(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('The file could not be opened as a spreadsheet.');
        }

        $shared = self::sharedStrings($zip);
        $sheetXml = self::firstSheetXml($zip);
        $zip->close();

        if ($sheetXml === null) {
            throw new \RuntimeException('No worksheet found in the file.');
        }

        $dom = new \DOMDocument();
        if (!@$dom->loadXML($sheetXml)) {
            throw new \RuntimeException('The worksheet could not be parsed.');
        }

        $rows = [];
        foreach ($dom->getElementsByTagName('row') as $rowEl) {
            /** @var \DOMElement $rowEl */
            $cells = [];
            $maxIdx = -1;
            $autoIdx = 0;
            foreach ($rowEl->getElementsByTagName('c') as $cEl) {
                /** @var \DOMElement $cEl */
                $ref = $cEl->getAttribute('r');
                $colIdx = $ref !== '' ? self::colIndex($ref) : $autoIdx;
                $autoIdx = $colIdx + 1;

                $type = $cEl->getAttribute('t');
                $value = '';
                if ($type === 'inlineStr') {
                    foreach ($cEl->getElementsByTagName('t') as $t) {
                        $value .= $t->textContent;
                    }
                } else {
                    $vEls = $cEl->getElementsByTagName('v');
                    if ($vEls->length > 0) {
                        $raw = $vEls->item(0)->textContent;
                        if ($type === 's') {
                            $value = $shared[(int) $raw] ?? '';
                        } else {
                            $value = $raw;
                        }
                    }
                }
                $cells[$colIdx] = trim($value);
                $maxIdx = max($maxIdx, $colIdx);
            }

            // Normalise to a dense 0..maxIdx array.
            $row = [];
            for ($i = 0; $i <= $maxIdx; $i++) {
                $row[$i] = $cells[$i] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return string[] */
    private static function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || $xml === '') {
            return [];
        }
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml)) {
            return [];
        }
        $out = [];
        foreach ($dom->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $text .= $t->textContent;
            }
            $out[] = $text;
        }
        return $out;
    }

    private static function firstSheetXml(\ZipArchive $zip): ?string
    {
        // Resolve the first sheet target via workbook rels; fall back to sheet1.
        $target = 'worksheets/sheet1.xml';
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $wbDom = new \DOMDocument();
            $relDom = new \DOMDocument();
            if (@$wbDom->loadXML($wb) && @$relDom->loadXML($rels)) {
                $sheet = $wbDom->getElementsByTagName('sheet')->item(0);
                if ($sheet instanceof \DOMElement) {
                    $rid = $sheet->getAttribute('r:id') ?: $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                    foreach ($relDom->getElementsByTagName('Relationship') as $rel) {
                        if ($rel->getAttribute('Id') === $rid) {
                            $target = ltrim($rel->getAttribute('Target'), '/');
                            break;
                        }
                    }
                }
            }
        }
        $candidates = ['xl/' . $target, $target, 'xl/worksheets/sheet1.xml'];
        foreach ($candidates as $name) {
            $xml = $zip->getFromName($name);
            if ($xml !== false && $xml !== '') {
                return $xml;
            }
        }
        return null;
    }

    /** Column reference ("AB12") -> 0-based column index. */
    private static function colIndex(string $ref): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
        $letters = $m[1] ?? 'A';
        $idx = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - 64);
        }
        return $idx - 1;
    }

    /** Convert an Excel date serial number to YYYY-MM-DD (1900 date system). */
    public static function excelSerialToDate(string $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }
        $serial = (int) round((float) $value);
        if ($serial < 1 || $serial > 60000) {
            return null;
        }
        // Excel's 1900 system has a leap-year bug; offset accordingly.
        $unix = ($serial - 25569) * 86400;
        return gmdate('Y-m-d', $unix);
    }
}
