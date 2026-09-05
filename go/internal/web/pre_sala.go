package web

import (
	"context"
	"fmt"
	"html/template"
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"

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
	descNuevo := store.Int(clase["descuento_nuevo"])

	esNuevo := false
	if !isTeacher && descNuevo > 0 {
		row, err := p.DB.QueryOne(ctx,
			"SELECT COUNT(*) AS cnt FROM sesiones_clase WHERE claseId = ? AND estudianteId = ? AND pagado = 1",
			claseId, uid)
		esNuevo = err == nil && row != nil && store.Int(row["cnt"]) == 0
	}

	precioDesc := precio
	if esNuevo {
		precioDesc = round2(precio * (1 - float64(descNuevo)/100))
	}

	desc := store.Str(clase["descripcion"])
	descHTML := template.HTML(strings.ReplaceAll(template.HTMLEscapeString(desc), "\n", "<br>"))

	isTeacherJS := template.JS("false")
	if isTeacher {
		isTeacherJS = template.JS("true")
	}

	enVivo := p.claseEstaEnVivo(ctx, claseId)
	dispDays := p.loadClaseDisponibilidad(ctx, claseId)
	primerBloque := int64(0)
	if len(dispDays) > 0 && len(dispDays[0].Slots) > 0 {
		primerBloque = dispDays[0].Slots[0].DispID
	}

	flashReserva := s.Get("flash_reserva")
	s.Del("flash_reserva")

	hoy := time.Now()
	maxFecha := hoy.AddDate(0, 0, 30)

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
		"DescuentoNuevo":     descNuevo,
		"EsNuevo":            esNuevo,
		"PrecioDesc":         fmt.Sprintf("%.2f", precioDesc),
		"TieneSaldoDesc":     creditos >= precioDesc,
		"IsTeacher":          isTeacher,
		"IsTeacherJS":        isTeacherJS,
		"FromJSON":           template.JS(strconv.Quote(from)),
		"ClaseId":            claseId,
		"From":               from,
		"EnVivo":             enVivo,
		"PuedeReservar":      !isTeacher && !enVivo,
		"DispDays":           dispDays,
		"HasDisp":            len(dispDays) > 0,
		"PrimerBloque":       primerBloque,
		"FlashReserva":       flashReserva,
		"Hoy":                hoy.Format("2006-01-02"),
		"MaxFecha":           maxFecha.Format("2006-01-02"),
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
