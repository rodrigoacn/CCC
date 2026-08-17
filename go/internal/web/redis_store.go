package web

import (
	"context"
	"time"

	"github.com/redis/go-redis/v9"
)

// RedisStore stores sessions under the same "session:<id>" keys and TTL used by
// RedisSession.php, using JSON serialization.
type RedisStore struct {
	client *redis.Client
}

// NewRedisClient dials Redis with the given address, password and DB index.
// Returns nil when Redis cannot be reached, so callers can fall back to
// another backend.
func NewRedisClient(addr, password string, db int) *redis.Client {
	client := redis.NewClient(&redis.Options{
		Addr:         addr,
		Password:     password,
		DB:           db,
		DialTimeout:  500 * time.Millisecond,
		ReadTimeout:  500 * time.Millisecond,
		WriteTimeout: 500 * time.Millisecond,
		MaxRetries:   1,
		PoolSize:     4,
	})
	ctx, cancel := context.WithTimeout(context.Background(), 1500*time.Millisecond)
	defer cancel()
	if err := client.Ping(ctx).Err(); err != nil {
		return nil
	}
	return client
}

// NewRedisStore dials Redis with the given address, password and DB index.
// Returns a nil Store (interface nil, not a typed nil) when Redis cannot be
// reached, so callers fall back to memory without panicking.
func NewRedisStore(addr, password string, db int) Store {
	return NewRedisStoreClient(NewRedisClient(addr, password, db))
}

// NewRedisStoreClient wraps an already-dialed client. Passing a nil client
// returns a nil Store so callers fall back to memory.
func NewRedisStoreClient(client *redis.Client) Store {
	if client == nil {
		return nil
	}
	return &RedisStore{client: client}
}

func (r *RedisStore) Get(ctx context.Context, id string) ([]byte, error) {
	raw, err := r.client.Get(ctx, id).Bytes()
	if err == redis.Nil {
		return nil, nil
	}
	return raw, err
}

func (r *RedisStore) Set(ctx context.Context, id string, data []byte, ttl time.Duration) error {
	return r.client.Set(ctx, id, data, ttl).Err()
}

func (r *RedisStore) Del(ctx context.Context, id string) error {
	return r.client.Del(ctx, id).Err()
}
