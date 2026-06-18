<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Attachment;

/**
 * Builds a {@see Pdf} document for a file record in one of three view modes.
 *
 *   minimum  — one-page cover slip (ref, title, date, status, dept/type)
 *   standard — all metadata + (optional) attachments + (optional) history
 *   detailed — standard + the full file note
 *
 * Minimum always excludes attachments and history, regardless of options.
 */
final class PdfReport
{
    /**
     * @param array $ctx {
     *   app, config, record, fields, attachments, history, fileUrl, user,
     *   mode, include_attachments, include_history, include_qr
     * }
     */
    public static function build(array $ctx): Pdf
    {
        $mode   = in_array($ctx['mode'] ?? 'standard', ['minimum', 'standard', 'detailed'], true) ? $ctx['mode'] : 'standard';
        $config = $ctx['config'];
        $record = $ctx['record'];

        $isMinimum = $mode === 'minimum';
        $inclAtt   = !$isMinimum && !empty($ctx['include_attachments']);
        $inclHist  = !$isMinimum && !empty($ctx['include_history']);
        $inclQr    = !empty($ctx['include_qr']);

        $pdf = new Pdf('P', 'mm', 'A4');
        $pdf->SetTitle($config['label'] . ' Record ' . $record['reference_no'], true);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AliasNbPages();

        $pdf->docTitle = $config['label'] . ' File Record';
        $pdf->docRef   = $config['labels']['ref'] . ': ' . $record['reference_no'];
        $pdf->genBy    = (string) ($ctx['user'] ?? '');
        $pdf->genOn    = date('d-m-Y H:i');

        $pdf->AddPage();

        // Cover heading
        $pdf->SetFont('Helvetica', 'B', 15);
        $title = self::titleOf($config, $record);
        $pdf->MultiCell(0, 7, self::asText($title), 0, 'L');
        $pdf->Ln(1);

        // Metadata
        $pdf->sectionTitle($isMinimum ? 'Summary' : 'Metadata');
        foreach (self::pairs($config, $record, $ctx['fields'], $isMinimum) as [$label, $value]) {
            $pdf->kv($label, $value);
        }

        // QR (optional)
        if ($inclQr && !empty($ctx['fileUrl'])) {
            $pdf->sectionTitle('Quick Link');
            $y = $pdf->GetY();
            $pdf->drawQr((string) $ctx['fileUrl'], 12, $y, 28);
            $pdf->SetXY(45, $y + 4);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->MultiCell(0, 5, self::asText('Scan to open this file:' . "\n" . $ctx['fileUrl']), 0, 'L');
            $pdf->SetY(max($pdf->GetY(), $y + 30));
        }

        // Attachments
        if ($inclAtt) {
            $pdf->sectionTitle('Attachments');
            $rows = [];
            foreach (($ctx['attachments'] ?? []) as $a) {
                $rows[] = [
                    (string) $a['original_filename'],
                    Attachment::extension((string) $a['original_filename']),
                    self::bytes((int) $a['file_size_bytes']),
                    (string) ($a['uploaded_by_name'] ?? ''),
                    self::date((string) ($a['uploaded_at'] ?? ''), true),
                ];
            }
            $pdf->table([['Filename', 70], ['Type', 18], ['Size', 22], ['Uploaded By', 40], ['Date', 40]], $rows);
        }

        // Note (detailed only)
        if ($mode === 'detailed') {
            $pdf->sectionTitle('File Note');
            $pdf->paragraph(self::htmlToText((string) ($record['file_note'] ?? '')));
        }

        // History
        if ($inclHist) {
            $pdf->sectionTitle('Transaction History');
            $rows = [];
            foreach (($ctx['history'] ?? []) as $h) {
                $rows[] = [
                    self::date((string) $h['history_date']),
                    (string) $h['transaction_type'],
                    (string) ($h['from_status'] ?? ''),
                    (string) ($h['to_status'] ?? ''),
                    (string) ($h['note'] ?? ''),
                    (string) $h['performed_by_name'],
                ];
            }
            $pdf->table([['Date', 24], ['Type', 34], ['From', 22], ['To', 22], ['Note', 48], ['By', 40]], $rows);
        }

        return $pdf;
    }

    /** @return array<int,array{0:string,1:string}> */
    private static function pairs(array $config, array $record, array $fields, bool $minimum): array
    {
        $labels = $config['labels'];
        $pairs = [[$labels['ref'], (string) $record['reference_no']]];

        if ($minimum) {
            $pairs[] = [$labels['title'], self::titleOf($config, $record)];
            $pairs[] = [$labels['doc_date'], self::date((string) ($record[self::dateCol($config)] ?? ''))];
            $pairs[] = ['Status', (string) $record['status']];
            $pairs[] = [$labels['group'], (string) ($record[self::col($fields, 'group')] ?? '')];
            $pairs[] = [$labels['category'], (string) ($record[self::col($fields, 'category')] ?? '')];
            return $pairs;
        }

        $remarksCol = null;
        foreach ($fields as $key => [$col, $label, $required]) {
            if ($key === 'remarks') { $remarksCol = $col; continue; }
            $val = (string) ($record[$col] ?? '');
            if ($key === 'doc_date') {
                $val = self::date($val);
            }
            $pairs[] = [$label, $val];
        }
        $pairs[] = ['Status', (string) $record['status']];
        $pairs[] = ['Uploaded By', (string) ($record['uploaded_by_name'] ?? '')];
        $pairs[] = ['Upload Date', self::date((string) ($record['upload_date'] ?? ''), true)];
        $pairs[] = ['Last Updated By', (string) ($record['updated_by_name'] ?? '')];
        $pairs[] = ['Last Updated On', self::date((string) ($record['last_updated_on'] ?? ''), true)];
        if ($remarksCol) {
            $pairs[] = ['Remarks', (string) ($record[$remarksCol] ?? '')];
        }
        return $pairs;
    }

    private static function titleOf(array $config, array $record): string
    {
        $col = str_replace('m.', '', $config['title_col']);
        return (string) ($record[$col] ?? $record['reference_no']);
    }

    private static function dateCol(array $config): string
    {
        return str_replace('m.', '', $config['date_col']);
    }

    private static function col(array $fields, string $key): string
    {
        return $fields[$key][0] ?? $key;
    }

    private static function date(string $value, bool $withTime = false): string
    {
        if ($value === '' || str_starts_with($value, '0000')) return '-';
        $ts = strtotime($value);
        if (!$ts) return '-';
        return $withTime ? date('d-m-Y H:i', $ts) : date('d-m-Y', $ts);
    }

    private static function bytes(int $b): string
    {
        return function_exists('format_bytes') ? format_bytes($b) : (string) $b;
    }

    private static function asText(string $s): string
    {
        return $s;
    }

    /** Convert stored note HTML to readable plain text with line breaks. */
    private static function htmlToText(string $html): string
    {
        $html = preg_replace('#<\s*br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</\s*(p|div|li|h[1-6]|tr)\s*>#i', "\n", $html);
        $html = preg_replace('#<\s*li[^>]*>#i', '- ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        return trim($text);
    }
}
