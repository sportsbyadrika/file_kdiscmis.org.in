<?php
/**
 * Dashboard — Zone A (stats), Zone B (module panels), Zone C (activity feed).
 *
 * Expects: $stats, $docTypes, $activity.
 */
$activity = $activity ?? [];
$eo = $stats['eoffice'] ?? [];
$op = $stats['ospyndocs'] ?? [];

$appLabel = ['eoffice' => 'eOffice', 'ospyndocs' => 'OspynDocs'];

$actionMeta = [
    'Inserted' => ['cls' => 'text-bg-success', 'icon' => 'bi-plus-circle'],
    'Updated'  => ['cls' => 'text-bg-info',    'icon' => 'bi-pencil-square'],
    'Deleted'  => ['cls' => 'text-bg-danger',  'icon' => 'bi-trash'],
];
?>
<div class="container-fluid py-4">

  <!-- Page header + refresh -->
  <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
    <div>
      <h1 class="h4 mb-0">Dashboard</h1>
      <p class="text-muted small mb-0">Overview of the eOffice &amp; OspynDocs repositories</p>
    </div>
    <button type="button" class="btn btn-outline-primary" id="refreshStats" data-url="<?= e(base_url('/dashboard/stats')) ?>">
      <i class="bi bi-arrow-clockwise me-1"></i>Refresh
    </button>
  </div>

  <!-- Zone A: stat cards (AJAX-refreshable) -->
  <div id="statsZone" class="mb-4">
    <?= \App\View::renderPartial('dashboard/_stats', ['stats' => $stats, 'docTypes' => $docTypes]) ?>
  </div>

  <!-- Zone B: module entry panels -->
  <div class="row g-3 mb-4">
    <?php
      $panels = [
        'eoffice'   => ['label' => 'eOffice',   'icon' => 'bi-folder2-open', 'desc' => 'Government file records',  'stats' => $eo],
        'ospyndocs' => ['label' => 'OspynDocs', 'icon' => 'bi-files',        'desc' => 'Project document library', 'stats' => $op],
      ];
      foreach ($panels as $key => $p):
    ?>
      <div class="col-12 col-md-6">
        <div class="card shadow-sm module-panel h-100">
          <div class="card-body d-flex flex-column flex-sm-row align-items-sm-center gap-3">
            <div class="module-icon app-badge-<?= e($key) ?>">
              <i class="bi <?= e($p['icon']) ?>"></i>
            </div>
            <div class="flex-grow-1">
              <h2 class="h5 mb-1"><?= e($p['label']) ?></h2>
              <p class="text-muted small mb-2"><?= e($p['desc']) ?></p>
              <div class="d-flex flex-wrap gap-3 small">
                <span><strong><?= e(number_format((int)($p['stats']['total'] ?? 0))) ?></strong> records</span>
                <span class="text-muted">Last updated <?= e(format_dt($p['stats']['last_updated'] ?? null)) ?></span>
              </div>
            </div>
          </div>
          <div class="card-footer bg-white d-flex gap-2">
            <a href="<?= e(base_url('/' . $key)) ?>" class="btn btn-primary btn-sm flex-grow-1">
              <i class="bi bi-list-ul me-1"></i>Open File List
            </a>
            <a href="<?= e(base_url('/bulk-upload?app=' . $key)) ?>" class="btn btn-outline-secondary btn-sm">
              <i class="bi bi-cloud-arrow-up me-1"></i>Bulk Upload
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Zone C: recent activity feed -->
  <div class="card shadow-sm">
    <div class="card-header bg-white d-flex align-items-center">
      <h2 class="h6 mb-0"><i class="bi bi-activity me-2"></i>Recent Activity</h2>
      <span class="ms-auto text-muted small">Last <?= e((string) count($activity)) ?> events</span>
    </div>
    <div class="card-body p-0">
      <?php if (empty($activity)): ?>
        <div class="text-center text-muted py-5">
          <div class="display-6 mb-2"><i class="bi bi-inbox"></i></div>
          No activity yet.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 activity-table">
            <thead class="table-light">
              <tr>
                <th style="width:42px;"></th>
                <th>Source</th>
                <th>Reference &amp; Title</th>
                <th>Action</th>
                <th>By</th>
                <th>When</th>
                <th class="text-end">&nbsp;</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($activity as $row):
                $am = $actionMeta[$row['action']] ?? ['cls' => 'text-bg-secondary', 'icon' => 'bi-dot'];
                $app = (string) $row['source_app'];
              ?>
                <tr>
                  <td><span class="activity-dot <?= e($am['cls']) ?>"><i class="bi <?= e($am['icon']) ?>"></i></span></td>
                  <td><span class="app-badge-pill app-badge-<?= e($app) ?>"><?= e($appLabel[$app] ?? $app) ?></span></td>
                  <td>
                    <div class="fw-semibold text-truncate" style="max-width:340px;"><?= e($row['title']) ?></div>
                    <div class="text-muted small"><?= e($row['reference_no']) ?></div>
                  </td>
                  <td><span class="badge <?= e($am['cls']) ?>"><?= e($row['action']) ?></span></td>
                  <td class="small"><?= e($row['actor']) ?></td>
                  <td class="small text-nowrap"><?= e(format_dt($row['ts'])) ?></td>
                  <td class="text-end">
                    <a href="<?= e(base_url('/' . $app)) ?>" class="btn btn-sm btn-outline-secondary" title="View File">
                      <i class="bi bi-box-arrow-up-right"></i><span class="d-none d-lg-inline ms-1">View File</span>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('refreshStats');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var zone = document.getElementById('statsZone');
      var url = btn.getAttribute('data-url');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Refreshing…';
      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
        .then(function (html) {
          zone.innerHTML = html;
          if (window.showToast) window.showToast('Statistics refreshed.', 'success');
        })
        .catch(function () {
          if (window.showToast) window.showToast('Could not refresh statistics.', 'error');
        })
        .finally(function () {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Refresh';
        });
    });
  });
})();
</script>
