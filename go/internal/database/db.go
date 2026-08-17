package database

import (
	"context"
	"database/sql"
	"fmt"
	"time"

	"classexpress/internal/config"

	_ "github.com/go-sql-driver/mysql"
)

// DB wraps the SQL pool.
type DB struct {
	Pool *sql.DB
}

// Open creates and validates the MySQL connection pool.
func Open(ctx context.Context, cfg *config.Config) (*DB, error) {
	dsn := fmt.Sprintf("%s:%s@tcp(%s:%d)/%s?parseTime=true&charset=utf8mb4&collation=utf8mb4_unicode_ci",
		cfg.DBUser, cfg.DBPass, cfg.DBHost, cfg.DBPort, cfg.DBName)

	pool, err := sql.Open("mysql", dsn)
	if err != nil {
		return nil, fmt.Errorf("sql.Open: %w", err)
	}
	pool.SetMaxOpenConns(25)
	pool.SetMaxIdleConns(10)
	pool.SetConnMaxLifetime(5 * time.Minute)

	pingCtx, cancel := context.WithTimeout(ctx, 3*time.Second)
	defer cancel()
	if err := pool.PingContext(pingCtx); err != nil {
		pool.Close()
		return nil, fmt.Errorf("ping %s:%d/%s: %w", cfg.DBHost, cfg.DBPort, cfg.DBName, err)
	}
	return &DB{Pool: pool}, nil
}

// Ping reports whether the pool is reachable right now.
func (d *DB) Ping(ctx context.Context) error {
	pingCtx, cancel := context.WithTimeout(ctx, 2*time.Second)
	defer cancel()
	return d.Pool.PingContext(pingCtx)
}

// Close shuts the pool down.
func (d *DB) Close() error {
	return d.Pool.Close()
}
