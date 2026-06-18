<?php
/**
 * File list table (header with sort + rows with actions).
 *
 * Expects: $app, $config, $columns, $visible (keys), $rows, $sort.
 */
$visibleSet = array_flip($visible);
$visibleCols = array_filter($columns, static fn ($c) => isset($visibleSet[$c['key']]));

/** Status badge colour. */
$statusClass = static function (string $status): string {
    $s = strtolower($status);
    if (in_array($s, ['closed', 'completed', 'disposed', 'archived', 'rejected', 'cancelled'], true)) return 'text-bg-secondary';
    if (in_array($s, ['open', 'active', 'approved'], true)) return 'text-bg-success';
    if (in_array($s, ['pending', 'under review', 'in progress', 'draft'], true)) return 'text-bg-warning';
    return 'text-bg-light border';
};

/** Render a cell value by column key. */
$cell = static function (array $row, string $key) use ($statusClass): string {
    switch ($key) {
        case 'ref':
            return '<span class="fw-semibold">' . e((string) $row['ref']) . '</span>';
        case 'title':
            return '<span class="d-inline-block text-truncate" style="max-width:260px;" title="' . e((string) $row['title']) . '">' . e((string) $row['title']) . '</span>';
        case 'group':
            return e((string) ($row['group'] ?? ''));
        case 'category':
            return $row['category'] !== '' && $row['category'] !== null
                ? '<span class="badge text-bg-light border">' . e((string) $row['category']) . '</span>' : '<span class="text-muted">—</span>';
        case 'doc_date':
            return e(format_date($row['doc_date'] ?? null));
        case 'status':
            return '<span class="badge ' . $statusClass((string) $row['status']) . '">' . e((string) $row['status']) . '</span>';
        case 'upload_date':
            return '<span class="text-nowrap">' . e(format_dt($row['upload_date'] ?? null)) . '</span>';
        case 'last_updated':
            return '<span class="text-nowrap">' . e(format_dt($row['last_updated'] ?? null)) . '</span>';
        case 'uploaded_by':
            return e((string) ($row['uploaded_by'] ?? ''));
    }
    return '';
};
?>
<table class="table table-hover table-sm align-middle mb-0 filelist-table">
  <thead class="table-light">
    <tr>
      <?php foreach ($visibleCols as $col):
        $isActive = $sort['key'] === $col['key'];
        $nextDir  = ($isActive && $sort['dir'] === 'asc') ? 'desc' : 'asc';
      ?>
        <th scope="col" <?= $col['sortable'] ? 'class="sortable" role="button" data-sort-key="' . e($col['key']) . '" data-sort-dir="' . e($nextDir) . '"' : '' ?>>
          <?= e($col['label']) ?>
          <?php if ($col['sortable']): ?>
            <?php if ($isActive): ?>
              <i class="bi <?= $sort['dir'] === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill' ?> sort-arrow"></i>
            <?php else: ?>
              <i class="bi bi-arrow-down-up sort-arrow text-muted"></i>
            <?php endif; ?>
          <?php endif; ?>
        </th>
      <?php endforeach; ?>
      <th scope="col" class="text-end" style="width:1%;">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr>
        <td colspan="<?= count($visibleCols) + 1 ?>" class="text-center text-muted py-5">
          <div class="display-6 mb-2"><i class="bi bi-inbox"></i></div>
          No records match the current filters.
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($rows as $row):
        $id = (int) $row['id'];
        $attCount = (int) $row['attachment_count'];
        $viewUrl = base_url('/' . $app . '/view?id=' . $id);
      ?>
        <tr data-id="<?= $id ?>">
          <?php foreach ($visibleCols as $col): ?>
            <td><?= $cell($row, $col['key']) ?></td>
          <?php endforeach; ?>
          <td class="text-end text-nowrap">
            <div class="btn-group btn-group-sm" role="group">
              <a href="<?= e($viewUrl) ?>" class="btn btn-outline-primary" title="View (Work Area)">
                <i class="bi bi-eye"></i>
              </a>
              <button type="button" class="btn btn-outline-secondary js-download" data-count="<?= $attCount ?>"
                      data-id="<?= $id ?>" title="Download attachments (<?= $attCount ?>)" <?= $attCount === 0 ? 'disabled' : '' ?>>
                <i class="bi bi-download"></i><?= $attCount > 0 ? '<span class="badge text-bg-secondary ms-1">' . $attCount . '</span>' : '' ?>
              </button>
              <button type="button" class="btn btn-outline-secondary js-pdf" data-id="<?= $id ?>" title="Generate PDF">
                <i class="bi bi-file-earmark-pdf"></i>
              </button>
              <button type="button" class="btn btn-outline-secondary js-edit" data-id="<?= $id ?>" title="Edit metadata">
                <i class="bi bi-pencil"></i>
              </button>
              <button type="button" class="btn btn-outline-danger js-delete" data-id="<?= $id ?>"
                      data-ref="<?= e((string) $row['ref']) ?>" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
