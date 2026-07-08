<?php
// ─────────────────────────────────────────────────────────────────────────────
//  Shared DB connection — MySQL
//  Returns a PDO singleton or null if unavailable
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('getDB')) {

// Error reporting configuration (hide warnings, show errors)
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Mailgun Configuration (get from https://app.mailgun.com/settings/api_security)
putenv('MAILGUN_API_KEY=YOUR_MAILGUN_API_KEY');
putenv('MAILGUN_DOMAIN=YOUR_MAILGUN_DOMAIN');
putenv('EMAIL_DEV_MODE=true'); // Log emails instead of sending (for development)

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
