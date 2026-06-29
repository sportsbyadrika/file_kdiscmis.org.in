<?php
declare(strict_types=1);

/**
 * Attach eOffice PDF files to their records.
 *
 * Each PDF is named "<Computer Number>.pdf". This script maps the computer
 * number to the imported File Reference No (via a mapping CSV produced during
 * conversion), finds the matching record, copies the PDF into the app's managed
 * storage, and registers a file_attachments row.
 *
 * Usage:
 *   php bin/attach_pdfs.php --dir=/path/to/pdfs --map=/path/to/computer_to_ref.csv [--app=eoffice] [--dry-run]
 *
 *   --dir      Folder containing the <computer>.pdf files (required)
 *   --map      computer_to_ref.csv (Computer Number,File Reference No). Optional:
 *              if omitted, the PDF's computer number is used directly as the
 *              File Reference No.
 *   --app      eoffice (default) | ospyndocs
 *   --dry-run  Report what would happen without writing anything
 *
 * Safe to re-run: a PDF already attached to a record (same original filename)
 * is skipped.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config;
use App\Database;
use App\Models\Attachment;
use App\Models\FileRecord;

// ---- args ----------------------------------------------------------
$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    }
}
$dir    = isset($args['dir']) ? rtrim((string) $args['dir'], '/') : '';
$mapCsv = isset($args['map']) ? (string) $args['map'] : '';
$app    = isset($args['app']) ? (string) $args['app'] : 'eoffice';
$dryRun = isset($args['dry-run']);

if ($dir === '' || !is_dir($dir)) {
    fwrite(STDERR, "Provide --dir=/path/to/pdfs (the folder is required and must exist).\n");
    exit(1);
}
if (!in_array($app, ['eoffice', 'ospyndocs'], true)) {
    fwrite(STDERR, "--app must be eoffice or ospyndocs.\n");
    exit(1);
}

@set_time_limit(0);

// ---- mapping computer number -> reference_no -----------------------
$map = [];
if ($mapCsv !== '') {
    if (!is_file($mapCsv)) {
        fwrite(STDERR, "Mapping file not found: {$mapCsv}\n");
        exit(1);
    }
    $fh = fopen($mapCsv, 'r');
    $first = true;
    while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
        if ($first) { $first = false; continue; } // header
        $comp = trim((string) ($r[0] ?? ''));
        $ref  = trim((string) ($r[1] ?? ''));
        if ($comp !== '' && $ref !== '') {
            $map[$comp] = $ref;
        }
    }
    fclose($fh);
    fwrite(STDOUT, "Loaded " . count($map) . " computer-number -> reference mappings.\n");
}

// ---- admin user (performed_by) -------------------------------------
$adminId = (int) (Database::run("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetch()['id'] ?? 0);
if ($adminId === 0) {
    fwrite(STDERR, "No admin user found.\n");
    exit(1);
}

$uploadsBase = rtrim((string) Config::get('storage.uploads'), '/');

// ---- process -------------------------------------------------------
$pdfs = glob($dir . '/*.pdf') ?: [];
$pdfs = array_merge($pdfs, glob($dir . '/*.PDF') ?: []);
fwrite(STDOUT, "Found " . count($pdfs) . " PDF file(s) in {$dir}\n");
if ($dryRun) {
    fwrite(STDOUT, "** DRY RUN — no changes will be written **\n");
}

$attached = $already = $noRecord = $failed = 0;

foreach ($pdfs as $pdf) {
    $computer = pathinfo($pdf, PATHINFO_FILENAME);
    $ref = $map[$computer] ?? $computer; // fall back to computer number as the ref

    $fileId = FileRecord::findIdByRef($app, $ref);
    if ($fileId === null) {
        fwrite(STDOUT, "  SKIP (no record)  {$computer}.pdf  (ref: {$ref})\n");
        $noRecord++;
        continue;
    }

    $originalName = $computer . '.pdf';

    // Idempotency: already attached?
    $exists = Database::run(
        'SELECT 1 FROM file_attachments WHERE file_id = :f AND original_filename = :n AND is_deleted = 0 LIMIT 1',
        ['f' => $fileId, 'n' => $originalName]
    )->fetch();
    if ($exists) {
        $already++;
        continue;
    }

    if ($dryRun) {
        fwrite(STDOUT, "  WOULD ATTACH  {$originalName} -> file #{$fileId} ({$ref})\n");
        $attached++;
        continue;
    }

    try {
        $destDir = $uploadsBase . '/' . $fileId;
        if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {
            throw new \RuntimeException('cannot create storage dir');
        }
        $stored = bin2hex(random_bytes(16)) . '.pdf';
        if (!@copy($pdf, $destDir . '/' . $stored)) {
            throw new \RuntimeException('copy failed');
        }
        @chmod($destDir . '/' . $stored, 0640);

        Attachment::create(
            $fileId,
            $originalName,
            $fileId . '/' . $stored,
            'application/pdf',
            (int) filesize($pdf),
            $adminId
        );
        $attached++;
    } catch (\Throwable $e) {
        fwrite(STDOUT, "  FAIL  {$originalName}: " . $e->getMessage() . "\n");
        $failed++;
    }
}

fwrite(STDOUT, "\nDone. Attached: {$attached}, already attached: {$already}, no matching record: {$noRecord}, failed: {$failed}\n");
