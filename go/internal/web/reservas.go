package web

import (
	"context"
	"fmt"
	"net/http"
	"strings"
	"time"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

// availSlot es un bloque de disponibilidad de una clase.
type availSlot struct {
	DispID  int64
	HoraIni string
	HoraFin string
}

// availDay agrupa los bloques de disponibilidad por día de la semana.
type availDay struct {
	Dia   string
	Slots []availSlot
}

// reservaItem es una reserva mostrada en dashboard / mi_sala.
type reservaItem struct {
	ReservaId    int64
	ClaseId      int64
	ClaseTitulo  string
	Estudiante   string
	EstudianteId int64
	Fecha        string
	HoraIni      string
	HoraFin      string
	Estado       string
	EstadoKey    string
	CreatedAt    string
	Pasada       bool
}

// notifItem es una notificación de la tabla notificaciones_web.
type notifItem struct {
	NotifId   int64
	Tipo      string
	Titulo    string
	Cuerpo    string
	Enlace    string
	Leida     bool
	CreatedAt string
}

var diaSemanaOrder = []string{"lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"}

// diaSemanaIndex devuelve el índice (lunes=0) de un día o -1.
func diaSemanaIndex(dia string) int {
	for i, d := range diaSemanaOrder {
		if d == dia {
			return i
		}
	}
	return -1
}

// diaSemanaGo convierte time.Weekday en el nombre español usado en BD.
func diaSemanaGo(w time.Weekday) string {
	// Sunday = 0 en Go; lo convertimos a domingo (índice 6).
	if w == time.Sunday {
		return "domingo"
	}
	return diaSemanaOrder[int(w)-1]
}

// loadClaseDisponibilidad carga los bloques de disponibilidad de una clase.
func (p *Pages) loadClaseDisponibilidad(ctx context.Context, claseId int64) []availDay {
	rows, err := p.DB.QueryAll(ctx,
		`SELECT dispId, dia_semana, hora_inicio, hora_fin
		 FROM clase_disponibilidad WHERE claseId = ?
		 ORDER BY FIELD(dia_semana, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), hora_inicio`,
		claseId)
	if err != nil {
		return nil
	}
	m := make(map[string][]availSlot)
	for _, r := range rows {
		dia := store.Str(r["dia_semana"])
		hi := formatTimeHHMM(store.Str(r["hora_inicio"]))
		hf := formatTimeHHMM(store.Str(r["hora_fin"]))
		m[dia] = append(m[dia], availSlot{DispID: store.Int(r["dispId"]), HoraIni: hi, HoraFin: hf})
	}
	var out []availDay
	for _, d := range diaSemanaOrder {
		if slots := m[d]; len(slots) > 0 {
			out = append(out, availDay{Dia: d, Slots: slots})
		}
	}
	return out
}

// claseEstaEnVivo indica si la clase tiene una sala abierta (entrada directa).
func (p *Pages) claseEstaEnVivo(ctx context.Context, claseId int64) bool {
	row, err := p.DB.QueryOne(ctx,
		"SELECT salaId FROM salas WHERE claseId = ? AND activa = true ORDER BY salaId DESC LIMIT 1", claseId)
	return err == nil && row != nil && store.Int(row["salaId"]) > 0
}

// addNotif inserta una notificación in-app para un usuario.
func (p *Pages) addNotif(ctx context.Context, usuarioId int64, tipo, titulo, cuerpo, enlace string) {
	if usuarioId <= 0 {
		return
	}
	_, _ = p.DB.Exec(ctx,
		"INSERT INTO notificaciones_web (usuarioId, tipo, titulo, cuerpo, enlace, leida) VALUES (?, ?, ?, ?, ?, 0)",
		usuarioId, tipo, titulo, cuerpo, enlace)
}

// reservaEstados traduce el estado interno a clave i18n.
func reservaEstadoKey(estado string) string {
	switch estado {
	case "pendiente":
		return "reserva.pending"
	case "confirmada":
		return "reserva.confirmed"
	case "rechazada":
		return "reserva.rejected"
	case "cancelada":
		return "reserva.cancelled"
	default:
		return "reserva.pending"
	}
}

// notifMarksEstado devuelve print breve del título para una notificación de reserva.
func notifTituloEstado(lang, estado, clase string) string {
	switch estado {
	case "confirmada":
		return i18n.T(lang, "notif.reserva_confirmada", map[string]string{"clase": clase})
	case "rechazada":
		return i18n.T(lang, "notif.reserva_rechazada", map[string]string{"clase": clase})
	case "cancelada":
		return i18n.T(lang, "notif.reserva_cancelada", map[string]string{"clase": clase})
	default:
		return i18n.T(lang, "notif.reserva_nueva", map[string]string{"clase": clase})
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// pre_sala.php (estudiante): crear una reserva desde la disponibilidad de la clase.
// ─────────────────────────────────────────────────────────────────────────────

// HandleReservar procesa el POST de pre_sala.php cuando action=reservar.
func (p *Pages) HandleReservar(w http.ResponseWriter, r *http.Request) {
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

	if !CSRFRequire(w, r, s) {
		return
	}

	claseId := store.Int(r.PostFormValue("claseId"))
	dispId := store.Int(r.PostFormValue("dispId"))
	fechaStr := strings.TrimSpace(r.PostFormValue("fecha"))

	errorMsg := ""
	if claseId <= 0 || dispId <= 0 {
		errorMsg = i18n.T(lang, "reserva.invalid", nil)
	} else if fechaStr == "" {
		errorMsg = i18n.T(lang, "reserva.pick_date", nil)
	} else {
		fecha, ferr := time.Parse("2006-01-02", fechaStr)
		if ferr != nil {
			errorMsg = i18n.T(lang, "reserva.invalid", nil)
		} else if fecha.Before(time.Now().Truncate(24 * time.Hour)) {
			errorMsg = i18n.T(lang, "reserva.past_date", nil)
		} else {
			p.crearReserva(ctx, w, r, s, lang, uid, claseId, dispId, fecha)
			return
		}
	}
	flash := s.Get("flash_reserva") + errorMsg
	s.Set("flash_reserva", flash)
	redirect(w, r, "pre_sala.php?clase="+store.Str(claseId))
}

func (p *Pages) crearReserva(ctx context.Context, w http.ResponseWriter, r *http.Request, s *Session, lang string, uid, claseId, dispId int64, fecha time.Time) {
	clase, err := p.DB.QueryOne(ctx,
		`SELECT cp.instructorId, cp.titulo, cp.activa FROM clases_programadas cp WHERE cp.claseId = ?`, claseId)
	if err != nil || clase == nil {
		s.Set("flash_reserva", i18n.T(lang, "reserva.invalid", nil))
		redirect(w, r, "pre_sala.php?clase="+store.Str(claseId))
		return
	}
	instructorId := store.Int(clase["instructorId"])
	titulo := store.Str(clase["titulo"])
	if uid == instructorId || (store.Int(clase["activa"]) == 0) {
		s.Set("flash_reserva", i18n.T(lang, "reserva.invalid", nil))
		redirect(w, r, "pre_sala.php?clase="+store.Str(claseId))
		return
	}

	bloque, err := p.DB.QueryOne(ctx,
		"SELECT dia_semana, hora_inicio, hora_fin FROM clase_disponibilidad WHERE dispId = ? AND claseId = ?", dispId, claseId)
	if err != nil || bloque == nil {
		s.Set("flash_reserva", i18n.T(lang, "reserva.invalid", nil))
		redirect(w, r, "pre_sala.php?clase="+store.Str(claseId))
		return
	}

	// El día de la semana de la fecha elegida debe coincidir con el bloque.
	if diaSemanaGo(fecha.Weekday()) != store.Str(bloque["dia_semana"]) {
		s.Set("flash_reserva", i18n.T(lang, "reserva.day_mismatch", nil))
		redirect(w, r, "pre_sala.php?clase="+store.Str(claseId))
		return
	}

	hi := formatTimeHHMM(store.Str(bloque["hora_inicio"]))
	hf := formatTimeHHMM(store.Str(bloque["hora_fin"]))
	_, err = p.DB.Exec(ctx,
		`INSERT INTO reservas_clase (claseId, estudianteId, instructorId, fecha, hora_inicio, hora_fin, estado, mensaje)
		 VALUES (?, ?, ?, ?, ?, ?, 'pendiente', NULL)`,
		claseId, uid, instructorId, fecha.Format("2006-01-02"), hi, hf)
	if err != nil {
		s.Set("flash_reserva", i18n.T(lang, "reserva.db_error", nil))
		redirect(w, r, "pre_sala.php?clase="+store.Str(claseId))
		return
	}

	p.addNotif(ctx, instructorId, "reserva_nueva",
		notifTituloEstado(lang, "nueva", titulo),
		fmt.Sprintf("%s — %s %s", titulo, fecha.Format("02 Jan"), hi+"-"+hf),
		"dashboard_profesor.php")
	s.Set("flash_reserva", i18n.T(lang, "reserva.requested", nil))
	redirect(w, r, "pre_sala.php?clase="+store.Str(claseId))
}

// ─────────────────────────────────────────────────────────────────────────────
// dashboard_profesor.php / mi_sala.php: confirmar / rechazar / cancelar.
// ─────────────────────────────────────────────────────────────────────────────

func (p *Pages) reservaActualizar(w http.ResponseWriter, r *http.Request, nuevo string) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	lang := p.ResolveLang(s, r)
	uid := UID(s)
	if !CSRFRequire(w, r, s) {
		return
	}
	reservaId := store.Int(r.PostFormValue("reservaId"))
	if reservaId <= 0 {
		redirect(w, r, "dashboard_profesor.php")
		return
	}
	row, err := p.DB.QueryOne(ctx,
		`SELECT r.claseId, r.estudianteId, r.instructorId, r.estado, r.fecha, cp.titulo
		 FROM reservas_clase r JOIN clases_programadas cp ON cp.claseId = r.claseId
		 WHERE r.reservaId = ?`, reservaId)
	if err != nil || row == nil {
		redirect(w, r, "dashboard_profesor.php")
		return
	}
	estado := store.Str(row["estado"])
	if estado == "rechazada" || estado == "cancelada" {
		redirect(w, r, "dashboard_profesor.php")
		return
	}
	instructorId := store.Int(row["instructorId"])
	estudianteId := store.Int(row["estudianteId"])
	titulo := store.Str(row["titulo"])

	// Confirmar/Rechazar: solo el instructor. Cancelar: puede cualquiera de los dos.
	switch nuevo {
	case "confirmada", "rechazada":
		if uid != instructorId {
			redirect(w, r, "dashboard_profesor.php")
			return
		}
	case "cancelada":
		if uid != estudianteId && uid != instructorId {
			redirect(w, r, "dashboard_profesor.php")
			return
		}
	}

	_, err = p.DB.Exec(ctx, "UPDATE reservas_clase SET estado = ? WHERE reservaId = ?", nuevo, reservaId)
	if err != nil {
		redirect(w, r, "dashboard_profesor.php")
		return
	}

	// Notificar al otro lado.
	if nuevo == "confirmada" || nuevo == "rechazada" {
		p.addNotif(ctx, estudianteId, "reserva_"+nuevo,
			notifTituloEstado(lang, nuevo, titulo),
			fmt.Sprintf("%s — %s", titulo, store.Str(row["fecha"])),
			"mi_sala.php")
		// Aviso de hora a ambos cuando llega el momento (se genera en HandleNotifAPI).
	} else {
		p.addNotif(ctx, instructorId, "reserva_cancelada",
			notifTituloEstado(lang, "cancelada", titulo),
			store.Str(row["fecha"]),
			"dashboard_profesor.php")
	}

	flashKey := "flash_reserva"
	if uid == instructorId {
		flashKey = "flash_dashboard"
	}
	s.Set(flashKey, i18n.T(lang, "reserva."+nuevo, nil))
	if uid == instructorId {
		redirect(w, r, "dashboard_profesor.php")
	} else {
		redirect(w, r, "mi_sala.php")
	}
}

// loadReservasInstructor carga las reservas recibidas por un profesor (dashboard).
func (p *Pages) loadReservasInstructor(ctx context.Context, instructorId int64) []reservaItem {
	rows, err := p.DB.QueryAll(ctx,
		`SELECT r.reservaId, r.fecha, r.hora_inicio, r.hora_fin, r.estado, r.created_at,
		        cp.titulo AS clase, cp.claseId AS claseId, u.nombre AS estudiante
		 FROM reservas_clase r
		 JOIN clases_programadas cp ON cp.claseId = r.claseId
		 JOIN usuarios u ON u.usuarioId = r.estudianteId
		 WHERE r.instructorId = ?
		 ORDER BY FIELD(r.estado,'pendiente') DESC, r.created_at DESC
		 LIMIT 50`, instructorId)
	if err != nil {
		return nil
	}
	now := time.Now()
	var out []reservaItem
	for _, row := range rows {
		estado := store.Str(row["estado"])
		fin := time.Time{}
		if t, err := time.Parse("2006-01-02 15:04:05", store.Str(row["fecha"])+" "+formatTimeHHMM(store.Str(row["hora_fin"]))+":00"); err == nil {
			fin = t
		}
		out = append(out, reservaItem{
			ReservaId:   store.Int(row["reservaId"]),
			ClaseId:     store.Int(row["claseId"]),
			ClaseTitulo: store.Str(row["clase"]),
			Estudiante:  store.Str(row["estudiante"]),
			Fecha:       dateFmt(store.Str(row["fecha"]), "02 Jan 2006"),
			HoraIni:     formatTimeHHMM(store.Str(row["hora_inicio"])),
			HoraFin:     formatTimeHHMM(store.Str(row["hora_fin"])),
			Estado:      estado,
			EstadoKey:   reservaEstadoKey(estado),
			CreatedAt:   dateFmt(store.Str(row["created_at"]), "02 Jan 2006 15:04"),
			Pasada:      !fin.IsZero() && fin.Before(now),
		})
	}
	return out
}

// loadReservasEstudiante carga las reservas de un estudiante (mi_sala).
func (p *Pages) loadReservasEstudiante(ctx context.Context, estudianteId int64) []reservaItem {
	rows, err := p.DB.QueryAll(ctx,
		`SELECT r.reservaId, r.fecha, r.hora_inicio, r.hora_fin, r.estado, r.created_at,
		        cp.titulo AS clase, cp.claseId AS claseId, u.nombre AS profesor, u.usuarioId AS profId
		 FROM reservas_clase r
		 JOIN clases_programadas cp ON cp.claseId = r.claseId
		 JOIN usuarios u ON u.usuarioId = r.instructorId
		 WHERE r.estudianteId = ?
		 ORDER BY FIELD(r.estado,'pendiente') DESC, r.created_at DESC
		 LIMIT 30`, estudianteId)
	if err != nil {
		return nil
	}
	now := time.Now()
	var out []reservaItem
	for _, row := range rows {
		estado := store.Str(row["estado"])
		fin := time.Time{}
		if t, err := time.Parse("2006-01-02 15:04:05", store.Str(row["fecha"])+" "+formatTimeHHMM(store.Str(row["hora_fin"]))+":00"); err == nil {
			fin = t
		}
		out = append(out, reservaItem{
			ReservaId:    store.Int(row["reservaId"]),
			ClaseId:      store.Int(row["claseId"]),
			ClaseTitulo:  store.Str(row["clase"]),
			Estudiante:   store.Str(row["profesor"]),
			EstudianteId: store.Int(row["profId"]),
			Fecha:        dateFmt(store.Str(row["fecha"]), "02 Jan 2006"),
			HoraIni:      formatTimeHHMM(store.Str(row["hora_inicio"])),
			HoraFin:      formatTimeHHMM(store.Str(row["hora_fin"])),
			Estado:       estado,
			EstadoKey:    reservaEstadoKey(estado),
			CreatedAt:    dateFmt(store.Str(row["created_at"]), "02 Jan 2006 15:04"),
			Pasada:       !fin.IsZero() && fin.Before(now),
		})
	}
	return out
}

// ─────────────────────────────────────────────────────────────────────────────
// reserva_actualizar.php: POST para confirmar / rechazar / cancelar.
// ─────────────────────────────────────────────────────────────────────────────

// HandleReservaActualizar procesa el POST genérico de estado de reserva.
func (p *Pages) HandleReservaActualizar(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if !p.GuardPage(w, r, s) {
		return
	}
	nuevo := r.PostFormValue("estado")
	switch nuevo {
	case "confirmada", "rechazada", "cancelada":
		p.reservaActualizar(w, r, nuevo)
	default:
		redirect(w, r, "dashboard_profesor.php")
	}
}

// ─────────────────────────────────────────────────────────────────────────────
// notif_api.php: unread count + avisos de hora inminente para la app instalable.
// ─────────────────────────────────────────────────────────────────────────────

// HandleNotifAPI devuelve JSON con notificaciones no leídas y reservas
// confirmadas que empiezan en los próximos 15 minutos.
func (p *Pages) HandleNotifAPI(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		writeJSON(w, http.StatusOK, map[string]any{"unread": 0, "alerts": []any{}})
		return
	}
	uid := UID(s)
	lang := p.ResolveLang(s, r)
	writeJSON(w, http.StatusOK, p.notifPayload(ctx, uid, lang))
}

func (p *Pages) notifPayload(ctx context.Context, uid int64, lang string) map[string]any {
	unread := int64(0)
	if row, err := p.DB.QueryOne(ctx,
		"SELECT COUNT(*) AS n FROM notificaciones_web WHERE usuarioId = ? AND leida = 0", uid); err == nil && row != nil {
		unread = store.Int(row["n"])
	}

	var alerts []map[string]any
	// Reservas confirmadas que empiezan pronto (próximos 15 minutos).
	rows, err := p.DB.QueryAll(ctx,
		`SELECT r.reservaId, r.claseId, r.estudianteId, r.fecha, r.hora_inicio, r.hora_fin, cp.titulo
		 FROM reservas_clase r
		 JOIN clases_programadas cp ON cp.claseId = r.claseId
		 WHERE r.estado = 'confirmada' AND r.avisada = 0
		   AND (r.estudianteId = ? OR r.instructorId = ?)
		   AND TIMESTAMP(r.fecha, r.hora_inicio) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 15 MINUTE)
		 LIMIT 10`, uid, uid)
	if err == nil {
		for _, row := range rows {
			clase := store.Str(row["titulo"])
			body := fmt.Sprintf("%s — %s %s", clase, formatTimeHHMM(store.Str(row["hora_inicio"])), formatTimeHHMM(store.Str(row["hora_fin"])))
			alerts = append(alerts, map[string]any{
				"title": i18n.T(lang, "notif.hora_clase", map[string]string{"clase": clase}),
				"body":  body,
				"link":  "pre_sala.php?clase=" + store.Str(row["claseId"]),
			})
			// Marcar avisada y registrar notificación para ambas partes.
			uidOther := store.Int(row["estudianteId"])
			_, _ = p.DB.Exec(ctx, "UPDATE reservas_clase SET avisada = 1 WHERE reservaId = ?", store.Int(row["reservaId"]))
			p.addNotif(ctx, uidOther, "hora_clase",
				i18n.T(lang, "notif.hora_clase", map[string]string{"clase": clase}),
				body, "pre_sala.php?clase="+store.Str(row["claseId"]))
		}
	}

	return map[string]any{"unread": unread, "alerts": alerts}
}

// ─────────────────────────────────────────────────────────────────────────────
// notificaciones.php: listado de notificaciones in-app.
// ─────────────────────────────────────────────────────────────────────────────

func (p *Pages) HandleNotificaciones(w http.ResponseWriter, r *http.Request) {
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

_, _ = p.DB.Exec(ctx, "UPDATE notificaciones_web SET leida = 1 WHERE usuarioId = ?", uid)
	rows, err := p.DB.QueryAll(ctx,
		`SELECT notifId, tipo, titulo, cuerpo, enlace, leida, created_at
		FROM notificaciones_web WHERE usuarioId = ? ORDER BY created_at DESC, notifId DESC LIMIT 100`, uid)
	if err != nil {
		serverError(w, err)
		return
	}
	var notifs []notifItem
	for _, r := range rows {
		_ = lang
		notifs = append(notifs, notifItem{
			NotifId:   store.Int(r["notifId"]),
			Tipo:      store.Str(r["tipo"]),
			Titulo:    store.Str(r["titulo"]),
			Cuerpo:    store.Str(r["cuerpo"]),
			Enlace:    store.Str(r["enlace"]),
			Leida:     store.Int(r["leida"]) > 0,
			CreatedAt: dateFmt(store.Str(r["created_at"]), "02 Jan 2006 15:04"),
		})
	}

	data := map[string]any{
		"Lang":      lang,
		"NavData":   nav,
		"Notifs":    notifs,
		"HasNotifs": len(notifs) > 0,
	}
	if err := p.Templates.RenderAuthed(w, "notificaciones", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}
