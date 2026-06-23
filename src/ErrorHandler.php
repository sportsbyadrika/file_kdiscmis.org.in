<?php
declare(strict_types=1);

namespace App;

/**
 * Global error & exception handling.
 *
 * Always logs the real cause (to PHP's error log and, if writable, to
 * storage/logs/app.log). Shows full detail in the browser only when
 * app.debug is true; otherwise renders a short, safe 500 page.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(\Throwable $e): void
    {
        self::log('Uncaught ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
        self::render($e);
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::log('Fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
            // Headers may already be sent; do a best-effort render.
            self::render(new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']));
        }
    }

    private static function log(string $message): void
    {
        error_log($message);
        try {
            $dir = ROOT_PATH . '/storage/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                @file_put_contents(
                    $dir . '/app.log',
                    '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n\n",
                    FILE_APPEND | LOCK_EX
                );
            }
        } catch (\Throwable $ignore) {
            // Never let logging itself break the response.
        }
    }

    private static function render(\Throwable $e): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        $debug = (bool) Config::get('app.debug', false);
        if ($debug) {
            echo '<!doctype html><meta charset="utf-8"><title>Application error</title>';
            echo '<div style="font-family:system-ui,Arial,sans-serif;max-width:900px;margin:2rem auto;padding:1rem">';
            echo '<h1 style="color:#b02a37">Application error</h1>';
            echo '<p><strong>' . htmlspecialchars(get_class($e), ENT_QUOTES) . ':</strong> '
                . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';
            echo '<p style="color:#555">' . htmlspecialchars($e->getFile(), ENT_QUOTES) . ':' . (int) $e->getLine() . '</p>';
            echo '<pre style="background:#f6f8fa;padding:1rem;overflow:auto;font-size:13px">'
                . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES) . '</pre>';
            echo '<p style="color:#888">Set <code>app.debug</code> to <code>false</code> in config/config.php to hide this page.</p>';
            echo '</div>';
        } else {
            echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>';
            echo '<div style="font-family:system-ui,Arial,sans-serif;max-width:560px;margin:4rem auto;text-align:center">';
            echo '<h1>Something went wrong</h1>';
            echo '<p>The server encountered an error. Please try again. If the problem persists, contact the administrator.</p>';
            echo '</div>';
        }
    }
}
