<?php
/**
 * Database configuration and PDO connection.
 *
 * Credentials are NOT hardcoded here. They are read from (in order):
 *   1. Environment variables (e.g. Apache "SetEnv DB_HOST ..." in the vhost)
 *   2. The file config/.env (copy config/.env.example and edit it)
 */

function loadDbConfig(): array
{
    $keys = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
    $defaults = ['DB_HOST' => 'localhost', 'DB_PORT' => '3306', 'DB_NAME' => 'expense_tracker'];

    $file = [];
    $envFile = __DIR__ . '/.env';
    if (is_readable($envFile)) {
        // INI_SCANNER_RAW keeps special characters in passwords intact.
        $file = parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [];
    }

    $config = [];
    foreach ($keys as $key) {
        $env = getenv($key);
        $config[$key] = $env !== false ? $env : ($file[$key] ?? $defaults[$key] ?? '');
    }

    return $config;
}

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $c = loadDbConfig();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $c['DB_HOST'],
            $c['DB_PORT'],
            $c['DB_NAME']
        );

        $pdo = new PDO($dsn, $c['DB_USER'], $c['DB_PASSWORD'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
