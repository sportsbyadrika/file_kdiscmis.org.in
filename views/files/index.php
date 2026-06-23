<?php
/**
 * File List View page (shared by both apps).
 *
 * Expects: $app, $config, $columns, $visible, $options, $rows, $total,
 *          $sort, $page, $perPage, $perPageOpts, $filters.
 */
use App\View;

$labels = $config['labels'];
$visibleSet = array_flip($visible);
$countStart = $total > 0 ? 1 : 0;
$countEnd   = min($perPage, $total);
?>
<div class="container-fluid py-4">

  <!-- Module header bar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
      <span class="app-badge app-badge-<?= e($app) ?>"><i class="bi <?= $app === 'eoffice' ? 'bi-folder2-open' : 'bi-files' ?>"></i></span>
      <div>
        <h1 class="h4 mb-0"><?= e($config['label']) ?> Files</h1>
        <span class="small text-muted" id="recordCount">Showing <?= $countStart ?>–<?= $countEnd ?> of <?= (int) $total ?> records</span>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterToolbar" aria-expanded="false">
        <i class="bi bi-funnel me-1"></i>Filters
      </button>
      <!-- Columns dropdown -->
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
          <i class="bi bi-layout-three-columns me-1"></i>Columns
        </button>
        <ul class="dropdown-menu dropdown-menu-end p-2" id="columnsMenu" style="min-width:220px;">
          <?php foreach ($columns as $col): ?>
            <li>
              <label class="dropdown-item d-flex align-items-center gap-2">
                <input type="checkbox" class="form-check-input mt-0 js-col-toggle" value="<?= e($col['key']) ?>"
                       <?= isset($visibleSet[$col['key']]) ? 'checked' : '' ?>>
                <?= e($col['label']) ?>
              </label>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <a href="<?= e(base_url('/bulk-upload?app=' . $app)) ?>" class="btn btn-primary">
        <i class="bi bi-cloud-arrow-up me-1"></i>Bulk Upload
      </a>
    </div>
  </div>

  <!-- Collapsible filter toolbar -->
  <div class="collapse mb-3" id="filterToolbar">
    <div class="card card-body shadow-sm">
      <form id="filterForm">
        <div class="row g-3">
          <div class="col-12 col-lg-4">
            <label class="form-label small">Keyword</label>
            <input type="text" class="form-control" name="keyword" placeholder="Ref/Doc no., title, tags, remarks"
                   value="<?= e($filters['keyword'] ?? '') ?>">
          </div>

          <div class="col-12 col-lg-4">
            <label class="form-label small">Date Range</label>
            <div class="input-group">
              <input type="date" class="form-control" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
              <span class="input-group-text">to</span>
              <input type="date" class="form-control" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="mt-1 small">
              <label class="me-2"><input type="radio" name="date_basis" value="document" <?= ($filters['date_basis'] ?? 'document') !== 'upload' ? 'checked' : '' ?>> <?= e($labels['doc_date']) ?></label>
              <label><input type="radio" name="date_basis" value="upload" <?= ($filters['date_basis'] ?? '') === 'upload' ? 'checked' : '' ?>> Upload date</label>
            </div>
          </div>

          <div class="col-6 col-lg-2">
            <label class="form-label small"><?= e($labels['group']) ?></label>
            <select class="form-select" name="group[]" multiple size="3">
              <?php foreach ($options['groups'] as $g): ?>
                <option value="<?= e($g) ?>" <?= in_array($g, $filters['group'] ?? [], true) ? 'selected' : '' ?>><?= e($g) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-lg-2">
            <label class="form-label small"><?= e($labels['category']) ?></label>
            <select class="form-select" name="category[]" multiple size="3">
              <?php foreach ($options['categories'] as $cat): ?>
                <option value="<?= e($cat) ?>" <?= in_array($cat, $filters['category'] ?? [], true) ? 'selected' : '' ?>><?= e($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-lg-2">
            <label class="form-label small">Status</label>
            <select class="form-select" name="status[]" multiple size="3">
              <?php foreach ($options['statuses'] as $st): ?>
                <option value="<?= e($st) ?>" <?= in_array($st, $filters['status'] ?? [], true) ? 'selected' : '' ?>><?= e($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-6 col-lg-3">
            <label class="form-label small">Uploaded By</label>
            <select class="form-select" name="uploaded_by">
              <option value="0">Anyone</option>
              <?php foreach ($options['uploaders'] as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= (int) ($filters['uploaded_by'] ?? 0) === $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-lg-5 d-flex align-items-end gap-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="has_attachments" id="hasAtt" value="1" <?= !empty($filters['has_attachments']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="hasAtt">Has attachments</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="has_history" id="hasHist" value="1" <?= !empty($filters['has_history']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="hasHist">Has history</label>
            </div>
          </div>

          <div class="col-12 col-lg-4 d-flex align-items-end justify-content-lg-end gap-2">
            <button type="button" class="btn btn-outline-secondary js-clear-filters">Clear All Filters</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Apply Filters</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Active filter chips -->
  <div id="activeFilters" class="mb-2">
    <?= View::renderPartial('files/_active_filters', ['app' => $app, 'config' => $config, 'filters' => $filters]) ?>
  </div>

  <!-- Table -->
  <div class="card shadow-sm position-relative">
    <div class="table-responsive" id="tableContainer">
      <?= View::renderPartial('files/_table', [
            'app' => $app, 'config' => $config, 'columns' => $columns,
            'visible' => $visible, 'rows' => $rows, 'sort' => $sort,
      ]) ?>
    </div>
    <div class="loading-overlay d-none" id="loadingOverlay">
      <div class="spinner-border text-primary"></div>
    </div>
    <div class="card-footer bg-white" id="paginationContainer">
      <?= View::renderPartial('files/_pagination', [
            'total' => $total, 'page' => $page, 'perPage' => $perPage, 'perPageOpts' => $perPageOpts,
      ]) ?>
    </div>
  </div>
</div>

<?= View::renderPartial('partials/pdf_modal') ?>

<!-- Edit modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" id="editModalContent">
      <div class="modal-body text-center py-5"><div class="spinner-border text-primary"></div></div>
    </div>
  </div>
</div>

<!-- Delete confirm modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>You are about to delete <strong id="deleteRef"></strong>. This is a soft-delete and can be restored by an administrator.</p>
        <p class="mb-0">Are you sure you want to continue?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
          <span class="spinner-border spinner-border-sm me-1 d-none" id="deleteSpinner"></span>Delete
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  window.FileListConfig = {
    app: <?= json_encode($app) ?>,
    dataUrl: <?= json_encode(base_url('/' . $app . '/data')) ?>,
    editUrl: <?= json_encode(base_url('/' . $app . '/edit')) ?>,
    deleteUrl: <?= json_encode(base_url('/' . $app . '/delete')) ?>,
    viewUrl: <?= json_encode(base_url('/' . $app . '/view')) ?>,
    csrfToken: <?= json_encode(\App\Csrf::token()) ?>
  };
  window.PdfConfig = { url: <?= json_encode(base_url('/' . $app . '/pdf')) ?> };
</script>
<script src="<?= e(base_url('/assets/js/pdf.js')) ?>"></script>
<script src="<?= e(base_url('/assets/js/filelist.js')) ?>"></script>
