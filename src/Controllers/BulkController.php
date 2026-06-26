<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Csrf;
use App\Session;
use App\View;
use App\Models\BulkTemplate;
use App\Models\FileList;
use App\Services\BulkProcessor;
use App\Services\BulkValidator;
use App\Services\Xlsx\XlsxReader;
use App\Services\Xlsx\XlsxWriter;

/**
 * Bulk Upload — 5-step wizard: select app, download template, upload &
 * validate, confirm & process, import summary.
 */
final class BulkController
{
    /** Max rows rendered into the validation preview / error panels. */
    private const PREVIEW_CAP = 200;

    /** GET /bulk-upload?app= */
    public function index(): void
    {
        Auth::requireLogin();
        $app = (string) ($_GET['app'] ?? '');
        $app = FileList::isApp($app) ? $app : '';

        View::render('bulk/index', [
            'pageTitle' => 'Bulk Upload',
            'active'    => 'bulk-upload',
            'app'       => $app,
            'apps'      => [
                'eoffice'   => ['label' => 'eOffice',   'desc' => BulkTemplate::formatDescription('eoffice')],
                'ospyndocs' => ['label' => 'OspynDocs', 'desc' => BulkTemplate::formatDescription('ospyndocs')],
            ],
        ]);
    }

    /** GET /bulk-upload/template?app=&format=xlsx|csv */
    public function template(): void
    {
        Auth::requireLogin();
        $app = (string) ($_GET['app'] ?? '');
        if (!FileList::isApp($app)) {
            http_response_code(404);
            exit('Unknown module.');
        }
        $format = ($_GET['format'] ?? 'xlsx') === 'csv' ? 'csv' : 'xlsx';
        $headers = BulkTemplate::headers($app);
        $sample  = BulkTemplate::sampleRow($app);
        $base    = $app . '_bulk_template';

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $base . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ',', '"', '');
            fputcsv($out, $sample, ',', '"', '');
            fclose($out);
            exit;
        }

        $widths = array_fill(1, count($headers), 22);
        $bytes = XlsxWriter::build($headers, $sample, $widths);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $base . '.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    /** POST /bulk-upload/validate  (multipart: app, file) */
    public function validate(): void
    {
        Auth::requireLogin();
        Csrf::check();
        $app = (string) ($_POST['app'] ?? '');
        if (!FileList::isApp($app)) {
            $this->json(['ok' => false, 'error' => 'Please select a valid source app.'], 422);
            return;
        }
        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'error' => 'Please choose a file to upload.'], 422);
            return;
        }

        $file = $_FILES['file'];
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            $this->json(['ok' => false, 'error' => 'Only .xlsx and .csv files are accepted.'], 422);
            return;
        }
        if ($file['size'] > (int) Config::get('uploads.max_size_bytes', 25 * 1024 * 1024)) {
            $this->json(['ok' => false, 'error' => 'The file is too large.'], 422);
            return;
        }

        @set_time_limit(180);
        @ini_set('memory_limit', '512M');

        // Persist the upload so the process step can re-read it without keeping
        // (potentially huge) parsed data in the session. Re-validation at
        // process time is deterministic and reflects the latest DB state.
        $this->gcTempFiles();
        $token = BulkProcessor::uuid();
        $tmpDir = rtrim((string) Config::get('storage.tmp'), '/');
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }
        $storedPath = $tmpDir . '/bulk_' . $token . '.' . $ext;
        if (!@move_uploaded_file((string) $file['tmp_name'], $storedPath) && !@copy((string) $file['tmp_name'], $storedPath)) {
            $this->json(['ok' => false, 'error' => 'Could not stage the uploaded file (check storage/tmp permissions).'], 500);
            return;
        }

        try {
            $rows = $this->parseFile($storedPath, $ext);
            $validation = BulkValidator::validate($app, $rows);
        } catch (\Throwable $e) {
            @unlink($storedPath);
            $this->json(['ok' => false, 'error' => 'Could not read the file: ' . $e->getMessage()], 422);
            return;
        }

        if ($validation['fatal'] !== null) {
            @unlink($storedPath);
            $this->json(['ok' => false, 'error' => $validation['fatal']], 422);
            return;
        }

        Session::set('bulk_' . $token, ['app' => $app, 'path' => $storedPath, 'ext' => $ext]);

        $summary = $validation['summary'];
        $canProceed = ($summary['insert'] + $summary['update'] + $summary['history_only']) > 0;

        // Cap rendered rows so a very large file does not produce megabytes of
        // HTML; ALL valid rows are still processed at the next step.
        $cap = self::PREVIEW_CAP;
        $previewRows = array_slice($validation['rows'], 0, $cap);
        $errorRows = array_slice(
            array_values(array_filter($validation['rows'], static fn ($r) => $r['level'] === 'error')),
            0,
            $cap
        );

        $this->json([
            'ok'          => true,
            'token'       => $token,
            'summary'     => $summary,
            'can_proceed' => $canProceed,
            'preview'     => View::renderPartial('bulk/_preview', ['app' => $app, 'rows' => $previewRows]),
            'errors'      => View::renderPartial('bulk/_errors', ['rows' => $errorRows]),
            'truncated'   => $summary['total'] > $cap,
            'cap'         => $cap,
        ]);
    }

    /** POST /bulk-upload/process  (token) */
    public function process(): void
    {
        Auth::requireLogin();
        Csrf::check();
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $token = (string) ($_POST['token'] ?? '');
        if (!preg_match('/^[0-9a-f\-]{36}$/', $token)) {
            $this->json(['ok' => false, 'error' => 'Invalid import session.'], 422);
            return;
        }
        $stash = Session::get('bulk_' . $token);
        if (!is_array($stash) || empty($stash['path']) || !is_file((string) $stash['path'])) {
            $this->json(['ok' => false, 'error' => 'This import session has expired. Please re-upload.'], 422);
            return;
        }

        $app = (string) $stash['app'];
        try {
            $rows = $this->parseFile((string) $stash['path'], (string) $stash['ext']);
            $validation = BulkValidator::validate($app, $rows);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Could not process the file: ' . $e->getMessage()], 422);
            return;
        }

        $valid = array_values(array_filter($validation['rows'], static fn ($r) => $r['level'] !== 'error'));
        if (empty($valid)) {
            $this->json(['ok' => false, 'error' => 'There are no valid rows to import.'], 422);
            return;
        }

        $summary = BulkProcessor::process($app, $valid, (int) Auth::id());

        Session::remove('bulk_' . $token);
        @unlink((string) $stash['path']);

        $summary['ok'] = true;
        $summary['app'] = $app;
        $summary['list_url'] = base_url('/' . $app);
        $summary['report_url'] = $summary['has_report'] ? base_url('/bulk-upload/report?batch=' . $summary['batch_id']) : null;
        $summary['audit_url'] = base_url('/audit-log?batch=' . $summary['batch_id']);
        $this->json($summary);
    }

    /** GET /bulk-upload/report?batch= */
    public function report(): void
    {
        Auth::requireLogin();
        $batch = (string) ($_GET['batch'] ?? '');
        if (!preg_match('/^[0-9a-f\-]{36}$/', $batch)) {
            http_response_code(404);
            exit('Invalid batch.');
        }
        $dir = rtrim((string) Config::get('storage.reports'), '/');
        $path = $dir . '/' . $batch . '.csv';
        if (!is_file($path)) {
            http_response_code(404);
            exit('Report not found.');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="import_' . $batch . '.csv"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    // ---------------------------------------------------------------

    /** @return array<int,array<int,string>> */
    private function parseFile(string $path, string $ext): array
    {
        return $ext === 'csv' ? $this->readCsv($path) : XlsxReader::rows($path);
    }

    /** Remove abandoned staged uploads older than a day. */
    private function gcTempFiles(): void
    {
        $tmpDir = rtrim((string) Config::get('storage.tmp'), '/');
        foreach (glob($tmpDir . '/bulk_*') ?: [] as $f) {
            if (is_file($f) && (time() - (int) @filemtime($f)) > 86400) {
                @unlink($f);
            }
        }
    }

    /** @return array<int,array<int,string>> */
    private function readCsv(string $path): array
    {
        $rows = [];
        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new \RuntimeException('Unable to open the CSV file.');
        }
        $first = true;
        while (($r = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if ($first && isset($r[0])) {
                $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $r[0]); // strip UTF-8 BOM
                $first = false;
            }
            $rows[] = array_map(static fn ($v) => (string) $v, $r);
        }
        fclose($fh);
        return $rows;
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
    }
}
