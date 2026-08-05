<?php
// ─────────────────────────────────────────────────────────────────────────────
//  Migration runner — applies pending migrations/*.sql in filename order.
//  Tracks applied files in the `schema_migrations` table.
//  Call from scripts/migrate.php or from a deploy hook.
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
        try {
            $db->exec($sql);
        } catch (\Exception $e) {
            // Fallback for pre-existing tables with a stale schema
            if (strpos($version, 'mobile_tokens') !== false) {
                $db->exec("DROP TABLE IF EXISTS mobile_tokens");
                $db->exec($sql);
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
