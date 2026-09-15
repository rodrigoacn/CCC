package web

import (
	"net/http"
	"strings"

	"classexpress/internal/store"
)

// HandleCalificar ports calificar.php (rate a finished session).
func (p *Pages) HandleCalificar(w http.ResponseWriter, r *http.Request) {
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
	uid := UID(s)

	sesionId := store.Int(r.URL.Query().Get("sesion"))

	if r.Method == http.MethodPost {
		if !CSRFRequire(w, r, s) {
			return
		}
		rating := store.Int(r.PostFormValue("rating"))
		comentario := strings.TrimSpace(r.PostFormValue("comentario"))

		sesion, err := p.DB.QueryOne(ctx,
			`SELECT s.sesionId, cp.instructorId
			 FROM sesiones_clase s
			 JOIN clases_programadas cp ON cp.claseId = s.claseId
			 WHERE s.sesionId = ? AND s.estudianteId = ?`, sesionId, uid)
		if rating >= 1 && rating <= 5 && err == nil && sesion != nil {
			profId := store.Int(sesion["instructorId"])

			existing, err := p.DB.QueryOne(ctx,
				"SELECT resenaId FROM resenas WHERE sesionId = ? AND estudianteId = ?", sesionId, uid)
			if err == nil && existing != nil {
				_, _ = p.DB.Exec(ctx,
					"UPDATE resenas SET rating = ?, comentario = ? WHERE sesionId = ? AND estudianteId = ?",
					rating, nullableStr(comentario), sesionId, uid)
			} else {
				_, _ = p.DB.Exec(ctx,
					`INSERT INTO resenas (sesionId, estudianteId, profesorId, rating, comentario)
					 VALUES (?, ?, ?, ?, ?)`,
					sesionId, uid, profId, rating, nullableStr(comentario))
			}

			// Recalcular el promedio desde las reseñas reales del profesor:
			// idempotente aunque se vuelva a calificar la misma sesión.
			if aggr, err := p.DB.QueryOne(ctx,
				"SELECT COUNT(rating) AS cnt, COALESCE(AVG(rating), 0) AS avg FROM resenas WHERE profesorId = ?",
				profId); err == nil && aggr != nil {
				newCount := int(store.Int(aggr["cnt"]))
				newAvg := store.Float(aggr["avg"])
				if newCount == 0 {
					newAvg = 0
				}
				_, _ = p.DB.Exec(ctx,
					"UPDATE usuarios SET calificacion = ?, num_resenas = ? WHERE usuarioId = ?",
					round2(newAvg), newCount, profId)
			}
		}
		redirect(w, r, "materias.php")
		return
	}

	data := map[string]any{
		"Lang":    lang,
		"NavData": nav,
		"Sesion":  sesionId,
	}
	if err := p.Templates.RenderAuthed(w, "calificar", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}
