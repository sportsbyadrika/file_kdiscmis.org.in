<?php
/**
 * Bulk Upload wizard (5 steps). Driven by public/assets/js/bulk.js.
 *
 * Expects: $app (preselected or ''), $apps (label/desc per app).
 */
?>
<div class="container py-4">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-cloud-arrow-up me-2"></i>Bulk Upload</h1>
    <a href="<?= e(base_url('/attach-pdfs')) ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-paperclip me-1"></i>Attach PDFs to records
    </a>
  </div>

  <!-- Step indicator -->
  <ol class="wizard-steps mb-4" id="wizardSteps">
    <li class="active" data-step="1"><span>1</span> Select App</li>
    <li data-step="2"><span>2</span> Template</li>
    <li data-step="3"><span>3</span> Upload &amp; Validate</li>
    <li data-step="4"><span>4</span> Confirm</li>
    <li data-step="5"><span>5</span> Summary</li>
  </ol>

  <!-- Step 1: Select source app -->
  <section class="wizard-pane" data-pane="1">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3">Step 1 — Select Source App</h2>
        <div class="row g-3">
          <?php foreach ($apps as $key => $meta): ?>
            <div class="col-12 col-md-6">
              <label class="app-select-card <?= $app === $key ? 'selected' : '' ?>" data-app="<?= e($key) ?>">
                <input type="radio" name="app" value="<?= e($key) ?>" class="d-none" <?= $app === $key ? 'checked' : '' ?>>
                <span class="app-badge app-badge-<?= e($key) ?>"><i class="bi <?= $key === 'eoffice' ? 'bi-folder2-open' : 'bi-files' ?>"></i></span>
                <span class="flex-grow-1">
                  <span class="fw-semibold d-block"><?= e($meta['label']) ?></span>
                  <span class="small text-muted"><?= e($meta['label']) ?> bulk import</span>
                </span>
                <i class="bi bi-check-circle-fill check-icon"></i>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-end">
        <button class="btn btn-primary" id="step1Next" <?= $app === '' ? 'disabled' : '' ?>>Next <i class="bi bi-arrow-right ms-1"></i></button>
      </div>
    </div>
  </section>

  <!-- Step 2: Template -->
  <section class="wizard-pane d-none" data-pane="2">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3">Step 2 — Download Template</h2>
        <p class="text-muted" id="templateDesc"></p>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-primary" id="tplXlsx" href="#"><i class="bi bi-file-earmark-excel me-1"></i>Download .xlsx template</a>
          <a class="btn btn-outline-secondary" id="tplCsv" href="#"><i class="bi bi-filetype-csv me-1"></i>Download .csv template</a>
        </div>
        <div class="alert alert-light border mt-3 mb-0 small">
          <i class="bi bi-info-circle me-1"></i>
          The header row is locked and a greyed sample row shows the expected format. Keep the columns in place;
          the <strong>Operation</strong> column accepts <code>INSERT</code>, <code>UPDATE</code> or <code>HISTORY_ONLY</code> (case-insensitive).
        </div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between">
        <button class="btn btn-outline-secondary js-back"><i class="bi bi-arrow-left me-1"></i>Back</button>
        <button class="btn btn-primary js-to-step" data-step="3">Next <i class="bi bi-arrow-right ms-1"></i></button>
      </div>
    </div>
  </section>

  <!-- Step 3: Upload & validate -->
  <section class="wizard-pane d-none" data-pane="3">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3">Step 3 — Upload &amp; Validate</h2>
        <form id="uploadForm" enctype="multipart/form-data" class="d-flex flex-wrap align-items-center gap-2">
          <input type="file" class="form-control" id="bulkFile" name="file" accept=".xlsx,.csv" required style="max-width:360px;">
          <button type="submit" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm me-1 d-none" id="validateSpinner"></span>
            <i class="bi bi-search me-1"></i>Validate
          </button>
        </form>

        <div id="validateResult" class="mt-3 d-none">
          <div class="alert d-flex flex-wrap gap-3 align-items-center" id="summaryBanner"></div>
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#previewTab" type="button">Preview</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#errorsTab" type="button">Errors <span class="badge text-bg-danger" id="errCount">0</span></button></li>
          </ul>
          <div class="tab-content border border-top-0 p-2" style="max-height:48vh; overflow:auto;">
            <div class="tab-pane fade show active" id="previewTab"></div>
            <div class="tab-pane fade" id="errorsTab"></div>
          </div>
        </div>
      </div>
      <div class="card-footer bg-white d-flex justify-content-between">
        <button class="btn btn-outline-secondary js-back"><i class="bi bi-arrow-left me-1"></i>Back</button>
        <button class="btn btn-primary" id="toConfirmBtn" disabled>Continue with valid rows <i class="bi bi-arrow-right ms-1"></i></button>
      </div>
    </div>
  </section>

  <!-- Step 4: Confirm -->
  <section class="wizard-pane d-none" data-pane="4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3">Step 4 — Confirm &amp; Process</h2>
        <p id="confirmText" class="lead mb-3"></p>
        <p class="text-muted">Each record is processed independently — a single failure will not roll back the others.</p>
        <label class="form-label">Type <code>IMPORT</code> to confirm:</label>
        <input type="text" class="form-control" id="confirmPhrase" style="max-width:240px;" autocomplete="off" placeholder="IMPORT">
      </div>
      <div class="card-footer bg-white d-flex justify-content-between">
        <button class="btn btn-outline-secondary js-back"><i class="bi bi-arrow-left me-1"></i>Back</button>
        <button class="btn btn-success" id="processBtn" disabled>
          <span class="spinner-border spinner-border-sm me-1 d-none" id="processSpinner"></span>
          <i class="bi bi-gear me-1"></i>Confirm &amp; Process
        </button>
      </div>
    </div>
  </section>

  <!-- Step 5: Summary -->
  <section class="wizard-pane d-none" data-pane="5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h2 class="h6 mb-3"><i class="bi bi-check-circle text-success me-2"></i>Import Complete</h2>
        <p class="text-muted mb-2">Batch ID: <code id="summaryBatch"></code></p>
        <div class="row g-2 text-center mb-3" id="summaryCounts"></div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-secondary" id="downloadReport" href="#"><i class="bi bi-filetype-csv me-1"></i>Download result report</a>
          <a class="btn btn-outline-primary" id="viewRecords" href="#"><i class="bi bi-list-ul me-1"></i>View records</a>
          <a class="btn btn-outline-secondary" id="viewAudit" href="#"><i class="bi bi-clipboard-data me-1"></i>View in Audit Log</a>
          <button class="btn btn-link ms-auto" id="newImport">Start another import</button>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
  window.BulkConfig = {
    app: <?= json_encode($app) ?>,
    descriptions: <?= json_encode(array_map(static fn ($m) => $m['desc'], $apps)) ?>,
    templateUrl: <?= json_encode(base_url('/bulk-upload/template')) ?>,
    validateUrl: <?= json_encode(base_url('/bulk-upload/validate')) ?>,
    processUrl: <?= json_encode(base_url('/bulk-upload/process')) ?>,
    csrfToken: <?= json_encode(\App\Csrf::token()) ?>
  };
</script>
<script src="<?= e(base_url('/assets/js/bulk.js')) ?>"></script>
