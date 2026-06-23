<?php
/**
 * Details / Metadata tab — two-column key/value.
 *
 * Expects: $app, $config, $record, $fields.
 */
$labels = $config['labels'];

$statusClass = static function (string $status): string {
    $s = strtolower($status);
    if (in_array($s, ['closed', 'completed', 'disposed', 'archived', 'rejected', 'cancelled'], true)) return 'text-bg-secondary';
    if (in_array($s, ['open', 'active', 'approved'], true)) return 'text-bg-success';
    if (in_array($s, ['pending', 'under review', 'in progress', 'draft'], true)) return 'text-bg-warning';
    return 'text-bg-light border';
};

// Build ordered label/value pairs.
$pairs = [];
$pairs[] = [$labels['ref'], e((string) $record['reference_no'])];

$remarksCol = null;
foreach ($fields as $key => [$col, $label, $required]) {
    if ($key === 'remarks') { $remarksCol = $col; continue; }
    $raw = (string) ($record[$col] ?? '');
    if ($key === 'doc_date') {
        $val = $raw !== '' ? e(format_date($raw)) : '<span class="text-muted">—</span>';
    } elseif ($key === 'tags') {
        $val = $raw !== '' ? implode(' ', array_map(
            static fn ($t) => '<span class="badge text-bg-light border me-1">' . e(trim($t)) . '</span>',
            array_filter(explode(',', $raw), static fn ($t) => trim($t) !== '')
        )) : '<span class="text-muted">—</span>';
    } else {
        $val = $raw !== '' ? e($raw) : '<span class="text-muted">—</span>';
    }
    $pairs[] = [$label, $val];
}

$pairs[] = ['Status', '<span class="badge ' . $statusClass((string) $record['status']) . '">' . e((string) $record['status']) . '</span>'];
$pairs[] = ['Uploaded By', e((string) ($record['uploaded_by_name'] ?? '—'))];
$pairs[] = ['Upload Date', e(format_dt($record['upload_date'] ?? null))];
$pairs[] = ['Last Updated By', e((string) ($record['updated_by_name'] ?? '—'))];
$pairs[] = ['Last Updated On', e(format_dt($record['last_updated_on'] ?? null))];

$remarks = $remarksCol ? (string) ($record[$remarksCol] ?? '') : '';
$pairs[] = ['Remarks', $remarks !== '' ? nl2br(e($remarks)) : '<span class="text-muted">—</span>'];
?>
<div class="details-grid">
  <?php foreach ($pairs as [$k, $v]): ?>
    <div class="detail-row">
      <div class="detail-key"><?= e($k) ?></div>
      <div class="detail-val"><?= $v ?></div>
    </div>
  <?php endforeach; ?>
</div>
