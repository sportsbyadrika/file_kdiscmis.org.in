<?php
declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Single include point for every entry script:
 *   require dirname(__DIR__) . '/src/bootstrap.php';
 *
 * Responsibilities:
 *   - Define ROOT_PATH
 *   - Register a PSR-4 autoloader for the App\ namespace (-> /src)
 *   - Load configuration
 *   - Apply timezone & error-reporting settings
 *   - Load global helper functions
 */

define('ROOT_PATH', dirname(__DIR__));

// ---------------------------------------------------------------------
// Autoloader for App\ -> /src
// ---------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = ROOT_PATH . '/src/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// ---------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------
\App\Config::load(ROOT_PATH . '/config/config.php');

// ---------------------------------------------------------------------
// Environment
// ---------------------------------------------------------------------
date_default_timezone_set((string) \App\Config::get('app.timezone', 'UTC'));

if (\App\Config::get('app.debug', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------
require ROOT_PATH . '/src/helpers.php';
