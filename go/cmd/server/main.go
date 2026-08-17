package main

import (
	"context"
	"fmt"
	"log"
	"os"
	"os/signal"
	"path/filepath"
	"syscall"

	"classexpress/internal/config"
	"classexpress/internal/database"
	"classexpress/internal/httpapi"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("config: %v", err)
	}
	if err := cfg.Validate(); err != nil {
		log.Fatalf("config: %v", err)
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	db, err := database.Open(ctx, cfg)
	if err != nil {
		log.Printf("aviso: base de datos no disponible: %v", err)
		db = nil
	} else {
		defer db.Close()
		log.Printf("conectado a MySQL %s:%d/%s", cfg.DBHost, cfg.DBPort, cfg.DBName)
	}

	srv := httpapi.New(cfg, db)
	webRoot := os.Getenv("CE_WEB_ROOT")
	if webRoot == "" {
		if abs, err := filepath.Abs(".."); err == nil {
			webRoot = abs
		}
	}
	srv.SetWebDir(webRoot)
	addr := fmt.Sprintf(":%d", cfg.HTTPPort)
	if err := srv.Serve(ctx, addr); err != nil {
		log.Printf("servidor detenido: %v", err)
	}
}
