<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Bulk-upload template definitions per app: the ordered column list (with the
 * internal key each maps to), an example sample row, and helpers shared by the
 * template download, validator and processor.
 *
 * Internal keys align with {@see FileRecord::fields()} so metadata writes are
 * shared: title, group, category, doc_date, version, tags, remarks. Plus
 * operation, ref (match key), status, and the five history_* columns.
 */
final class BulkTemplate
{
    public const HISTORY_KEYS = ['history_date', 'history_type', 'history_from', 'history_to', 'history_note'];

    /**
     * @return array<int, array{header:string,key:string,req_insert:bool,sample:string}>
     */
    public static function columns(string $app): array
    {
        $history = [
            ['header' => 'History Date',             'key' => 'history_date', 'req_insert' => false, 'sample' => '15-04-2026'],
            ['header' => 'History Transaction Type', 'key' => 'history_type', 'req_insert' => false, 'sample' => 'Created'],
            ['header' => 'History From Status',      'key' => 'history_from', 'req_insert' => false, 'sample' => ''],
            ['header' => 'History To Status',        'key' => 'history_to',   'req_insert' => false, 'sample' => 'Open'],
            ['header' => 'History Note',             'key' => 'history_note', 'req_insert' => false, 'sample' => 'Initial entry'],
        ];

        if ($app === 'eoffice') {
            return array_merge([
                ['header' => 'Operation',        'key' => 'operation', 'req_insert' => true,  'sample' => 'INSERT'],
                ['header' => 'File Reference No', 'key' => 'ref',      'req_insert' => true,  'sample' => 'EO/2026/050'],
                ['header' => 'Subject/Title',    'key' => 'title',     'req_insert' => true,  'sample' => 'Example file subject'],
                ['header' => 'Department',       'key' => 'group',     'req_insert' => true,  'sample' => 'Finance'],
                ['header' => 'File Category',    'key' => 'category',  'req_insert' => true,  'sample' => 'Proposal'],
                ['header' => 'Date of Document', 'key' => 'doc_date',  'req_insert' => true,  'sample' => '15-04-2026'],
                ['header' => 'Current Status',   'key' => 'status',    'req_insert' => true,  'sample' => 'Open'],
                ['header' => 'Tags',             'key' => 'tags',      'req_insert' => false, 'sample' => 'tag1,tag2'],
                ['header' => 'Remarks',          'key' => 'remarks',   'req_insert' => false, 'sample' => 'Example remark'],
            ], $history);
        }

        return array_merge([
            ['header' => 'Operation',       'key' => 'operation', 'req_insert' => true,  'sample' => 'INSERT'],
            ['header' => 'Document ID',     'key' => 'ref',       'req_insert' => true,  'sample' => 'OD-2050'],
            ['header' => 'Document Name',   'key' => 'title',     'req_insert' => true,  'sample' => 'Example document'],
            ['header' => 'Document Type',   'key' => 'category',  'req_insert' => true,  'sample' => 'Specification'],
            ['header' => 'Project/Module',  'key' => 'group',     'req_insert' => false, 'sample' => 'Portal'],
            ['header' => 'Version',         'key' => 'version',   'req_insert' => false, 'sample' => '1.0'],
            ['header' => 'Date of Creation', 'key' => 'doc_date', 'req_insert' => true,  'sample' => '15-04-2026'],
            ['header' => 'Current Status',  'key' => 'status',    'req_insert' => true,  'sample' => 'Active'],
            ['header' => 'Tags',            'key' => 'tags',      'req_insert' => false, 'sample' => 'tag1'],
            ['header' => 'Remarks',         'key' => 'remarks',   'req_insert' => false, 'sample' => 'Example remark'],
        ], $history);
    }

    /** @return string[] header labels in order */
    public static function headers(string $app): array
    {
        return array_map(static fn ($c) => $c['header'], self::columns($app));
    }

    /** @return string[] sample row values in order */
    public static function sampleRow(string $app): array
    {
        return array_map(static fn ($c) => $c['sample'], self::columns($app));
    }

    /** Metadata field keys (exclude operation/ref/status/history). */
    public static function metaKeys(string $app): array
    {
        $skip = array_merge(['operation', 'ref', 'status'], self::HISTORY_KEYS);
        $keys = [];
        foreach (self::columns($app) as $c) {
            if (!in_array($c['key'], $skip, true)) {
                $keys[] = $c['key'];
            }
        }
        return $keys;
    }

    /** Required-for-INSERT metadata keys. */
    public static function requiredInsertKeys(string $app): array
    {
        $keys = [];
        foreach (self::columns($app) as $c) {
            if ($c['req_insert'] && !in_array($c['key'], ['operation', 'ref'], true)) {
                $keys[] = $c['key'];
            }
        }
        return $keys;
    }

    public static function formatDescription(string $app): string
    {
        $req = array_map(static function ($c) {
            return $c['header'];
        }, array_filter(self::columns($app), static fn ($c) => $c['req_insert']));
        return 'Operation (INSERT / UPDATE / HISTORY_ONLY) and the match key are always required. '
            . 'Required for INSERT: ' . implode(', ', $req) . '. Dates use DD-MM-YYYY.';
    }
}
