<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\BulkTemplate;
use App\Models\FileRecord;
use App\Services\Xlsx\XlsxReader;

/**
 * Validates parsed bulk-upload rows against an app's template. Pure (no
 * writes) — produces per-row results the preview and processor consume.
 */
final class BulkValidator
{
    private const OPERATIONS = ['INSERT', 'UPDATE', 'HISTORY_ONLY'];

    /**
     * @param array<int,array<int,string>> $rows  raw rows (row 0 = headers)
     * @return array{fatal:?string, rows:array<int,array<string,mixed>>, summary:array<string,int>}
     */
    public static function validate(string $app, array $rows): array
    {
        $columns = BulkTemplate::columns($app);
        $keyToHeader = [];
        $headerToKey = [];
        foreach ($columns as $c) {
            $keyToHeader[$c['key']] = $c['header'];
            $headerToKey[self::norm($c['header'])] = $c['key'];
        }

        if (empty($rows)) {
            return ['fatal' => 'The file is empty.', 'rows' => [], 'summary' => self::zeroSummary()];
        }

        // Map uploaded headers -> column index.
        $headerRow = $rows[0];
        $keyIndex = [];
        foreach ($headerRow as $idx => $label) {
            $key = $headerToKey[self::norm((string) $label)] ?? null;
            if ($key !== null && !isset($keyIndex[$key])) {
                $keyIndex[$key] = $idx;
            }
        }
        foreach (['operation', 'ref'] as $required) {
            if (!isset($keyIndex[$required])) {
                return [
                    'fatal' => 'Missing required column: "' . $keyToHeader[$required] . '". Please use the provided template.',
                    'rows' => [], 'summary' => self::zeroSummary(),
                ];
            }
        }

        $metaKeys = BulkTemplate::metaKeys($app);
        $reqInsert = BulkTemplate::requiredInsertKeys($app);

        $results = [];
        $summary = self::zeroSummary();

        $dataRows = array_slice($rows, 1, null, true);
        foreach ($dataRows as $i => $row) {
            $lineNo = $i + 1; // spreadsheet row number (1-based; header is row 1)

            $get = static function (string $key) use ($keyIndex, $row): string {
                return isset($keyIndex[$key]) ? trim((string) ($row[$keyIndex[$key]] ?? '')) : '';
            };

            // Skip completely blank rows.
            $nonEmpty = array_filter($row, static fn ($v) => trim((string) $v) !== '');
            if (empty($nonEmpty)) {
                continue;
            }

            $errors = [];
            $operation = strtoupper($get('operation'));
            $ref = $get('ref');

            if (!in_array($operation, self::OPERATIONS, true)) {
                $errors[] = ['column' => $keyToHeader['operation'], 'message' => 'Operation must be INSERT, UPDATE or HISTORY_ONLY.'];
            }
            if ($ref === '') {
                $errors[] = ['column' => $keyToHeader['ref'], 'message' => 'Match key is required.'];
            }

            $existingId = ($ref !== '') ? FileRecord::findIdByRef($app, $ref) : null;

            // Metadata values (normalised).
            $data = [];
            foreach ($metaKeys as $k) {
                $val = $get($k);
                if ($k === 'doc_date' && $val !== '') {
                    $norm = self::parseDate($val);
                    if ($norm === null) {
                        $errors[] = ['column' => $keyToHeader[$k], 'message' => 'Invalid date — use DD-MM-YYYY.'];
                    } else {
                        $val = $norm;
                    }
                }
                $data[$k] = $val;
            }
            $status = $get('status');

            // History entry (optional, except HISTORY_ONLY).
            $history = self::parseHistory($get, $keyToHeader, $errors);

            // Operation-specific rules.
            $kind = 'insert';
            if ($operation === 'INSERT') {
                $kind = 'insert';
                if ($existingId !== null) {
                    $errors[] = ['column' => $keyToHeader['ref'], 'message' => 'A record with this match key already exists — use UPDATE.'];
                }
                foreach ($reqInsert as $rk) {
                    if ($rk === 'status') {
                        continue;
                    }
                    if (($data[$rk] ?? '') === '') {
                        $errors[] = ['column' => $keyToHeader[$rk], 'message' => $keyToHeader[$rk] . ' is required for INSERT.'];
                    }
                }
                if ($status === '') {
                    $errors[] = ['column' => $keyToHeader['status'], 'message' => 'Current Status is required for INSERT.'];
                }
            } elseif ($operation === 'UPDATE') {
                $kind = 'update';
                if ($existingId === null && $ref !== '') {
                    $errors[] = ['column' => $keyToHeader['ref'], 'message' => 'No existing record matches this key — cannot UPDATE.'];
                }
                if ($status === '') {
                    $errors[] = ['column' => $keyToHeader['status'], 'message' => 'Current Status is required for UPDATE.'];
                }
            } elseif ($operation === 'HISTORY_ONLY') {
                $kind = 'history_only';
                if ($existingId === null && $ref !== '') {
                    $errors[] = ['column' => $keyToHeader['ref'], 'message' => 'No existing record matches this key — cannot add history.'];
                }
                if ($history === null) {
                    $errors[] = ['column' => $keyToHeader['history_date'], 'message' => 'HISTORY_ONLY requires a History Date and Transaction Type.'];
                }
            }

            $level = !empty($errors) ? 'error' : 'ok';

            // A valid UPDATE that changes nothing and has no history -> warning.
            if ($level === 'ok' && $kind === 'update' && $history === null) {
                $hasMeta = false;
                foreach ($metaKeys as $k) {
                    if (($data[$k] ?? '') !== '') { $hasMeta = true; break; }
                }
                if (!$hasMeta && $status === '') {
                    $level = 'warning';
                }
            }

            $results[] = [
                'line'       => $lineNo,
                'operation'  => $operation,
                'kind'       => $kind,
                'ref'        => $ref,
                'status'     => $status,
                'data'       => $data,
                'history'    => $history,
                'file_id'    => $existingId,
                'level'      => $level,
                'errors'     => $errors,
            ];

            if ($level === 'error') {
                $summary['errors']++;
            } else {
                if ($level === 'warning') {
                    $summary['warnings']++;
                }
                if ($kind === 'insert') {
                    $summary['insert']++;
                } elseif ($kind === 'update') {
                    $summary['update']++;
                } else {
                    $summary['history_only']++;
                }
            }
            $summary['total']++;
        }

        return ['fatal' => null, 'rows' => $results, 'summary' => $summary];
    }

    /**
     * @param callable(string):string $get
     * @param array<string,string>     $keyToHeader
     * @param array<int,array{column:string,message:string}> $errors
     * @return array{date:string,type:string,from:string,to:string,note:string}|null
     */
    private static function parseHistory(callable $get, array $keyToHeader, array &$errors): ?array
    {
        $date = $get('history_date');
        $type = $get('history_type');
        $from = $get('history_from');
        $to   = $get('history_to');
        $note = $get('history_note');

        if ($date === '' && $type === '' && $from === '' && $to === '' && $note === '') {
            return null;
        }

        $normDate = $date !== '' ? self::parseDate($date) : null;
        if ($date === '' || $normDate === null) {
            $errors[] = ['column' => $keyToHeader['history_date'], 'message' => 'History Date is required and must be DD-MM-YYYY.'];
        }
        if ($type === '') {
            $errors[] = ['column' => $keyToHeader['history_type'], 'message' => 'History Transaction Type is required when adding history.'];
        }

        return [
            'date' => $normDate ?? '',
            'type' => $type,
            'from' => $from,
            'to'   => $to,
            'note' => $note,
        ];
    }

    /** Parse a date in DD-MM-YYYY / YYYY-MM-DD / DD/MM/YYYY or an Excel serial. */
    public static function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y', 'd.m.Y'] as $fmt) {
            $d = \DateTime::createFromFormat('!' . $fmt, $value);
            if ($d !== false && $d->format($fmt) === $value) {
                return $d->format('Y-m-d');
            }
        }
        // Excel serial number fallback.
        $serial = XlsxReader::excelSerialToDate($value);
        return $serial;
    }

    private static function norm(string $s): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $s) ?? $s));
    }

    private static function zeroSummary(): array
    {
        return ['insert' => 0, 'update' => 0, 'history_only' => 0, 'errors' => 0, 'warnings' => 0, 'total' => 0];
    }
}
