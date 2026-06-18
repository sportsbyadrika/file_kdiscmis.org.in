<?php
declare(strict_types=1);

namespace App;

/**
 * Minimal template renderer.
 *
 *   View::render('auth/login', ['error' => $e], 'guest');
 *
 * Templates live in /views and are plain PHP files. The chosen layout
 * (/views/layouts/<layout>.php) receives the rendered child markup as
 * $content plus any shared data.
 */
final class View
{
    private static string $base = '';

    private static function base(): string
    {
        if (self::$base === '') {
            self::$base = ROOT_PATH . '/views';
        }
        return self::$base;
    }

    /** Render a template into a layout and echo the result. */
    public static function render(string $template, array $data = [], ?string $layout = 'app'): void
    {
        echo self::capture($template, $data, $layout);
    }

    /** Render and return as a string. */
    public static function capture(string $template, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::renderPartial($template, $data);

        if ($layout === null) {
            return $content;
        }

        $layoutData = array_merge($data, ['content' => $content]);
        return self::renderPartial('layouts/' . $layout, $layoutData);
    }

    /** Render a single template file with no layout. */
    public static function renderPartial(string $template, array $data = []): string
    {
        $file = self::base() . '/' . ltrim($template, '/') . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
