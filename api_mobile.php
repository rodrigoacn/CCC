<?php
// ─────────────────────────────────────────────────────────────────────────────
//  api_mobile.php — thin shim. Logic moved to lib/app/ (MobileApi front
//  controller + domain controllers under lib/app/Controllers/).
//  URL and JSON contract are unchanged.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/lib/app/MobileApi.php';
\App\MobileApi::dispatch();
