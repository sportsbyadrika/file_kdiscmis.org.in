<?php
/**
 * Metadata edit form (injected into the edit modal).
 *
 * Expects: $app, $config, $fields, $record, $statuses.
 */
use App\Csrf;

$val = static function (string $col) use ($record): string {
    return (string) ($record[$col] ?? '');
};
?>
<div class="modal-header">
  <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Metadata</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<form id="editForm" data-action="<?= e(base_url('/' . $app . '/update')) ?>">
  <div class="modal-body">
    <?= Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">

    <div class="alert alert-danger d-none" id="editFormError"></div>

    <div class="mb-3">
      <label class="form-label"><?= e($config['labels']['ref']) ?></label>
      <input type="text" class="form-control" value="<?= e((string) $record['reference_no']) ?>" readonly disabled>
      <div class="form-text">The match key cannot be changed here.</div>
    </div>

    <?php foreach ($fields as $key => [$col, $label, $required]): ?>
      <div class="mb-3">
        <label for="f_<?= e($key) ?>" class="form-label">
          <?= e($label) ?><?= $required ? ' <span class="text-danger">*</span>' : '' ?>
        </label>
        <?php if ($key === 'remarks'): ?>
          <textarea class="form-control" id="f_<?= e($key) ?>" name="<?= e($key) ?>" rows="2"><?= e($val($col)) ?></textarea>
        <?php elseif ($key === 'doc_date'): ?>
          <input type="date" class="form-control" id="f_<?= e($key) ?>" name="<?= e($key) ?>"
                 value="<?= e($val($col) !== '' ? substr($val($col), 0, 10) : '') ?>">
        <?php else: ?>
          <input type="text" class="form-control" id="f_<?= e($key) ?>" name="<?= e($key) ?>"
                 value="<?= e($val($col)) ?>" <?= $required ? 'required' : '' ?>>
        <?php endif; ?>
        <div class="invalid-feedback" data-error-for="<?= e($key) ?>"></div>
      </div>
    <?php endforeach; ?>

    <div class="mb-1">
      <label for="f_status" class="form-label">Status <span class="text-danger">*</span></label>
      <input type="text" class="form-control" id="f_status" name="status" list="statusOptions"
             value="<?= e((string) $record['status']) ?>" required autocomplete="off">
      <datalist id="statusOptions">
        <?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>"></option><?php endforeach; ?>
      </datalist>
      <div class="invalid-feedback" data-error-for="status"></div>
    </div>
  </div>

  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-primary">
      <span class="spinner-border spinner-border-sm me-1 d-none" id="editSpinner"></span>Save Changes
    </button>
  </div>
</form>
