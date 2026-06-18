<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\View;

/**
 * Authenticated landing pages. The Dashboard and module screens are
 * implemented from Stage 3 onward; for now they render the full shell
 * (so the navbar appears on every authenticated page) with a placeholder.
 */
final class PageController
{
    /** GET /{app}/view — File Work Area (full implementation in Stage 5). */
    public function workArea(): void
    {
        Auth::requireLogin();
        $app = str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/ospyndocs') ? 'ospyndocs' : 'eoffice';
        $id  = (int) ($_GET['id'] ?? 0);
        View::render('placeholder', [
            'pageTitle' => 'File Work Area',
            'active'    => $app,
            'heading'   => 'File Work Area',
            'icon'      => 'bi-window-split',
            'stage'     => '5',
            'note'      => 'The split-panel work area (note, details, attachments, history) for record #' . $id . ' arrives in Stage 5.',
        ]);
    }

    public function bulkUpload(): void
    {
        Auth::requireLogin();
        View::render('placeholder', [
            'pageTitle' => 'Bulk Upload',
            'active'    => 'bulk-upload',
            'heading'   => 'Bulk Upload',
            'icon'      => 'bi-cloud-arrow-up',
            'stage'     => '7',
            'note'      => 'The 5-step bulk upload wizard arrives in Stage 7.',
        ]);
    }

    public function auditLog(): void
    {
        Auth::requireLogin();
        View::render('placeholder', [
            'pageTitle' => 'Audit Log',
            'active'    => 'audit-log',
            'heading'   => 'Audit Log',
            'icon'      => 'bi-clipboard-data',
            'stage'     => '8',
            'note'      => 'The cross-file audit log arrives in Stage 8.',
        ]);
    }
}
