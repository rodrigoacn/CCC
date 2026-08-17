package web

import (
	"context"
	"crypto/rand"
	"encoding/hex"
	"html/template"
	"net"
	"net/http"
	"strconv"
	"strings"
	"time"

	"classexpress/internal/config"
	"classexpress/internal/i18n"
	"classexpress/internal/mail"
	"classexpress/internal/mp"
	"classexpress/internal/store"
)

// Pages bundles the dependencies shared by all web page handlers.
type Pages struct {
	DB        *store.DB
	Cfg       *config.Config
	Sessions  *Manager
	Rate      *RateLimiter
	Mail      *mail.Sender
	Templates *TemplateSet
	WebDir    string
	MP        *mp.Gateway
}

// ResolveLang mirrors i18n.DetectLang precedence: session -> cookie -> browser.
func (p *Pages) ResolveLang(s *Session, r *http.Request) string {
	cookie := ""
	if c, err := r.Cookie("ce_lang"); err == nil {
		cookie = c.Value
	}
	return i18n.DetectLang(s.Get("_lang"), cookie, r.Header.Get("Accept-Language"))
}

// ClientIP mirrors $_SERVER['REMOTE_ADDR'].
func ClientIP(r *http.Request) string {
	if fwd := r.Header.Get("X-Forwarded-For"); fwd != "" {
		if i := strings.Index(fwd, ","); i != -1 {
			return strings.TrimSpace(fwd[:i])
		}
		return strings.TrimSpace(fwd)
	}
	ip := r.RemoteAddr
	host, _, err := net.SplitHostPort(ip)
	if err == nil {
		return host
	}
	if i := strings.LastIndex(ip, ":"); i != -1 && strings.Count(ip, ":") == 1 {
		return ip[:i]
	}
	return strings.Trim(ip, "[]")
}

// UID mirrors ce_uid().
func UID(s *Session) int64 {
	return store.Int(s.Get("usuarioId"))
}

// RememberAutoLogin mirrors ce_remember_autologin(): it rotates the remember-me
// token on each hit and restores the session. Callers must Save the session.
func (p *Pages) RememberAutoLogin(ctx context.Context, w http.ResponseWriter, s *Session, r *http.Request) {
	if s.Get("usuarioId") != "" {
		return
	}
	c, err := r.Cookie("ce_remember")
	if err != nil || c.Value == "" {
		return
	}
	row, err := p.DB.QueryOne(ctx,
		"SELECT usuarioId, nombre, rol, creditos, idioma_preferido FROM usuarios WHERE remember_token = ? AND remember_token IS NOT NULL AND eliminado = 0 LIMIT 1",
		HashToken(c.Value))
	if err != nil || row == nil {
		return
	}
	newTok := NewRememberToken()
	_, _ = p.DB.Exec(ctx,
		"UPDATE usuarios SET remember_token = ? WHERE usuarioId = ?",
		HashToken(newTok), store.Str(row["usuarioId"]))
	s.Set("usuarioId", store.Str(row["usuarioId"]))
	s.Set("nombre", store.Str(row["nombre"]))
	s.Set("rol", store.Str(row["rol"]))
	s.Set("creditos", store.Str(row["creditos"]))
	if lang := store.Str(row["idioma_preferido"]); lang != "" {
		s.Set("_lang", lang)
		http.SetCookie(w, &http.Cookie{Name: "ce_lang", Value: lang, Path: "/", MaxAge: 30 * 86400})
	}
	http.SetCookie(w, &http.Cookie{
		Name: "ce_remember", Value: newTok, Path: "/", MaxAge: 30 * 24 * 60 * 60,
		HttpOnly: true, Secure: IsHTTPS(r),
	})
}

// LoggedIn reports whether the session has a user.
func LoggedIn(s *Session) bool {
	return s.Get("usuarioId") != ""
}

// TimeAgo mirrors timeAgo() in web_bootstrap.php.
func TimeAgo(value any) string {
	var ts int64
	switch t := value.(type) {
	case int64:
		ts = t
	case string:
		if n, err := strconv.ParseInt(t, 10, 64); err == nil {
			ts = n
		} else if tm, err := time.Parse("2006-01-02 15:04:05", t); err == nil {
			ts = tm.Unix()
		}
	case time.Time:
		ts = t.Unix()
	}
	diff := time.Now().Unix() - ts
	if diff < 0 {
		diff = 0
	}
	sec := int64(diff)
	switch {
	case sec < 60:
		return "ahora"
	case sec < 3600:
		return strconv.FormatInt(sec/60, 10) + " min"
	case sec < 86400:
		return strconv.FormatInt(sec/3600, 10) + " h"
	case sec < 604800:
		return strconv.FormatInt(sec/86400, 10) + " d"
	case sec < 2592000:
		return strconv.FormatInt(sec/604800, 10) + " sem"
	case sec < 31536000:
		return strconv.FormatInt(sec/2592000, 10) + " mes"
	default:
		return strconv.FormatInt(sec/31536000, 10) + " a"
	}
}

// PendingPaymentSession returns the oldest unpaid finished session id or 0.
func (p *Pages) PendingPaymentSession(ctx context.Context, uid int64) int64 {
	row, err := p.DB.QueryOne(ctx,
		"SELECT sesionId FROM sesiones_clase WHERE estudianteId = ? AND pagado = 0 AND fin IS NOT NULL ORDER BY fin ASC LIMIT 1",
		uid)
	if err != nil || row == nil {
		return 0
	}
	return store.Int(row["sesionId"])
}

// NewRememberToken returns a 64-char hex token (bin2hex(random_bytes(32))).
func NewRememberToken() string {
	b := make([]byte, 32)
	if _, err := rand.Read(b); err != nil {
		return hex.EncodeToString([]byte(time.Now().String()))
	}
	return hex.EncodeToString(b)
}

// Funcs returns the template function map shared by all page templates.
func (p *Pages) Funcs(s *Session, lang string) template.FuncMap {
	return template.FuncMap{
		"t": func(key string, params ...map[string]string) string {
			var m map[string]string
			if len(params) > 0 {
				m = params[0]
			}
			return i18n.T(lang, key, m)
		},
		"dict": func(pairs ...any) map[string]string {
			m := make(map[string]string, len(pairs)/2)
			for i := 0; i+1 < len(pairs); i += 2 {
				m[store.Str(pairs[i])] = store.Str(pairs[i+1])
			}
			return m
		},
		"lang":             func() string { return lang },
		"langSelector":     func() template.HTML { return i18n.RenderLangSelector(lang) },
		"csrf":             func() template.HTML { return template.HTML(CSRTField(s)) },
		"csrfToken":        func() string { return CSRFToken(s) },
		"timeAgo":          func(v any) string { return TimeAgo(v) },
		"ce_uid":           func() int64 { return UID(s) },
		"sessionVal":       func(k string) string { return s.Get(k) },
		"attrs":             func(v any) string { return store.Str(v) },
		"htmlsafe":          func(v any) template.HTML { return template.HTML(store.Str(v)) },
		"translationsJSON":  func() template.JS { return template.JS(i18n.QuoteJSON()) },
		"contains":          func(list []string, v any) bool { for _, x := range list { if x == store.Str(v) { return true } }; return false },
		"lower":             func(v any) string { return strings.ToLower(store.Str(v)) },
		"add":               func(a, b int64) int64 { return a + b },
		"sub":               func(a, b int64) int64 { return a - b },
		"mul":               func(a, b int64) int64 { return a * b },
		"div":               func(a, b int64) int64 { if b == 0 { return 0 }; return a / b },
		"indexMaybe":        func(m map[string]any, k string) any { return m[k] },
	}
}
