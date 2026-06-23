<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\View;
use App\Models\Attachment;
use App\Models\FileList;
use App\Models\FileRecord;
use App\Models\TransactionHistory;
use App\Services\PdfReport;

/**
 * PDF generation — streamed in memory, never stored on disk.
 *
 * GET /{app}/pdf?id=&mode=&attachments=&history=&qr=&action=download|preview
 */
final class PdfController
{
    public function generate(): void
    {
        Auth::requireLogin();
        $app = $this->currentApp();
        $id  = (int) ($_GET['id'] ?? 0);

        $record = FileRecord::find($app, $id);
        if ($record === null) {
            http_response_code(404);
            exit('Record not found.');
        }

        $mode = (string) ($_GET['mode'] ?? 'standard');
        $ctx = [
            'app'                 => $app,
            'config'              => FileList::config($app),
            'record'              => $record,
            'fields'              => FileRecord::fields($app),
            'attachments'         => Attachment::forFile($id),
            'history'             => TransactionHistory::forFile($id),
            'fileUrl'             => $this->absoluteFileUrl($app, $id),
            'user'                => (string) (Auth::user()['full_name'] ?? Auth::user()['username'] ?? ''),
            'mode'                => $mode,
            'include_attachments' => $this->boolParam('attachments'),
            'include_history'     => $this->boolParam('history'),
            'include_qr'          => $this->boolParam('qr'),
        ];

        $pdf = PdfReport::build($ctx);

        $filename = preg_replace('/[^\w.\-]+/', '_', (string) $record['reference_no']) . '.pdf';
        $action = ($_GET['action'] ?? 'download') === 'preview' ? 'I' : 'D';

        // FPDF sends its own headers; ensure nothing leaked first.
        if (ob_get_length()) {
            ob_clean();
        }
        $pdf->Output($action, $filename);
        exit;
    }

    private function boolParam(string $key): bool
    {
        $v = $_GET[$key] ?? '';
        return $v === '1' || $v === 'true' || $v === 'on';
    }

    private function absoluteFileUrl(string $app, int $id): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = rtrim((string) Config::get('app.base_url', ''), '/');
        return $scheme . '://' . $host . $base . '/' . $app . '/view?id=' . $id;
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
}
