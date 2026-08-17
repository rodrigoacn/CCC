// Command cron ports cron_cleanup.php: background garbage collection to run
// via the system scheduler (cron) every hour.
package main

import (
	"context"
	"fmt"
	"log"
	"net"
	"os"
	"time"

	"github.com/redis/go-redis/v9"

	"classexpress/internal/config"
	"classexpress/internal/database"
	"classexpress/internal/store"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("config: %v", err)
	}
	if err := cfg.Validate(); err != nil {
		log.Fatalf("config: %v", err)
	}

	ctx := context.Background()
	db, err := database.Open(ctx, cfg)
	if err != nil {
		fmt.Fprintln(os.Stderr, "DB unavailable:", err)
		os.Exit(1)
	}
	defer db.Close()

	storeDB := &store.DB{Pool: db.Pool}
	cleaned := 0

	// 1. Expired mobile tokens (>30 days old)
	n := execCount(ctx, storeDB, "DELETE FROM mobile_tokens WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)")
	cleaned += n
	fmt.Printf("mobile_tokens: deleted %d expired rows\n", n)

	// 2. Old WebRTC signals (>2 hours old)
	n = execCount(ctx, storeDB, "DELETE FROM webrtc_signals WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)")
	cleaned += n
	fmt.Printf("webrtc_signals: deleted %d old rows\n", n)

	// 3. Stale spectator entries (>1 hour old, still pending)
	n = execCount(ctx, storeDB, "DELETE FROM espectadores WHERE estado = 'pendiente' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)")
	cleaned += n
	fmt.Printf("espectadores: deleted %d stale pending rows\n", n)

	// 4. Expired checkout sessions (>24 hours old, still pending)
	n = execCount(ctx, storeDB, "UPDATE checkout_sessions SET status = 'expired' WHERE status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)")
	cleaned += n
	fmt.Printf("checkout_sessions: expired %d pending rows\n", n)

	// 5. Orphaned sessions (no fin, no ultima_salida for >30 minutes)
	n = execCount(ctx, storeDB, "UPDATE sesiones_clase SET fin = NOW() WHERE fin IS NULL AND ultima_salida IS NULL AND inicio < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")
	cleaned += n
	fmt.Printf("sesiones_clase: closed %d orphaned sessions\n", n)

	// 6. Old chat messages (>7 days) — keep DB lean
	n = execCount(ctx, storeDB, "DELETE FROM mensajes_chat WHERE enviado_at < DATE_SUB(NOW(), INTERVAL 7 DAY)")
	cleaned += n
	fmt.Printf("mensajes_chat: deleted %d old messages\n", n)

	// 7. Old webrtc signals from Redis cache (poll:* keys)
	redisAddr := cfg.RedisHost
	if cfg.RedisPort != 0 {
		redisAddr = fmt.Sprintf("%s:%d", cfg.RedisHost, cfg.RedisPort)
	}
	if redisAddr != "" && tcpReachable(redisAddr) {
		rc := redis.NewClient(&redis.Options{Addr: redisAddr, Password: cfg.RedisPass})
		if err := rc.Ping(ctx).Err(); err == nil {
			keys, err := rc.Keys(ctx, "poll:*").Result()
			if err == nil {
				n := 0
				for _, k := range keys {
					ttl, err := rc.TTL(ctx, k).Result()
					if err == nil && ttl <= 0 {
						rc.Del(ctx, k)
						n++
					}
				}
				cleaned += n
				fmt.Printf("redis: checked %d poll keys\n", len(keys))
			}
		}
		rc.Close()
	}

	fmt.Printf("Total cleaned: %d rows\n", cleaned)
}

func execCount(ctx context.Context, db *store.DB, query string) int {
	n, err := db.Exec(ctx, query)
	if err != nil {
		log.Printf("cron: %v", err)
		return 0
	}
	return int(n)
}

// tcpReachable reports whether host:port accepts connections (mirrors
// getRedis() returning null when Redis is down, without go-redis log noise).
func tcpReachable(addr string) bool {
	conn, err := net.DialTimeout("tcp", addr, 2*time.Second)
	if err != nil {
		return false
	}
	conn.Close()
	return true
}
