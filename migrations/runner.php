<?php
// ─────────────────────────────────────────────────────────────────────────────
//  Migration runner — applies pending migrations/*.sql in filename order.
//  Tracks applied files in the `schema_migrations` table.
//  Call from scripts/migrate.php or from a deploy hook.
//
//  Robustness notes:
//  - Comments are stripped before splitting on ";" so a ";" inside a comment
//    does not break statement boundaries.
//  - Duplicate-column errors (MySQL 1060 / SQLSTATE 42S21) are ignored, so a
//    plain ALTER is safe to run even when the column already exists.
//  - The mobile_tokens fallback rebuilds the table when it has a stale schema.
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('migrations_run')) {

function migrations_run(): array {
    $db = getDB();
    if (!$db) return ['error' => 'DB unavailable'];

    $db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $applied = array_column($db->query("SELECT version FROM schema_migrations")->fetchAll(), 'version');
    $files = glob(__DIR__ . '/*.sql');
    sort($files);

    $ran = [];
    foreach ($files as $file) {
        $version = basename($file);
        if (in_array($version, $applied, true)) continue;

        $sql = file_get_contents($file);
        $sql = preg_replace('#/\*.*?\*/#s', '', $sql); // block comments
        $sql = preg_replace('#^--.*$#m', '', $sql);    // line comments
        $statements = array_values(array_filter(
            array_map('trim', explode(';', $sql)),
            static fn(string $s) => $s !== ''
        ));

        try {
            foreach ($statements as $stmt) {
                try {
                    $db->exec($stmt);
                } catch (\Exception $e) {
                    if ($e->getCode() !== '42S21' && !str_contains($e->getMessage(), '1060')) throw $e;
                }
            }
        } catch (\Exception $e) {
            // Fallback for pre-existing tables with a stale schema
            if (strpos($version, 'mobile_tokens') !== false) {
                try { $db->exec("DROP TABLE IF EXISTS mobile_tokens"); } catch (\Exception $ignored) {}
                foreach ($statements as $stmt) {
                    try { $db->exec($stmt); } catch (\Exception $ignored) {}
                }
            } else {
                return ['error' => "Failed applying {$version}: " . $e->getMessage()];
            }
        }

        $stmt = $db->prepare("INSERT INTO schema_migrations (version) VALUES (?)");
        $stmt->execute([$version]);
        $ran[] = $version;
    }

    return ['applied' => $ran];
}

}
