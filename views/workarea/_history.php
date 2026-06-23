<?php
/**
 * Transaction History tab — immutable, newest first.
 *
 * Expects: $history.
 */
$srcBadge = static function (string $src): string {
    return $src === 'BULK_IMPORT' ? 'text-bg-info' : 'text-bg-secondary';
};
?>
<div class="table-responsive">
  <table class="table table-sm table-hover align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th style="width:1%;">#</th>
        <th>Date</th>
        <th>Transaction Type</th>
        <th>From</th>
        <th>To</th>
        <th>Note</th>
        <th>Performed By</th>
        <th>Source</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($history)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No transaction history recorded.</td></tr>
      <?php else: ?>
        <?php foreach ($history as $i => $h): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td class="text-nowrap"><?= e(format_date($h['history_date'])) ?></td>
            <td><?= e((string) $h['transaction_type']) ?></td>
            <td><?= $h['from_status'] !== null && $h['from_status'] !== '' ? e((string) $h['from_status']) : '<span class="text-muted">—</span>' ?></td>
            <td><?= $h['to_status'] !== null && $h['to_status'] !== '' ? e((string) $h['to_status']) : '<span class="text-muted">—</span>' ?></td>
            <td><?= $h['note'] !== null && $h['note'] !== '' ? e((string) $h['note']) : '<span class="text-muted">—</span>' ?></td>
            <td><?= e((string) $h['performed_by_name']) ?></td>
            <td><span class="badge <?= e($srcBadge((string) $h['source'])) ?>"><?= e((string) $h['source']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>
