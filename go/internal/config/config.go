package config

import (
	"bufio"
	"errors"
	"fmt"
	"os"
	"strconv"
	"strings"
)

// Config holds all runtime settings, mirroring the PHP .env file.
type Config struct {
	DBHost string
	DBPort int
	DBName string
	DBUser string
	DBPass string

	RedisHost string
	RedisPort int
	RedisPass string
	RedisDB   int

	EmailProvider string
	BrevoAPIKey   string
	MailgunAPIKey string
	MailgunDomain string
	EmailFrom     string
	EmailFromName string
	EmailDevMode  bool

	MPAccessToken    string
	MPPublicKey      string
	MPMode           string
	MPWebhookSecret  string
	MPDefaultCurrency string
	MPClpPerUSD       float64

	AppOwnerEmails        []string
	LoginAllowedIPs       []string
	LoginOwnerAccessEmail string

	AAAdUnitID string

	HTTPPort int
}

// Default returns a Config with sane defaults for local dev.
func Default() *Config {
	return &Config{
		DBHost:   "localhost",
		DBPort:   3306,
		DBName:   "classexpress",
		DBUser:   "root",
		DBPass:   "",
		HTTPPort: 8080,

		MPDefaultCurrency: "CLP",
		MPClpPerUSD:       950,
	}
}

// Load reads the .env file. It looks for the file in this order:
// 1. the CE_ENV_PATH environment variable
// 2. ../.env (project root relative to the go/ dir)
// 3. ./.env
func Load() (*Config, error) {
	cfg := Default()

	path := os.Getenv("CE_ENV_PATH")
	if path == "" {
		for _, cand := range []string{"../.env", "./.env"} {
			if fi, err := os.Stat(cand); err == nil && !fi.IsDir() {
				path = cand
				break
			}
		}
	}
	if path != "" {
		if err := applyEnvFile(cfg, path); err != nil {
			return nil, err
		}
	}

	if port := os.Getenv("HTTP_PORT"); port != "" {
		n, err := strconv.Atoi(port)
		if err != nil || n <= 0 {
			return nil, fmt.Errorf("HTTP_PORT invalido: %q", port)
		}
		cfg.HTTPPort = n
	}
	return cfg, nil
}

func applyEnvFile(cfg *Config, path string) error {
	f, err := os.Open(path)
	if err != nil {
		return err
	}
	defer f.Close()

	sc := bufio.NewScanner(f)
	for sc.Scan() {
		line := strings.TrimSpace(sc.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		eq := strings.Index(line, "=")
		if eq < 1 {
			continue
		}
		key := strings.TrimSpace(line[:eq])
		val := strings.TrimSpace(line[eq+1:])
		apply(cfg, key, val)
	}
	return sc.Err()
}

func apply(cfg *Config, key, val string) {
	switch key {
	case "DB_HOST":
		cfg.DBHost = val
	case "DB_PORT":
		cfg.DBPort = atoiDefault(val, cfg.DBPort)
	case "DB_NAME":
		cfg.DBName = val
	case "DB_USER":
		cfg.DBUser = val
	case "DB_PASS":
		cfg.DBPass = val
	case "REDIS_HOST":
		cfg.RedisHost = val
	case "REDIS_PORT":
		cfg.RedisPort = atoiDefault(val, cfg.RedisPort)
	case "REDIS_PASS":
		cfg.RedisPass = val
	case "REDIS_DB":
		cfg.RedisDB = atoiDefault(val, cfg.RedisDB)
	case "EMAIL_PROVIDER":
		cfg.EmailProvider = val
	case "BREVO_API_KEY":
		cfg.BrevoAPIKey = val
	case "MAILGUN_API_KEY":
		cfg.MailgunAPIKey = val
	case "MAILGUN_DOMAIN":
		cfg.MailgunDomain = val
	case "EMAIL_FROM":
		cfg.EmailFrom = val
	case "EMAIL_FROM_NAME":
		cfg.EmailFromName = val
	case "EMAIL_DEV_MODE":
		cfg.EmailDevMode = val == "true" || val == "1"
	case "MP_ACCESS_TOKEN":
		cfg.MPAccessToken = val
	case "MP_PUBLIC_KEY":
		cfg.MPPublicKey = val
	case "MP_MODE":
		cfg.MPMode = val
	case "MP_WEBHOOK_SECRET":
		cfg.MPWebhookSecret = val
	case "MP_DEFAULT_CURRENCY":
		cfg.MPDefaultCurrency = strings.ToUpper(val)
	case "MP_CLP_PER_USD":
		if f, err := strconv.ParseFloat(val, 64); err == nil && f > 0 {
			cfg.MPClpPerUSD = f
		}
	case "APP_OWNER_EMAIL":
		cfg.AppOwnerEmails = splitList(val)
	case "LOGIN_ALLOWED_IPS":
		cfg.LoginAllowedIPs = splitList(val)
	case "LOGIN_OWNER_ACCESS_EMAIL":
		cfg.LoginOwnerAccessEmail = val
	case "AA_AD_UNIT_ID":
		cfg.AAAdUnitID = val
	}
}

func splitList(s string) []string {
	parts := strings.Split(s, ",")
	out := make([]string, 0, len(parts))
	for _, p := range parts {
		if p = strings.TrimSpace(p); p != "" {
			out = append(out, p)
		}
	}
	return out
}

func atoiDefault(s string, def int) int {
	n, err := strconv.Atoi(strings.TrimSpace(s))
	if err != nil {
		return def
	}
	return n
}

// Validate checks required settings.
func (c *Config) Validate() error {
	if c.DBName == "" {
		return errors.New("DB_NAME es obligatorio")
	}
	return nil
}
