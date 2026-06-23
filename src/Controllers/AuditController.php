<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\View;
use App\Models\AuditLog;

/**
 * Audit Log — full event/update log across all files, filterable
 * (notably by import_batch_id to inspect a single bulk session).
 */
final class AuditController
{
    /** GET /audit-log */
    public function index(): void
    {
        Auth::requireLogin();

        $filters = [
            'app'        => (string) ($_GET['app'] ?? ''),
            'event_type' => (string) ($_GET['event_type'] ?? ''),
            'source'     => (string) ($_GET['source'] ?? ''),
            'batch'      => trim((string) ($_GET['batch'] ?? '')),
            'keyword'    => trim((string) ($_GET['keyword'] ?? '')),
            'date_from'  => trim((string) ($_GET['date_from'] ?? '')),
            'date_to'    => trim((string) ($_GET['date_to'] ?? '')),
        ];

        $perPage = (int) ($_GET['per_page'] ?? AuditLog::DEFAULT_PER_PAGE);
        $perPage = in_array($perPage, AuditLog::PER_PAGE_OPTIONS, true) ? $perPage : AuditLog::DEFAULT_PER_PAGE;
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = AuditLog::query($filters, $page, $perPage);

        View::render('audit/index', [
            'pageTitle'   => 'Audit Log',
            'active'      => 'audit-log',
            'filters'     => $filters,
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'page'        => $page,
            'perPage'     => $perPage,
            'perPageOpts' => AuditLog::PER_PAGE_OPTIONS,
            'batches'     => AuditLog::recentBatches(),
            'sources'     => ['MANUAL_EDIT', 'BULK_IMPORT', 'USER_ACTION'],
        ]);
    }
}
