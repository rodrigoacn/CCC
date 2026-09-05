package web

import (
	"context"
	"net/http"
	"time"

	"classexpress/internal/store"
)

type scheduleSlot struct {
	ID        int64
	Dia       string
	HoraIni   string
	HoraFin   string
	IsPrimary bool
	StartIdx  int64
	EndIdx    int64
	Top       int
	Height    int
}

const scheduleRowMin = 30
const scheduleDayStart = "08:00"
const scheduleRowH = 26

type scheduleDay struct {
	Dia   string
	Slots []scheduleSlot
}

type classSchedule struct {
	ClaseId  int64
	Titulo   string
	Profesor string
	Slots    []scheduleSlot
}

// formatTimeHHMM normalizes a time string to "HH:MM" when possible.
func formatTimeHHMM(v string) string {
	if t, err := time.Parse("15:04", v); err == nil {
		return t.Format("15:04")
	}
	return v
}

// scheduleHours returns half-hour times from start to end (both inclusive),
// e.g. scheduleHours("08:00","20:00") yields 08:00..20:00 every 30min.
func scheduleHours(start, end string) []string {
	var out []string
	cur, err := time.Parse("15:04", start)
	if err != nil {
		return out
	}
	endT, err2 := time.Parse("15:04", end)
	if err2 != nil {
		return out
	}
	if endT.Before(cur) {
		return out
	}
	for !cur.After(endT) {
		out = append(out, cur.Format("15:04"))
		cur = cur.Add(30 * time.Minute)
	}
	return out
}

// timeToRow returns the 30-minute row index of a "HH:MM" time relative to the
// given day-start base. Returns -1 if the time cannot be parsed.
func timeToRow(v, base string) int64 {
	t, err := time.Parse("15:04", v)
	if err != nil {
		return -1
	}
	b, err2 := time.Parse("15:04", base)
	if err2 != nil {
		return -1
	}
	min := int64(t.Sub(b).Minutes())
	if min < 0 {
		return -1
	}
	return min / scheduleRowMin
}

var dayOrder = []string{"lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"}

// HandleSchedule ports schedule.php: per-class availability management (teacher)
// and the class availability list (student).
func (p *Pages) HandleSchedule(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if !p.GuardPage(w, r, s) {
		return
	}
	if p.ApplyLangParam(w, r, s) {
		return
	}
	lang := p.ResolveLang(s, r)
	page := CurrentPage(r)
	nav, stop := p.MenuData(w, r, s, page, lang)
	if stop {
		return
	}
	uid := UID(s)

	rol := s.Get("rol")
	isTeacher := rol == "instructor" || (s.Get("ce_app_modo") == "teacher" && rol == "both")

	claseSel := store.Int(r.URL.Query().Get("clase"))

	if r.Method == http.MethodPost {
		p.scheduleHandlePost(w, r, s, uid, lang, isTeacher, claseSel)
		return
	}

	if isTeacher {
		p.scheduleTeacherView(w, r, s, uid, lang, nav, claseSel)
	} else {
		p.scheduleStudentView(w, r, s, uid, lang, nav)
	}
}

func (p *Pages) scheduleTeacherView(w http.ResponseWriter, r *http.Request, s *Session, uid int64, lang string, nav NavData, claseSel int64) {
	ctx := r.Context()

	claseRows, err := p.DB.QueryAll(ctx,
		`SELECT cp.claseId, cp.titulo FROM clases_programadas cp
		 WHERE cp.instructorId = ? ORDER BY cp.created_at DESC`, uid)
	if err != nil {
		serverError(w, err)
		return
	}

	var clases []materiaOption
	if len(claseRows) > 0 {
		if claseSel <= 0 {
			claseSel = store.Int(claseRows[0]["claseId"])
		}
		for _, c := range claseRows {
			id := store.Int(c["claseId"])
			clases = append(clases, materiaOption{ID: id, Nombre: store.Str(c["titulo"]), Selected: id == claseSel})
		}
	}

	var rows []map[string]any
	if claseSel > 0 {
		rows, err = p.DB.QueryAll(ctx,
			`SELECT dispId, dia_semana, hora_inicio, hora_fin
			 FROM clase_disponibilidad WHERE claseId = ?
			 ORDER BY FIELD(dia_semana, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), hora_inicio`, claseSel)
		if err != nil {
			serverError(w, err)
			return
		}
	}

	dayMap := make(map[string][]scheduleSlot)
	for _, row := range rows {
		dia := store.Str(row["dia_semana"])
		hi := formatTimeHHMM(store.Str(row["hora_inicio"]))
		hf := formatTimeHHMM(store.Str(row["hora_fin"]))
		startIdx := timeToRow(hi, scheduleDayStart)
		endIdx := timeToRow(hf, scheduleDayStart)
		if startIdx < 0 {
			startIdx = 0
		}
		if endIdx < 0 {
			endIdx = startIdx + 1
		}
		if endIdx < startIdx+1 {
			endIdx = startIdx + 1
		}
		slot := scheduleSlot{
			ID:       store.Int(row["dispId"]),
			Dia:      dia,
			HoraIni:  hi,
			HoraFin:  hf,
			StartIdx: startIdx,
			EndIdx:   endIdx,
			Top:      int(startIdx) * scheduleRowH,
			Height:   int(endIdx-startIdx) * scheduleRowH,
		}
		dayMap[dia] = append(dayMap[dia], slot)
	}

	days := make([]scheduleDay, 0, 7)
	for _, d := range dayOrder {
		days = append(days, scheduleDay{Dia: d, Slots: dayMap[d]})
	}

	data := map[string]any{
		"Lang":      lang,
		"NavData":   nav,
		"UID":       uid,
		"IsTeacher": true,
		"Clases":    clases,
		"ClaseSel":  claseSel,
		"HasClases": len(clases) > 0,
		"Days":      days,
		"Hours":     scheduleHours("08:00", "20:00"),
		"HourStart": 8,
		"HourEnd":   20,
	}
	if err := p.Templates.RenderAuthed(w, "schedule", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func (p *Pages) scheduleStudentView(w http.ResponseWriter, r *http.Request, s *Session, uid int64, lang string, nav NavData) {
	ctx := r.Context()

	rows, err := p.DB.QueryAll(ctx,
		`SELECT cd.dispId, cd.dia_semana, cd.hora_inicio, cd.hora_fin,
		        cp.claseId AS claseId, cp.titulo AS clase, u.nombre AS profesor
		 FROM clase_disponibilidad cd
		 JOIN clases_programadas cp ON cp.claseId = cd.claseId
		 JOIN usuarios u ON u.usuarioId = cp.instructorId
		 WHERE cp.activa = true
		 ORDER BY cp.claseId, FIELD(cd.dia_semana, 'lunes','martes','miercoles','jueves','viernes','sabado','domingo'), cd.hora_inicio`)
	if err != nil {
		serverError(w, err)
		return
	}

	classMap := make(map[int64]*classSchedule)
	var order []int64

	for _, row := range rows {
		claseId := store.Int(row["claseId"])
		if _, ok := classMap[claseId]; !ok {
			classMap[claseId] = &classSchedule{
				ClaseId:  claseId,
				Titulo:   store.Str(row["clase"]),
				Profesor: store.Str(row["profesor"]),
			}
			order = append(order, claseId)
		}
		hi := formatTimeHHMM(store.Str(row["hora_inicio"]))
		hf := formatTimeHHMM(store.Str(row["hora_fin"]))
		classMap[claseId].Slots = append(classMap[claseId].Slots, scheduleSlot{
			Dia:     store.Str(row["dia_semana"]),
			HoraIni: hi,
			HoraFin: hf,
		})
	}

	classes := make([]classSchedule, 0, len(order))
	for _, id := range order {
		classes = append(classes, *classMap[id])
	}

	data := map[string]any{
		"Lang":      lang,
		"NavData":   nav,
		"UID":       uid,
		"IsTeacher": false,
		"Classes":   classes,
	}
	if err := p.Templates.RenderAuthed(w, "schedule", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func (p *Pages) scheduleHandlePost(w http.ResponseWriter, r *http.Request, s *Session, uid int64, lang string, isTeacher bool, claseSel int64) {
	ctx := r.Context()
	action := r.PostFormValue("action")

	claseId := store.Int(r.PostFormValue("claseId"))
	if claseId <= 0 {
		claseId = claseSel
	}

	if !isTeacher || claseId <= 0 {
		redirect(w, r, "schedule.php")
		return
	}

	own, err := p.DB.QueryOne(ctx,
		"SELECT claseId FROM clases_programadas WHERE claseId = ? AND instructorId = ? AND activa = true", claseId, uid)
	if err != nil || own == nil {
		redirect(w, r, "schedule.php")
		return
	}

	base := "schedule.php?clase=" + store.Str(claseId)

	switch action {
	case "add_slot":
		dia := r.PostFormValue("dia")
		horaStr := r.PostFormValue("hora_inicio")
		horaFinStr := r.PostFormValue("hora_fin")
		if dia == "" || horaStr == "" || horaFinStr == "" {
			redirect(w, r, base)
			return
		}
		if _, err := time.Parse("15:04", horaStr); err != nil {
			redirect(w, r, base)
			return
		}
		if _, err := time.Parse("15:04", horaFinStr); err != nil {
			redirect(w, r, base)
			return
		}
		if diaSemanaIndex(dia) < 0 {
			redirect(w, r, base)
			return
		}
		if !slotNoOverlap(p, ctx, claseId, dia, horaStr, horaFinStr, 0) {
			redirect(w, r, base)
			return
		}
		_, _ = p.DB.Exec(ctx,
			"INSERT INTO clase_disponibilidad (claseId, dia_semana, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)",
			claseId, dia, horaStr, horaFinStr)
		redirect(w, r, base)

	case "remove_slot":
		slotID := store.Int(r.PostFormValue("slot_id"))
		_, _ = p.DB.Exec(ctx, "DELETE FROM clase_disponibilidad WHERE dispId = ? AND claseId = ?", slotID, claseId)
		redirect(w, r, base)

	case "update_slot":
		slotID := store.Int(r.PostFormValue("slot_id"))
		dia := r.PostFormValue("dia")
		horaStr := r.PostFormValue("hora_inicio")
		horaFinStr := r.PostFormValue("hora_fin")
		if slotID <= 0 || dia == "" || horaStr == "" || horaFinStr == "" {
			redirect(w, r, base)
			return
		}
		if _, err := time.Parse("15:04", horaStr); err != nil {
			redirect(w, r, base)
			return
		}
		if _, err := time.Parse("15:04", horaFinStr); err != nil {
			redirect(w, r, base)
			return
		}
		if diaSemanaIndex(dia) < 0 {
			redirect(w, r, base)
			return
		}
		if !slotNoOverlap(p, ctx, claseId, dia, horaStr, horaFinStr, slotID) {
			redirect(w, r, base)
			return
		}
		_, _ = p.DB.Exec(ctx,
			"UPDATE clase_disponibilidad SET dia_semana = ?, hora_inicio = ?, hora_fin = ? WHERE dispId = ? AND claseId = ?",
			dia, horaStr, horaFinStr, slotID, claseId)
		redirect(w, r, base)
	}
	redirect(w, r, base)
}

// slotNoOverlap valida que un bloque nuevo no se solape con otros del mismo día.
func slotNoOverlap(p *Pages, ctx context.Context, claseId int64, dia, start, end string, exceptId int64) bool {
	rows, err := p.DB.QueryAll(ctx,
		`SELECT dispId, hora_inicio, hora_fin FROM clase_disponibilidad
		 WHERE claseId = ? AND dia_semana = ? AND dispId != ?`, claseId, dia, exceptId)
	if err != nil {
		return true
	}
	newStart, _ := time.Parse("15:04", start)
	newEnd, _ := time.Parse("15:04", end)
	for _, e := range rows {
		s, _ := time.Parse("15:04", store.Str(e["hora_inicio"]))
		f, _ := time.Parse("15:04", store.Str(e["hora_fin"]))
		if !newStart.After(f) && !newEnd.Before(s) {
			return false
		}
	}
	return true
}
