<?php
/**
 * Attach PDFs tool. Expects: $dirs (per-app path+count), $chunk.
 */
use App\Csrf;

$default = 'ospyndocs';
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
        <li>Upload your PDF files by <strong>FTP</strong> into the folder for the chosen app
            (sub-folders inside it are fine — they're scanned too):
            <div class="mt-1"><code id="stagePath"><?= e($dirs[$default]['path']) ?></code></div>
        </li>
        <li>Pick the app and matching mode below, click <strong>Scan</strong>, then <strong>Start</strong>.
            Import runs in batches with a progress bar, so large sets (10k+) won't time out.</li>
        <li>Re-running is safe (already-attached PDFs are skipped). A record can hold many PDFs.</li>
      </ol>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="alert alert-light border d-flex align-items-center gap-2">
        <i class="bi bi-folder2-open"></i>
        <span>PDFs currently staged: <strong id="pdfCount"><?= (int) $dirs[$default]['count'] ?></strong></span>
        <a href="<?= e(base_url('/attach-pdfs')) ?>" class="btn btn-sm btn-link ms-2">Refresh</a>
      </div>

      <form id="attachForm" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <div class="row g-3">
          <div class="col-6 col-md-3">
            <label class="form-label small">App</label>
            <select class="form-select" name="app" id="appSel">
              <option value="ospyndocs">OspynDocs</option>
              <option value="eoffice">eOffice</option>
            </select>
          </div>
          <div class="col-12 col-md-5">
            <label class="form-label small">Match files to records by</label>
            <select class="form-select" name="mode" id="modeSel">
              <option value="filename">Reference in filename (before first "_")</option>
              <option value="exact">Whole filename = reference</option>
              <option value="map">Computer-number mapping CSV (eOffice)</option>
            </select>
            <div class="form-text" id="modeHelp">
              e.g. <code>1-2020-KDISC_FairCopy_7.pdf</code> &rarr; reference <code>1/2020/KDISC</code>
              (separators are matched flexibly). Multiple PDFs per file are supported.
            </div>
          </div>
          <div class="col-12 col-md-4" id="mapWrap" style="display:none;">
            <label class="form-label small">Mapping CSV</label>
            <input type="file" class="form-control" name="map" accept=".csv">
          </div>

          <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="dryRun" name="dry_run" value="1" checked>
              <label class="form-check-label" for="dryRun">Preview only (don't attach yet)</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="delAfter" name="delete_after" value="1">
              <label class="form-check-label" for="delAfter">Delete each PDF from the staging folder after attaching (saves disk space)</label>
            </div>
          </div>

          <div class="col-12 d-flex gap-2">
            <button type="button" class="btn btn-primary" id="btnStart">
              <span class="spinner-border spinner-border-sm me-1 d-none" id="startSpin"></span>
              <i class="bi bi-play-fill me-1"></i>Scan &amp; Start
            </button>
            <button type="button" class="btn btn-outline-danger d-none" id="btnStop">Stop</button>
          </div>
        </div>
      </form>

      <!-- Progress -->
      <div id="progressWrap" class="mt-3 d-none">
        <div class="d-flex justify-content-between small mb-1">
          <span id="progressLabel">Starting…</span>
          <span id="progressPct">0%</span>
        </div>
        <div class="progress" role="progressbar" style="height:20px;">
          <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width:0%"></div>
        </div>
      </div>

      <!-- Totals -->
      <div id="totalsWrap" class="mt-3 d-none">
        <div class="alert" id="totalsBanner"></div>
        <div id="issuesBox"></div>
      </div>
    </div>
  </div>
</div>

<script>
  window.AttachConfig = {
    scanUrl: <?= json_encode(base_url('/attach-pdfs/scan')) ?>,
    runUrl:  <?= json_encode(base_url('/attach-pdfs/run')) ?>,
    csrfToken: <?= json_encode(\App\Csrf::token()) ?>,
    dirs: <?= json_encode($dirs) ?>
  };
</script>
<script src="<?= e(base_url('/assets/js/attach.js')) ?>"></script>
