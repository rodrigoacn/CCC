<?php
// ─────────────────────────────────────────────────────────────────────────────
//  RedisConnection.php — Redis singleton with graceful fallback
//  Returns a Redis instance or null if Redis is unavailable
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('getRedis')) {

function getRedis(): ?Redis {
    static $redis = null;
    if ($redis !== null) return $redis;

    $host = getenv('REDIS_HOST') ?: '127.0.0.1';
    $port = (int)(getenv('REDIS_PORT') ?: 6379);
    $pass = getenv('REDIS_PASS') ?: '';
    $db   = (int)(getenv('REDIS_DB') ?: 0);

    try {
        $redis = new Redis();
        $redis->connect($host, $port, 2.0);
        if ($pass !== '') $redis->auth($pass);
        $redis->select($db);
        $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
    } catch (Throwable $e) {
        $redis = null;
    }
    return $redis;
}

// Convenience: cache-aside read
function redisGet(string $key): mixed {
    $r = getRedis();
    if (!$r) return null;
    $val = $r->get($key);
    return $val === false ? null : $val;
}

// Convenience: cache-aside write with TTL
function redisSet(string $key, mixed $value, int $ttl = 300): bool {
    $r = getRedis();
    if (!$r) return false;
    return $r->set($key, $value, $ttl);
}

// Convenience: delete key(s)
function redisDel(string ...$keys): bool {
    $r = getRedis();
    if (!$r) return false;
    return $r->del(...$keys) !== false;
}

// Convenience: push to list (left-push for newest-first)
function redisLPush(string $key, mixed $value, int $maxLen = 0): bool {
    $r = getRedis();
    if (!$r) return false;
    $r->lPush($key, $value);
    if ($maxLen > 0) {
        $r->lTrim($key, 0, $maxLen - 1);
    }
    return true;
}

// Convenience: read range from list (0 = newest)
function redisLRange(string $key, int $start = 0, int $stop = -1): array {
    $r = getRedis();
    if (!$r) return [];
    $result = $r->lRange($key, $start, $stop);
    return $result ?: [];
}

// Convenience: increment counter
function redisIncr(string $key, int $ttl = 0): int {
    $r = getRedis();
    if (!$r) return 0;
    $val = $r->incr($key);
    if ($ttl > 0 && $val === 1) {
        $r->expire($key, $ttl);
    }
    return $val;
}

} // End function_exists check
