package web

import (
	"fmt"
	"net/http"
	"net/url"
	"strings"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

var subjectColors = map[int64]string{
	1: "#2563EB", 2: "#059669", 3: "#7C3AED", 4: "#0284C7", 5: "#D97706",
	6: "#0D9488", 7: "#DC2626", 8: "#DB2777", 9: "#EA580C", 10: "#0891B2", 11: "#E11D48",
}

var subjectIcons = map[int64]string{
	1: "hash", 2: "activity", 3: "zap", 4: "cpu", 5: "book-open",
	6: "map", 7: "feather", 8: "globe", 9: "pen-tool", 10: "monitor", 11: "heart",
}

type subjectItem struct {
	ID       int64
	Nombre   string
	NombreURL string
	Color    string
	Icon     string
	Activas  int64
}

// HandleMaterias ports materias.php.
// Supports both logged-in users and guests (via remember_token or role param).
func (p *Pages) HandleMaterias(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	lang := p.ResolveLang(nil, r) // Allow lang without session

	// Check for role parameter (from landing page redirect)
	roleParam := r.URL.Query().Get("rol")
	if roleParam != "" && (roleParam == "student" || roleParam == "instructor") {
		http.SetCookie(w, &http.Cookie{
			Name:     "ce_role",
			Value:    roleParam,
			Path:     "/",
			MaxAge:   365 * 24 * 60 * 60,
			SameSite: http.SameSiteLaxMode,
		})
	}

	// Try to get session (may be nil for new guests)
	if s == nil {
		// Try to auto-login via remember_token
		if cookie, err := r.Cookie("ce_remember"); err == nil && cookie.Value != "" {
			ctx := r.Context()
			row, err := p.DB.QueryOne(ctx,
				"SELECT usuarioId, nombre, rol, creditos, idioma_preferido FROM usuarios WHERE remember_token = ? AND remember_token IS NOT NULL AND eliminado = 0 LIMIT 1",
				HashToken(cookie.Value))
			if err == nil && row != nil {
				s = &Session{ID: newSessionID(), Values: map[string]string{}, IsNew: true}
				s.Set("usuarioId", store.Str(row["usuarioId"]))
				s.Set("nombre", store.Str(row["nombre"]))
				s.Set("rol", store.Str(row["rol"]))
				s.Set("creditos", store.Str(row["creditos"]))
				if lang := store.Str(row["idioma_preferido"]); lang != "" {
					s.Set("_lang", lang)
				}
				newTok := NewRememberToken()
				_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET remember_token = ? WHERE usuarioId = ?", HashToken(newTok), store.Str(row["usuarioId"]))
				http.SetCookie(w, &http.Cookie{Name: "ce_remember", Value: newTok, Path: "/", MaxAge: 30 * 24 * 60 * 60, HttpOnly: true, Secure: IsHTTPS(r), SameSite: http.SameSiteLaxMode})
				http.SetCookie(w, &http.Cookie{Name: CookieName, Value: s.ID, Path: "/", MaxAge: 30 * 24 * 60 * 60, HttpOnly: true, Secure: IsHTTPS(r), SameSite: http.SameSiteLaxMode})
			}
		}
	}

	// Get nav data (works with nil session)
	nav, redirect := p.MenuData(w, r, nil, "materias.php", lang)
	if redirect {
		return
	}
	nav.AAAdUnitID = p.Cfg.AAAdUnitID

	// Determine user state
	first := "Usuario"
	ultimaMateria := int64(0)

	if s != nil && LoggedIn(s) {
		userRow, err := p.DB.QueryOne(r.Context(), "SELECT nombre, rol, ultimaMateria FROM usuarios WHERE usuarioId = ?", UID(s))
		if err == nil && userRow != nil {
			n := store.Str(userRow["nombre"])
			if n != "" {
				first = n
				if i := strings.IndexByte(first, ' '); i > 0 {
					first = first[:i]
				}
			}
			if store.Str(userRow["rol"]) == "instructor" {
				nav.IsTeacher = true
			}
			ultimaMateria = store.Int(userRow["ultimaMateria"])
		}
	} else {
		if roleCookie, _ := r.Cookie("ce_role"); roleCookie != nil && roleCookie.Value == "instructor" {
			nav.IsTeacher = true
		}
	}

	// Load subjects
	subjects, err := p.DB.QueryAll(r.Context(),
		`SELECT m.materiaId AS id, m.nombre,
		        (SELECT COUNT(*) FROM clases_programadas cp WHERE cp.materiaId = m.materiaId AND cp.activa = true) AS clases_activas
		 FROM materias m ORDER BY m.nombre`)
	if err != nil || len(subjects) == 0 {
		names := []string{"Mathematics", "Biology", "Chemistry", "Physics", "History", "Geography", "Literature", "Foreign Languages", "Art and Music", "Technology", "Physical Education"}
		subjects = []map[string]any{}
		for i, n := range names {
			subjects = append(subjects, map[string]any{"id": i + 1, "nombre": n, "clases_activas": 0})
		}
	}

	var items []subjectItem
	for _, s := range subjects {
		id := store.Int(s["id"])
		nombre := store.Str(s["nombre"])
		if t := i18n.T(lang, "subject.name."+fmt.Sprint(id), nil); t != "" {
			nombre = t
		}
		items = append(items, subjectItem{
			ID:        id,
			Nombre:    nombre,
			NombreURL: url.QueryEscape(nombre),
			Color:     subjectColors[id],
			Icon:      subjectIcons[id],
			Activas:   store.Int(s["clases_activas"]),
		})
	}

	// Continuar card
	if s != nil && LoggedIn(s) {
		if row, _ := p.DB.QueryOne(r.Context(), "SELECT nombre FROM usuarios WHERE usuarioId = ?", UID(s)); row != nil {
			n := store.Str(row["nombre"])
			if n != "" {
				first = n
				if i := strings.IndexByte(first, ' '); i > 0 {
					first = first[:i]
				}
			}
		}
	}

isTeacher := nav.IsTeacher

data := map[string]any{
		"Lang":          lang,
		"NavData":       nav,
		"First":         first,
		"IsTeacher":     isTeacher,
		"Subjects":      items,
		"UltimaMateria": ultimaMateria,
	}

	if err := p.Templates.RenderAuthed(w, "materias", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func indexByte(s string, b byte) int {
	for i := 0; i < len(s); i++ {
		if s[i] == b {
			return i
		}
	}
	return -1
}