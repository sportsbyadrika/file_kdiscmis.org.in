<?php
declare(strict_types=1);

/**
 * Attach eOffice PDF files to their records (CLI).
 *
 * A web version is also available at /attach-pdfs (for hosts without terminal
 * access). Both share App\Services\PdfAttacher.
 *
 * Usage:
 *   php bin/attach_pdfs.php --dir=/path/to/pdfs [--map=/path/computer_to_ref.csv] [--app=eoffice] [--dry-run]
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;
use App\Services\PdfAttacher;

$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = $m[2] ?? true;
    }
}
$dir    = isset($args['dir']) ? (string) $args['dir'] : PdfAttacher::defaultDir();
$mapCsv = isset($args['map']) ? (string) $args['map'] : '';
$app    = isset($args['app']) ? (string) $args['app'] : 'eoffice';
$dryRun = isset($args['dry-run']);

if (!is_dir($dir)) {
    fwrite(STDERR, "PDF folder not found: {$dir}\n");
    exit(1);
}
if (!in_array($app, ['eoffice', 'ospyndocs'], true)) {
    fwrite(STDERR, "--app must be eoffice or ospyndocs.\n");
    exit(1);
}

@set_time_limit(0);

$map = $mapCsv !== '' ? PdfAttacher::parseMap($mapCsv) : [];
fwrite(STDOUT, "Mappings loaded: " . count($map) . ($dryRun ? "  (DRY RUN)\n" : "\n"));

$adminId = (int) (Database::run("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetch()['id'] ?? 0);
if ($adminId === 0) {
    fwrite(STDERR, "No admin user found.\n");
    exit(1);
}

$r = PdfAttacher::attach($app, $dir, $map, $adminId, $dryRun);
foreach ($r['results'] as $row) {
    if (in_array($row['status'], ['no_record', 'failed'], true)) {
        fwrite(STDOUT, "  " . strtoupper($row['status']) . "  " . $row['name'] . "  (" . $row['detail'] . ")\n");
    }
}
fwrite(STDOUT, "\nFound {$r['found']}. Attached: {$r['attached']}, already attached: {$r['already']}, no matching record: {$r['no_record']}, failed: {$r['failed']}\n");
