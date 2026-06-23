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
