package web

import (
	"fmt"
	"net/http"

	"classexpress/internal/store"
)

type classItem struct {
	ID       int64
	Titulo   string
	Precio   int64
	Profesor string
	Rating   string
	SalaAct  bool
	Desc     string
	DescTrunc string
	DescMore bool
}

// HandleContenido ports contenido.php (subject class listing).
func (p *Pages) HandleContenido(w http.ResponseWriter, r *http.Request) {
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
	nav, stop := p.MenuData(w, r, s, page, lang)
	if stop {
		return
	}

	materiaId := store.Int(r.URL.Query().Get("materia"))
	nombre := r.URL.Query().Get("nombre")
	if materiaId <= 0 {
		redirect(w, r, "materias.php")
		return
	}

	rows, err := p.DB.QueryAll(ctx,
		`SELECT cp.claseId AS id, cp.titulo, cp.precio_base AS precio, cp.descripcion,
		        u.nombre AS profesor, u.calificacion AS rating, m.nombre AS materia,
		        (SELECT s.activa FROM salas s WHERE s.claseId = cp.claseId AND s.activa = true LIMIT 1) AS sala_activa
		 FROM clases_programadas cp
		 JOIN usuarios u ON u.usuarioId = cp.instructorId
		 JOIN materias m ON m.materiaId = cp.materiaId
		 WHERE cp.materiaId = ? AND cp.activa = true
		 ORDER BY cp.created_at DESC`, materiaId)
	if err != nil {
		serverError(w, err)
		return
	}

	var classes []classItem
	for _, row := range rows {
		desc := store.Str(row["descripcion"])
		descTrunc := desc
		more := false
		if len([]rune(desc)) > 120 {
			descTrunc = truncUTF8(desc, 120)
			more = true
		}
		classes = append(classes, classItem{
			ID:        store.Int(row["id"]),
			Titulo:    store.Str(row["titulo"]),
			Precio:    store.Int(row["precio"]),
			Profesor:  store.Str(row["profesor"]),
			Rating:    rating1(store.Float(row["rating"])),
			SalaAct:   store.Int(row["sala_activa"]) > 0,
			Desc:      desc,
			DescTrunc: descTrunc,
			DescMore:  more,
		})
	}

	data := map[string]any{
		"Lang":     lang,
		"NavData":  nav,
		"Nombre":   nombre,
		"Classes":  classes,
		"Count":    len(classes),
		"PluralS":  pluralS(len(classes)),
		"PluralS2": pluralS2(len(classes)),
	}
	if err := p.Templates.RenderAuthed(w, "contenido", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func rating1(f float64) string {
	return fmt.Sprintf("%.1f", f)
}

func pluralS(n int) string {
	if n == 1 {
		return ""
	}
	return "s"
}

func pluralS2(n int) string {
	if n == 1 {
		return ""
	}
	return "s"
}
