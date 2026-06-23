<?php
/**
 * Error panel — one line per (row, column, description).
 *
 * Expects: $rows (validator results).
 */
$errorRows = array_filter($rows, static fn ($r) => $r['level'] === 'error');
?>
<?php if (empty($errorRows)): ?>
  <div class="text-success py-2"><i class="bi bi-check-circle me-1"></i>No errors found.</div>
<?php else: ?>
  <table class="table table-sm mb-0">
    <thead class="table-light">
      <tr><th style="width:80px;">Row</th><th>Column</th><th>Error</th></tr>
    </thead>
    <tbody>
      <?php foreach ($errorRows as $r): ?>
        <?php foreach ($r['errors'] as $err): ?>
          <tr>
            <td><?= (int) $r['line'] ?></td>
            <td><?= e((string) $err['column']) ?></td>
            <td class="text-danger"><?= e((string) $err['message']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
