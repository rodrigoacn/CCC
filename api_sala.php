<?php
// ─────────────────────────────────────────────────────────────────────────────
//  api_sala.php — thin shim. Logic moved to lib/app/ (SalaApi front controller
//  + lib/app/Controllers/SalaController.php).
//  URL and JSON contract are unchanged.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/lib/app/SalaApi.php';
\App\SalaApi::dispatch();
