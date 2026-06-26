<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Single-record read / metadata edit / soft-delete, generalised per app.
 * Every metadata change is written to file_update_log (JSON old/new).
 */
final class FileRecord
{
    /**
     * Editable metadata fields per app: generic key => [meta column, label, required].
     *
     * @return array<string, array{0:string,1:string,2:bool}>
     */
    public static function fields(string $app): array
    {
        if ($app === 'eoffice') {
            return [
                'title'    => ['subject',          'Subject',        true],
                'group'    => ['department',        'Department',     true],
                'category' => ['file_category',     'File Category',  true],
                'doc_date' => ['date_of_document',  'Date of Document', false],
                'tags'     => ['tags',              'Tags',           false],
                'remarks'  => ['remarks',           'Remarks',        false],
            ];
        }
        return [
            'title'    => ['document_name',    'Document Name',    true],
            'category' => ['document_type',    'Document Type',    true],
            'group'    => ['project_module',   'Project/Module',   false],
            'version'  => ['version',          'Version',          false],
            'doc_date' => ['date_of_creation', 'Date of Creation', false],
            'tags'     => ['tags',             'Tags',             false],
            'remarks'  => ['remarks',          'Remarks',          false],
        ];
    }

    /** Resolve a non-deleted record's id by its match key (reference_no). */
    public static function findIdByRef(string $app, string $ref): ?int
    {
        $row = Database::run(
            'SELECT id FROM files WHERE source_app = :app AND reference_no = :ref AND is_deleted = 0 LIMIT 1',
            ['app' => $app, 'ref' => $ref]
        )->fetch();
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Which of the given match keys already exist (non-deleted)? Batched in
     * chunks so bulk validation needs a few queries, not one per row.
     *
     * @param string[] $refs
     * @return array<string,bool> map of existing reference_no => true
     */
    public static function existingRefs(string $app, array $refs): array
    {
        $refs = array_values(array_unique(array_filter(array_map('strval', $refs), static fn ($r) => $r !== '')));
        if ($refs === []) {
            return [];
        }
        $found = [];
        foreach (array_chunk($refs, 500) as $chunk) {
            $ph = [];
            $params = ['app' => $app];
            foreach ($chunk as $i => $r) {
                $key = 'r' . $i;
                $ph[] = ':' . $key;
                $params[$key] = $r;
            }
            $rows = Database::run(
                'SELECT reference_no FROM files
                 WHERE source_app = :app AND is_deleted = 0 AND reference_no IN (' . implode(',', $ph) . ')',
                $params
            )->fetchAll();
            foreach ($rows as $row) {
                $found[(string) $row['reference_no']] = true;
            }
        }
        return $found;
    }

    /** Find a non-deleted record (core + metadata + actor names) for the app. */
    public static function find(string $app, int $id): ?array
    {
        $c = FileList::config($app);
        $sql = "SELECT f.id, f.source_app, f.reference_no, f.status, f.file_note,
                       f.upload_date, f.created_at, f.uploaded_by, f.last_updated_by, f.last_updated_on,
                       COALESCE(NULLIF(uu.full_name, ''), uu.username) AS uploaded_by_name,
                       COALESCE(NULLIF(lu.full_name, ''), lu.username) AS updated_by_name,
                       m.*
                FROM files f
                JOIN {$c['meta_table']} m ON m.file_id = f.id
                LEFT JOIN users uu ON uu.id = f.uploaded_by
                LEFT JOIN users lu ON lu.id = f.last_updated_by
                WHERE f.id = :id AND f.source_app = :app AND f.is_deleted = 0
                LIMIT 1";
        $row = Database::run($sql, ['id' => $id, 'app' => $app])->fetch();
        return $row ?: null;
    }

    /** Update the file note (rich HTML). Sanitised + logged. */
    public static function updateNote(string $app, int $id, string $html, int $userId): bool
    {
        $record = self::find($app, $id);
        if ($record === null) {
            return false;
        }

        $clean = self::sanitizeHtml($html);
        $oldText = trim(preg_replace('/\s+/', ' ', strip_tags((string) $record['file_note'])));
        $newText = trim(preg_replace('/\s+/', ' ', strip_tags($clean)));
        if ($oldText === $newText && (string) $record['file_note'] === $clean) {
            return true; // no change
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE files SET file_note = :n, last_updated_by = :u, last_updated_on = :now WHERE id = :id',
                ['n' => $clean, 'u' => $userId, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
            );
            Database::run(
                'INSERT INTO file_update_log (file_id, updated_by, update_source, updated_at, fields_changed, import_batch_id)
                 VALUES (:fid, :u, :src, :now, :fc, NULL)',
                [
                    'fid' => $id, 'u' => $userId, 'src' => 'MANUAL_EDIT', 'now' => date('Y-m-d H:i:s'),
                    'fc'  => json_encode(['File Note' => [
                        'old' => mb_substr($oldText, 0, 120),
                        'new' => mb_substr($newText, 0, 120),
                    ]], JSON_UNESCAPED_UNICODE),
                ]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
        return true;
    }

    /**
     * Light HTML sanitiser for stored notes: removes script/style blocks,
     * event-handler attributes, and javascript: URIs. (Single trusted admin
     * audience; this is defence-in-depth, not a full HTML purifier.)
     */
    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('#<\s*(script|style|iframe|object|embed)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
        $html = preg_replace('#<\s*(script|style|iframe|object|embed)\b[^>]*/?>#is', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = preg_replace('#(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\2#i', '$1=$2#$2', $html);
        return trim((string) $html);
    }

    /**
     * Update metadata + status. Returns [success, errors].
     *
     * @return array{0:bool,1:array<string,string>}
     */
    public static function update(string $app, int $id, array $input, int $userId): array
    {
        $record = self::find($app, $id);
        if ($record === null) {
            return [false, ['_' => 'Record not found.']];
        }

        $fields = self::fields($app);
        $errors = [];
        $clean  = [];

        foreach ($fields as $key => [$col, $label, $required]) {
            $val = trim((string) ($input[$key] ?? ''));
            if ($required && $val === '') {
                $errors[$key] = "{$label} is required.";
            }
            if ($key === 'doc_date' && $val !== '' && !self::isValidDate($val)) {
                $errors[$key] = "{$label} must be a valid date (YYYY-MM-DD).";
            }
            $clean[$key] = $val;
        }

        $status = trim((string) ($input['status'] ?? ''));
        if ($status === '') {
            $errors['status'] = 'Status is required.';
        }

        if ($errors) {
            return [false, $errors];
        }

        // Compute changes (generic key => [old, new, label]).
        $changes = [];
        foreach ($fields as $key => [$col, $label, $required]) {
            $old = (string) ($record[$col] ?? '');
            $new = $clean[$key];
            if ($key === 'doc_date') {
                $old = $old !== '' ? substr($old, 0, 10) : '';
            }
            if ($old !== $new) {
                $changes[$label] = ['old' => $old, 'new' => $new];
            }
        }
        if ((string) $record['status'] !== $status) {
            $changes['Status'] = ['old' => (string) $record['status'], 'new' => $status];
        }

        if ($changes === []) {
            return [true, []]; // nothing to do
        }

        $c = FileList::config($app);
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            // Metadata columns
            $set = [];
            $params = ['id' => $id];
            foreach ($fields as $key => [$col, $label, $required]) {
                $set[] = "{$col} = :{$key}";
                $params[$key] = $clean[$key] === '' && $key === 'doc_date' ? null : $clean[$key];
            }
            Database::run(
                "UPDATE {$c['meta_table']} SET " . implode(', ', $set) . ' WHERE file_id = :id',
                $params
            );

            // Core: status + audit stamps
            Database::run(
                'UPDATE files SET status = :s, last_updated_by = :u, last_updated_on = :now WHERE id = :id',
                ['s' => $status, 'u' => $userId, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
            );

            // Update log
            Database::run(
                'INSERT INTO file_update_log (file_id, updated_by, update_source, updated_at, fields_changed, import_batch_id)
                 VALUES (:fid, :u, :src, :now, :fc, NULL)',
                [
                    'fid' => $id, 'u' => $userId, 'src' => 'MANUAL_EDIT',
                    'now' => date('Y-m-d H:i:s'), 'fc' => json_encode($changes, JSON_UNESCAPED_UNICODE),
                ]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return [false, ['_' => 'Could not save changes: ' . $e->getMessage()]];
        }

        return [true, []];
    }

    /** Soft-delete a record and log it. */
    public static function softDelete(string $app, int $id, int $userId): bool
    {
        $record = self::find($app, $id);
        if ($record === null) {
            return false;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE files SET is_deleted = 1, last_updated_by = :u, last_updated_on = :now WHERE id = :id',
                ['u' => $userId, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
            );
            Database::run(
                'INSERT INTO file_update_log (file_id, updated_by, update_source, updated_at, fields_changed, import_batch_id)
                 VALUES (:fid, :u, :src, :now, :fc, NULL)',
                [
                    'fid' => $id, 'u' => $userId, 'src' => 'MANUAL_EDIT',
                    'now' => date('Y-m-d H:i:s'),
                    'fc'  => json_encode(['Deleted' => ['old' => 'active', 'new' => 'deleted']]),
                ]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
        return true;
    }

    private static function isValidDate(string $value): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
