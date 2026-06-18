<?php
/**
 * Attachments tab content (also returned by AJAX after upload/delete).
 *
 * Expects: $app, $record_id, $attachments.
 */
use App\Models\Attachment;
?>
<div class="table-responsive">
  <table class="table table-sm table-hover align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th style="width:1%;">#</th>
        <th>Filename</th>
        <th>Type</th>
        <th>Size</th>
        <th>Uploaded By</th>
        <th>Upload Date</th>
        <th class="text-end">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($attachments)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No attachments yet.</td></tr>
      <?php else: ?>
        <?php foreach ($attachments as $i => $a):
          $aid = (int) $a['attachment_id'];
          $kind = Attachment::isPreviewable((string) $a['mime_type']);
          $dlUrl = base_url('/' . $app . '/attachment/download?id=' . $aid);
          $pvUrl = base_url('/' . $app . '/attachment/preview?id=' . $aid);
        ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td>
              <?php if ($kind !== ''): ?>
                <a href="#" class="js-att-preview text-decoration-none"
                   data-kind="<?= e($kind) ?>" data-url="<?= e($pvUrl) ?>" data-name="<?= e((string) $a['original_filename']) ?>">
                  <i class="bi bi-paperclip me-1"></i><?= e((string) $a['original_filename']) ?>
                </a>
              <?php else: ?>
                <a href="<?= e($dlUrl) ?>" class="text-decoration-none">
                  <i class="bi bi-paperclip me-1"></i><?= e((string) $a['original_filename']) ?>
                </a>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= e(Attachment::badgeClass((string) $a['original_filename'])) ?>"><?= e(Attachment::extension((string) $a['original_filename'])) ?></span></td>
            <td class="text-nowrap"><?= e(format_bytes((int) $a['file_size_bytes'])) ?></td>
            <td><?= e((string) $a['uploaded_by_name']) ?></td>
            <td class="text-nowrap"><?= e(format_dt($a['uploaded_at'] ?? null)) ?></td>
            <td class="text-end text-nowrap">
              <div class="btn-group btn-group-sm">
                <?php if ($kind !== ''): ?>
                  <button type="button" class="btn btn-outline-secondary js-att-preview" title="Preview"
                          data-kind="<?= e($kind) ?>" data-url="<?= e($pvUrl) ?>" data-name="<?= e((string) $a['original_filename']) ?>">
                    <i class="bi bi-eye"></i>
                  </button>
                <?php endif; ?>
                <a href="<?= e($dlUrl) ?>" class="btn btn-outline-secondary" title="Download"><i class="bi bi-download"></i></a>
                <button type="button" class="btn btn-outline-danger js-att-delete" title="Delete"
                        data-id="<?= $aid ?>" data-name="<?= e((string) $a['original_filename']) ?>">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
