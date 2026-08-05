<?php
// ─────────────────────────────────────────────────────────────────────────────
//  Shared DB connection — MySQL
//  Returns a PDO singleton or null if unavailable
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('getDB')) {

// Error reporting configuration (hide warnings, show errors)
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_error.log');
ini_set('display_startup_errors', 0);

// ── Load .env file if present ──────────────────────────────────────────────
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!getenv($key)) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// ── Redis sessions (auto-starts if Redis available) ─────────────────────────
require_once __DIR__ . '/lib/RedisSession.php';

// Email Configuration (from .env or defaults)
if (!getenv('EMAIL_PROVIDER'))    putenv('EMAIL_PROVIDER=brevo');
if (!getenv('EMAIL_FROM'))        putenv('EMAIL_FROM=noreply@classexpress.online');
if (!getenv('EMAIL_FROM_NAME'))   putenv('EMAIL_FROM_NAME=ClassExpress');
if (!getenv('EMAIL_DEV_MODE'))    putenv('EMAIL_DEV_MODE=false');

function getDB(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $host = getenv('DB_HOST')     ?: 'localhost';
        $port = getenv('DB_PORT')     ?: '3306';
        $name = getenv('DB_NAME') ?: 'classexpress';
        $user = getenv('DB_USER')     ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        $pdo = null;
    }
    return $pdo;
}

// Convenience: fetch one row
function dbOne(string $sql, array $params = []): ?array {
    $db = getDB();
    if (!$db) return null;
    $st = $db->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row ?: null;
}

// Convenience: fetch many rows
function dbAll(string $sql, array $params = []): array {
    $db = getDB();
    if (!$db) return [];
    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    return $rows;
}

// Convenience: execute INSERT/UPDATE/DELETE, returns lastInsertId or rowCount
function dbExec(string $sql, array $params = []): int {
    $db = getDB();
    if (!$db) return 0;
    $st = $db->prepare($sql);
    $st->execute($params);
    return (int)($db->lastInsertId() ?: $st->rowCount());
}

} // End of function_exists check
