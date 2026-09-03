<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Csrf;
use App\View;
use App\Models\FileList;
use App\Services\PdfAttacher;

/**
 * Web tool to attach PDF files to records (no terminal required).
 *
 * PDFs are staged in storage/import_pdfs (via FTP / File Manager). "Scan"
 * builds a server-side job (manifest + options); "Run" processes it in chunks
 * so 10k+ files import with a progress bar and no timeout.
 */
final class PdfAttachController
{
    private const CHUNK = 300;

    /** GET /attach-pdfs */
    public function index(): void
    {
        Auth::requireLogin();

        $dirs = [];
        foreach (['ospyndocs', 'eoffice'] as $a) {
            $d = PdfAttacher::stagingDir($a);
            if (!is_dir($d)) {
                @mkdir($d, 0775, true);
            }
            $dirs[$a] = ['path' => $d, 'count' => count(PdfAttacher::listPdfs($d))];
        }

        View::render('tools/attach_pdfs', [
            'pageTitle' => 'Attach PDFs',
            'active'    => 'bulk-upload',
            'dirs'      => $dirs,
            'chunk'     => self::CHUNK,
        ]);
    }

    /** POST /attach-pdfs/scan — build a job, return {token, total}. */
    public function scan(): void
    {
        Auth::requireLogin();
        Csrf::check();
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        $app  = (string) ($_POST['app'] ?? 'ospyndocs');
        $mode = (string) ($_POST['mode'] ?? 'filename');
        if (!FileList::isApp($app)) {
            $this->json(['ok' => false, 'error' => 'Choose a valid app.'], 422);
            return;
        }
        if (!in_array($mode, PdfAttacher::MODES, true)) {
            $this->json(['ok' => false, 'error' => 'Invalid matching mode.'], 422);
            return;
        }
        $deleteAfter = !empty($_POST['delete_after']);
        $dryRun      = !empty($_POST['dry_run']);

        $dir = PdfAttacher::stagingDir($app);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $paths = PdfAttacher::listPdfs($dir);
        if (empty($paths)) {
            $this->json(['ok' => false, 'error' => 'No PDF files found in ' . basename(dirname($dir)) . '/' . basename($dir) . '. Upload them via FTP first.'], 422);
            return;
        }

        // Mapping CSV (map mode only).
        $map = [];
        if ($mode === 'map' && !empty($_FILES['map']) && ($_FILES['map']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $map = PdfAttacher::parseMap((string) $_FILES['map']['tmp_name']);
        }

        $token = bin2hex(random_bytes(16));
        $tmpDir = rtrim((string) Config::get('storage.tmp'), '/');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $this->gcJobs($tmpDir);

        $job = [
            'app' => $app, 'mode' => $mode, 'delete_after' => $deleteAfter, 'dry_run' => $dryRun,
            'map' => $map, 'manifest' => $paths, 'created' => time(),
        ];
        if (@file_put_contents($tmpDir . '/attachjob_' . $token . '.json', json_encode($job)) === false) {
            $this->json(['ok' => false, 'error' => 'Could not create the job (check storage/tmp permissions).'], 500);
            return;
        }

        $this->json(['ok' => true, 'token' => $token, 'total' => count($paths), 'chunk' => self::CHUNK, 'mode' => $mode, 'dry_run' => $dryRun]);
    }

    /** POST /attach-pdfs/run — process one chunk of a job. */
    public function run(): void
    {
        Auth::requireLogin();
        Csrf::check();
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $token  = (string) ($_POST['token'] ?? '');
        $offset = max(0, (int) ($_POST['offset'] ?? 0));
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            $this->json(['ok' => false, 'error' => 'Invalid job.'], 422);
            return;
        }
        $tmpDir = rtrim((string) Config::get('storage.tmp'), '/');
        $jobFile = $tmpDir . '/attachjob_' . $token . '.json';
        if (!is_file($jobFile)) {
            $this->json(['ok' => false, 'error' => 'Job expired — please Scan again.'], 422);
            return;
        }
        $job = json_decode((string) file_get_contents($jobFile), true);
        if (!is_array($job)) {
            $this->json(['ok' => false, 'error' => 'Corrupt job.'], 422);
            return;
        }

        $manifest = $job['manifest'];
        $total = count($manifest);
        $chunk = array_slice($manifest, $offset, self::CHUNK);

        // Guard: only process paths inside this app's staging directory.
        $dirReal = realpath(PdfAttacher::stagingDir((string) $job['app'])) ?: '';
        $chunk = array_values(array_filter($chunk, static function ($p) use ($dirReal) {
            $rp = realpath($p);
            return $rp !== false && $dirReal !== '' && str_starts_with($rp, $dirReal . DIRECTORY_SEPARATOR);
        }));

        $refIndex = in_array($job['mode'], ['filename', 'exact'], true) ? PdfAttacher::buildRefIndex($job['app']) : null;

        $r = PdfAttacher::attachFiles(
            $job['app'], $chunk, $job['mode'], $job['map'] ?? [], $refIndex,
            (int) Auth::id(), !empty($job['delete_after']), !empty($job['dry_run']), 60
        );

        $next = $offset + self::CHUNK;
        $done = $next >= $total;
        if ($done) {
            @unlink($jobFile);
        }

        $r['ok'] = true;
        $r['offset'] = $offset;
        $r['next_offset'] = $next;
        $r['total'] = $total;
        $r['done'] = $done;
        $this->json($r);
    }

    private function gcJobs(string $tmpDir): void
    {
        foreach (glob($tmpDir . '/attachjob_*.json') ?: [] as $f) {
            if (is_file($f) && (time() - (int) @filemtime($f)) > 86400) {
                @unlink($f);
            }
        }
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
    }
}
