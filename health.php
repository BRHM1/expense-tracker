<?php
/**
 * Connectivity check: Browser -> Apache -> PHP -> MySQL.
 * Returns plain text and HTTP 200 if MySQL is reachable, 500 otherwise.
 * Never prints credentials.
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo 'PHP version:   ' . PHP_VERSION . "\n";
echo 'Server API:    ' . PHP_SAPI . "\n";
echo 'pdo_mysql:     ' . (extension_loaded('pdo_mysql') ? 'loaded' : 'MISSING') . "\n";

try {
    $db = getDb();
    $version = $db->query('SELECT VERSION()')->fetchColumn();
    $count = $db->query('SELECT COUNT(*) FROM expenses')->fetchColumn();

    echo "MySQL:         connected (server $version)\n";
    echo "Expenses rows: $count\n";
    echo "Status:        OK\n";
} catch (Throwable $ex) {
    http_response_code(500);
    error_log('Health check failed: ' . $ex->getMessage());
    echo "MySQL:         connection FAILED (see Apache/PHP error log for details)\n";
    echo "Status:        ERROR\n";
}
