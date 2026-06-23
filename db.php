<?php
// ─────────────────────────────────────────────────────────────────────────────
//  Shared DB connection — PostgreSQL via Replit env vars
//  Returns a PDO singleton or null if unavailable
// ─────────────────────────────────────────────────────────────────────────────
function getDB(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $host = getenv('PGHOST')     ?: 'localhost';
        $port = getenv('PGPORT')     ?: '5432';
        $name = getenv('PGDATABASE') ?: 'replit_db';
        $user = getenv('PGUSER')     ?: 'postgres';
        $pass = getenv('PGPASSWORD') ?: '';

        $pdo = new PDO(
            "pgsql:host=$host;port=$port;dbname=$name",
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

// Convenience: fetch one row (keys always lowercased — PostgreSQL convention)
function dbOne(string $sql, array $params = []): ?array {
    $db = getDB();
    if (!$db) return null;
    $st = $db->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row ? array_change_key_case($row, CASE_LOWER) : null;
}

// Convenience: fetch many rows (keys always lowercased)
function dbAll(string $sql, array $params = []): array {
    $db = getDB();
    if (!$db) return [];
    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();
    return array_map(fn($r) => array_change_key_case($r, CASE_LOWER), $rows);
}

// Convenience: execute INSERT/UPDATE/DELETE, returns lastInsertId or rowCount
function dbExec(string $sql, array $params = []): int {
    $db = getDB();
    if (!$db) return 0;
    $st = $db->prepare($sql);
    $st->execute($params);
    return (int)($db->lastInsertId() ?: $st->rowCount());
}
