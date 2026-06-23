<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Csrf;
use App\View;
use App\Models\Attachment;
use App\Models\FileList;
use App\Models\FileRecord;
use App\Models\TransactionHistory;
use App\Services\Uploader;

/**
 * File Work Area — single-record split-panel workspace and its sub-actions
 * (note edit, attachments upload/download/preview/delete, history CSV).
 */
final class WorkAreaController
{
    /** GET /{app}/view?id= */
    public function show(): void
    {
        Auth::requireLogin();
        $app = $this->currentApp();
        $id  = (int) ($_GET['id'] ?? 0);

        $record = FileRecord::find($app, $id);
        if ($record === null) {
            http_response_code(404);
            View::render('placeholder', [
                'pageTitle' => 'Not Found', 'active' => $app,
                'heading' => 'Record not found', 'icon' => 'bi-exclamation-triangle', 'stage' => '',
                'note' => 'This record does not exist or has been deleted.',
            ]);
            return;
        }

        View::render('workarea/show', [
            'pageTitle'   => $record['reference_no'],
            'active'      => $app,
            'app'         => $app,
            'config'      => FileList::config($app),
            'record'      => $record,
            'fields'      => FileRecord::fields($app),
            'attachments' => Attachment::forFile($id),
            'history'     => TransactionHistory::forFile($id),
        ]);
    }

    /** POST /{app}/note */
    public function saveNote(): void
    {
        Auth::requireLogin();
        Csrf::check();
        $app = $this->currentApp();
        $id  = (int) ($_POST['id'] ?? 0);
        $html = (string) ($_POST['note'] ?? '');

        if (!FileRecord::updateNote($app, $id, $html, (int) Auth::id())) {
            $this->json(['ok' => false, 'error' => 'Could not save the note.'], 422);
            return;
        }
        $clean = FileRecord::sanitizeHtml($html);
        $text  = trim(preg_replace('/\s+/', ' ', strip_tags($clean)));
        $this->json([
            'ok' => true,
            'html' => $clean,
            'chars' => mb_strlen($text),
            'words' => $text === '' ? 0 : count(preg_split('/\s+/u', $text)),
            'message' => 'Note saved.',
        ]);
    }

    /** POST /{app}/attachment/upload  (multipart) */
    public function uploadAttachment(): void
    {
        Auth::requireLogin();
        Csrf::check();
        $app = $this->currentApp();
        $id  = (int) ($_POST['id'] ?? 0);

        $record = FileRecord::find($app, $id);
        if ($record === null) {
            $this->json(['ok' => false, 'error' => 'Record not found.'], 404);
            return;
        }
        if (empty($_FILES['attachment'])) {
            $this->json(['ok' => false, 'error' => 'No file was uploaded.'], 422);
            return;
        }

        try {
            $stored = Uploader::store($_FILES['attachment'], $id);
        } catch (\RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            return;
        }

        Attachment::create($id, $stored['original'], $stored['stored_path'], $stored['mime'], $stored['size'], (int) Auth::id());
        $this->respondAttachments($app, $id, 'Attachment uploaded.');
    }

    /** POST /{app}/attachment/delete */
    public function deleteAttachment(): void
    {
        Auth::requireLogin();
        Csrf::check();
        $app = $this->currentApp();
        $attId = (int) ($_POST['attachment_id'] ?? 0);

        $att = Attachment::findForApp($app, $attId);
        if ($att === null) {
            $this->json(['ok' => false, 'error' => 'Attachment not found.'], 404);
            return;
        }
        Attachment::softDelete($attId);
        $this->respondAttachments($app, (int) $att['file_id'], 'Attachment deleted.');
    }

    /** GET /{app}/attachment/download?id= */
    public function downloadAttachment(): void
    {
        Auth::requireLogin();
        $this->streamAttachment($this->currentApp(), (int) ($_GET['id'] ?? 0), false);
    }

    /** GET /{app}/attachment/preview?id= */
    public function previewAttachment(): void
    {
        Auth::requireLogin();
        $this->streamAttachment($this->currentApp(), (int) ($_GET['id'] ?? 0), true);
    }

    /** GET /{app}/history.csv?id= */
    public function historyCsv(): void
    {
        Auth::requireLogin();
        $app = $this->currentApp();
        $id  = (int) ($_GET['id'] ?? 0);

        $record = FileRecord::find($app, $id);
        if ($record === null) {
            http_response_code(404);
            exit('Record not found.');
        }
        $rows = TransactionHistory::forFile($id);

        $filename = 'history_' . preg_replace('/[^\w.\-]+/', '_', (string) $record['reference_no']) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['#', 'Date', 'Transaction Type', 'From Status', 'To Status', 'Note', 'Performed By', 'Source'], ',', '"', '');
        foreach ($rows as $i => $r) {
            fputcsv($out, [
                $i + 1,
                format_date($r['history_date']),
                $r['transaction_type'],
                $r['from_status'] ?? '',
                $r['to_status'] ?? '',
                $r['note'] ?? '',
                $r['performed_by_name'],
                $r['source'],
            ], ',', '"', '');
        }
        fclose($out);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function streamAttachment(string $app, int $attId, bool $inline): void
    {
        $att = Attachment::findForApp($app, $attId);
        if ($att === null) {
            http_response_code(404);
            exit('Attachment not found.');
        }
        $path = Uploader::absolutePath((string) $att['stored_path']);
        if ($path === null || !is_file($path)) {
            http_response_code(404);
            exit('File missing on server.');
        }

        $mime = (string) $att['mime_type'] ?: 'application/octet-stream';
        // Only allow inline rendering for safe, previewable types.
        $previewKind = Attachment::isPreviewable($mime);
        $disposition = ($inline && $previewKind !== '') ? 'inline' : 'attachment';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', (string) $att['original_filename']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($path);
        exit;
    }

    private function respondAttachments(string $app, int $fileId, string $message): void
    {
        $attachments = Attachment::forFile($fileId);
        $html = View::renderPartial('workarea/_attachments', [
            'app' => $app, 'record_id' => $fileId, 'attachments' => $attachments,
        ]);
        $this->json(['ok' => true, 'html' => $html, 'count' => count($attachments), 'message' => $message]);
    }

    private function currentApp(): string
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim((string) Config::get('app.base_url', ''), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $segment = explode('/', trim($uri, '/'))[0] ?? '';
        if (!FileList::isApp($segment)) {
            http_response_code(404);
            exit('Unknown module.');
        }
        return $segment;
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
    }
}
