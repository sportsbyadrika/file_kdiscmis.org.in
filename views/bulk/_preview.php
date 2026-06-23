<?php
/**
 * Validation preview table — colour-coded rows.
 *   green  = valid INSERT
 *   blue   = valid UPDATE / HISTORY_ONLY
 *   yellow = warning
 *   red    = error
 *
 * Expects: $app, $rows (validator results).
 */
$rowClass = static function (array $r): string {
    if ($r['level'] === 'error') return 'table-danger';
    if ($r['level'] === 'warning') return 'table-warning';
    if ($r['kind'] === 'insert') return 'table-success';
    return 'table-primary'; // update / history_only
};
$kindLabel = ['insert' => 'INSERT', 'update' => 'UPDATE', 'history_only' => 'HISTORY'];
?>
<table class="table table-sm table-bordered align-middle mb-0 bulk-preview">
  <thead class="table-light">
    <tr>
      <th>Row</th>
      <th>Operation</th>
      <th>Match Key</th>
      <th>Title / Name</th>
      <th>Status</th>
      <th>Result</th>
      <th>Issues</th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" class="text-center text-muted py-3">No data rows found.</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr class="<?= $rowClass($r) ?>">
        <td><?= (int) $r['line'] ?></td>
        <td><?= e((string) $r['operation']) ?></td>
        <td><?= e((string) $r['ref']) ?></td>
        <td><?= e((string) ($r['data']['title'] ?? '')) ?></td>
        <td><?= e((string) $r['status']) ?></td>
        <td>
          <?php if ($r['level'] === 'error'): ?>
            <span class="badge text-bg-danger">Error</span>
          <?php elseif ($r['level'] === 'warning'): ?>
            <span class="badge text-bg-warning">Warning</span>
          <?php else: ?>
            <span class="badge text-bg-secondary"><?= e($kindLabel[$r['kind']] ?? '') ?></span>
            <?php if (!empty($r['history'])): ?><span class="badge text-bg-info">+history</span><?php endif; ?>
          <?php endif; ?>
        </td>
        <td class="small">
          <?php if (!empty($r['errors'])): ?>
            <?= e(implode('; ', array_map(static fn ($x) => $x['message'], $r['errors']))) ?>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
