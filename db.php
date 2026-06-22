<?php
// ─────────────────────────────────────────────────────────────────────────────
//  Shared DB connection — returns a PDO singleton or null if unavailable
// ─────────────────────────────────────────────────────────────────────────────
function getDB(): ?PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=ce;charset=utf8mb4',
            'root', 'v6h470fdz0',
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
    return $st->fetchAll();
}

// Convenience: execute INSERT/UPDATE/DELETE, returns lastInsertId or rowCount
function dbExec(string $sql, array $params = []): int {
    $db = getDB();
    if (!$db) return 0;
    $st = $db->prepare($sql);
    $st->execute($params);
    return (int)($db->lastInsertId() ?: $st->rowCount());
}
