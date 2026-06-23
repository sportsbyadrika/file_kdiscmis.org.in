<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * File listing with filtering, sorting and pagination — generalised for
 * both source apps. All values are bound; column/sort identifiers are
 * resolved against fixed whitelists (never interpolated from user input).
 */
final class FileList
{
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    public const DEFAULT_PER_PAGE = 25;

    /**
     * Per-app configuration: which metadata table/columns back the shared
     * list, plus the human labels used in headers and filter tags.
     */
    public static function config(string $app): array
    {
        $configs = [
            'eoffice' => [
                'label'        => 'eOffice',
                'meta_table'   => 'eoffice_metadata',
                'title_col'    => 'm.subject',
                'group_col'    => 'm.department',
                'category_col' => 'm.file_category',
                'date_col'     => 'm.date_of_document',
                'tags_col'     => 'm.tags',
                'remarks_col'  => 'm.remarks',
                'labels'       => [
                    'ref'      => 'File Ref No',
                    'title'    => 'Subject',
                    'group'    => 'Department',
                    'category' => 'File Category',
                    'doc_date' => 'Document Date',
                ],
            ],
            'ospyndocs' => [
                'label'        => 'OspynDocs',
                'meta_table'   => 'ospyndocs_metadata',
                'title_col'    => 'm.document_name',
                'group_col'    => 'm.project_module',
                'category_col' => 'm.document_type',
                'date_col'     => 'm.date_of_creation',
                'tags_col'     => 'm.tags',
                'remarks_col'  => 'm.remarks',
                'labels'       => [
                    'ref'      => 'Document ID',
                    'title'    => 'Name',
                    'group'    => 'Project/Module',
                    'category' => 'Document Type',
                    'doc_date' => 'Date of Creation',
                ],
            ],
        ];

        if (!isset($configs[$app])) {
            throw new \InvalidArgumentException("Unknown source app: {$app}");
        }
        return $configs[$app];
    }

    public static function isApp(string $app): bool
    {
        return $app === 'eoffice' || $app === 'ospyndocs';
    }

    /**
     * Column definitions for the list table (order = display order).
     *
     * @return array<int, array{key:string,label:string,sortable:bool,default:bool}>
     */
    public static function columns(string $app): array
    {
        $l = self::config($app)['labels'];
        return [
            ['key' => 'ref',          'label' => $l['ref'],      'sortable' => true,  'default' => true],
            ['key' => 'title',        'label' => $l['title'],    'sortable' => true,  'default' => true],
            ['key' => 'group',        'label' => $l['group'],    'sortable' => true,  'default' => true],
            ['key' => 'category',     'label' => $l['category'], 'sortable' => false, 'default' => true],
            ['key' => 'doc_date',     'label' => $l['doc_date'], 'sortable' => true,  'default' => true],
            ['key' => 'status',       'label' => 'Status',       'sortable' => true,  'default' => true],
            ['key' => 'upload_date',  'label' => 'Upload Date',  'sortable' => true,  'default' => true],
            ['key' => 'last_updated', 'label' => 'Last Updated', 'sortable' => true,  'default' => false],
            ['key' => 'uploaded_by',  'label' => 'Uploaded By',  'sortable' => true,  'default' => false],
        ];
    }

    /** Map of sort key -> SQL expression (whitelist). */
    private static function sortExpr(string $app): array
    {
        $c = self::config($app);
        return [
            'ref'          => 'f.reference_no',
            'title'        => $c['title_col'],
            'group'        => $c['group_col'],
            'doc_date'     => $c['date_col'],
            'status'       => 'f.status',
            'upload_date'  => 'f.upload_date',
            'last_updated' => 'COALESCE(f.last_updated_on, f.created_at)',
            'uploaded_by'  => 'u.username',
        ];
    }

    public static function defaultSort(): array
    {
        return ['key' => 'upload_date', 'dir' => 'desc'];
    }

    public static function normalizeSort(string $app, ?string $key, ?string $dir): array
    {
        $valid = array_keys(self::sortExpr($app));
        if (!in_array($key, $valid, true)) {
            return self::defaultSort();
        }
        $dir = strtolower((string) $dir) === 'asc' ? 'asc' : 'desc';
        return ['key' => $key, 'dir' => $dir];
    }

    public static function normalizePerPage(?int $perPage): int
    {
        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE;
    }

    /**
     * Build the shared WHERE clause + bound params from filters.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private static function buildWhere(string $app, array $filters): array
    {
        $c = self::config($app);
        $where  = ['f.source_app = :app', 'f.is_deleted = 0'];
        $params = ['app' => $app];

        // Keyword across ref, title, tags, remarks
        $kw = trim((string) ($filters['keyword'] ?? ''));
        if ($kw !== '') {
            $where[] = '(f.reference_no LIKE :kw1 OR ' . $c['title_col'] . ' LIKE :kw2 OR '
                     . $c['tags_col'] . ' LIKE :kw3 OR ' . $c['remarks_col'] . ' LIKE :kw4)';
            $like = '%' . $kw . '%';
            $params['kw1'] = $like;
            $params['kw2'] = $like;
            $params['kw3'] = $like;
            $params['kw4'] = $like;
        }

        // Date range (document date vs upload date)
        $basis   = ($filters['date_basis'] ?? 'document') === 'upload' ? 'upload' : 'document';
        $dateCol = $basis === 'upload' ? 'f.upload_date' : $c['date_col'];
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to   = trim((string) ($filters['date_to'] ?? ''));
        if ($from !== '') {
            $where[] = $dateCol . ' >= :date_from';
            $params['date_from'] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[] = $dateCol . ' <= :date_to';
            $params['date_to'] = $to . ' 23:59:59';
        }

        // Multi-select: department/project
        self::applyInClause($where, $params, $c['group_col'], 'grp', $filters['group'] ?? []);
        // Multi-select: category/type
        self::applyInClause($where, $params, $c['category_col'], 'cat', $filters['category'] ?? []);
        // Multi-select: status
        self::applyInClause($where, $params, 'f.status', 'st', $filters['status'] ?? []);

        // Uploaded by (single)
        $uploadedBy = (int) ($filters['uploaded_by'] ?? 0);
        if ($uploadedBy > 0) {
            $where[] = 'f.uploaded_by = :uploaded_by';
            $params['uploaded_by'] = $uploadedBy;
        }

        // Has attachments
        if (!empty($filters['has_attachments'])) {
            $where[] = 'EXISTS (SELECT 1 FROM file_attachments a WHERE a.file_id = f.id AND a.is_deleted = 0)';
        }
        // Has transaction history
        if (!empty($filters['has_history'])) {
            $where[] = 'EXISTS (SELECT 1 FROM file_transaction_history h WHERE h.file_id = f.id)';
        }

        return [implode(' AND ', $where), $params];
    }

    /** Append an IN (...) clause for a multi-select filter, if non-empty. */
    private static function applyInClause(array &$where, array &$params, string $col, string $prefix, $values): void
    {
        if (!is_array($values)) {
            $values = $values === '' || $values === null ? [] : [$values];
        }
        $values = array_values(array_filter(array_map('strval', $values), static fn ($v) => $v !== ''));
        if ($values === []) {
            return;
        }
        $ph = [];
        foreach ($values as $i => $val) {
            $key = $prefix . $i;
            $ph[] = ':' . $key;
            $params[$key] = $val;
        }
        $where[] = $col . ' IN (' . implode(',', $ph) . ')';
    }

    /**
     * Run the listing query.
     *
     * @return array{rows: array<int,array<string,mixed>>, total: int}
     */
    public static function listing(string $app, array $filters, array $sort, int $page, int $perPage): array
    {
        $c = self::config($app);
        [$whereSql, $params] = self::buildWhere($app, $filters);

        $total = (int) Database::run(
            "SELECT COUNT(*) c
             FROM files f
             JOIN {$c['meta_table']} m ON m.file_id = f.id
             WHERE {$whereSql}",
            $params
        )->fetch()['c'];

        $sort = self::normalizeSort($app, $sort['key'] ?? null, $sort['dir'] ?? null);
        $orderExpr = self::sortExpr($app)[$sort['key']];
        $dir = $sort['dir'] === 'asc' ? 'ASC' : 'DESC';

        $perPage = self::normalizePerPage($perPage);
        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $sql = "SELECT
                    f.id,
                    f.source_app,
                    f.reference_no AS ref,
                    {$c['title_col']} AS title,
                    {$c['group_col']} AS `group`,
                    {$c['category_col']} AS category,
                    {$c['date_col']} AS doc_date,
                    f.status,
                    f.upload_date,
                    COALESCE(f.last_updated_on, f.created_at) AS last_updated,
                    COALESCE(NULLIF(u.full_name, ''), u.username, 'System') AS uploaded_by,
                    (SELECT COUNT(*) FROM file_attachments a WHERE a.file_id = f.id AND a.is_deleted = 0) AS attachment_count,
                    (SELECT COUNT(*) FROM file_transaction_history h WHERE h.file_id = f.id) AS history_count
                FROM files f
                JOIN {$c['meta_table']} m ON m.file_id = f.id
                LEFT JOIN users u ON u.id = f.uploaded_by
                WHERE {$whereSql}
                ORDER BY {$orderExpr} {$dir}, f.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = Database::run($sql, $params)->fetchAll();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Distinct filter option values for the toolbar.
     *
     * @return array{groups: string[], categories: string[], statuses: string[], uploaders: array<int,array{id:int,name:string}>}
     */
    public static function filterOptions(string $app): array
    {
        $c = self::config($app);

        $distinct = static function (string $col) use ($c, $app): array {
            $rows = Database::run(
                "SELECT DISTINCT {$col} v
                 FROM files f JOIN {$c['meta_table']} m ON m.file_id = f.id
                 WHERE f.source_app = :app AND f.is_deleted = 0 AND {$col} <> ''
                 ORDER BY v ASC",
                ['app' => $app]
            )->fetchAll();
            return array_map(static fn ($r) => (string) $r['v'], $rows);
        };

        $uploaderRows = Database::run(
            "SELECT DISTINCT u.id, COALESCE(NULLIF(u.full_name,''), u.username) name
             FROM files f JOIN users u ON u.id = f.uploaded_by
             WHERE f.source_app = :app AND f.is_deleted = 0
             ORDER BY name ASC",
            ['app' => $app]
        )->fetchAll();

        return [
            'groups'     => $distinct($c['group_col']),
            'categories' => $distinct($c['category_col']),
            'statuses'   => $distinct('f.status'),
            'uploaders'  => array_map(static fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name']], $uploaderRows),
        ];
    }
}
