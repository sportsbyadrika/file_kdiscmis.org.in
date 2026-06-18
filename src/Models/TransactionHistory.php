<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Immutable transaction history for a file (newest first). No update/delete
 * methods exist by design — entries are append-only for audit integrity.
 */
final class TransactionHistory
{
    /** @return array<int,array<string,mixed>> */
    public static function forFile(int $fileId): array
    {
        return Database::run(
            "SELECT h.history_id, h.history_date, h.transaction_type, h.from_status, h.to_status,
                    h.note, h.source, h.created_at,
                    COALESCE(NULLIF(u.full_name,''), u.username, 'System') AS performed_by_name
             FROM file_transaction_history h
             LEFT JOIN users u ON u.id = h.performed_by
             WHERE h.file_id = :fid
             ORDER BY h.history_date DESC, h.history_id DESC",
            ['fid' => $fileId]
        )->fetchAll();
    }

    public static function count(int $fileId): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) c FROM file_transaction_history WHERE file_id = :fid',
            ['fid' => $fileId]
        )->fetch()['c'];
    }
}
