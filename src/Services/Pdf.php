<?php
declare(strict_types=1);

namespace App\Services;

if (!defined('FPDF_FONTPATH')) {
    define('FPDF_FONTPATH', ROOT_PATH . '/vendor/fpdf/font/');
}
require_once ROOT_PATH . '/vendor/fpdf/fpdf.php';
require_once ROOT_PATH . '/vendor/qrcode/qrcode.php';

/**
 * A4-portrait PDF document with a per-page header (logo placeholder, title,
 * ref no.) and footer ("Generated on … by …" + "Page X of Y"). Built and
 * streamed entirely in memory — never written to disk.
 *
 * Extends the vendored FPDF (global namespace).
 */
final class Pdf extends \FPDF
{
    public string $docTitle = 'File Record';
    public string $docRef   = '';
    public string $genBy    = '';
    public string $genOn    = '';
    /** @var array{int,int,int} */
    public array $accent = [31, 58, 95];

    public function Header(): void
    {
        // Logo placeholder (coloured rounded box with initials)
        $this->SetFillColor($this->accent[0], $this->accent[1], $this->accent[2]);
        $this->Rect(10, 9, 16, 11, 'F');
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 9);
        $this->Cell(16, 11, 'FR', 0, 0, 'C');

        // Title + ref
        $this->SetTextColor(33, 37, 41);
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetXY(29, 9);
        $this->Cell(0, 6, $this->conv($this->docTitle), 0, 2, 'L');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(110, 117, 125);
        $this->Cell(0, 5, $this->conv($this->docRef), 0, 1, 'L');

        // Rule
        $this->SetDrawColor(210, 214, 220);
        $this->Line(10, 23, 200, 23);
        $this->SetTextColor(33, 37, 41);
        $this->SetY(28);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetDrawColor(210, 214, 220);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(110, 117, 125);
        $left = 'Generated on ' . $this->genOn . ($this->genBy !== '' ? ' by ' . $this->genBy : '');
        $this->Cell(0, 6, $this->conv($left), 0, 0, 'L');
        $this->Cell(0, 6, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
        $this->SetTextColor(33, 37, 41);
    }

    // --- Layout helpers ------------------------------------------------

    public function sectionTitle(string $text): void
    {
        $this->Ln(2);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor($this->accent[0], $this->accent[1], $this->accent[2]);
        $this->Cell(0, 7, $this->conv($text), 0, 1, 'L');
        $this->SetDrawColor(220, 224, 230);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        $this->SetTextColor(33, 37, 41);
    }

    /** Key/value row (label column + wrapped value). */
    public function kv(string $label, string $value): void
    {
        $labelW = 50;
        $valueW = 140;
        $this->SetFont('Helvetica', 'B', 9);
        $x = $this->GetX();
        $y = $this->GetY();
        $this->MultiCell($labelW, 5.5, $this->conv($label), 0, 'L');
        $yAfterLabel = $this->GetY();
        $this->SetXY($x + $labelW, $y);
        $this->SetFont('Helvetica', '', 9);
        $this->MultiCell($valueW, 5.5, $this->conv($value !== '' ? $value : '-'), 0, 'L');
        $yAfterValue = $this->GetY();
        $this->SetY(max($yAfterLabel, $yAfterValue));
    }

    public function paragraph(string $text): void
    {
        $this->SetFont('Helvetica', '', 9.5);
        $this->MultiCell(0, 5.5, $this->conv($text !== '' ? $text : '(empty)'), 0, 'L');
        $this->Ln(1);
    }

    /**
     * Simple bordered table.
     *
     * @param array<int,array{0:string,1:float}> $columns  [label, widthMm]
     * @param array<int,array<int,string>>       $rows
     */
    public function table(array $columns, array $rows): void
    {
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetFillColor(238, 241, 245);
        $this->SetDrawColor(210, 214, 220);
        foreach ($columns as [$label, $w]) {
            $this->Cell($w, 7, $this->conv($label), 1, 0, 'L', true);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 8.5);
        if (empty($rows)) {
            $total = array_sum(array_map(static fn ($c) => $c[1], $columns));
            $this->Cell($total, 7, $this->conv('No records.'), 1, 1, 'C');
            return;
        }
        foreach ($rows as $row) {
            // Page-break guard for long tables
            if ($this->GetY() > 270) {
                $this->AddPage();
                $this->SetFont('Helvetica', 'B', 8.5);
                foreach ($columns as [$label, $w]) {
                    $this->Cell($w, 7, $this->conv($label), 1, 0, 'L', true);
                }
                $this->Ln();
                $this->SetFont('Helvetica', '', 8.5);
            }
            foreach ($columns as $i => [$label, $w]) {
                $text = $this->truncateToWidth((string) ($row[$i] ?? ''), $w - 2);
                $this->Cell($w, 6.5, $this->conv($text), 1, 0, 'L');
            }
            $this->Ln();
        }
    }

    /** Draw a QR code (from a boolean matrix) as filled vector squares. */
    public function drawQr(string $url, float $x, float $y, float $sizeMm): void
    {
        try {
            $qr = \QRCode::getMinimumQRCode($url, QR_ERROR_CORRECT_LEVEL_M);
            $n = $qr->getModuleCount();
        } catch (\Throwable $e) {
            return; // QR is best-effort; never break the document
        }
        $module = $sizeMm / $n;
        $this->SetFillColor(0, 0, 0);
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if ($qr->isDark($r, $c)) {
                    $this->Rect($x + $c * $module, $y + $r * $module, $module, $module, 'F');
                }
            }
        }
    }

    private function truncateToWidth(string $text, float $maxW): string
    {
        if ($this->GetStringWidth($this->conv($text)) <= $maxW) {
            return $text;
        }
        while ($text !== '' && $this->GetStringWidth($this->conv($text . '…')) > $maxW) {
            $text = mb_substr($text, 0, -1);
        }
        return $text . '…';
    }

    /** Convert UTF-8 to the cp1252 page FPDF's core fonts expect. */
    private function conv(string $s): string
    {
        $s = str_replace(['–', '—', '→', '“', '”', '‘', '’', '…', '•'], ['-', '-', '->', '"', '"', "'", "'", '...', '*'], $s);
        $out = @iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
        return $out !== false ? $out : $s;
    }
}
