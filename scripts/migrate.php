<?php
// ─────────────────────────────────────────────────────────────────────────────
//  CLI migration runner — php scripts/migrate.php
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../migrations/runner.php';

$result = migrations_run();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
