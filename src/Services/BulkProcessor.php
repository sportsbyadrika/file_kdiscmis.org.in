<?php
declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Database;
use App\Models\BulkTemplate;
use App\Models\FileList;
use App\Models\FileRecord;

/**
 * Processes validated bulk rows. Each record is committed in its own
 * transaction so one failure never rolls back the others. History rows are
 * deduplicated on (file_id, history_date, transaction_type); every metadata
 * change is written to file_update_log with the batch id.
 */
final class BulkProcessor
{
    /**
     * @param array<int,array<string,mixed>> $rows  validated rows (errors excluded)
     * @return array<string,mixed> summary incl. batch_id, counts, report_path, results
     */
    public static function process(string $app, array $rows, int $userId): array
    {
        $batchId = self::uuid();
        $now = date('Y-m-d H:i:s');
        $fields = FileRecord::fields($app);
        $dupCol = $app === 'eoffice' ? 'file_ref_no' : 'document_id';
        $metaTable = FileList::config($app)['meta_table'];

        $counts = ['total' => 0, 'inserted' => 0, 'updated' => 0, 'history' => 0, 'skipped' => 0, 'failed' => 0];
        $results = [];

        foreach ($rows as $row) {
            $counts['total']++;
            $line = $row['line'];
            $ref  = (string) $row['ref'];
            $op   = (string) $row['operation'];

            try {
                $pdo = Database::connection();
                $pdo->beginTransaction();

                if ($row['kind'] === 'insert') {
                    $fileId = self::insertRecord($app, $metaTable, $dupCol, $fields, $row, $userId, $now);
                    self::logEvent($fileId, $userId, $now, $batchId, ['_event' => 'Inserted']);
                    $hist = self::applyHistory($fileId, $row['history'], $userId);
                    $counts['inserted']++;
                    $counts['history'] += $hist;
                    $detail = 'Inserted' . ($hist ? " (+{$hist} history)" : '');
                    $result = 'Inserted';
                } elseif ($row['kind'] === 'update') {
                    $fileId = FileRecord::findIdByRef($app, $ref);
                    if ($fileId === null) {
                        throw new \RuntimeException('Match key no longer exists.');
                    }
                    $changed = self::updateRecord($app, $metaTable, $fields, $fileId, $row, $userId, $now, $batchId);
                    $hist = self::applyHistory($fileId, $row['history'], $userId);
                    $counts['history'] += $hist;
                    if ($changed > 0) {
                        $counts['updated']++;
                        $result = 'Updated';
                        $detail = "Updated {$changed} field(s)" . ($hist ? " (+{$hist} history)" : '');
                    } elseif ($hist > 0) {
                        self::logEvent($fileId, $userId, $now, $batchId, ['_event' => 'History Added']);
                        $result = 'History Added';
                        $detail = "Added {$hist} history row(s); no metadata change";
                    } else {
                        $counts['skipped']++;
                        $result = 'Skipped';
                        $detail = 'No changes to apply';
                    }
                } else { // history_only
                    $fileId = FileRecord::findIdByRef($app, $ref);
                    if ($fileId === null) {
                        throw new \RuntimeException('Match key no longer exists.');
                    }
                    $hist = self::applyHistory($fileId, $row['history'], $userId);
                    $counts['history'] += $hist;
                    if ($hist > 0) {
                        self::logEvent($fileId, $userId, $now, $batchId, ['_event' => 'History Added']);
                        $result = 'History Added';
                        $detail = "Added {$hist} history row(s)";
                    } else {
                        $counts['skipped']++;
                        $result = 'Skipped';
                        $detail = 'History already present (deduplicated)';
                    }
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                if (Database::connection()->inTransaction()) {
                    Database::connection()->rollBack();
                }
                $counts['failed']++;
                $result = 'Failed';
                $detail = $e->getMessage();
            }

            $results[] = ['line' => $line, 'operation' => $op, 'ref' => $ref, 'result' => $result, 'detail' => $detail];
        }

        $skippedTotal = $counts['skipped'] + $counts['failed'];
        $reportPath = self::writeReport($batchId, $results);

        Database::run(
            'INSERT INTO bulk_import_batches (batch_id, source_app, imported_by, imported_at, total_rows, inserted, updated, skipped, report_path)
             VALUES (:b, :app, :u, :at, :t, :i, :upd, :s, :rp)',
            [
                'b' => $batchId, 'app' => $app, 'u' => $userId, 'at' => $now,
                't' => $counts['total'], 'i' => $counts['inserted'], 'upd' => $counts['updated'],
                's' => $skippedTotal, 'rp' => $reportPath,
            ]
        );

        return [
            'batch_id'        => $batchId,
            'total'           => $counts['total'],
            'inserted'        => $counts['inserted'],
            'updated'         => $counts['updated'],
            'history'         => $counts['history'],
            'skipped'         => $counts['skipped'],
            'failed'          => $counts['failed'],
            'results'         => $results,
            'has_report'      => $reportPath !== null,
        ];
    }

    private static function insertRecord(string $app, string $metaTable, string $dupCol, array $fields, array $row, int $userId, string $now): int
    {
        Database::run(
            'INSERT INTO files (source_app, reference_no, status, file_note, uploaded_by, upload_date, last_updated_by, last_updated_on, is_deleted, created_at)
             VALUES (:app, :ref, :status, :note, :uby, :udate, :luby, :luon, 0, :created)',
            [
                'app' => $app, 'ref' => $row['ref'], 'status' => $row['status'], 'note' => '',
                'uby' => $userId, 'udate' => $now, 'luby' => $userId, 'luon' => $now, 'created' => $now,
            ]
        );
        $fileId = (int) Database::connection()->lastInsertId();

        $cols = ['file_id' => $fileId, $dupCol => $row['ref']];
        foreach ($fields as $key => [$col, $label, $req]) {
            $val = (string) ($row['data'][$key] ?? '');
            $cols[$col] = ($key === 'doc_date' && $val === '') ? null : $val;
        }
        $names = implode(', ', array_keys($cols));
        $place = implode(', ', array_map(static fn ($c) => ':' . $c, array_keys($cols)));
        Database::run("INSERT INTO {$metaTable} ({$names}) VALUES ({$place})", $cols);

        return $fileId;
    }

    /** @return int number of changed fields */
    private static function updateRecord(string $app, string $metaTable, array $fields, int $fileId, array $row, int $userId, string $now, string $batchId): int
    {
        $current = FileRecord::find($app, $fileId);
        if ($current === null) {
            throw new \RuntimeException('Record not found.');
        }

        $changes = [];
        $set = [];
        $params = ['id' => $fileId];
        foreach ($fields as $key => [$col, $label, $req]) {
            $new = (string) ($row['data'][$key] ?? '');
            if ($new === '') {
                continue; // only provided (non-empty) cells overwrite
            }
            $old = (string) ($current[$col] ?? '');
            if ($key === 'doc_date') {
                $old = $old !== '' ? substr($old, 0, 10) : '';
            }
            if ($old !== $new) {
                $set[] = "{$col} = :{$key}";
                $params[$key] = $new;
                $changes[$label] = ['old' => $old, 'new' => $new];
            }
        }
        if ($set) {
            Database::run("UPDATE {$metaTable} SET " . implode(', ', $set) . ' WHERE file_id = :id', $params);
        }

        $status = (string) $row['status'];
        if ($status !== '' && $status !== (string) $current['status']) {
            $changes['Status'] = ['old' => (string) $current['status'], 'new' => $status];
            Database::run(
                'UPDATE files SET status = :s, last_updated_by = :u, last_updated_on = :now WHERE id = :id',
                ['s' => $status, 'u' => $userId, 'now' => $now, 'id' => $fileId]
            );
        } elseif ($changes) {
            Database::run(
                'UPDATE files SET last_updated_by = :u, last_updated_on = :now WHERE id = :id',
                ['u' => $userId, 'now' => $now, 'id' => $fileId]
            );
        }

        if ($changes) {
            Database::run(
                'INSERT INTO file_update_log (file_id, updated_by, update_source, updated_at, fields_changed, import_batch_id)
                 VALUES (:fid, :u, :src, :now, :fc, :batch)',
                [
                    'fid' => $fileId, 'u' => $userId, 'src' => 'BULK_IMPORT', 'now' => $now,
                    'fc' => json_encode($changes, JSON_UNESCAPED_UNICODE), 'batch' => $batchId,
                ]
            );
        }

        return count($changes);
    }

    /** Write a batch-linked audit entry (so every touched record is traceable). */
    private static function logEvent(int $fileId, int $userId, string $now, string $batchId, array $changes): void
    {
        Database::run(
            'INSERT INTO file_update_log (file_id, updated_by, update_source, updated_at, fields_changed, import_batch_id)
             VALUES (:fid, :u, :src, :now, :fc, :batch)',
            [
                'fid' => $fileId, 'u' => $userId, 'src' => 'BULK_IMPORT', 'now' => $now,
                'fc' => json_encode($changes, JSON_UNESCAPED_UNICODE), 'batch' => $batchId,
            ]
        );
    }

    /** Insert a deduplicated history row; returns 1 if inserted, 0 if deduped/none. */
    private static function applyHistory(int $fileId, $history, int $userId): int
    {
        if (!is_array($history) || ($history['date'] ?? '') === '' || ($history['type'] ?? '') === '') {
            return 0;
        }
        $exists = Database::run(
            'SELECT 1 FROM file_transaction_history WHERE file_id = :f AND history_date = :d AND transaction_type = :t LIMIT 1',
            ['f' => $fileId, 'd' => $history['date'], 't' => $history['type']]
        )->fetch();
        if ($exists) {
            return 0;
        }
        Database::run(
            'INSERT INTO file_transaction_history (file_id, history_date, transaction_type, from_status, to_status, note, source, performed_by, created_at)
             VALUES (:f, :d, :t, :fr, :to, :n, :src, :u, :now)',
            [
                'f' => $fileId, 'd' => $history['date'], 't' => $history['type'],
                'fr' => $history['from'] !== '' ? $history['from'] : null,
                'to' => $history['to'] !== '' ? $history['to'] : null,
                'n'  => $history['note'] !== '' ? $history['note'] : null,
                'src' => 'BULK_IMPORT', 'u' => $userId, 'now' => date('Y-m-d H:i:s'),
            ]
        );
        return 1;
    }

    private static function writeReport(string $batchId, array $results): ?string
    {
        $dir = rtrim((string) Config::get('storage.reports'), '/');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $path = $dir . '/' . $batchId . '.csv';
        $fh = @fopen($path, 'w');
        if ($fh === false) {
            return null;
        }
        fputcsv($fh, ['Row', 'Operation', 'Match Key', 'Result', 'Error Detail'], ',', '"', '');
        foreach ($results as $r) {
            fputcsv($fh, [$r['line'], $r['operation'], $r['ref'], $r['result'], $r['detail']], ',', '"', '');
        }
        fclose($fh);
        return $batchId . '.csv';
    }

    public static function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
