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

        try {
            $rows = $ext === 'csv' ? $this->readCsv((string) $file['tmp_name']) : XlsxReader::rows((string) $file['tmp_name']);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Could not read the file: ' . $e->getMessage()], 422);
            return;
        }

        $validation = BulkValidator::validate($app, $rows);
        if ($validation['fatal'] !== null) {
            $this->json(['ok' => false, 'error' => $validation['fatal']], 422);
            return;
        }

        // Stash the validated rows for the process step.
        $token = BulkProcessor::uuid();
        Session::set('bulk_' . $token, ['app' => $app, 'rows' => $validation['rows']]);

        $summary = $validation['summary'];
        $canProceed = ($summary['insert'] + $summary['update'] + $summary['history_only']) > 0;

        $this->json([
            'ok'          => true,
            'token'       => $token,
            'summary'     => $summary,
            'can_proceed' => $canProceed,
            'preview'     => View::renderPartial('bulk/_preview', ['app' => $app, 'rows' => $validation['rows']]),
            'errors'      => View::renderPartial('bulk/_errors', ['rows' => $validation['rows']]),
        ]);
    }

    /** POST /bulk-upload/process  (token) */
    public function process(): void
    {
        Auth::requireLogin();
        Csrf::check();
        $token = (string) ($_POST['token'] ?? '');
        $stash = Session::get('bulk_' . $token);
        if (!is_array($stash) || empty($stash['rows'])) {
            $this->json(['ok' => false, 'error' => 'This import session has expired. Please re-upload.'], 422);
            return;
        }

        $app = (string) $stash['app'];
        $valid = array_values(array_filter($stash['rows'], static fn ($r) => ($r['level'] ?? 'error') !== 'error'));
        if (empty($valid)) {
            $this->json(['ok' => false, 'error' => 'There are no valid rows to import.'], 422);
            return;
        }

        $summary = BulkProcessor::process($app, $valid, (int) Auth::id());
        Session::remove('bulk_' . $token);

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
