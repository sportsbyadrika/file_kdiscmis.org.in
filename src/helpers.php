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
