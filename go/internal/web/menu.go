package web

import (
	"html/template"
	"net/http"
	"path/filepath"
	"strings"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

// NavTab is one item of the bottom navigation bar.
type NavTab struct {
	File   string
	Icon   string
	Label  string
	Active bool
}

// NavData carries everything the base layout needs, ported from menu.php.
type NavData struct {
	Page                string
	MateriaPagina       int
	UltimoContenido     string
	UltimaClase         string
	UltimaSala          string
	EsVisibleContenidos string
	EsVisibleClases     string
	EsVisibleSala       string
	NavNombre           string
	NavCreditos         string
	NavRol              string
	IsTeacher           bool
	AdsFreeActive       bool
	PropellerZoneID     string
	NavTabs             []NavTab
	Translations        template.JS
}

var pageMateria = map[string]int{
	"matematicas.php":      1,
	"biologia.php":         2,
	"quimica.php":          3,
	"fisica.php":           4,
	"historia.php":         5,
	"geografia.php":        6,
	"literatura.php":       7,
	"idiomas.php":          8,
	"arte.php":             9,
	"tecnologia.php":       10,
	"educacion_fisica.php": 11,
}

// GuardPage enforces the menu.php auth guard: redirects to login when not
// authenticated. Returns false when the handler must stop.
func (p *Pages) GuardPage(w http.ResponseWriter, r *http.Request, s *Session) bool {
	if !LoggedIn(s) {
		redirect(w, r, "login.php")
		return false
	}
	return true
}

// MenuData ports menu.php: pending-payment redirect, subject color resolution,
// last-visited items, navbar variables and tab list. The redirect flag tells
// the caller to stop (a Location header was already written).
func (p *Pages) MenuData(w http.ResponseWriter, r *http.Request, s *Session, currentPage string, lang string) (NavData, bool) {
	ctx := r.Context()
	uid := UID(s)
	nav := NavData{Page: currentPage, NavCreditos: s.Get("creditos"), PropellerZoneID: p.Cfg.PropellerZoneID}

	// A global "sin anuncios" purchase hides ads on every page.
	if row, err := p.DB.QueryOne(ctx,
		"SELECT id FROM ads_free_compras WHERE estado='activo' AND valido_hasta > NOW() ORDER BY valido_hasta DESC LIMIT 1"); err == nil && row != nil {
		nav.AdsFreeActive = true
	}

	// Subject color resolution.
	mp := pageMateria[currentPage]
	if mp == 0 && r.URL.Query().Get("materia") != "" {
		if m := int(store.Int(r.URL.Query().Get("materia"))); m >= 1 && m <= 11 {
			mp = m
		}
	}
	if mp == 0 {
		if row, err := p.DB.QueryOne(ctx, "SELECT ultimaMateria FROM usuarios WHERE usuarioId = ?", uid); err == nil && row != nil {
			if m := int(store.Int(row["ultimaMateria"])); m >= 1 && m <= 11 {
				mp = m
			}
		}
	}
	if mp > 0 {
		_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET ultimaMateria = ? WHERE usuarioId = ?", mp, uid)
	}
	nav.MateriaPagina = mp

	// Last-visited items + fresh credits.
	if s.Get("ultimoContenido") != "" || s.Get("ultimaClase") != "" || s.Get("ultimaSala") != "" {
		nav.UltimoContenido = s.Get("ultimoContenido")
		nav.UltimaClase = s.Get("ultimaClase")
		nav.UltimaSala = s.Get("ultimaSala")
	} else if row, err := p.DB.QueryOne(ctx,
		"SELECT ultimoContenido, ultimaClase, ultimaSala, creditos FROM usuarios WHERE usuarioId = ?", uid); err == nil && row != nil {
		nav.UltimoContenido = store.Str(row["ultimoContenido"])
		nav.UltimaClase = store.Str(row["ultimaClase"])
		nav.UltimaSala = store.Str(row["ultimaSala"])
		s.Set("ultimoContenido", nav.UltimoContenido)
		s.Set("ultimaClase", nav.UltimaClase)
		s.Set("ultimaSala", nav.UltimaSala)
		if cr := store.Str(row["creditos"]); cr != "" {
			s.Set("creditos", cr)
			nav.NavCreditos = cr
		}
	}
	nav.EsVisibleContenidos = boolClass(nav.UltimoContenido != "")
	nav.EsVisibleClases = boolClass(nav.UltimaClase != "")
	nav.EsVisibleSala = boolClass(nav.UltimaSala != "")

	// Navbar variables.
	name := strings.TrimSpace(s.Get("nombre"))
	if name == "" {
		name = "Usuario"
	}
	first := name
	if i := strings.Index(name, " "); i > 0 {
		first = name[:i]
	}
	nav.NavNombre = template.HTMLEscapeString(first)
	dbRol := s.Get("rol")
	if dbRol == "" {
		dbRol = "estudiante"
	}
	navRol := dbRol
	if c, err := r.Cookie("ce_app_modo"); err == nil {
		switch c.Value {
		case "teacher":
			if dbRol == "instructor" || dbRol == "both" {
				navRol = "instructor"
			}
		case "student":
			navRol = "estudiante"
		}
	}
	nav.NavRol = navRol
	nav.IsTeacher = navRol != "estudiante" && navRol != "student"

	// Active tab: subject pages map to materias.php.
	page := currentPage
	for k := range pageMateria {
		if page == k {
			page = "materias.php"
			break
		}
	}
	if page == "contenido.php" {
		page = "materias.php"
	}
	tabs := []struct {
		file, icon, key string
	}{
		{"materias.php", "home", "nav.materias"},
		{"buscar.php", "search", "nav.buscar"},
		{"foro.php", "message-circle", "nav.foro"},
		{"mi_sala.php", "camera", "nav.sala"},
		{"personas.php", "users", "nav.personas"},
		{"creditos.php", "credit-card", "nav.creditos"},
		{"retiro.php", "dollar-sign", "retiro.withdraw"},
		{"perfil.php", "user", "nav.perfil"},
	}
	for _, tb := range tabs {
		if tb.file == "buscar.php" && nav.IsTeacher {
			continue
		}
		if tb.file == "retiro.php" && !nav.IsTeacher {
			continue
		}
		if tb.file == "creditos.php" && nav.IsTeacher {
			continue
		}
		nav.NavTabs = append(nav.NavTabs, NavTab{
			File:   tb.file,
			Icon:   tb.icon,
			Label:  i18n.T(lang, tb.key, nil),
			Active: page == tb.file,
		})
	}
	nav.Translations = template.JS(i18n.QuoteJSON())
	return nav, false
}

func boolClass(b bool) string {
	if b {
		return "visible"
	}
	return "hidden"
}

// CurrentPage returns the page basename from the request path.
func CurrentPage(r *http.Request) string {
	return filepath.Base(r.URL.Path)
}

// Flash reads and clears a one-shot session message (PHP unset pattern).
func Flash(s *Session, key string) string {
	v := s.Get(key)
	s.Del(key)
	return v
}

// ApplyLangParam ports lang.php's ?lang= handling: persists the selection and
// redirects to the same URL without the param. Returns true when the handler
// must stop (a Location header was already written).
func (p *Pages) ApplyLangParam(w http.ResponseWriter, r *http.Request, s *Session) bool {
	code := r.URL.Query().Get("lang")
	if code == "" {
		return false
	}
	if !i18n.IsSupported(code) {
		return false
	}
	s.Set("_lang", code)
	if uid := UID(s); uid > 0 {
		_, _ = p.DB.Exec(r.Context(), "UPDATE usuarios SET idioma_preferido = ? WHERE usuarioId = ?", code, uid)
	}
	http.SetCookie(w, &http.Cookie{Name: "ce_lang", Value: code, Path: "/", MaxAge: 30 * 86400, HttpOnly: false, Secure: IsHTTPS(r)})
	u := *r.URL
	q := u.Query()
	q.Del("lang")
	u.RawQuery = q.Encode()
	redirect(w, r, u.RequestURI())
	return true
}

// HandleMenu ports menu.php: renders the authenticated navbar page (used by the
// functional suite and by direct access to the menu URL).
func (p *Pages) HandleMenu(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if !p.GuardPage(w, r, s) {
		return
	}
	lang := p.ResolveLang(s, r)
	nav, stop := p.MenuData(w, r, s, CurrentPage(r), lang)
	if stop {
		return
	}
	data := map[string]any{
		"Lang":    lang,
		"NavData": nav,
	}
	if err := p.Templates.RenderAuthed(w, "menu", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

// RequireCSRFOnPost validates CSRF on POST requests and returns false when it
// should abort. Mirrors csrf_require().
func (p *Pages) RequireCSRFOnPost(w http.ResponseWriter, r *http.Request, s *Session) bool {
	if r.Method != http.MethodPost {
		return true
	}
	return CSRFRequire(w, r, s)
}
