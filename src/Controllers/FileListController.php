<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Config;
use App\Csrf;
use App\Session;
use App\View;
use App\Models\FileList;
use App\Models\FileRecord;
use App\Models\UserPreference;

/**
 * File List View — shared controller for both source apps. The app is
 * resolved from the first path segment (eoffice | ospyndocs).
 */
final class FileListController
{
    /** GET /{app} — full list page (initial server-side render). */
    public function index(): void
    {
        Auth::requireLogin();
        $app = $this->currentApp();

        $sort     = $this->sortFromSession($app);
        $perPage  = $this->perPage($app);
        $visible  = $this->visibleColumns($app);
        $filters  = $this->parseFilters();

        $result = FileList::listing($app, $filters, $sort, 1, $perPage);

        View::render('files/index', [
            'pageTitle'   => FileList::config($app)['label'] . ' Files',
            'active'      => $app,
            'app'         => $app,
            'config'      => FileList::config($app),
            'columns'     => FileList::columns($app),
            'visible'     => $visible,
            'options'     => FileList::filterOptions($app),
            'rows'        => $result['rows'],
            'total'       => $result['total'],
            'sort'        => $sort,
            'page'        => 1,
            'perPage'     => $perPage,
            'perPageOpts' => FileList::PER_PAGE_OPTIONS,
            'filters'     => $filters,
        ]);
    }

    /** GET /{app}/data — AJAX list refresh (table + pagination + tags). */
    public function data(): void
    {
        Auth::requireLogin();
        $app = $this->currentApp();

        // Sort: persist to session when supplied.
        if (isset($_GET['sort'])) {
            $sort = FileList::normalizeSort($app, (string) $_GET['sort'], $_GET['dir'] ?? null);
            Session::set($this->sortKey($app), $sort);
        } else {
            $sort = $this->sortFromSession($app);
        }

        // Per-page: persist preference when supplied.
        if (isset($_GET['per_page'])) {
            $perPage = FileList::normalizePerPage((int) $_GET['per_page']);
            UserPreference::set((int) Auth::id(), $this->module($app), 'per_page', (string) $perPage);
        } else {
            $perPage = $this->perPage($app);
        }

        // Column visibility: persist preference when supplied.
        if (isset($_GET['columns'])) {
            $this->saveVisibleColumns($app, explode(',', (string) $_GET['columns']));
        }
        $visible = $this->visibleColumns($app);

        $filters = $this->parseFilters();
        $page    = max(1, (int) ($_GET['page'] ?? 1));

        $result = FileList::listing($app, $filters, $sort, $page, $perPage);
        $total  = $result['total'];

        $shared = [
            'app'      => $app,
            'config'   => FileList::config($app),
            'columns'  => FileList::columns($app),
            'visible'  => $visible,
            'rows'     => $result['rows'],
            'sort'     => $sort,
        ];

        $countText = $this->countText($total, $page, $perPage, count($result['rows']));

        $this->json([
            'ok'         => true,
            'table'      => View::renderPartial('files/_table', $shared),
            'pagination' => View::renderPartial('files/_pagination', [
                'total' => $total, 'page' => $page, 'perPage' => $perPage,
                'perPageOpts' => FileList::PER_PAGE_OPTIONS,
            ]),
            'active'     => View::renderPartial('files/_active_filters', [
                'app' => $app, 'config' => FileList::config($app), 'filters' => $filters,
            ]),
            'count'      => $countText,
            'total'      => $total,
        ]);
    }

    /** GET /{app}/edit?id= — return the metadata edit form (modal body). */
    public function edit(): void
    {
        Auth::requireLogin();
        $app = $this->currentApp();
        $id  = (int) ($_GET['id'] ?? 0);

        $record = FileRecord::find($app, $id);
        if ($record === null) {
            http_response_code(404);
            $this->json(['ok' => false, 'error' => 'Record not found.']);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo View::renderPartial('files/_edit_form', [
            'app'      => $app,
            'config'   => FileList::config($app),
            'fields'   => FileRecord::fields($app),
            'record'   => $record,
            'statuses' => FileList::filterOptions($app)['statuses'],
        ]);
    }

    /** POST /{app}/update — save metadata edit. */
    public function update(): void
    {
        Auth::requireLogin();
        Csrf::check();
        $app = $this->currentApp();
        $id  = (int) ($_POST['id'] ?? 0);

        [$ok, $errors] = FileRecord::update($app, $id, $_POST, (int) Auth::id());
        if (!$ok) {
            $this->json(['ok' => false, 'errors' => $errors], 422);
            return;
        }
        $this->json(['ok' => true, 'message' => 'Record updated.']);
    }

    /** POST /{app}/delete — soft-delete a record. */
    public function delete(): void
    {
        Auth::requireLogin();
        Csrf::check();
        $app = $this->currentApp();
        $id  = (int) ($_POST['id'] ?? 0);

        if (!FileRecord::softDelete($app, $id, (int) Auth::id())) {
            $this->json(['ok' => false, 'error' => 'Record not found or already deleted.'], 404);
            return;
        }
        $this->json(['ok' => true, 'message' => 'Record deleted.']);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function currentApp(): string
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim((string) Config::get('app.base_url', ''), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $segment = explode('/', trim($uri, '/'))[0] ?? '';
        if (!FileList::isApp($segment)) {
            http_response_code(404);
            exit('Unknown module.');
        }
        return $segment;
    }

    private function module(string $app): string
    {
        return 'list_' . $app;
    }

    private function sortKey(string $app): string
    {
        return 'file_sort_' . $app;
    }

    private function sortFromSession(string $app): array
    {
        $s = Session::get($this->sortKey($app));
        if (is_array($s) && isset($s['key'], $s['dir'])) {
            return FileList::normalizeSort($app, $s['key'], $s['dir']);
        }
        return FileList::defaultSort();
    }

    private function perPage(string $app): int
    {
        $pref = UserPreference::get((int) Auth::id(), $this->module($app), 'per_page');
        return FileList::normalizePerPage($pref !== null ? (int) $pref : null);
    }

    /** @return string[] visible column keys */
    private function visibleColumns(string $app): array
    {
        $all     = array_column(FileList::columns($app), 'key');
        $default = array_column(array_filter(FileList::columns($app), static fn ($c) => $c['default']), 'key');

        $saved = UserPreference::getJson((int) Auth::id(), $this->module($app), 'columns');
        if (!is_array($saved)) {
            return $default;
        }
        $visible = array_values(array_intersect($saved, $all));
        return $visible === [] ? $default : $visible;
    }

    private function saveVisibleColumns(string $app, array $keys): void
    {
        $all     = array_column(FileList::columns($app), 'key');
        $visible = array_values(array_intersect(array_map('trim', $keys), $all));
        if ($visible !== []) {
            UserPreference::set((int) Auth::id(), $this->module($app), 'columns', json_encode($visible));
        }
    }

    /** @return array<string,mixed> */
    private function parseFilters(): array
    {
        $arr = static function ($v): array {
            if (is_array($v)) {
                return array_values(array_filter(array_map('strval', $v), static fn ($x) => $x !== ''));
            }
            return ($v === null || $v === '') ? [] : [(string) $v];
        };

        return [
            'keyword'         => trim((string) ($_GET['keyword'] ?? '')),
            'date_basis'      => ($_GET['date_basis'] ?? 'document') === 'upload' ? 'upload' : 'document',
            'date_from'       => trim((string) ($_GET['date_from'] ?? '')),
            'date_to'         => trim((string) ($_GET['date_to'] ?? '')),
            'group'           => $arr($_GET['group'] ?? []),
            'category'        => $arr($_GET['category'] ?? []),
            'status'          => $arr($_GET['status'] ?? []),
            'uploaded_by'     => (int) ($_GET['uploaded_by'] ?? 0),
            'has_attachments' => !empty($_GET['has_attachments']),
            'has_history'     => !empty($_GET['has_history']),
        ];
    }

    private function countText(int $total, int $page, int $perPage, int $shown): string
    {
        if ($total === 0) {
            return 'Showing 0 of 0 records';
        }
        $start = ($page - 1) * $perPage + 1;
        $end   = $start + $shown - 1;
        return "Showing {$start}–{$end} of {$total} records";
    }

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data);
    }
}
