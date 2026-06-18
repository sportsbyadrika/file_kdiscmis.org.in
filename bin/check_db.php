<?php
declare(strict_types=1);

/**
 * CLI database check.
 *
 * Verifies the configured connection works, the schema is applied, and the
 * admin account is seeded.
 *
 *   php bin/check_db.php
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config;
use App\Database;

fwrite(STDOUT, "Checking database connection...\n");

try {
    $pdo = Database::connection();
} catch (\Throwable $e) {
    fwrite(STDERR, "  FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
fwrite(STDOUT, "  OK: connected to '" . Config::get('db.name') . "'\n");

$expected = [
    'users', 'files', 'eoffice_metadata', 'ospyndocs_metadata',
    'file_attachments', 'file_transaction_history', 'file_update_log',
    'bulk_import_batches', 'user_preferences',
];

$rows = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
$missing = array_diff($expected, $rows);

if ($missing) {
    fwrite(STDERR, "  MISSING TABLES: " . implode(', ', $missing) . "\n");
    fwrite(STDERR, "  Apply schema.sql first.\n");
    exit(1);
}
fwrite(STDOUT, "  OK: all " . count($expected) . " tables present\n");

$admin = Database::run('SELECT username, email, role FROM users WHERE role = ? LIMIT 1', ['admin'])->fetch();
if (!$admin) {
    fwrite(STDERR, "  WARN: no admin user seeded\n");
    exit(1);
}
fwrite(STDOUT, "  OK: admin seeded ({$admin['username']} / {$admin['email']})\n");

fwrite(STDOUT, "All checks passed.\n");
