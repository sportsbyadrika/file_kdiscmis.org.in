<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Unified, read-only audit log across all files: metadata updates (from
 * file_update_log) and transaction history (from file_transaction_history),
 * filterable — notably by import_batch_id to inspect a single bulk session.
 */
final class AuditLog
{
    public const PER_PAGE_OPTIONS = [25, 50, 100];
    public const DEFAULT_PER_PAGE = 25;

    private const TITLE = "COALESCE(NULLIF(em.subject, ''), NULLIF(om.document_name, ''), f.reference_no)";

    private const ACTOR_U = "COALESCE(NULLIF(uu.full_name, ''), uu.username, 'System')";
    private const ACTOR_H = "COALESCE(NULLIF(uh.full_name, ''), uh.username, 'System')";

    private static function unionSql(): string
    {
        $title = self::TITLE;
        $update = "SELECT 'update' AS event_type, l.updated_at AS ts, l.update_source AS source,
                          l.file_id, f.source_app, f.reference_no, {$title} AS title,
                          l.fields_changed AS detail, NULL AS tx_type, NULL AS from_status, NULL AS to_status,
                          l.import_batch_id AS batch_id, " . self::ACTOR_U . " AS actor
                   FROM file_update_log l
                   JOIN files f ON f.id = l.file_id
                   LEFT JOIN eoffice_metadata em ON em.file_id = f.id
                   LEFT JOIN ospyndocs_metadata om ON om.file_id = f.id
                   LEFT JOIN users uu ON uu.id = l.updated_by";

        $history = "SELECT 'history' AS event_type, h.created_at AS ts, h.source AS source,
                           h.file_id, f.source_app, f.reference_no, {$title} AS title,
                           h.note AS detail, h.transaction_type AS tx_type, h.from_status, h.to_status,
                           NULL AS batch_id, " . self::ACTOR_H . " AS actor
                    FROM file_transaction_history h
                    JOIN files f ON f.id = h.file_id
                    LEFT JOIN eoffice_metadata em ON em.file_id = f.id
                    LEFT JOIN ospyndocs_metadata om ON om.file_id = f.id
                    LEFT JOIN users uh ON uh.id = h.performed_by";

        return "($update UNION ALL $history)";
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function where(array $filters): array
    {
        $conds = [];
        $params = [];

        if (!empty($filters['app']) && FileList::isApp($filters['app'])) {
            $conds[] = 'e.source_app = :app';
            $params['app'] = $filters['app'];
        }
        if (!empty($filters['event_type']) && in_array($filters['event_type'], ['update', 'history'], true)) {
            $conds[] = 'e.event_type = :etype';
            $params['etype'] = $filters['event_type'];
        }
        if (!empty($filters['source'])) {
            $conds[] = 'e.source = :source';
            $params['source'] = $filters['source'];
        }
        if (!empty($filters['batch'])) {
            $conds[] = 'e.batch_id = :batch';
            $params['batch'] = $filters['batch'];
        }
        if (!empty($filters['keyword'])) {
            $conds[] = '(e.reference_no LIKE :kw1 OR e.title LIKE :kw2)';
            $like = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $like;
            $params['kw2'] = $like;
        }
        if (!empty($filters['date_from'])) {
            $conds[] = 'e.ts >= :dfrom';
            $params['dfrom'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conds[] = 'e.ts <= :dto';
            $params['dto'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql = $conds ? (' WHERE ' . implode(' AND ', $conds)) : '';
        return [$sql, $params];
    }

    /**
     * @return array{rows: array<int,array<string,mixed>>, total: int}
     */
    public static function query(array $filters, int $page, int $perPage): array
    {
        [$whereSql, $params] = self::where($filters);
        $union = self::unionSql();

        $total = (int) Database::run(
            "SELECT COUNT(*) c FROM {$union} e{$whereSql}",
            $params
        )->fetch()['c'];

        $perPage = in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $rows = Database::run(
            "SELECT * FROM {$union} e{$whereSql}
             ORDER BY e.ts DESC, e.file_id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total];
    }

    /** Recent bulk batches for the filter dropdown. */
    public static function recentBatches(int $limit = 25): array
    {
        $limit = max(1, min($limit, 100));
        return Database::run(
            "SELECT batch_id, source_app, imported_at, total_rows, inserted, updated, skipped
             FROM bulk_import_batches
             ORDER BY imported_at DESC
             LIMIT {$limit}"
        )->fetchAll();
    }
}
