<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csrf;
use App\View;
use App\Models\FileList;
use App\Services\PdfAttacher;

/**
 * Web tool to attach "<computer-number>.pdf" files to records, for hosts with
 * no terminal/SSH. PDFs are staged in storage/import_pdfs (via File Manager);
 * the small mapping CSV is uploaded through the form.
 */
final class PdfAttachController
{
    /** GET /attach-pdfs */
    public function index(): void
    {
        Auth::requireLogin();
        $dir = PdfAttacher::defaultDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $pdfCount = count(array_merge(glob($dir . '/*.pdf') ?: [], glob($dir . '/*.PDF') ?: []));

        View::render('tools/attach_pdfs', [
            'pageTitle' => 'Attach PDFs',
            'active'    => 'bulk-upload',
            'dir'       => $dir,
            'pdfCount'  => $pdfCount,
        ]);
    }

    /** POST /attach-pdfs/run */
    public function run(): void
    {
        Auth::requireLogin();
        Csrf::check();
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $app = (string) ($_POST['app'] ?? 'eoffice');
        if (!FileList::isApp($app)) {
            $this->json(['ok' => false, 'error' => 'Choose a valid app.'], 422);
            return;
        }
        $dryRun = !empty($_POST['dry_run']);

        $dir = PdfAttacher::defaultDir();
        if (!is_dir($dir)) {
            $this->json(['ok' => false, 'error' => 'The folder storage/import_pdfs does not exist yet.'], 422);
            return;
        }

        // Mapping CSV (optional): uploaded through the form.
        $map = [];
        if (!empty($_FILES['map']) && ($_FILES['map']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo((string) $_FILES['map']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                $this->json(['ok' => false, 'error' => 'The mapping must be a .csv file.'], 422);
                return;
            }
            $map = PdfAttacher::parseMap((string) $_FILES['map']['tmp_name']);
        }

        $summary = PdfAttacher::attach($app, $dir, $map, (int) Auth::id(), $dryRun, 1000);
        $summary['ok'] = true;
        $summary['dry_run'] = $dryRun;
        $summary['map_count'] = count($map);
        $this->json($summary);
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
    }
}
