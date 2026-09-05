package web

import (
	"net/http"

	"classexpress/internal/store"
)

// HandleMiSala ports mi_sala.php (the user's active room card).
func (p *Pages) HandleMiSala(w http.ResponseWriter, r *http.Request) {
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

	// Role is cookie-aware (ce_app_modo), like $_navRol in mi_sala.php.
	isTeacher := nav.NavRol != "estudiante" && nav.NavRol != "student"

	var room map[string]any
	if isTeacher {
		room, _ = p.DB.QueryOne(ctx,
			`SELECT s.salaId AS id, s.claseId AS claseId, cp.titulo AS clase, cp.precio_base AS precio
			 FROM salas s JOIN clases_programadas cp ON cp.claseId = s.claseId
			 WHERE cp.instructorId = ? AND s.activa = true LIMIT 1`, uid)
	} else {
		room, _ = p.DB.QueryOne(ctx,
			`SELECT s.salaId AS id, s.claseId AS claseId, cp.titulo AS clase, cp.precio_base AS precio
			 FROM participantes_sala ps JOIN salas s ON s.salaId = ps.salaId
			 JOIN clases_programadas cp ON cp.claseId = s.claseId
			 WHERE ps.usuarioId = ? AND s.activa = true LIMIT 1`, uid)
	}

	var reservas []reservaItem
	if !isTeacher {
		reservas = p.loadReservasEstudiante(ctx, uid)
	}

	flashReserva := s.Get("flash_reserva")
	s.Del("flash_reserva")

	data := map[string]any{
		"Lang":         lang,
		"NavData":      nav,
		"IsTeacher":    isTeacher,
		"HasRoom":      room != nil,
		"Reservas":     reservas,
		"HasReservas":  len(reservas) > 0,
		"FlashReserva": flashReserva,
	}
	if room != nil {
		precio := store.Float(room["precio"])
		precioTxt := "Gratis"
		if precio > 0 {
			precioTxt = itoa(int(precio)) + " cr."
		}
		data["ClaseTitulo"] = store.Str(room["clase"])
		data["PrecioTxt"] = precioTxt
		data["ClaseId"] = store.Int(room["claseId"])
	}
	if err := p.Templates.RenderAuthed(w, "mi_sala", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}
