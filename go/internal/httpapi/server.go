package httpapi

import (
	"context"
	"encoding/json"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	"classexpress/internal/api"
	"classexpress/internal/config"
	"classexpress/internal/database"
	"classexpress/internal/mail"
	"classexpress/internal/mp"
	"classexpress/internal/store"
	"classexpress/internal/web"
	roomws "classexpress/internal/ws"
	"github.com/redis/go-redis/v9"
)

// Server bundles the HTTP handlers with their dependencies.
type Server struct {
	cfg    *config.Config
	db     *database.DB
	mux    *http.ServeMux
	mobile *api.API
	pages  *web.Pages
	ws     *roomws.Hub
	webDir string
}

// New builds the router.
func New(cfg *config.Config, db *database.DB) *Server {
	s := &Server{cfg: cfg, db: db, mux: http.NewServeMux()}
	if db != nil {
		s.mobile = &api.API{
			DB:   &store.DB{Pool: db.Pool},
			Cfg:  cfg,
			Mail: mail.New(cfg),
			MP:   mp.New(cfg, &store.DB{Pool: db.Pool}),
		}
		// Web pages layer.
		redisAddr := cfg.RedisHost
		if cfg.RedisPort != 0 {
			redisAddr = cfg.RedisHost + ":" + itoa(cfg.RedisPort)
		}
		var sessionStore web.Store
		var redisClient *redis.Client
		if redisAddr != "" {
			redisClient = web.NewRedisClient(redisAddr, cfg.RedisPass, cfg.RedisDB)
			sessionStore = web.NewRedisStoreClient(redisClient)
		}
		if sessionStore == nil {
			log.Println("web: Redis no disponible, sesiones en memoria")
		}
		s.pages = &web.Pages{
			DB:        &store.DB{Pool: db.Pool},
			Cfg:       cfg,
			Sessions:  web.NewManager(sessionStore),
			Rate:      web.NewRateLimiter(redisClient, ""),
			Mail:      mail.New(cfg),
			Templates: web.NewTemplateSet(),
			MP:        mp.New(cfg, &store.DB{Pool: db.Pool}),
		}
		s.mobile.SessionAuth = s.webSessionAuth
		s.ws = roomws.NewHub(&store.DB{Pool: db.Pool})
	}
	s.routes()
	return s
}

// register binds pattern (and its /CCC/… alias) to h, so the server responds
// both when hit directly at the root and behind the nginx /CCC/ location.
// Keeping r.URL.Path untouched matters: relative redirects/forms then resolve
// against /CCC/… exactly like the PHP app does behind nginx.
func (s *Server) register(pattern string, h http.Handler) {
	s.mux.Handle(pattern, h)
	if pattern == "/" {
		return
	}
	s.mux.Handle("/CCC"+pattern, h)
}

// security wraps h with the security headers (lib/security_headers.php).
func security(h http.Handler) http.Handler {
	return web.SecurityHeadersMiddleware(h)
}

func (s *Server) routes() {
	s.mux.HandleFunc("/health", s.handleHealth)
	if s.mobile != nil {
		s.register("/api_mobile", s.mobile)
		s.register("/api_mobile.php", s.mobile)
		s.register("/api_sala", http.HandlerFunc(s.mobile.SalaServeHTTP))
		s.register("/ws/", http.HandlerFunc(s.ws.ServeHTTP))
	}
	if s.pages != nil {
		p := s.pages
		// /api_sala.php is the endpoint the sala.php page calls with a
		// PHPSESSID cookie (session + CSRF), while /api_sala uses bearer
		// tokens for mobile clients.
		s.register("/api_sala.php", p.WithSession(func(w http.ResponseWriter, r *http.Request) {
			s.mobile.SalaServeHTTP(w, r)
		}))
		s.register("/login.php", security(p.WithSession(p.HandleLogin)))
		s.register("/logout.php", p.WithSession(p.HandleLogout))
		s.register("/lang_api.php", p.WithSession(p.HandleLangAPI))
		s.register("/verify.php", p.WithSession(p.HandleVerify))
		s.register("/forgot_password.php", security(p.WithSession(p.HandleForgotPassword)))
		s.register("/reset_password.php", security(p.WithSession(p.HandleResetPassword)))
		s.register("/index.php", p.WithSession(p.HandleIndex))
		s.register("/landing.php", p.WithSession(p.HandleLanding))
		s.register("/landing_api.php", p.WithSession(p.HandleLandingAPI))
		s.register("/materias.php", p.WithSession(p.HandleMaterias))
		s.register("/menu.php", p.WithSession(p.HandleMenu))
		s.register("/contenido.php", p.WithSession(p.HandleContenido))
		s.register("/pre_sala.php", p.WithSession(p.HandlePreSala))
		s.register("/reservar.php", p.WithSession(p.HandleReservar))
		s.register("/reserva_actualizar.php", p.WithSession(p.HandleReservaActualizar))
		s.register("/notif_api.php", p.WithSession(p.HandleNotifAPI))
		s.register("/notificaciones.php", p.WithSession(p.HandleNotificaciones))
		s.register("/oferta_clase.php", p.WithSession(p.HandleOfertaClase))
		s.register("/perfil.php", p.WithSession(p.HandlePerfil))
		s.register("/perfil_usuario.php", p.WithSession(p.HandlePerfilUsuario))
		s.register("/personas.php", p.WithSession(p.HandlePersonas))
		s.register("/creditos.php", p.WithSession(p.HandleCreditos))
		s.register("/retiro.php", p.WithSession(p.HandleRetiro))
		s.register("/admin_retiros.php", p.WithSession(p.HandleAdminRetiros))
		s.register("/buscar.php", p.WithSession(p.HandleBuscar))
		s.register("/profesores.php", p.WithSession(p.HandleProfesores))
		s.register("/mp_success.php", p.WithSession(p.HandleMPSuccess))
		s.register("/mp_pending.php", p.WithSession(p.HandleMPPending))
		s.register("/mp_failure.php", p.WithSession(p.HandleMPFailure))
		s.register("/mp_webhook.php", http.HandlerFunc(p.HandleMPWebhook))
		s.register("/update_bio.php", p.WithSession(p.HandleUpdateBio))
		s.register("/update_languages.php", p.WithSession(p.HandleUpdateLanguages))
		s.register("/upload_avatar.php", p.WithSession(p.HandleUploadAvatar))
		s.register("/delete_account.php", p.WithSession(p.HandleDeleteAccount))
		s.register("/switch_role.php", security(p.WithSession(p.HandleSwitchRole)))
		s.register("/dashboard_profesor.php", p.WithSession(p.HandleDashboardProfesor))
		s.register("/crear_clase.php", p.WithSession(p.HandleCrearClase))
		s.register("/calificar.php", p.WithSession(p.HandleCalificar))
		s.register("/foro.php", p.WithSession(p.HandleForo))
		s.register("/schedule.php", p.WithSession(p.HandleSchedule))
		s.register("/mi_sala.php", p.WithSession(p.HandleMiSala))
		s.register("/sala.php", p.WithSession(p.HandleSala))
		for _, subjPage := range web.SubjectPages() {
			page := subjPage
			s.register("/"+page, p.WithSession(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				p.HandleSubjectPage(w, r, page)
			})))
		}
		s.register("/robots.txt", http.HandlerFunc(p.HandleRobots))
		s.register("/sitemap.xml", http.HandlerFunc(p.HandleSitemap))
		s.register("/", http.HandlerFunc(s.handleStaticOrRoot))
	}
}

// webSessionAuth resolves a logged-in web session for the sala API. It is
// invoked by api_sala.php (the web page endpoint) which carries a PHPSESSID
// cookie; the request must have passed through WithSession so the session is
// available in the context.
func (s *Server) webSessionAuth(r *http.Request) *api.WebSession {
	sess := web.SessionFrom(r.Context())
	if sess == nil || !web.LoggedIn(sess) {
		return nil
	}
	return &api.WebSession{
		UID:          sess.Get("usuarioId"),
		ValidateCSRF: func(r *http.Request) bool { return web.CSRFValidate(r, sess) },
	}
}

// SetWebDir sets the directory containing static web assets (styles.css, ...).
func (s *Server) SetWebDir(dir string) {
	s.webDir = dir
	if s.pages != nil {
		s.pages.WebDir = dir
	}
}

func (s *Server) handleStaticOrRoot(w http.ResponseWriter, r *http.Request) {
	path := r.URL.Path
	if strings.HasPrefix(path, "/CCC") {
		path = strings.TrimPrefix(path, "/CCC")
		if path == "" {
			path = "/"
		}
	}
	if path == "/" {
		s.pages.WithSession(s.pages.HandleLanding)(w, r)
		return
	}
	if s.webDir != "" && isStaticAsset(path) {
		clean := filepath.Clean(path)
		full := filepath.Join(s.webDir, clean)
		real, err := filepath.Abs(full)
		if err != nil {
			http.NotFound(w, r)
			return
		}
		root, err := filepath.Abs(s.webDir)
		if err != nil || !within(root, real) {
			http.NotFound(w, r)
			return
		}
		if fi, err := os.Stat(real); err == nil && !fi.IsDir() {
			http.ServeFile(w, r, real)
			return
		}
	}
	http.NotFound(w, r)
}

func isStaticAsset(path string) bool {
	switch filepath.Ext(path) {
	case ".css", ".js", ".svg", ".png", ".ico", ".webmanifest", ".json", ".txt", ".xml",
		".jpg", ".jpeg", ".gif", ".webp", ".woff", ".woff2", ".ttf", ".eot", ".otf",
		".mp4", ".webm", ".ogv", ".mov", ".mp3", ".ogg", ".oga", ".wav", ".m4a", ".aac":
		return true
	}
	name := filepath.Base(path)
	return name == "manifest.json" || name == "apple-touch-icon.png" || name == "favico.svg"
}

func within(root, target string) bool {
	rel, err := filepath.Rel(root, target)
	if err != nil {
		return false
	}
	if rel == "." {
		return true
	}
	if filepath.IsAbs(rel) || rel == ".." {
		return false
	}
	return !strings.HasPrefix(rel, ".."+string(filepath.Separator))
}

func itoa(n int) string {
	if n == 0 {
		return "0"
	}
	neg := n < 0
	if neg {
		n = -n
	}
	var b [20]byte
	i := len(b)
	for n > 0 {
		i--
		b[i] = byte('0' + n%10)
		n /= 10
	}
	if neg {
		i--
		b[i] = '-'
	}
	return string(b[i:])
}

// Serve starts the HTTP listener.
func (s *Server) Serve(ctx context.Context, addr string) error {
	srv := &http.Server{
		Addr:         addr,
		Handler:      s.withMiddleware(s.mux),
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 60 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	errCh := make(chan error, 1)
	go func() {
		log.Printf("servidor escuchando en %s", addr)
		errCh <- srv.ListenAndServe()
	}()

	select {
	case err := <-errCh:
		return err
	case <-ctx.Done():
		shCtx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer cancel()
		return srv.Shutdown(shCtx)
	}
}

func (s *Server) withMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		log.Printf("%s %s %s", r.Method, r.URL.Path, time.Since(start))
	})
}

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodGet {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]string{"error": "method not allowed"})
		return
	}
	dbStatus := "up"
	if s.db != nil {
		if err := s.db.Ping(r.Context()); err != nil {
			dbStatus = "down"
		}
	} else {
		dbStatus = "unavailable"
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"status":  "ok",
		"db":      dbStatus,
		"version": "go-skeleton",
		"time":    time.Now().Format(time.RFC3339),
	})
}
