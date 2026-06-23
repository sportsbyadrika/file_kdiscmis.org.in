<?php
/**
 * Audit Log page.
 *
 * Expects: $filters, $rows, $total, $page, $perPage, $perPageOpts, $batches, $sources.
 */
$appLabel = ['eoffice' => 'eOffice', 'ospyndocs' => 'OspynDocs'];
$sourceBadge = ['MANUAL_EDIT' => 'text-bg-secondary', 'BULK_IMPORT' => 'text-bg-info', 'USER_ACTION' => 'text-bg-primary'];

$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);
$start = $total ? ($page - 1) * $perPage + 1 : 0;
$end   = min($page * $perPage, $total);

// Build a query string from the current filters (+ overrides) for links.
$qs = static function (array $over = []) use ($filters, $perPage): string {
    $base = array_filter([
        'keyword'    => $filters['keyword'],
        'app'        => $filters['app'],
        'event_type' => $filters['event_type'],
        'source'     => $filters['source'],
        'batch'      => $filters['batch'],
        'date_from'  => $filters['date_from'],
        'date_to'    => $filters['date_to'],
        'per_page'   => $perPage,
    ], static fn ($v) => $v !== '' && $v !== null);
    return '?' . http_build_query(array_merge($base, $over));
};

/** Render the human-readable detail for an event row. */
$renderDetail = static function (array $r) use ($appLabel): string {
    if ($r['event_type'] === 'history') {
        $bits = [];
        if (($r['from_status'] ?? '') !== '' || ($r['to_status'] ?? '') !== '') {
            $bits[] = '<span class="text-muted">' . e((string) ($r['from_status'] ?: '—')) . ' → ' . e((string) ($r['to_status'] ?: '—')) . '</span>';
        }
        $type = '<span class="badge text-bg-light border">' . e((string) $r['tx_type']) . '</span>';
        $note = ($r['detail'] ?? '') !== '' ? ' ' . e((string) $r['detail']) : '';
        return $type . ' ' . implode(' ', $bits) . $note;
    }

    // update event: fields_changed JSON
    $changes = json_decode((string) ($r['detail'] ?? ''), true);
    if (!is_array($changes)) {
        return '<span class="text-muted">—</span>';
    }
    if (isset($changes['_event'])) {
        $map = ['Inserted' => 'text-bg-success', 'History Added' => 'text-bg-info'];
        $lbl = (string) $changes['_event'];
        return '<span class="badge ' . ($map[$lbl] ?? 'text-bg-secondary') . '">' . e($lbl) . '</span>';
    }
    $parts = [];
    foreach ($changes as $field => $change) {
        if (is_array($change) && array_key_exists('old', $change)) {
            $old = trim((string) $change['old']);
            $new = trim((string) $change['new']);
            $parts[] = '<div><strong>' . e((string) $field) . ':</strong> <span class="text-muted">'
                . e($old !== '' ? $old : '—') . '</span> → ' . e($new !== '' ? $new : '—') . '</div>';
        } else {
            $parts[] = '<div><strong>' . e((string) $field) . '</strong></div>';
        }
    }
    return implode('', $parts) ?: '<span class="text-muted">—</span>';
};

$activeBatch = null;
if ($filters['batch'] !== '') {
    foreach ($batches as $b) {
        if ($b['batch_id'] === $filters['batch']) { $activeBatch = $b; break; }
    }
}
?>
<div class="container-fluid py-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
      <h1 class="h4 mb-0"><i class="bi bi-clipboard-data me-2"></i>Audit Log</h1>
      <span class="small text-muted">Showing <?= $start ?>–<?= $end ?> of <?= (int) $total ?> events</span>
    </div>
    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#auditFilters">
      <i class="bi bi-funnel me-1"></i>Filters
    </button>
  </div>

  <?php if ($activeBatch): ?>
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-3">
      <span><i class="bi bi-box-seam me-1"></i>Filtering by bulk batch <code><?= e($filters['batch']) ?></code></span>
      <span class="small">App: <strong><?= e($appLabel[$activeBatch['source_app']] ?? $activeBatch['source_app']) ?></strong></span>
      <span class="small">Imported: <strong><?= e(format_dt($activeBatch['imported_at'])) ?></strong></span>
      <span class="small">Inserted <?= (int) $activeBatch['inserted'] ?> · Updated <?= (int) $activeBatch['updated'] ?> · Skipped <?= (int) $activeBatch['skipped'] ?></span>
      <a href="<?= e(base_url('/audit-log')) ?>" class="btn btn-sm btn-outline-secondary ms-auto">Clear batch filter</a>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <div class="collapse mb-3 <?= array_filter($filters) ? 'show' : '' ?>" id="auditFilters">
    <div class="card card-body shadow-sm">
      <form method="get" action="<?= e(base_url('/audit-log')) ?>">
        <div class="row g-3">
          <div class="col-12 col-lg-3">
            <label class="form-label small">Keyword</label>
            <input type="text" class="form-control" name="keyword" value="<?= e($filters['keyword']) ?>" placeholder="Ref or title">
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label small">App</label>
            <select class="form-select" name="app">
              <option value="">All</option>
              <option value="eoffice" <?= $filters['app'] === 'eoffice' ? 'selected' : '' ?>>eOffice</option>
              <option value="ospyndocs" <?= $filters['app'] === 'ospyndocs' ? 'selected' : '' ?>>OspynDocs</option>
            </select>
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label small">Event Type</label>
            <select class="form-select" name="event_type">
              <option value="">All</option>
              <option value="update" <?= $filters['event_type'] === 'update' ? 'selected' : '' ?>>Update</option>
              <option value="history" <?= $filters['event_type'] === 'history' ? 'selected' : '' ?>>History</option>
            </select>
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label small">Source</label>
            <select class="form-select" name="source">
              <option value="">All</option>
              <?php foreach ($sources as $s): ?>
                <option value="<?= e($s) ?>" <?= $filters['source'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-lg-3">
            <label class="form-label small">Bulk Batch</label>
            <input type="text" class="form-control" name="batch" value="<?= e($filters['batch']) ?>" list="batchList" placeholder="Batch UUID">
            <datalist id="batchList">
              <?php foreach ($batches as $b): ?>
                <option value="<?= e($b['batch_id']) ?>"><?= e(($appLabel[$b['source_app']] ?? $b['source_app']) . ' · ' . format_dt($b['imported_at'])) ?></option>
              <?php endforeach; ?>
            </datalist>
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label small">From</label>
            <input type="date" class="form-control" name="date_from" value="<?= e($filters['date_from']) ?>">
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label small">To</label>
            <input type="date" class="form-control" name="date_to" value="<?= e($filters['date_to']) ?>">
          </div>
          <div class="col-6 col-lg-2">
            <label class="form-label small">Rows</label>
            <select class="form-select" name="per_page">
              <?php foreach ($perPageOpts as $opt): ?>
                <option value="<?= (int) $opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= (int) $opt ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-lg-6 d-flex align-items-end gap-2">
            <a href="<?= e(base_url('/audit-log')) ?>" class="btn btn-outline-secondary">Clear All</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Apply</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Table -->
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>When</th>
            <th>App</th>
            <th>Event</th>
            <th>Reference &amp; Title</th>
            <th>Detail</th>
            <th>By</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-center text-muted py-5">
              <div class="display-6 mb-2"><i class="bi bi-clipboard-x"></i></div>No audit events match the current filters.
            </td></tr>
          <?php else: foreach ($rows as $r):
            $app = (string) $r['source_app'];
          ?>
            <tr>
              <td class="text-nowrap small"><?= e(format_dt($r['ts'])) ?></td>
              <td><span class="app-badge-pill app-badge-<?= e($app) ?>"><?= e($appLabel[$app] ?? $app) ?></span></td>
              <td>
                <?php if ($r['event_type'] === 'update'): ?>
                  <span class="badge text-bg-light border"><i class="bi bi-pencil-square me-1"></i>Update</span>
                <?php else: ?>
                  <span class="badge text-bg-light border"><i class="bi bi-clock-history me-1"></i>History</span>
                <?php endif; ?>
                <span class="badge <?= e($sourceBadge[$r['source']] ?? 'text-bg-secondary') ?> ms-1"><?= e((string) $r['source']) ?></span>
              </td>
              <td>
                <a href="<?= e(base_url('/' . $app . '/view?id=' . (int) $r['file_id'])) ?>" class="text-decoration-none fw-semibold">
                  <?= e((string) $r['reference_no']) ?>
                </a>
                <div class="text-muted small text-truncate" style="max-width:240px;"><?= e((string) $r['title']) ?></div>
              </td>
              <td class="small"><?= $renderDetail($r) ?></td>
              <td class="small text-nowrap"><?= e((string) $r['actor']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="card-footer bg-white">
        <nav>
          <ul class="pagination pagination-sm mb-0 justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($qs(['page' => 1])) ?>">First</a></li>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($qs(['page' => max(1, $page - 1)])) ?>">Prev</a></li>
            <li class="page-item disabled"><span class="page-link">Page <?= $page ?> of <?= $pages ?></span></li>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($qs(['page' => min($pages, $page + 1)])) ?>">Next</a></li>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($qs(['page' => $pages])) ?>">Last</a></li>
          </ul>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</div>
