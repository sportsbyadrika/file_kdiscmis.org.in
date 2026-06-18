<?php
declare(strict_types=1);

namespace App;

use App\Models\User;

/**
 * Session-based authentication.
 *
 * Single Admin role this release: every authenticated user is treated as
 * Admin. The role is still tracked so gating can be added later.
 */
final class Auth
{
    private const KEY = '_auth_user_id';
    private static ?array $cache = null;

    /**
     * Attempt login by username/email + password.
     * Returns true on success (session established).
     */
    public static function attempt(string $login, string $password): bool
    {
        $user = User::findByLogin(trim($login));
        if ($user === null) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Transparent re-hash if the algorithm/cost has changed.
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
            User::updatePassword((int) $user['id'], password_hash($password, PASSWORD_BCRYPT));
        }

        Session::start();
        Session::regenerate();
        Session::set(self::KEY, (int) $user['id']);
        self::$cache = null;
        return true;
    }

    public static function check(): bool
    {
        Session::start();
        return Session::has(self::KEY);
    }

    public static function id(): ?int
    {
        Session::start();
        $id = Session::get(self::KEY);
        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    /** Current user row (cached per request), or null. */
    public static function user(): ?array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $id = self::id();
        if ($id === null) {
            return null;
        }
        $user = User::findById($id);
        if ($user === null || (int) $user['is_active'] !== 1) {
            self::logout();
            return null;
        }
        self::$cache = $user;
        return $user;
    }

    public static function logout(): void
    {
        Session::start();
        Session::remove(self::KEY);
        self::$cache = null;
        Session::destroy();
    }

    /** Guard: redirect unauthenticated visitors to login. */
    public static function requireLogin(): void
    {
        if (!self::check() || self::user() === null) {
            redirect('/login');
        }
    }
}
