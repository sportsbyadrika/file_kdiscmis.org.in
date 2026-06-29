<?php
declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Database;
use App\Models\Attachment;
use App\Models\FileRecord;

/**
 * Attaches "<computer-number>.pdf" files to their records. Shared by the web
 * tool and the CLI script. Idempotent: a PDF already attached to a record
 * (same original filename) is skipped.
 */
final class PdfAttacher
{
    /** Default staging folder for uploaded PDFs: storage/import_pdfs. */
    public static function defaultDir(): string
    {
        return dirname(rtrim((string) Config::get('storage.uploads'), '/')) . '/import_pdfs';
    }

    /**
     * @param array<string,string> $map  computer number => File Reference No
     * @return array{found:int,attached:int,already:int,no_record:int,failed:int,results:array<int,array{name:string,status:string,detail:string}>}
     */
    public static function attach(string $app, string $pdfDir, array $map, int $userId, bool $dryRun = false, int $resultCap = 500): array
    {
        $pdfDir = rtrim($pdfDir, '/');
        $pdfs = array_merge(glob($pdfDir . '/*.pdf') ?: [], glob($pdfDir . '/*.PDF') ?: []);

        $base = rtrim((string) Config::get('storage.uploads'), '/');
        $summary = ['found' => count($pdfs), 'attached' => 0, 'already' => 0, 'no_record' => 0, 'failed' => 0, 'results' => []];

        foreach ($pdfs as $pdf) {
            $computer = pathinfo($pdf, PATHINFO_FILENAME);
            $ref = $map[$computer] ?? $computer; // fall back to computer number as the reference
            $name = $computer . '.pdf';

            $fileId = FileRecord::findIdByRef($app, $ref);
            if ($fileId === null) {
                $summary['no_record']++;
                self::push($summary, $resultCap, $name, 'no_record', 'No record with reference ' . $ref);
                continue;
            }

            $exists = Database::run(
                'SELECT 1 FROM file_attachments WHERE file_id = :f AND original_filename = :n AND is_deleted = 0 LIMIT 1',
                ['f' => $fileId, 'n' => $name]
            )->fetch();
            if ($exists) {
                $summary['already']++;
                continue;
            }

            if ($dryRun) {
                $summary['attached']++;
                self::push($summary, $resultCap, $name, 'would_attach', 'file #' . $fileId);
                continue;
            }

            try {
                $destDir = $base . '/' . $fileId;
                if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
                    throw new \RuntimeException('cannot create storage directory');
                }
                $stored = bin2hex(random_bytes(16)) . '.pdf';
                if (!@copy($pdf, $destDir . '/' . $stored)) {
                    throw new \RuntimeException('copy failed (check permissions)');
                }
                @chmod($destDir . '/' . $stored, 0640);

                Attachment::create($fileId, $name, $fileId . '/' . $stored, 'application/pdf', (int) filesize($pdf), $userId);
                $summary['attached']++;
            } catch (\Throwable $e) {
                $summary['failed']++;
                self::push($summary, $resultCap, $name, 'failed', $e->getMessage());
            }
        }

        return $summary;
    }

    /**
     * Parse a mapping CSV (Computer Number, File Reference No) into an array.
     *
     * @return array<string,string>
     */
    public static function parseMap(string $path): array
    {
        $map = [];
        $fh = @fopen($path, 'r');
        if ($fh === false) {
            return $map;
        }
        $first = true;
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if ($first) {
                // strip BOM and skip a header row if it looks like one
                if (isset($r[0])) {
                    $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $r[0]);
                }
                $first = false;
                $a = strtolower(trim((string) ($r[0] ?? '')));
                if (str_contains($a, 'computer') || str_contains($a, 'number')) {
                    continue; // header
                }
            }
            $comp = trim((string) ($r[0] ?? ''));
            $ref  = trim((string) ($r[1] ?? ''));
            if ($comp !== '' && $ref !== '') {
                $map[$comp] = $ref;
            }
        }
        fclose($fh);
        return $map;
    }

    private static function push(array &$summary, int $cap, string $name, string $status, string $detail): void
    {
        if (count($summary['results']) < $cap) {
            $summary['results'][] = ['name' => $name, 'status' => $status, 'detail' => $detail];
        }
    }
}
