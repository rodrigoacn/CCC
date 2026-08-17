package web

import (
	"context"
	"encoding/json"
	"os"
	"path/filepath"
	"regexp"
	"time"

	"github.com/redis/go-redis/v9"
)

var rateKeyRE = regexp.MustCompile(`[^a-zA-Z0-9_]`)

// RateLimiter ports lib/RateLimiter.php: Redis-backed with a file fallback.
type RateLimiter struct {
	redis *redis.Client
	dir   string
}

// NewRateLimiter builds a rate limiter. redis may be nil to force the file
// fallback (mirrors getRedis() returning null).
func NewRateLimiter(redisClient *redis.Client, tempDir string) *RateLimiter {
	if tempDir == "" {
		tempDir = os.TempDir()
	}
	return &RateLimiter{redis: redisClient, dir: filepath.Join(tempDir, "ce_ratelimit")}
}

// Allow mirrors rateLimit(): reports whether the attempt is allowed, recording
// it when it is. maxAttempts per windowSeconds.
func (rl *RateLimiter) Allow(ctx context.Context, action, identifier string, maxAttempts int, windowSeconds int) bool {
	key := "ratelimit:" + action + ":" + identifier
	if rl.redis != nil {
		current, err := rl.redis.Get(ctx, key).Int()
		if err == nil && current >= maxAttempts {
			return false
		}
		if err == redis.Nil {
			rl.redis.Set(ctx, key, 1, time.Duration(windowSeconds)*time.Second)
		} else {
			rl.redis.Incr(ctx, key)
		}
		return true
	}
	return rl.fileAllow(action, identifier, maxAttempts, windowSeconds)
}

// Remaining mirrors rateLimitRemaining().
func (rl *RateLimiter) Remaining(ctx context.Context, action, identifier string, maxAttempts int, windowSeconds int) int {
	key := "ratelimit:" + action + ":" + identifier
	if rl.redis != nil {
		current, err := rl.redis.Get(ctx, key).Int()
		if err != nil {
			return maxAttempts
		}
		if current >= maxAttempts {
			return 0
		}
		return maxAttempts - current
	}
	return rl.fileRemaining(action, identifier, maxAttempts, windowSeconds)
}

func (rl *RateLimiter) filePath(action, identifier string) string {
	_ = os.MkdirAll(rl.dir, 0o777)
	name := rateKeyRE.ReplaceAllString(action+"_"+identifier, "_")
	return filepath.Join(rl.dir, name)
}

func (rl *RateLimiter) fileAllow(action, identifier string, maxAttempts int, windowSeconds int) bool {
	path := rl.filePath(action, identifier)
	now := time.Now().Unix()
	var ts []int64
	if raw, err := os.ReadFile(path); err == nil {
		_ = json.Unmarshal(raw, &ts)
	}
	cut := now - int64(windowSeconds)
	fresh := ts[:0]
	for _, t := range ts {
		if t > cut {
			fresh = append(fresh, t)
		}
	}
	if len(fresh) >= maxAttempts {
		return false
	}
	fresh = append(fresh, now)
	if b, err := json.Marshal(fresh); err == nil {
		_ = os.WriteFile(path, b, 0o644)
	}
	return true
}

func (rl *RateLimiter) fileRemaining(action, identifier string, maxAttempts int, windowSeconds int) int {
	path := rl.filePath(action, identifier)
	now := time.Now().Unix()
	raw, err := os.ReadFile(path)
	if err != nil {
		return maxAttempts
	}
	var ts []int64
	_ = json.Unmarshal(raw, &ts)
	cut := now - int64(windowSeconds)
	count := 0
	for _, t := range ts {
		if t > cut {
			count++
		}
	}
	if count >= maxAttempts {
		return 0
	}
	return maxAttempts - count
}
