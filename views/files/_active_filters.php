<?php
/**
 * Active filter chips (removable). Each chip carries data-filter (+ data-value
 * for multi-selects) so the client can clear it and re-query.
 *
 * Expects: $app, $config, $filters.
 */
$labels = $config['labels'];
$chips = [];

if (($filters['keyword'] ?? '') !== '') {
    $chips[] = ['key' => 'keyword', 'value' => '', 'text' => 'Keyword: "' . $filters['keyword'] . '"'];
}
if (($filters['date_from'] ?? '') !== '' || ($filters['date_to'] ?? '') !== '') {
    $basis = ($filters['date_basis'] ?? 'document') === 'upload' ? 'Upload date' : $labels['doc_date'];
    $range = trim(($filters['date_from'] ?? '') . ' → ' . ($filters['date_to'] ?? ''), ' →');
    $chips[] = ['key' => 'date', 'value' => '', 'text' => $basis . ': ' . $range];
}
foreach (($filters['group'] ?? []) as $v) {
    $chips[] = ['key' => 'group', 'value' => $v, 'text' => $labels['group'] . ': ' . $v];
}
foreach (($filters['category'] ?? []) as $v) {
    $chips[] = ['key' => 'category', 'value' => $v, 'text' => $labels['category'] . ': ' . $v];
}
foreach (($filters['status'] ?? []) as $v) {
    $chips[] = ['key' => 'status', 'value' => $v, 'text' => 'Status: ' . $v];
}
if ((int) ($filters['uploaded_by'] ?? 0) > 0) {
    $chips[] = ['key' => 'uploaded_by', 'value' => '', 'text' => 'Uploaded by (filtered)'];
}
if (!empty($filters['has_attachments'])) {
    $chips[] = ['key' => 'has_attachments', 'value' => '', 'text' => 'Has attachments'];
}
if (!empty($filters['has_history'])) {
    $chips[] = ['key' => 'has_history', 'value' => '', 'text' => 'Has history'];
}
?>
<?php if (!empty($chips)): ?>
  <div class="d-flex flex-wrap align-items-center gap-2">
    <span class="small text-muted">Active filters:</span>
    <?php foreach ($chips as $chip): ?>
      <span class="badge rounded-pill text-bg-primary d-inline-flex align-items-center gap-1 active-filter-chip"
            data-filter="<?= e($chip['key']) ?>" data-value="<?= e($chip['value']) ?>">
        <?= e($chip['text']) ?>
        <button type="button" class="btn-close btn-close-white btn-sm js-remove-filter" aria-label="Remove"></button>
      </span>
    <?php endforeach; ?>
    <button type="button" class="btn btn-sm btn-link text-decoration-none js-clear-filters">Clear all</button>
  </div>
<?php endif; ?>
