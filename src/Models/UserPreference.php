<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * Per-user preferences (column visibility, per-page, …) — stored in the
 * user_preferences table, keyed by (user_id, module, preference_key).
 */
final class UserPreference
{
    public static function get(int $userId, string $module, string $key, ?string $default = null): ?string
    {
        $row = Database::run(
            'SELECT preference_value FROM user_preferences
             WHERE user_id = :u AND module = :m AND preference_key = :k LIMIT 1',
            ['u' => $userId, 'm' => $module, 'k' => $key]
        )->fetch();
        return $row ? (string) $row['preference_value'] : $default;
    }

    public static function set(int $userId, string $module, string $key, string $value): void
    {
        // Upsert without relying on MySQL-specific syntax (portable for tests).
        $exists = Database::run(
            'SELECT preference_id FROM user_preferences
             WHERE user_id = :u AND module = :m AND preference_key = :k LIMIT 1',
            ['u' => $userId, 'm' => $module, 'k' => $key]
        )->fetch();

        if ($exists) {
            Database::run(
                'UPDATE user_preferences SET preference_value = :v
                 WHERE user_id = :u AND module = :m AND preference_key = :k',
                ['v' => $value, 'u' => $userId, 'm' => $module, 'k' => $key]
            );
        } else {
            Database::run(
                'INSERT INTO user_preferences (user_id, module, preference_key, preference_value)
                 VALUES (:u, :m, :k, :v)',
                ['u' => $userId, 'm' => $module, 'k' => $key, 'v' => $value]
            );
        }
    }

    /** Get a JSON-encoded preference decoded to an array, or $default. */
    public static function getJson(int $userId, string $module, string $key, ?array $default = null): ?array
    {
        $raw = self::get($userId, $module, $key);
        if ($raw === null) {
            return $default;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : $default;
    }
}
