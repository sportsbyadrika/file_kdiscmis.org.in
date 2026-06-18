<?php
/**
 * Toast container. Renders any queued flash messages and exposes a JS
 * helper (window.showToast) for client-side notifications.
 *
 * Toast types map to Bootstrap colour utilities.
 */
use App\Flash;

$messages = Flash::pull();
$map = [
    'success' => 'text-bg-success',
    'warning' => 'text-bg-warning',
    'error'   => 'text-bg-danger',
    'info'    => 'text-bg-info',
];
?>
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" style="z-index: 1100;">
  <?php foreach ($messages as $m):
      $cls = $map[$m['type']] ?? 'text-bg-secondary'; ?>
    <div class="toast align-items-center <?= e($cls) ?> border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="5000">
      <div class="d-flex">
        <div class="toast-body"><?= e($m['message']) ?></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  <?php endforeach; ?>
</div>
