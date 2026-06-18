<?php
/**
 * Zone A — summary stat cards (one group per source app).
 * Rendered both inline and by the AJAX refresh endpoint.
 *
 * Expects: $stats (per-app), $docTypes (ospyndocs breakdown).
 */
$eo = $stats['eoffice'] ?? [];
$op = $stats['ospyndocs'] ?? [];
$docTypes = $docTypes ?? [];

/** Small stat tile. */
$tile = static function (string $label, string $value, string $icon, string $cls = 'text-primary') {
    ?>
    <div class="col-6 col-xl-4">
      <div class="stat-tile">
        <div class="stat-icon <?= e($cls) ?>"><i class="bi <?= e($icon) ?>"></i></div>
        <div class="stat-meta">
          <div class="stat-value"><?= e($value) ?></div>
          <div class="stat-label"><?= e($label) ?></div>
        </div>
      </div>
    </div>
    <?php
};
?>
<div class="row g-3">
  <!-- eOffice -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white d-flex align-items-center gap-2">
        <span class="app-badge app-badge-eoffice"><i class="bi bi-folder2-open"></i></span>
        <h2 class="h6 mb-0">eOffice</h2>
        <span class="ms-auto text-muted small">Updated <?= e(format_dt($eo['last_updated'] ?? null)) ?></span>
      </div>
      <div class="card-body">
        <div class="row g-2">
          <?php
            $tile('Total Files', number_format((int)($eo['total'] ?? 0)), 'bi-files', 'text-primary');
            $tile('Added This Month', number_format((int)($eo['added_this_month'] ?? 0)), 'bi-plus-circle', 'text-success');
            $tile('Updated This Month', number_format((int)($eo['updated_this_month'] ?? 0)), 'bi-arrow-repeat', 'text-info');
            $tile('Pending / Open', number_format((int)($eo['pending_open'] ?? 0)), 'bi-hourglass-split', 'text-warning');
            $tile('Attachments', number_format((int)($eo['total_attachments'] ?? 0)), 'bi-paperclip', 'text-secondary');
            $tile('Storage Used', format_bytes((int)($eo['storage_bytes'] ?? 0)), 'bi-hdd', 'text-danger');
          ?>
        </div>
      </div>
    </div>
  </div>

  <!-- OspynDocs -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white d-flex align-items-center gap-2">
        <span class="app-badge app-badge-ospyndocs"><i class="bi bi-files"></i></span>
        <h2 class="h6 mb-0">OspynDocs</h2>
        <span class="ms-auto text-muted small">Updated <?= e(format_dt($op['last_updated'] ?? null)) ?></span>
      </div>
      <div class="card-body">
        <div class="row g-2">
          <?php
            $tile('Total Documents', number_format((int)($op['total'] ?? 0)), 'bi-file-earmark-text', 'text-primary');
            $tile('Added This Month', number_format((int)($op['added_this_month'] ?? 0)), 'bi-plus-circle', 'text-success');
            $tile('Updated This Month', number_format((int)($op['updated_this_month'] ?? 0)), 'bi-arrow-repeat', 'text-info');
            $tile('Attachments', number_format((int)($op['total_attachments'] ?? 0)), 'bi-paperclip', 'text-secondary');
            $tile('Storage Used', format_bytes((int)($op['storage_bytes'] ?? 0)), 'bi-hdd', 'text-danger');
          ?>
          <div class="col-6 col-xl-4">
            <div class="stat-tile">
              <div class="stat-icon text-dark"><i class="bi bi-pie-chart"></i></div>
              <div class="stat-meta">
                <div class="stat-value"><?= e((string) count($docTypes)) ?></div>
                <div class="stat-label">Document Types</div>
              </div>
            </div>
          </div>
        </div>

        <?php if (!empty($docTypes)): ?>
          <hr class="my-3">
          <div class="small text-muted mb-2">By Document Type</div>
          <div class="d-flex flex-wrap gap-2">
            <?php foreach ($docTypes as $dt): ?>
              <span class="badge rounded-pill text-bg-light border">
                <?= e($dt['type']) ?> <span class="badge text-bg-secondary ms-1"><?= e((string) $dt['count']) ?></span>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
