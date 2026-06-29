<?php
/**
 * Attach PDFs tool. Expects: $dir, $pdfCount.
 */
use App\Csrf;
?>
<div class="container py-4">
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= e(base_url('/dashboard')) ?>">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= e(base_url('/bulk-upload')) ?>">Bulk Upload</a></li>
      <li class="breadcrumb-item active">Attach PDFs</li>
    </ol>
  </nav>

  <h1 class="h4 mb-3"><i class="bi bi-paperclip me-2"></i>Attach PDFs to Records</h1>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h2 class="h6">How it works</h2>
      <ol class="small mb-0">
        <li>In cPanel <strong>File Manager</strong>, upload your PDFs (named <code>&lt;computer-number&gt;.pdf</code>)
            into this folder — zipping them and using <em>Extract</em> is fastest:
            <div class="mt-1"><code><?= e($dir) ?></code></div>
        </li>
        <li>Below, choose the app, upload the <code>computer_to_ref.csv</code> mapping, and click
            <strong>Preview</strong> first, then <strong>Attach</strong>.</li>
        <li>Re-running is safe — already-attached PDFs are skipped. You can delete the
            <code>import_pdfs</code> folder afterwards.</li>
      </ol>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="alert alert-light border d-flex align-items-center gap-2">
        <i class="bi bi-folder2-open"></i>
        <span>PDFs currently staged in <code>import_pdfs</code>:
          <strong id="pdfCount"><?= (int) $pdfCount ?></strong></span>
        <span class="text-muted small ms-2">(upload them via File Manager, then refresh this page)</span>
      </div>

      <form id="attachForm" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-3">
            <label class="form-label small">App</label>
            <select class="form-select" name="app">
              <option value="eoffice">eOffice</option>
              <option value="ospyndocs">OspynDocs</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small">Mapping CSV (computer_to_ref.csv)</label>
            <input type="file" class="form-control" name="map" accept=".csv">
            <div class="form-text">Optional — if omitted, each PDF's computer number is used directly as the File Reference No.</div>
          </div>
          <div class="col-12 col-md-3 d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary flex-grow-1" id="btnPreview">
              <span class="spinner-border spinner-border-sm me-1 d-none" data-spin></span>Preview
            </button>
            <button type="button" class="btn btn-primary flex-grow-1" id="btnAttach">
              <span class="spinner-border spinner-border-sm me-1 d-none" data-spin></span>Attach
            </button>
          </div>
        </div>
      </form>

      <div id="attachResult" class="mt-3 d-none">
        <div class="alert" id="attachSummary"></div>
        <div id="attachIssues"></div>
      </div>
    </div>
  </div>
</div>

<script>
  window.AttachConfig = {
    runUrl: <?= json_encode(base_url('/attach-pdfs/run')) ?>,
    csrfToken: <?= json_encode(\App\Csrf::token()) ?>
  };
</script>
<script src="<?= e(base_url('/assets/js/attach.js')) ?>"></script>
