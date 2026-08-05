<?php
// ─────────────────────────────────────────────────────────────────────────────
//  RedisSession.php — PHP session handler backed by Redis
//  Includes: Redis-based session handler + auto-start
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/RedisConnection.php';

if (!class_exists('CE_RedisSessionHandler')) {

class CE_RedisSessionHandler implements SessionHandlerInterface {
    private Redis $redis;
    private int $ttl;

    public function __construct(Redis $redis, int $ttl = 86400) {
        $this->redis = $redis;
        $this->ttl   = $ttl;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string {
        $data = $this->redis->get("session:$id");
        return $data !== false ? (string)$data : '';
    }

    public function write(string $id, string $data): bool {
        return $this->redis->setex("session:$id", $this->ttl, $data);
    }

    public function destroy(string $id): bool {
        return $this->redis->del("session:$id") !== false;
    }

    public function gc(int $max_lifetime): int {
        // Redis handles expiry natively via TTL, nothing to do
        return 0;
    }
}

// Auto-start Redis sessions if Redis is available and PHP sessions aren't already active
if (session_status() === PHP_SESSION_NONE) {
    $redis = getRedis();
    if ($redis) {
        $handler = new CE_RedisSessionHandler($redis);
        session_set_save_handler($handler, true);
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        session_start();
    } else {
        session_start();
    }
}

} // End class_exists check
