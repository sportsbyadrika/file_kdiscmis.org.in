<?php
/**
 * Pagination controls + per-page selector.
 *
 * Expects: $total, $page, $perPage, $perPageOpts.
 */
$pages = max(1, (int) ceil($total / $perPage));
$page  = min(max(1, $page), $pages);

// Window of page numbers around the current page.
$window = 2;
$start  = max(1, $page - $window);
$end    = min($pages, $page + $window);

$link = static function (int $p, string $label, bool $disabled = false, bool $activeP = false, ?string $aria = null): string {
    $cls = 'page-item' . ($disabled ? ' disabled' : '') . ($activeP ? ' active' : '');
    $a = '<a class="page-link" href="#" data-page="' . $p . '"' . ($aria ? ' aria-label="' . e($aria) . '"' : '') . '>' . $label . '</a>';
    return '<li class="' . $cls . '">' . $a . '</li>';
};
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div class="d-flex align-items-center gap-2">
    <label for="perPageSelect" class="form-label mb-0 small text-muted">Rows</label>
    <select id="perPageSelect" class="form-select form-select-sm" style="width:auto;">
      <?php foreach ($perPageOpts as $opt): ?>
        <option value="<?= (int) $opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= (int) $opt ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <nav aria-label="File list pagination">
    <ul class="pagination pagination-sm mb-0">
      <?= $link(1, '<i class="bi bi-chevron-bar-left"></i>', $page <= 1, false, 'First') ?>
      <?= $link($page - 1, '<i class="bi bi-chevron-left"></i>', $page <= 1, false, 'Previous') ?>
      <?php if ($start > 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
      <?php for ($p = $start; $p <= $end; $p++): ?>
        <?= $link($p, (string) $p, false, $p === $page) ?>
      <?php endfor; ?>
      <?php if ($end < $pages): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
      <?= $link($page + 1, '<i class="bi bi-chevron-right"></i>', $page >= $pages, false, 'Next') ?>
      <?= $link($pages, '<i class="bi bi-chevron-bar-right"></i>', $page >= $pages, false, 'Last') ?>
    </ul>
  </nav>
</div>
