<?php
declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Database;
use App\Models\Attachment;
use App\Models\FileRecord;

/**
 * Attaches PDF files to records. Supports three matching modes and processes
 * in chunks so very large batches (10k+ files) never time out.
 *
 *   filename : reference is taken from the file name up to the first "_",
 *              with separators normalised — e.g. "1-2020-KDISC_FairCopy_7.pdf"
 *              matches File/Doc reference "1/2020/KDISC". A record may have
 *              many PDFs (each distinct file name = a separate attachment).
 *   exact    : the whole file name (minus .pdf) is the reference (normalised).
 *   map      : <computer-number>.pdf via a mapping CSV (eOffice migration).
 *
 * Idempotent: a PDF already attached to a record (same original file name) is
 * skipped, so runs can be repeated safely.
 */
final class PdfAttacher
{
    public const MODES = ['filename', 'exact', 'map'];

    /** Default staging folder (FTP target): storage/import_pdfs. */
    public static function defaultDir(): string
    {
        return dirname(rtrim((string) Config::get('storage.uploads'), '/')) . '/import_pdfs';
    }

    /** Recursively list *.pdf paths under $dir, sorted deterministically. */
    public static function listPdfs(string $dir): array
    {
        $dir = rtrim($dir, '/');
        if (!is_dir($dir)) {
            return [];
        }
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'pdf') {
                $out[] = $f->getPathname();
            }
        }
        sort($out, SORT_STRING);
        return $out;
    }

    /** Normalise a reference/candidate: lowercase, separators -> '-'. */
    public static function normalizeRef(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }

    /**
     * Build a normalised reference -> file id index for an app.
     *
     * @return array{index: array<string,int>, ambiguous: array<string,bool>}
     */
    public static function buildRefIndex(string $app): array
    {
        $rows = Database::run(
            'SELECT id, reference_no FROM files WHERE source_app = :app AND is_deleted = 0',
            ['app' => $app]
        )->fetchAll();

        $index = [];
        $ambiguous = [];
        foreach ($rows as $r) {
            $key = self::normalizeRef((string) $r['reference_no']);
            if ($key === '') {
                continue;
            }
            if (isset($index[$key]) && $index[$key] !== (int) $r['id']) {
                $ambiguous[$key] = true;
            } else {
                $index[$key] = (int) $r['id'];
            }
        }
        return ['index' => $index, 'ambiguous' => $ambiguous];
    }

    /** Candidate reference derived from a file name (without extension). */
    public static function deriveCandidate(string $stem, string $mode): string
    {
        if ($mode === 'filename') {
            return explode('_', $stem, 2)[0];
        }
        return $stem; // exact
    }

    /**
     * Attach a specific set of PDF paths.
     *
     * @param string[]              $paths
     * @param array<string,string>  $map        computer-number => reference (map mode)
     * @param array{index:array<string,int>,ambiguous:array<string,bool>}|null $refIndex
     * @return array<string,mixed>
     */
    public static function attachFiles(
        string $app,
        array $paths,
        string $mode,
        array $map,
        ?array $refIndex,
        int $userId,
        bool $deleteAfter = false,
        bool $dryRun = false,
        int $resultCap = 100
    ): array {
        $base = rtrim((string) Config::get('storage.uploads'), '/');
        $s = ['processed' => 0, 'attached' => 0, 'already' => 0, 'no_record' => 0, 'ambiguous' => 0, 'failed' => 0, 'results' => []];

        foreach ($paths as $path) {
            $s['processed']++;
            $name = basename($path);
            $stem = pathinfo($name, PATHINFO_FILENAME);

            // Resolve the target record.
            $fileId = null;
            $refDisp = '';
            if ($mode === 'map') {
                $refDisp = $map[$stem] ?? $stem;
                $fileId = FileRecord::findIdByRef($app, $refDisp);
            } else {
                $cand = self::deriveCandidate($stem, $mode);
                $refDisp = $cand;
                $key = self::normalizeRef($cand);
                if ($refIndex !== null && isset($refIndex['ambiguous'][$key])) {
                    $s['ambiguous']++;
                    self::push($s, $resultCap, $name, 'ambiguous', 'multiple records match ' . $cand);
                    continue;
                }
                $fileId = $refIndex['index'][$key] ?? null;
            }

            if ($fileId === null) {
                $s['no_record']++;
                self::push($s, $resultCap, $name, 'no_record', 'no record for ' . $refDisp);
                continue;
            }

            $exists = Database::run(
                'SELECT 1 FROM file_attachments WHERE file_id = :f AND original_filename = :n AND is_deleted = 0 LIMIT 1',
                ['f' => $fileId, 'n' => $name]
            )->fetch();
            if ($exists) {
                $s['already']++;
                continue;
            }

            if ($dryRun) {
                $s['attached']++;
                continue;
            }

            try {
                $destDir = $base . '/' . $fileId;
                if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
                    throw new \RuntimeException('cannot create storage directory');
                }
                $stored = bin2hex(random_bytes(16)) . '.pdf';
                if (!@copy($path, $destDir . '/' . $stored)) {
                    throw new \RuntimeException('copy failed (check permissions/space)');
                }
                @chmod($destDir . '/' . $stored, 0640);
                Attachment::create($fileId, $name, $fileId . '/' . $stored, 'application/pdf', (int) filesize($path), $userId);
                $s['attached']++;
                if ($deleteAfter) {
                    @unlink($path);
                }
            } catch (\Throwable $e) {
                $s['failed']++;
                self::push($s, $resultCap, $name, 'failed', $e->getMessage());
            }
        }

        return $s;
    }

    /**
     * Whole-directory convenience (used by the CLI). Small batches only.
     * @return array<string,mixed>
     */
    public static function attach(string $app, string $pdfDir, array $map, int $userId, bool $dryRun = false, int $resultCap = 1000): array
    {
        $paths = self::listPdfs($pdfDir);
        $refIndex = self::buildRefIndex($app);
        $r = self::attachFiles($app, $paths, empty($map) ? 'filename' : 'map', $map, $refIndex, $userId, false, $dryRun, $resultCap);
        $r['found'] = count($paths);
        return $r;
    }

    /** @return array<string,string> computer-number => reference */
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
                if (isset($r[0])) {
                    $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $r[0]);
                }
                $first = false;
                $a = strtolower(trim((string) ($r[0] ?? '')));
                if (str_contains($a, 'computer') || str_contains($a, 'number')) {
                    continue;
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

    private static function push(array &$s, int $cap, string $name, string $status, string $detail): void
    {
        if (count($s['results']) < $cap) {
            $s['results'][] = ['name' => $name, 'status' => $status, 'detail' => $detail];
        }
    }
}
