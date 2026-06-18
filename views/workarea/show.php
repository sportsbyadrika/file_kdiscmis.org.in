<?php
/**
 * File Work Area — split-panel single-record workspace.
 *
 * Expects: $app, $config, $record, $fields, $attachments, $history.
 */
use App\View;
use App\Models\FileRecord;

$id   = (int) $record['id'];
$note = FileRecord::sanitizeHtml((string) ($record['file_note'] ?? ''));
$noteText = trim(preg_replace('/\s+/', ' ', strip_tags($note)));
$chars = mb_strlen($noteText);
$words = $noteText === '' ? 0 : count(preg_split('/\s+/u', $noteText));

$statusClass = static function (string $status): string {
    $s = strtolower($status);
    if (in_array($s, ['closed', 'completed', 'disposed', 'archived', 'rejected', 'cancelled'], true)) return 'text-bg-secondary';
    if (in_array($s, ['open', 'active', 'approved'], true)) return 'text-bg-success';
    if (in_array($s, ['pending', 'under review', 'in progress', 'draft'], true)) return 'text-bg-warning';
    return 'text-bg-light border';
};
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">

<!-- Sticky top bar -->
<div class="workarea-topbar">
  <div class="container-fluid py-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <nav aria-label="breadcrumb" class="me-auto">
        <ol class="breadcrumb mb-0 small">
          <li class="breadcrumb-item"><a href="<?= e(base_url('/dashboard')) ?>">Home</a></li>
          <li class="breadcrumb-item"><a href="<?= e(base_url('/' . $app)) ?>"><?= e($config['label']) ?></a></li>
          <li class="breadcrumb-item"><a href="<?= e(base_url('/' . $app)) ?>">File List</a></li>
          <li class="breadcrumb-item active" aria-current="page"><?= e((string) $record['reference_no']) ?></li>
        </ol>
      </nav>

      <span class="badge <?= $statusClass((string) $record['status']) ?>"><?= e((string) $record['status']) ?></span>
      <span class="small text-muted">
        Updated <?= e(format_dt($record['last_updated_on'] ?? null)) ?>
        <?php if (!empty($record['updated_by_name'])): ?>by <?= e((string) $record['updated_by_name']) ?><?php endif; ?>
      </span>

      <div class="btn-group btn-group-sm ms-2">
        <button type="button" class="btn btn-outline-primary" id="btnEditMeta"><i class="bi bi-pencil me-1"></i>Edit Metadata</button>
        <button type="button" class="btn btn-outline-secondary" id="btnGenPdf"><i class="bi bi-file-earmark-pdf me-1"></i>Generate PDF</button>
        <a href="<?= e(base_url('/' . $app)) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to List</a>
      </div>
    </div>
  </div>
</div>

<!-- Split workspace -->
<div class="workarea-split" id="workareaSplit">

  <!-- Left: File Note (40%) -->
  <section class="wa-panel wa-note" id="notePanel">
    <div class="wa-panel-head">
      <div class="d-flex align-items-center gap-2">
        <strong><i class="bi bi-journal-text me-1"></i>File Note</strong>
        <span class="small text-muted" id="noteCounts"><?= $chars ?> chars · <?= $words ?> words</span>
      </div>
      <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-secondary" id="btnExpandNote" title="Expand">
          <i class="bi bi-arrows-angle-expand"></i>
        </button>
        <button type="button" class="btn btn-outline-primary" id="btnEditNote"><i class="bi bi-pencil-square me-1"></i>Edit Note</button>
      </div>
    </div>

    <div class="wa-panel-body">
      <!-- Display mode -->
      <div id="noteDisplay" class="note-content"><?= $note !== '' ? $note : '<p class="text-muted">No note recorded. Click <strong>Edit Note</strong> to add one.</p>' ?></div>

      <!-- Edit mode (hidden until toggled) -->
      <div id="noteEditor" class="d-none">
        <div id="quillEditor"></div>
        <div class="d-flex justify-content-end gap-2 mt-2">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCancelNote">Cancel</button>
          <button type="button" class="btn btn-sm btn-primary" id="btnSaveNote">
            <span class="spinner-border spinner-border-sm me-1 d-none" id="noteSpinner"></span>Save Note
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- Right: Tabs (60%) -->
  <section class="wa-panel wa-tabs" id="tabsPanel">
    <ul class="nav nav-tabs px-2 pt-2" id="waTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDetails" type="button" data-tab="details">
          <i class="bi bi-info-circle me-1"></i>Details
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAttachments" type="button" data-tab="attachments">
          <i class="bi bi-paperclip me-1"></i>Attachments <span class="badge text-bg-secondary" id="attCount"><?= count($attachments) ?></span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHistory" type="button" data-tab="history">
          <i class="bi bi-clock-history me-1"></i>History <span class="badge text-bg-secondary"><?= count($history) ?></span>
        </button>
      </li>
    </ul>

    <div class="tab-content wa-panel-body" id="waTabContent">
      <!-- Details -->
      <div class="tab-pane fade show active" id="tabDetails" role="tabpanel">
        <?= View::renderPartial('workarea/_details', ['app' => $app, 'config' => $config, 'record' => $record, 'fields' => $fields]) ?>
      </div>

      <!-- Attachments -->
      <div class="tab-pane fade" id="tabAttachments" role="tabpanel">
        <div class="d-flex justify-content-end mb-2">
          <form id="attUploadForm" class="d-flex align-items-center gap-2" enctype="multipart/form-data">
            <input type="file" class="form-control form-control-sm" name="attachment" id="attFile" required style="max-width:280px;">
            <button type="submit" class="btn btn-sm btn-primary text-nowrap">
              <span class="spinner-border spinner-border-sm me-1 d-none" id="attSpinner"></span>
              <i class="bi bi-cloud-arrow-up me-1"></i>Upload Additional Attachment
            </button>
          </form>
        </div>
        <div id="attachmentsContainer">
          <?= View::renderPartial('workarea/_attachments', ['app' => $app, 'record_id' => $id, 'attachments' => $attachments]) ?>
        </div>
      </div>

      <!-- History -->
      <div class="tab-pane fade" id="tabHistory" role="tabpanel">
        <div class="d-flex justify-content-end mb-2">
          <a href="<?= e(base_url('/' . $app . '/history.csv?id=' . $id)) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-filetype-csv me-1"></i>Export CSV
          </a>
        </div>
        <?= View::renderPartial('workarea/_history', ['history' => $history]) ?>
      </div>
    </div>
  </section>
</div>

<!-- Edit metadata modal (reuses the list's edit endpoints) -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content" id="editModalContent">
      <div class="modal-body text-center py-5"><div class="spinner-border text-primary"></div></div>
    </div>
  </div>
</div>

<!-- Attachment preview modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewTitle">Preview</h5>
        <div class="ms-auto d-flex gap-2">
          <a href="#" class="btn btn-sm btn-outline-secondary" id="previewDownload" target="_blank"><i class="bi bi-download me-1"></i>Download</a>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
      </div>
      <div class="modal-body p-0" id="previewBody" style="min-height:70vh;"></div>
    </div>
  </div>
</div>

<!-- Attachment delete confirm -->
<div class="modal fade" id="attDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Attachment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">Delete <strong id="attDeleteName"></strong>? This is a soft-delete.</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="attConfirmDelete">Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
  window.WorkAreaConfig = {
    app: <?= json_encode($app) ?>,
    id: <?= $id ?>,
    noteUrl: <?= json_encode(base_url('/' . $app . '/note')) ?>,
    editUrl: <?= json_encode(base_url('/' . $app . '/edit')) ?>,
    uploadUrl: <?= json_encode(base_url('/' . $app . '/attachment/upload')) ?>,
    attDeleteUrl: <?= json_encode(base_url('/' . $app . '/attachment/delete')) ?>,
    csrfToken: <?= json_encode(\App\Csrf::token()) ?>
  };
</script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script src="<?= e(base_url('/assets/js/workarea.js')) ?>"></script>
