<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;

/**
 * User data access. Prepared statements only.
 */
final class User
{
    /** Find an active user by username OR email. */
    public static function findByLogin(string $login): ?array
    {
        $sql = 'SELECT id, username, email, password_hash, full_name, role, is_active
                FROM users
                WHERE (username = :login OR email = :login) AND is_active = 1
                LIMIT 1';
        $row = Database::run($sql, ['login' => $login])->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $sql = 'SELECT id, username, email, password_hash, full_name, role, is_active, created_at
                FROM users WHERE id = :id LIMIT 1';
        $row = Database::run($sql, ['id' => $id])->fetch();
        return $row ?: null;
    }

    public static function updatePassword(int $id, string $newHash): void
    {
        Database::run(
            'UPDATE users SET password_hash = :h WHERE id = :id',
            ['h' => $newHash, 'id' => $id]
        );
    }

    public static function updateProfile(int $id, string $fullName, string $email): void
    {
        Database::run(
            'UPDATE users SET full_name = :n, email = :e WHERE id = :id',
            ['n' => $fullName, 'e' => $email, 'id' => $id]
        );
    }

    /** Is this email used by a different user? */
    public static function emailTakenByOther(string $email, int $exceptId): bool
    {
        $row = Database::run(
            'SELECT id FROM users WHERE email = :e AND id <> :id LIMIT 1',
            ['e' => $email, 'id' => $exceptId]
        )->fetch();
        return (bool) $row;
    }
}
