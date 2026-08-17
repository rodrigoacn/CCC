package web

import (
	"context"
	"fmt"
	"html/template"
	"net/http"
	"regexp"
	"strconv"
	"strings"

	"classexpress/internal/store"
)

var preSalaFromRe = regexp.MustCompile(`[^a-zA-Z0-9_-]`)

// HandlePreSala ports pre_sala.php (camera check + class entry).
func (p *Pages) HandlePreSala(w http.ResponseWriter, r *http.Request) {
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

	uid := UID(s)
	claseId := store.Int(r.URL.Query().Get("clase"))
	from := preSalaFromRe.ReplaceAllString(r.URL.Query().Get("from"), "")
	if claseId <= 0 {
		redirect(w, r, "buscar.php")
		return
	}

	clase, err := p.DB.QueryOne(ctx,
		`SELECT cp.*, u.nombre AS profesor, u.usuarioId AS prof_uid,
		        m.nombre AS materia
		 FROM clases_programadas cp
		 JOIN usuarios u ON u.usuarioId = cp.instructorId
		 LEFT JOIN materias m ON m.materiaId = cp.materiaId
		 WHERE cp.claseId = ?`, claseId)
	if err != nil || clase == nil {
		redirect(w, r, "buscar.php")
		return
	}

	isTeacher := uid == store.Int(clase["instructorId"])
	creditos := fetchCreditos(p, ctx, uid)
	precio := store.Float(clase["precio_base"])

	desc := store.Str(clase["descripcion"])
	descHTML := template.HTML(strings.ReplaceAll(template.HTMLEscapeString(desc), "\n", "<br>"))

	isTeacherJS := template.JS("false")
	if isTeacher {
		isTeacherJS = template.JS("true")
	}

	data := map[string]any{
		"Lang":               lang,
		"Titulo":             store.Str(clase["titulo"]),
		"Materia":            store.Str(clase["materia"]),
		"Profesor":           store.Str(clase["profesor"]),
		"Duracion":           store.Int(clase["duracion_min"]),
		"Rating":             fmt.Sprintf("%.1f", store.Float(clase["calificacion"])),
		"Descripcion":        descHTML,
		"DescripcionPresent": desc != "",
		"Precio":             fmt.Sprintf("%.2f", precio),
		"Creditos":           fmt.Sprintf("%.0f", creditos),
		"TieneSaldo":         creditos >= precio,
		"IsTeacher":          isTeacher,
		"IsTeacherJS":        isTeacherJS,
		"FromJSON":           template.JS(strconv.Quote(from)),
		"ClaseId":            claseId,
		"From":               from,
	}
	if err := p.Templates.Render(w, "pre_sala", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func fetchCreditos(p *Pages, ctx context.Context, uid int64) float64 {
	row, err := p.DB.QueryOne(ctx, "SELECT creditos FROM usuarios WHERE usuarioId = ?", uid)
	if err != nil || row == nil {
		return 0
	}
	return store.Float(row["creditos"])
}
