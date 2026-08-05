#!/usr/bin/env php
<?php
// ─────────────────────────────────────────────────────────────────────────────
//  cron_cleanup.php — Background garbage collection (run via cron every hour)
//  Cleans: expired mobile tokens, old webrtc signals, stale sessions,
//          orphaned spectators, expired checkout sessions
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/RedisConnection.php';

$db = getDB();
if (!$db) { fwrite(STDERR, "DB unavailable\n"); exit(1); }

$now = new DateTime();
$cleaned = 0;

// 1. Expired mobile tokens (>30 days old)
$r = $db->exec("DELETE FROM mobile_tokens WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
$cleaned += $r;
echo "mobile_tokens: deleted $r expired rows\n";

// 2. Old WebRTC signals (>2 hours old)
$r = $db->exec("DELETE FROM webrtc_signals WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)");
$cleaned += $r;
echo "webrtc_signals: deleted $r old rows\n";

// 3. Stale spectator entries (>1 hour old, still pending)
$r = $db->exec("DELETE FROM espectadores WHERE estado = 'pendiente' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$cleaned += $r;
echo "espectadores: deleted $r stale pending rows\n";

// 4. Expired checkout sessions (>24 hours old, still pending)
$r = $db->exec("UPDATE checkout_sessions SET status = 'expired' WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$cleaned += $r;
echo "checkout_sessions: expired $r pending rows\n";

// 5. Orphaned sessions (no fin, no ultima_salida for >30 minutes)
$r = $db->exec("UPDATE sesiones_clase SET fin = NOW() WHERE fin IS NULL AND ultima_salida IS NULL AND inicio < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
$cleaned += $r;
echo "sesiones_clase: closed $r orphaned sessions\n";

// 6. Old chat messages (>7 days) — keep DB lean
$r = $db->exec("DELETE FROM mensajes_chat WHERE enviado_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
$cleaned += $r;
echo "mensajes_chat: deleted $r old messages\n";

// 7. Old webrtc signals from Redis cache
$redis = getRedis();
if ($redis) {
    $keys = $redis->keys('poll:*');
    foreach ($keys as $key) {
        $ttl = $redis->ttl($key);
        if ($ttl <= 0) {
            $redis->del($key);
        }
    }
    echo "redis: checked " . count($keys) . " poll keys\n";
}

echo "Total cleaned: $cleaned rows\n";
