<?php
declare(strict_types=1);

namespace App;

/**
 * CSRF token management. One token per session, verified on every POST.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();
        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }
        return $token;
    }

    /** Hidden input field for forms. */
    public static function field(): string
    {
        $token = self::token();
        return '<input type="hidden" name="' . self::KEY . '" value="' . e($token) . '">';
    }

    public static function fieldName(): string
    {
        return self::KEY;
    }

    /** Constant-time verification of a submitted token. */
    public static function verify(?string $submitted): bool
    {
        $expected = Session::get(self::KEY);
        if (!is_string($expected) || !is_string($submitted) || $submitted === '') {
            return false;
        }
        return hash_equals($expected, $submitted);
    }

    /** Verify the token from the current POST request; aborts with 419 on failure. */
    public static function check(): void
    {
        $submitted = $_POST[self::KEY] ?? null;
        if (!self::verify(is_string($submitted) ? $submitted : null)) {
            http_response_code(419);
            exit('Invalid or expired form token. Please reload the page and try again.');
        }
    }
}
