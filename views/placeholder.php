<?php
/**
 * Generic "coming in a later stage" placeholder, shown inside the full
 * authenticated shell so the navbar is present on every page.
 *
 * Expects: $heading, $icon, $stage, optional $note.
 */
$heading = $heading ?? 'Module';
$icon    = $icon ?? 'bi-hourglass-split';
$stage   = $stage ?? '';
$note    = $note ?? 'This module will be implemented in a later build stage.';
?>
<div class="container py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi <?= e($icon) ?> me-2"></i><?= e($heading) ?></h1>
    <?php if ($stage !== ''): ?>
      <span class="badge text-bg-light border">Stage <?= e($stage) ?></span>
    <?php endif; ?>
  </div>

  <div class="card shadow-sm">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-3"><i class="bi <?= e($icon) ?>"></i></div>
      <p class="lead mb-1"><?= e($heading) ?></p>
      <p class="text-muted mb-0"><?= e($note) ?></p>
    </div>
  </div>
</div>
