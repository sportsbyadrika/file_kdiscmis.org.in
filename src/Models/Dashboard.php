<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;
use DateTimeImmutable;

/**
 * Dashboard statistics & activity, computed server-side.
 *
 * SQL is kept portable (standard functions, no DATE_FORMAT) so the same
 * queries run under MySQL in production and SQLite in tests.
 */
final class Dashboard
{
    /** Statuses treated as "closed" (everything else counts as pending/open). */
    private const CLOSED_STATUSES = ['Closed', 'Completed', 'Disposed', 'Archived', 'Rejected', 'Cancelled'];

    public const APPS = ['eoffice', 'ospyndocs'];

    /**
     * Per-app summary statistics.
     *
     * @return array{total:int,added_this_month:int,updated_this_month:int,pending_open:int,total_attachments:int,storage_bytes:int,last_updated:?string}
     */
    public static function appStats(string $app, ?DateTimeImmutable $now = null): array
    {
        $now        = $now ?? new DateTimeImmutable('now');
        $monthStart = $now->modify('first day of this month')->format('Y-m-01 00:00:00');
        $nextMonth  = $now->modify('first day of next month')->format('Y-m-01 00:00:00');

        $total = (int) Database::run(
            'SELECT COUNT(*) c FROM files WHERE source_app = :app AND is_deleted = 0',
            ['app' => $app]
        )->fetch()['c'];

        $added = (int) Database::run(
            'SELECT COUNT(*) c FROM files
             WHERE source_app = :app AND is_deleted = 0
               AND created_at >= :start AND created_at < :next',
            ['app' => $app, 'start' => $monthStart, 'next' => $nextMonth]
        )->fetch()['c'];

        $updated = (int) Database::run(
            'SELECT COUNT(*) c FROM files
             WHERE source_app = :app AND is_deleted = 0
               AND last_updated_on IS NOT NULL
               AND last_updated_on >= :start AND last_updated_on < :next',
            ['app' => $app, 'start' => $monthStart, 'next' => $nextMonth]
        )->fetch()['c'];

        // Pending/open: not in the closed set and not blank.
        $placeholders = [];
        $params = ['app' => $app];
        foreach (self::CLOSED_STATUSES as $i => $status) {
            $key = 'cs' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }
        $pending = (int) Database::run(
            'SELECT COUNT(*) c FROM files
             WHERE source_app = :app AND is_deleted = 0
               AND status <> \'\' AND status NOT IN (' . implode(',', $placeholders) . ')',
            $params
        )->fetch()['c'];

        $att = Database::run(
            'SELECT COUNT(*) c, COALESCE(SUM(a.file_size_bytes), 0) bytes
             FROM file_attachments a
             JOIN files f ON f.id = a.file_id
             WHERE f.source_app = :app AND a.is_deleted = 0 AND f.is_deleted = 0',
            ['app' => $app]
        )->fetch();

        $lastUpdated = Database::run(
            'SELECT MAX(COALESCE(last_updated_on, created_at)) lu
             FROM files WHERE source_app = :app AND is_deleted = 0',
            ['app' => $app]
        )->fetch()['lu'] ?? null;

        return [
            'total'             => $total,
            'added_this_month'  => $added,
            'updated_this_month' => $updated,
            'pending_open'      => $pending,
            'total_attachments' => (int) $att['c'],
            'storage_bytes'     => (int) $att['bytes'],
            'last_updated'      => $lastUpdated,
        ];
    }

    /**
     * OspynDocs document-type breakdown (non-deleted).
     *
     * @return array<int, array{type:string, count:int}>
     */
    public static function docTypeBreakdown(int $limit = 6): array
    {
        $rows = Database::run(
            'SELECT COALESCE(NULLIF(m.document_type, \'\'), \'Unspecified\') type, COUNT(*) c
             FROM files f
             JOIN ospyndocs_metadata m ON m.file_id = f.id
             WHERE f.source_app = \'ospyndocs\' AND f.is_deleted = 0
             GROUP BY type
             ORDER BY c DESC, type ASC'
        )->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[] = ['type' => (string) $r['type'], 'count' => (int) $r['c']];
        }
        return array_slice($out, 0, $limit);
    }

    /**
     * Recent activity across both apps (inserts, updates, deletes).
     *
     * @return array<int, array<string,mixed>>
     */
    public static function recentActivity(int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));

        $title = "COALESCE(NULLIF(em.subject, ''), NULLIF(om.document_name, ''), f.reference_no)";
        $joins = '
            LEFT JOIN eoffice_metadata em ON em.file_id = f.id
            LEFT JOIN ospyndocs_metadata om ON om.file_id = f.id';

        // Inserts
        $a = "SELECT f.id file_id, f.source_app, f.reference_no, $title title,
                     'Inserted' action, f.created_at ts,
                     COALESCE(NULLIF(ui.full_name, ''), ui.username, 'System') actor
              FROM files f $joins
              LEFT JOIN users ui ON ui.id = f.uploaded_by";

        // Updates (from the update log)
        $b = "SELECT f.id file_id, f.source_app, f.reference_no, $title title,
                     'Updated' action, l.updated_at ts,
                     COALESCE(NULLIF(uu.full_name, ''), uu.username, 'System') actor
              FROM file_update_log l
              JOIN files f ON f.id = l.file_id $joins
              LEFT JOIN users uu ON uu.id = l.updated_by";

        // Deletes (soft-deleted files)
        $c = "SELECT f.id file_id, f.source_app, f.reference_no, $title title,
                     'Deleted' action, COALESCE(f.last_updated_on, f.created_at) ts,
                     COALESCE(NULLIF(ud.full_name, ''), ud.username, 'System') actor
              FROM files f $joins
              LEFT JOIN users ud ON ud.id = f.last_updated_by
              WHERE f.is_deleted = 1";

        $sql = "SELECT * FROM ($a UNION ALL $b UNION ALL $c) ev
                ORDER BY ts DESC, file_id DESC
                LIMIT $limit";

        return Database::run($sql)->fetchAll();
    }
}
