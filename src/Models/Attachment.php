<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * File attachments — listing, lookup (scoped to app), create, soft-delete.
 */
final class Attachment
{
    /** @return array<int,array<string,mixed>> non-deleted attachments for a file */
    public static function forFile(int $fileId): array
    {
        return Database::run(
            "SELECT a.attachment_id, a.file_id, a.original_filename, a.stored_path, a.mime_type,
                    a.file_size_bytes, a.uploaded_at,
                    COALESCE(NULLIF(u.full_name,''), u.username, 'System') AS uploaded_by_name
             FROM file_attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.file_id = :fid AND a.is_deleted = 0
             ORDER BY a.uploaded_at ASC, a.attachment_id ASC",
            ['fid' => $fileId]
        )->fetchAll();
    }

    public static function count(int $fileId): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) c FROM file_attachments WHERE file_id = :fid AND is_deleted = 0',
            ['fid' => $fileId]
        )->fetch()['c'];
    }

    /**
     * Find an attachment, verifying it belongs to a non-deleted record of the
     * given app (prevents cross-module / deleted-record access).
     */
    public static function findForApp(string $app, int $attachmentId): ?array
    {
        $row = Database::run(
            "SELECT a.*
             FROM file_attachments a
             JOIN files f ON f.id = a.file_id
             WHERE a.attachment_id = :aid AND a.is_deleted = 0
               AND f.source_app = :app AND f.is_deleted = 0
             LIMIT 1",
            ['aid' => $attachmentId, 'app' => $app]
        )->fetch();
        return $row ?: null;
    }

    public static function create(int $fileId, string $originalName, string $storedPath, string $mime, int $size, int $userId): int
    {
        Database::run(
            'INSERT INTO file_attachments (file_id, original_filename, stored_path, mime_type, file_size_bytes, uploaded_by, uploaded_at, is_deleted)
             VALUES (:fid, :orig, :path, :mime, :size, :uby, :uat, 0)',
            [
                'fid' => $fileId, 'orig' => $originalName, 'path' => $storedPath,
                'mime' => $mime, 'size' => $size, 'uby' => $userId, 'uat' => date('Y-m-d H:i:s'),
            ]
        );
        return (int) Database::connection()->lastInsertId();
    }

    public static function softDelete(int $attachmentId): void
    {
        Database::run('UPDATE file_attachments SET is_deleted = 1 WHERE attachment_id = :aid', ['aid' => $attachmentId]);
    }

    /** Extension -> Bootstrap badge class (file-type colour coding). */
    public static function badgeClass(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'pdf'  => 'text-bg-danger',
            'doc'  => 'text-bg-primary', 'docx' => 'text-bg-primary',
            'xls'  => 'text-bg-success', 'xlsx' => 'text-bg-success', 'csv' => 'text-bg-success',
            'ppt'  => 'text-bg-warning', 'pptx' => 'text-bg-warning',
            'jpg'  => 'text-bg-info', 'jpeg' => 'text-bg-info', 'png' => 'text-bg-info', 'gif' => 'text-bg-info',
            'zip'  => 'text-bg-dark',
            'txt'  => 'text-bg-secondary',
        ];
        return $map[$ext] ?? 'text-bg-secondary';
    }

    public static function extension(string $filename): string
    {
        return strtoupper(pathinfo($filename, PATHINFO_EXTENSION)) ?: 'FILE';
    }

    public static function isPreviewable(string $mime): string
    {
        if ($mime === 'application/pdf') return 'pdf';
        if (str_starts_with($mime, 'image/')) return 'image';
        return '';
    }
}
