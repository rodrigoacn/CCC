package web

import (
	"fmt"
	"net/http"
	"net/url"

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
func (p *Pages) HandleMaterias(w http.ResponseWriter, r *http.Request) {
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
	page := CurrentPage(r)
	nav, redirect := p.MenuData(w, r, s, page, lang)
	if redirect {
		return
	}

	uid := UID(s)
	userRow, err := p.DB.QueryOne(ctx, "SELECT nombre, rol, ultimaMateria FROM usuarios WHERE usuarioId = ?", uid)
	if err != nil {
		serverError(w, err)
		return
	}
	first := "Usuario"
	if userRow != nil {
		n := store.Str(userRow["nombre"])
		if n != "" {
			first = n
			if i := indexByte(first, ' '); i > 0 {
				first = first[:i]
			}
		}
	}
	isTeacher := nav.IsTeacher
	ultimaMateria := int64(0)
	if userRow != nil {
		ultimaMateria = store.Int(userRow["ultimaMateria"])
	}

	subjects, err := p.DB.QueryAll(ctx,
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

	data := map[string]any{
		"Lang":      lang,
		"NavData":   nav,
		"First":     first,
		"IsTeacher": isTeacher,
		"Subjects":  items,
	}

	// Continuar card (last subject opened).
	if ultimaMateria >= 1 && ultimaMateria <= 11 {
		for _, it := range items {
			if it.ID == ultimaMateria {
				data["Continuar"] = true
				data["UltimaMateria"] = ultimaMateria
				data["ContinuarNombre"] = it.Nombre
				data["ContinuarNombreURL"] = it.NombreURL
				data["ContinuarColor"] = subjectColors[ultimaMateria]
				break
			}
		}
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
