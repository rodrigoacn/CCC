package web

import (
	"crypto/rand"
	"crypto/subtle"
	"encoding/hex"
	"net/http"
)

// CSRFToken returns the session CSRF token, generating one when absent
// (mirrors csrf_token() in lib/csrf.php).
func CSRFToken(s *Session) string {
	if t := s.Get("csrf_token"); t != "" {
		return t
	}
	b := make([]byte, 32)
	if _, err := rand.Read(b); err != nil {
		return ""
	}
	t := hex.EncodeToString(b)
	s.Set("csrf_token", t)
	return t
}

// CSRTField renders the hidden input used by csrf_field().
func CSRTField(s *Session) string {
	return `<input type="hidden" name="csrf_token" value="` + CSRFToken(s) + `">`
}

// CSRFValidate checks a POST request's CSRF token (mirrors csrf_validate()).
func CSRFValidate(r *http.Request, s *Session) bool {
	if r.Method != http.MethodPost {
		return true
	}
	token := r.PostFormValue("csrf_token")
	if token == "" {
		token = r.Header.Get("X-CSRF-Token")
	}
	if token == "" {
		token = r.Header.Get("X-CSRF-TOKEN")
	}
	expected := s.Get("csrf_token")
	if token == "" || expected == "" {
		return false
	}
	return subtle.ConstantTimeCompare([]byte(expected), []byte(token)) == 1
}

// CSRFRequire aborts with 419 when the token is invalid (csrf_require()).
// Callers must save the session after a failed attempt if they mutated it.
func CSRFRequire(w http.ResponseWriter, r *http.Request, s *Session) bool {
	if CSRFValidate(r, s) {
		return true
	}
	http.Error(w, "CSRF token inválido. Recarga la página e inténtalo de nuevo.", 419)
	return false
}

// SecurityHeadersMiddleware ports lib/security_headers.php.
func SecurityHeadersMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		h := w.Header()
		h.Set("X-Content-Type-Options", "nosniff")
		h.Set("X-Frame-Options", "DENY")
		h.Set("X-XSS-Protection", "1; mode=block")
		h.Set("Referrer-Policy", "strict-origin-when-cross-origin")
		h.Set("Permissions-Policy", "geolocation=(), microphone=(self), camera=(self)")
		if IsHTTPS(r) {
			h.Set("Strict-Transport-Security", "max-age=31536000; includeSubDomains; preload")
		}
		h.Set("Content-Security-Policy", "default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://code.jquery.com https://cdnjs.cloudflare.com 'unsafe-inline' 'unsafe-eval'; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self' https:; frame-src 'self'; media-src 'self' https:;")
		next.ServeHTTP(w, r)
	})
}

// IsHTTPS detects HTTPS the same way PHP does via $_SERVER['HTTPS'].
func IsHTTPS(r *http.Request) bool {
	v := r.Header.Get("X-Forwarded-Proto")
	if v == "https" {
		return true
	}
	if r.TLS != nil {
		return true
	}
	// PHP checks $_SERVER['HTTPS'] which Go exposes nowhere; fallback false.
	return false
}

// BaseURL reconstructs the scheme://host used by pages for verify links.
func BaseURL(r *http.Request) string {
	scheme := "http"
	if IsHTTPS(r) {
		scheme = "https"
	}
	host := r.Host
	if host == "" {
		host = "localhost"
	}
	return scheme + "://" + host
}
