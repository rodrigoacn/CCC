package web

import (
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
}

type teacherSchedule struct {
	Nombre   string
	Avatar   string
	Slots    []scheduleSlot
}

// HandleSchedule ports schedule.php: teacher availability management and student view.
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

	// Check if user is teacher
	rol := s.Get("rol")
	isTeacher := rol == "instructor" || (s.Get("ce_app_modo") == "teacher" && rol == "both")

	if r.Method == http.MethodPost {
		p.scheduleHandlePost(w, r, s, uid, lang, isTeacher)
		return
	}

	if isTeacher {
		p.scheduleTeacherView(w, r, s, uid, lang, nav)
	} else {
		p.scheduleStudentView(w, r, s, uid, lang, nav)
	}
}

func (p *Pages) scheduleTeacherView(w http.ResponseWriter, r *http.Request, s *Session, uid int64, lang string, nav NavData) {
	ctx := r.Context()

	// Get all schedule slots for this teacher
	rows, err := p.DB.QueryAll(ctx,
		`SELECT scheduleId, dia_semana, hora_inicio, hora_fin, is_primary
		 FROM schedules WHERE usuarioId = ORDER BY
		 CASE dia_semana
		  WHEN 'lunes' THEN 1 WHEN 'martes' THEN 2 WHEN 'miercoles' THEN 3
		  WHEN 'jueves' THEN 4 WHEN 'viernes' THEN 5 WHEN 'sabado' THEN 6 WHEN 'domingo' THEN 7
		END, hora_inicio`, uid)
	if err != nil {
		serverError(w, err)
		return
	}

	// Parse times and organize by day
	daysOrder := []string{"lunes", "martes", "miercoles", "jueves", "viernes", "sabado", "domingo"}
 dayMap := make(map[string][]scheduleSlot)

	for _, row := range rows {
		dia := store.Str(row["dia_semana"])
		hi := store.Str(row["hora_inicio"])
		hf := store.Str(row["hora_fin"])
		// Format times
		hiStr := hi
		hfStr := hf
		if t, err := time.Parse("15:04", hi); err == nil {
			hiStr = t.Format("15:04")
		}
		if t, err := time.Parse("15:04", hf); err == nil {
			hfStr = t.Format("15:04")
		}

		slot := scheduleSlot{
			ID:       store.Int(row["scheduleId"]),
			Dia:      dia,
			HoraIni:  hiStr,
			HoraFin:  hfStr,
			IsPrimary: store.Int(row["is_primary"]) > 0,
		}
		dayMap[dia] = append(dayMap[dia], slot)
	}

	// Build ordered days
	days := make([]scheduleSlot, 0)
	for _, d := range daysOrder {
		if slots, ok := dayMap[d]; ok {
			days = append(days, slots...)
		}
	}

	data := map[string]any{
		"Lang":    lang,
		"NavData": nav,
		"UID":     uid,
		"IsTeacher": true,
		"Slots":   days,
	}
	if err := p.Templates.RenderAuthed(w, "schedule", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func (p *Pages) scheduleStudentView(w http.ResponseWriter, r *http.Request, s *Session, uid int64, lang string, nav NavData) {
	ctx := r.Context()

	// Get all active schedules with teacher info
	rows, err := p.DB.QueryAll(ctx,
		`SELECT s.scheduleId, s.dia_semana, s.hora_inicio, s.hora_fin, s.is_primary,
		       u.nombre AS profesor_nombre, u.avatar AS profesor_avatar,
		       u.usuarioId AS prof_id
		FROM schedules s JOIN usuarios u ON u.usuarioId = s.usuarioId ORDER BY s.dia_semana, s.hora_inicio`)
	if err != nil {
		serverError(w, err)
		return
	}

	// Group by teacher
	teacherMap := make(map[int64]*teacherSchedule)
	for _, row := range rows {
		profID := store.Int(row["prof_id"])
		if _, ok := teacherMap[profID]; !ok {
			teacherMap[profID] = &teacherSchedule{
				Nombre: store.Str(row["profesor_nombre"]),
				Avatar: store.Str(row["profesor_avatar"]),
			}
		}
		hiStr := ""
		hfStr := ""
		if t, _ := time.Parse("15:04", store.Str(row["hora_inicio"])); t != (time.Time{}) {
			hiStr = t.Format("15:04")
			if t2, _ := time.Parse("15:04", store.Str(row["hora_fin"])); t2 != (time.Time{}) {
				hfStr = t2.Format("15:04")
			} else {
				hfStr = hiStr + ":00"
			}
		} else {
			hiStr = store.Str(row["hora_inicio"])
			hfStr = store.Str(row["hora_fin"])
		}

		slot := scheduleSlot{
			Dia:      store.Str(row["dia_semana"]),
			HoraIni:  hiStr,
			HoraFin:  hfStr,
			IsPrimary: store.Int(row["is_primary"]) > 0,
		}
		teacherMap[profID].Slots = append(teacherMap[profID].Slots, slot)
	}

	// Convert map to slice for template
	teachers := make([]teacherSchedule, 0, len(teacherMap))
	for _, t := range teacherMap {
		teachers = append(teachers, *t)
	}

	data := map[string]any{
		"Lang":    lang,
		"NavData": nav,
		"UID":     uid,
		"IsTeacher": false,
		"Teachers": teachers,
	}
	if err := p.Templates.RenderAuthed(w, "schedule", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func (p *Pages) scheduleHandlePost(w http.ResponseWriter, r *http.Request, s *Session, uid int64, lang string, isTeacher bool) {
	ctx := r.Context()
	action := r.PostFormValue("action")

	switch action {
	case "add_slot":
		if !isTeacher {
			redirect(w, r, "schedule.php")
			return
		}
		dia := r.PostFormValue("dia")
		horaStr := r.PostFormValue("hora_inicio")
		if dia == "" || horaStr == "" {
			redirect(w, r, "schedule.php")
			return
		}
		// Validate time format
		if _, err := time.Parse("15:04", horaStr); err != nil {
			redirect(w, r, "schedule.php")
			return
		}
		horaFinStr := r.PostFormValue("hora_fin")
		if horaFinStr == "" {
			redirect(w, r, "schedule.php")
			return
		}
		if _, err := time.Parse("15:04", horaFinStr); err != nil {
			redirect(w, r, "schedule.php")
			return
		}
		// Check overlap with existing slots for same day
		existing, _ := p.DB.QueryAll(ctx,
			"SELECT hora_inicio, hora_fin FROM schedules WHERE usuarioId = ? AND dia_semana = ?",
			uid, dia)
		newStart, _ := time.Parse("15:04", horaStr)
		newEnd, _ := time.Parse("15:04", horaFinStr)
		conflict := false
		for _, e := range existing {
			start, _ := time.Parse("15:04", store.Str(e["hora_inicio"]))
			end, _ := time.Parse("15:04", store.Str(e["hora_fin"]))
			if !newStart.After(end) && !newEnd.Before(start) {
				conflict = true
				break
			}
		}
		if conflict {
			redirect(w, r, "schedule.php")
			return
		}
		_, _ = p.DB.Exec(ctx,
			"INSERT INTO schedules (usuarioId, dia_semana, hora_inicio, hora_fin, is_primary) VALUES (?, ?, ?, ?, 0)",
			uid, dia, horaStr, horaFinStr)
		redirect(w, r, "schedule.php")

	case "remove_slot":
		if !isTeacher {
			redirect(w, r, "schedule.php")
			return
		}
		slotID := store.Int(r.PostFormValue("slot_id"))
		_, _ = p.DB.Exec(ctx, "DELETE FROM schedules WHERE scheduleId = ?", slotID)
		redirect(w, r, "schedule.php")

	case "set_primary":
		if !isTeacher {
			redirect(w, r, "schedule.php")
			return
		}
		slotID := store.Int(r.PostFormValue("slot_id"))
		// First set all to 0 for this user
		_, _ = p.DB.Exec(ctx, "UPDATE schedules SET is_primary = 0 WHERE usuarioId = ?", uid)
		// Set the selected one to 1
		_, _ = p.DB.Exec(ctx, "UPDATE schedules SET is_primary = 1 WHERE scheduleId = ?", slotID)
		redirect(w, r, "schedule.php")
	}
}