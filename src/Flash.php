<?php
declare(strict_types=1);

namespace App;

/**
 * One-shot flash messages, surfaced as toast notifications.
 *
 * Types: success | warning | error | info
 */
final class Flash
{
    private const KEY = '_flash';

    public static function add(string $type, string $message): void
    {
        Session::start();
        $messages = Session::get(self::KEY, []);
        if (!is_array($messages)) {
            $messages = [];
        }
        $messages[] = ['type' => $type, 'message' => $message];
        Session::set(self::KEY, $messages);
    }

    public static function success(string $m): void { self::add('success', $m); }
    public static function warning(string $m): void { self::add('warning', $m); }
    public static function error(string $m): void   { self::add('error', $m); }
    public static function info(string $m): void    { self::add('info', $m); }

    /** Return all queued messages and clear them. */
    public static function pull(): array
    {
        Session::start();
        $messages = Session::get(self::KEY, []);
        Session::remove(self::KEY);
        return is_array($messages) ? $messages : [];
    }
}
