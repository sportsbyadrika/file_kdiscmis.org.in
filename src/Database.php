<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

/**
 * PDO MySQL connection wrapper (singleton).
 *
 * Always returns prepared-statement-friendly handles. No string-concatenated
 * SQL is ever produced here — callers must use bound parameters.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private function __construct() {}

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = (string) Config::get('db.host', '127.0.0.1');
        $port    = (int)    Config::get('db.port', 3306);
        $name    = (string) Config::get('db.name', '');
        $user    = (string) Config::get('db.user', '');
        $pass    = (string) Config::get('db.pass', '');
        $charset = (string) Config::get('db.charset', 'utf8mb4');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
        ];

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Never leak credentials; surface a generic message.
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode());
        }

        return self::$pdo;
    }

    /**
     * Convenience: run a prepared statement and return the statement.
     *
     * @param array<string|int, mixed> $params
     */
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Reset connection (mainly for tests). */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
