<?php
declare(strict_types=1);

namespace App;

/**
 * Lightweight configuration holder.
 *
 * Loads config/config.php once and exposes dot-notation access:
 *   Config::get('db.host')
 *   Config::get('app.debug', false)
 */
final class Config
{
    private static ?array $data = null;

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException(
                'Configuration file not found. Copy config/config.sample.php to config/config.php.'
            );
        }
        $data = require $path;
        if (!is_array($data)) {
            throw new \RuntimeException('Configuration file must return an array.');
        }
        self::$data = $data;
    }

    /** @return mixed */
    public static function get(string $key, $default = null)
    {
        if (self::$data === null) {
            throw new \RuntimeException('Config not loaded. Call Config::load() first.');
        }

        $segments = explode('.', $key);
        $value = self::$data;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public static function all(): array
    {
        return self::$data ?? [];
    }
}
