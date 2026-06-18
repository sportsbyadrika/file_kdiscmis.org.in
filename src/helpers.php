<?php
declare(strict_types=1);

/**
 * Global helper functions.
 *
 * Kept procedural (not namespaced) for ergonomic use inside view templates.
 */

if (!function_exists('e')) {
    /** HTML-escape a string for safe output. */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /** Build a URL relative to the app's configured base path. */
    function base_url(string $path = ''): string
    {
        $base = rtrim((string) \App\Config::get('app.base_url', ''), '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

if (!function_exists('redirect')) {
    /** Send a redirect and stop execution. */
    function redirect(string $path): never
    {
        header('Location: ' . base_url($path));
        exit;
    }
}

if (!function_exists('config')) {
    /** Shorthand for App\Config::get(). */
    function config(string $key, $default = null)
    {
        return \App\Config::get($key, $default);
    }
}

if (!function_exists('format_bytes')) {
    /** Human-readable file size (e.g. "2.4 MB", "1.1 GB"). */
    function format_bytes(int|float $bytes, int $decimals = 1): string
    {
        $bytes = max(0, (float) $bytes);
        if ($bytes < 1024) {
            return $bytes === 0.0 ? '0 KB' : number_format($bytes) . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB', 'PB'];
        $power = (int) floor(log($bytes, 1024));
        $power = max(1, min($power, count($units))); // start at KB
        $value = $bytes / (1024 ** $power);
        return number_format($value, $decimals) . ' ' . $units[$power - 1];
    }
}

if (!function_exists('format_dt')) {
    /** Format a datetime string/timestamp as DD-MM-YYYY HH:MM. */
    function format_dt(?string $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00 00:00:00') {
            return $fallback;
        }
        $ts = strtotime($value);
        return $ts ? date('d-m-Y H:i', $ts) : $fallback;
    }
}

if (!function_exists('format_date')) {
    /** Format a date string as DD-MM-YYYY. */
    function format_date(?string $value, string $fallback = '—'): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return $fallback;
        }
        $ts = strtotime($value);
        return $ts ? date('d-m-Y', $ts) : $fallback;
    }
}
