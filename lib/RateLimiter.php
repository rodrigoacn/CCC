<?php
// ─────────────────────────────────────────────────────────────────────────────
//  Rate limiter — IP-based, backed by Redis or fallback to file-based
//  Usage: rateLimit('login', $ip, 5, 300);  // 5 attempts per 300 seconds
// ─────────────────────────────────────────────────────────────────────────────

function rateLimit(string $action, string $identifier, int $maxAttempts, int $windowSeconds): bool {
    $key = "ratelimit:$action:$identifier";

    $redis = null;
    if (function_exists('getRedis')) {
        $redis = @getRedis();
    }

    if ($redis) {
        $current = (int)$redis->get($key);
        if ($current >= $maxAttempts) return false;
        if ($current === 0) {
            $redis->setex($key, $windowSeconds, 1);
        } else {
            $redis->incr($key);
        }
        return true;
    }

    // Fallback: file-based rate limiter
    $dir = sys_get_temp_dir() . '/ce_ratelimit';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_]/', '_', $action . '_' . $identifier);

    $now = time();
    $data = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $data = json_decode($raw, true) ?: [];
    }

    // Clean old entries
    $data = array_filter($data, fn($ts) => $ts > $now - $windowSeconds);

    if (count($data) >= $maxAttempts) return false;
    $data[] = $now;
    file_put_contents($file, json_encode($data));
    return true;
}

function rateLimitRemaining(string $action, string $identifier, int $maxAttempts, int $windowSeconds): int {
    $key = "ratelimit:$action:$identifier";
    $redis = null;
    if (function_exists('getRedis')) $redis = @getRedis();

    if ($redis) {
        $current = (int)$redis->get($key);
        return max(0, $maxAttempts - $current);
    }

    $dir = sys_get_temp_dir() . '/ce_ratelimit';
    $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_]/', '_', $action . '_' . $identifier);
    $now = time();
    if (!file_exists($file)) return $maxAttempts;
    $data = json_decode(file_get_contents($file), true) ?: [];
    $data = array_filter($data, fn($ts) => $ts > $now - $windowSeconds);
    return max(0, $maxAttempts - count($data));
}
