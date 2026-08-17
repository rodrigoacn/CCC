// Package web implements the server-side web layer ported from the PHP pages:
// sessions (Redis with graceful fallback, like RedisSession.php), remember-me
// auto-login, CSRF, security headers and shared page helpers.
package web

import (
	"context"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"log"
	"net/http"
	"sync"
	"time"
)

// CookieName matches PHP's default session cookie name so a rolling
// migration can share sessions while both stacks are live.
const CookieName = "PHPSESSID"

// SessionTTL mirrors the RedisSession.php TTL (86400s = 1 day).
const SessionTTL = 86400 * time.Second

type sessionCtxKey struct{}

// withSessionCtx stores the session in the request context.
func withSessionCtx(ctx context.Context, s *Session) context.Context {
	return context.WithValue(ctx, sessionCtxKey{}, s)
}

// SessionFrom retrieves the session previously stored by withSession.
// Returns nil when absent (e.g. handlers invoked without the wrapper).
func SessionFrom(ctx context.Context) *Session {
	if s, ok := ctx.Value(sessionCtxKey{}).(*Session); ok {
		return s
	}
	return nil
}

// Session is the JSON-serialized session data (all values as strings, like PHP).
type Session struct {
	ID      string            `json:"id"`
	Values  map[string]string `json:"values"`
	IsNew   bool              `json:"-"`
	Destroy bool              `json:"-"`
}

// Get returns a session value ('' when missing).
func (s *Session) Get(key string) string {
	if s == nil || s.Values == nil {
		return ""
	}
	return s.Values[key]
}

// Set stores a session value.
func (s *Session) Set(key, value string) {
	if s.Values == nil {
		s.Values = make(map[string]string)
	}
	s.Values[key] = value
}

// Del removes a session value.
func (s *Session) Del(key string) {
	if s.Values == nil {
		return
	}
	delete(s.Values, key)
}

// Store persists raw session JSON by id.
type Store interface {
	Get(ctx context.Context, id string) ([]byte, error)
	Set(ctx context.Context, id string, data []byte, ttl time.Duration) error
	Del(ctx context.Context, id string) error
}

// MemoryStore is an in-process fallback used when Redis is unavailable,
// mirroring PHP's graceful fallback when getRedis() returns null.
type MemoryStore struct {
	mu sync.Mutex
	m  map[string]memEntry
}

type memEntry struct {
	data []byte
	exp  time.Time
}

// NewMemoryStore builds an empty in-memory session store.
func NewMemoryStore() *MemoryStore {
	return &MemoryStore{m: make(map[string]memEntry)}
}

func (m *MemoryStore) Get(_ context.Context, id string) ([]byte, error) {
	m.mu.Lock()
	defer m.mu.Unlock()
	e, ok := m.m[id]
	if !ok {
		return nil, nil
	}
	if time.Now().After(e.exp) {
		delete(m.m, id)
		return nil, nil
	}
	return e.data, nil
}

func (m *MemoryStore) Set(_ context.Context, id string, data []byte, ttl time.Duration) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	m.m[id] = memEntry{data: data, exp: time.Now().Add(ttl)}
	return nil
}

func (m *MemoryStore) Del(_ context.Context, id string) error {
	m.mu.Lock()
	defer m.mu.Unlock()
	delete(m.m, id)
	return nil
}

// Manager coordinates sessions between the cookie and the store.
type Manager struct {
	store      Store
	secure     func(*http.Request) bool
	forceSecure bool
}

// NewManager creates a session manager. A nil store enables the in-memory
// fallback automatically.
func NewManager(store Store) *Manager {
	if store == nil {
		store = NewMemoryStore()
	}
	return &Manager{store: store}
}

// SetSecure forces the secure flag on the session cookie.
func (m *Manager) SetSecure(v bool) { m.forceSecure = v }

func (m *Manager) isSecure(r *http.Request) bool {
	if m.forceSecure {
		return true
	}
	if r.TLS != nil {
		return true
	}
	return r.Header.Get("X-Forwarded-Proto") == "https"
}

func newSessionID() string {
	b := make([]byte, 32)
	if _, err := rand.Read(b); err != nil {
		return hex.EncodeToString([]byte(time.Now().Format("20060102150405.000000000")))
	}
	return hex.EncodeToString(b)
}

// Get loads the session for the request, creating a new one when absent.
func (m *Manager) Get(w http.ResponseWriter, r *http.Request) *Session {
	c, err := r.Cookie(CookieName)
	var id string
	if err == nil && c.Value != "" {
		id = c.Value
	}
	if id == "" {
		return &Session{ID: newSessionID(), Values: map[string]string{}, IsNew: true}
	}
	raw, err := m.store.Get(r.Context(), "session:"+id)
	if err != nil {
		log.Printf("session store get %s: %v", id, err)
		return &Session{ID: newSessionID(), Values: map[string]string{}, IsNew: true}
	}
	if len(raw) == 0 {
		return &Session{ID: id, Values: map[string]string{}, IsNew: true}
	}
	s := &Session{ID: id}
	if err := json.Unmarshal(raw, s); err != nil {
		return &Session{ID: id, Values: map[string]string{}, IsNew: true}
	}
	if s.Values == nil {
		s.Values = map[string]string{}
	}
	return s
}

// Save persists the session to the store and refreshes the cookie.
func (m *Manager) Save(w http.ResponseWriter, r *http.Request, s *Session) {
	raw, err := json.Marshal(s)
	if err != nil {
		return
	}
	if err := m.store.Set(r.Context(), "session:"+s.ID, raw, SessionTTL); err != nil {
		log.Printf("session store set %s: %v", s.ID, err)
	}
	http.SetCookie(w, &http.Cookie{
		Name:     CookieName,
		Value:    s.ID,
		Path:     "/",
		HttpOnly: true,
		Secure:   m.isSecure(r),
		SameSite: http.SameSiteLaxMode,
		MaxAge:   int(SessionTTL.Seconds()),
	})
}

// Destroy removes the session from the store and clears the cookie.
func (m *Manager) Destroy(w http.ResponseWriter, r *http.Request, s *Session) {
	if s != nil && s.ID != "" {
		s.Destroy = true
		if err := m.store.Del(r.Context(), "session:"+s.ID); err != nil {
			log.Printf("session store del %s: %v", s.ID, err)
		}
	}
	http.SetCookie(w, &http.Cookie{
		Name:     CookieName,
		Value:    "",
		Path:     "/",
		HttpOnly: true,
		Secure:   m.isSecure(r),
		SameSite: http.SameSiteLaxMode,
		MaxAge:   -1,
	})
}

// Regenerate issues a new session id (PHP session_regenerate_id(true)).
func (m *Manager) Regenerate(w http.ResponseWriter, r *http.Request, s *Session) {
	if s != nil && s.ID != "" {
		if err := m.store.Del(r.Context(), "session:"+s.ID); err != nil {
			log.Printf("session store del %s: %v", s.ID, err)
		}
	}
	s.ID = newSessionID()
}

// HashToken mirrors PHP hash('sha256', $token) used for remember-me tokens.
func HashToken(token string) string {
	h := sha256.Sum256([]byte(token))
	return hex.EncodeToString(h[:])
}
