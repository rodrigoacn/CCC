package web

import (
	"fmt"
	"html"
	"net/http"
	"strconv"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

// itoa is a tiny int-to-string helper shared by page handlers.
func itoa(n int) string {
	return strconv.Itoa(n)
}

// htmlEscape escapes a string for safe HTML insertion (htmlspecialchars).
func htmlEscape(s string) string {
	return html.EscapeString(s)
}

// fmtMoney renders symbol + amount with 2 decimals (mirrors fmtMoney()).
func fmtMoney(sym string, amount float64) string {
	return sym + formatNumber(amount, 2)
}

type dashStats struct {
	TotalClases     int64
	ClasesActivas   int64
	TotalSesiones   int64
	SesionesPagadas int64
	GananciasUSD    float64
}

type dashEarning struct {
	MonedaLocal  string
	SimboloLocal string
	Total        string
	NumPagos     int64
	PagosTxt     string
}

type dashClase struct {
	ClaseId     int64
	Titulo      string
	Activa      bool
	Materia     string
	PrecioStr   string
	Moneda      string
	AlumnosMin  int64
	AlumnosMax  int64
	NumSesiones int64
	NumPagados  int64
	PagadosTxt  string
	CreatedAt   string
	QuickOffer  string
}

type dashSesion struct {
	Estudiante  string
	Clase       string
	Materia     string
	Duracion    string
	MontoStr    string
	MonedaLocal string
	Pagado      bool
	Fin         bool
	Inicio      string
	QuickOffer  string
}

// fmtDur mirrors fmtDur() in dashboard_profesor.php.
func fmtDur(min int64) string {
	if min <= 0 {
		return "–"
	}
	if min < 60 {
		return itoa(int(min)) + " min"
	}
	return itoa(int(min/60)) + "h " + itoa(int(min%60)) + "m"
}

// HandleDashboardProfesor ports dashboard_profesor.php (teacher dashboard).
func (p *Pages) HandleDashboardProfesor(w http.ResponseWriter, r *http.Request) {
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

	if nav.NavRol == "estudiante" || nav.NavRol == "student" {
		redirect(w, r, "materias.php")
		return
	}

	// POST actions (deactivate / activate / delete a class / reservation replies).
	if r.Method == http.MethodPost {
		if !CSRFRequire(w, r, s) {
			return
		}
		action := r.PostFormValue("action")
		claseId := store.Int(r.PostFormValue("claseId"))
		switch action {
		case "deactivate":
			_, _ = p.DB.Exec(ctx,
				"UPDATE clases_programadas SET activa=0 WHERE claseId=? AND instructorId=?", claseId, uid)
		case "activate":
			_, _ = p.DB.Exec(ctx,
				"UPDATE clases_programadas SET activa=1 WHERE claseId=? AND instructorId=?", claseId, uid)
		case "delete":
			_, _ = p.DB.Exec(ctx,
				"DELETE FROM clases_programadas WHERE claseId=? AND instructorId=?", claseId, uid)
		case "confirmar":
			p.reservaActualizar(w, r, "confirmada")
			return
		case "rechazar":
			p.reservaActualizar(w, r, "rechazada")
			return
		}
		redirect(w, r, "dashboard_profesor.php")
		return
	}

	me, err := p.DB.QueryOne(ctx,
		`SELECT u.nombre, u.rol, u.calificacion, u.num_resenas, u.avatar,
		        pa.nombre AS pais, pa.simbolo, pa.codigo_moneda
		 FROM usuarios u
		 LEFT JOIN paises pa ON pa.paisId = u.pais_id
		 WHERE u.usuarioId = ?`, uid)
	if err != nil {
		serverError(w, err)
		return
	}
	if me == nil {
		redirect(w, r, "login.php")
		return
	}

	statsRow, err := p.DB.QueryOne(ctx,
		`SELECT
		     COUNT(DISTINCT cp.claseId) AS total_clases,
		     SUM(cp.activa) AS clases_activas,
		     COUNT(DISTINCT sc.sesionId) AS total_sesiones,
		     COUNT(DISTINCT CASE WHEN sc.pagado=1 THEN sc.sesionId END) AS sesiones_pagadas,
		     COALESCE(SUM(CASE WHEN p.estado='completado' THEN p.monto_usd END), 0) AS ganancias_usd
		  FROM clases_programadas cp
		  LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId
		  LEFT JOIN pagos p ON p.sesionId = sc.sesionId
		  WHERE cp.instructorId = ?`, uid)
	if err != nil {
		serverError(w, err)
		return
	}
	stats := dashStats{}
	if statsRow != nil {
		stats.TotalClases = store.Int(statsRow["total_clases"])
		stats.ClasesActivas = store.Int(statsRow["clases_activas"])
		stats.TotalSesiones = store.Int(statsRow["total_sesiones"])
		stats.SesionesPagadas = store.Int(statsRow["sesiones_pagadas"])
		stats.GananciasUSD = store.Float(statsRow["ganancias_usd"])
	}

	liveRow, err := p.DB.QueryOne(ctx,
		`SELECT COUNT(*) AS n
		 FROM participantes_sala ps
		 JOIN salas s ON s.salaId = ps.salaId
		 JOIN clases_programadas cp ON cp.salaId = s.salaId
		 WHERE cp.instructorId = ?`, uid)
	if err != nil {
		serverError(w, err)
		return
	}
	live := int64(0)
	if liveRow != nil {
		live = store.Int(liveRow["n"])
	}

	earnRows, err := p.DB.QueryAll(ctx,
		`SELECT p.moneda_local, p.simbolo_local,
		         SUM(p.monto_local) AS total, COUNT(*) AS num_pagos
		  FROM pagos p
		  WHERE p.profesorId = ? AND p.estado = 'completado'
		  GROUP BY p.moneda_local, p.simbolo_local
		  ORDER BY total DESC`, uid)
	if err != nil {
		serverError(w, err)
		return
	}
	var earnings []dashEarning
	for _, e := range earnRows {
		num := store.Int(e["num_pagos"])
		key := "dashboard.payments_count"
		if num != 1 {
			key = "dashboard.payments_count_plural"
		}
		earnings = append(earnings, dashEarning{
			MonedaLocal:  store.Str(e["moneda_local"]),
			SimboloLocal: store.Str(e["simbolo_local"]),
			Total:        fmtMoney(store.Str(e["simbolo_local"]), store.Float(e["total"])),
			NumPagos:     num,
			PagosTxt:     i18n.T(lang, key, map[string]string{"count": fmt.Sprint(num)}),
		})
	}

	claseRows, err := p.DB.QueryAll(ctx,
		`SELECT cp.claseId, cp.titulo, cp.activa, cp.precio_min, cp.precio_max,
		         cp.precio_base, cp.codigo_moneda, cp.alumnos_min, cp.alumnos_max,
		         cp.created_at, m.nombre AS materia,
		         COUNT(sc.sesionId) AS num_sesiones,
		         SUM(CASE WHEN sc.pagado=1 THEN 1 ELSE 0 END) AS num_pagados
		  FROM clases_programadas cp
		  LEFT JOIN materias m ON m.materiaId = cp.materiaId
		  LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId
		  WHERE cp.instructorId = ?
		  GROUP BY cp.claseId, cp.titulo, cp.activa, cp.precio_min, cp.precio_max,
		           cp.precio_base, cp.codigo_moneda, cp.alumnos_min, cp.alumnos_max,
		           cp.created_at, m.nombre
		  ORDER BY cp.activa DESC, cp.created_at DESC`, uid)
	if err != nil {
		serverError(w, err)
		return
	}
	simbolo := store.Str(me["simbolo"])
	if simbolo == "" {
		simbolo = "$"
	}
	quickOffer := i18n.T(lang, "dashboard.quick_offer", nil)
	var clases []dashClase
	for _, c := range claseRows {
		precioMin := store.Float(c["precio_min"])
		precioMax := store.Float(c["precio_max"])
		precioBase := store.Float(c["precio_base"])
		var precioStr string
		switch {
		case precioMin > 0 && precioMax > 0:
			precioStr = fmtMoney(simbolo, precioMin) + " – " + fmtMoney(simbolo, precioMax)
		case precioBase > 0:
			precioStr = fmtMoney(simbolo, precioBase)
		default:
			precioStr = "–"
		}
		numPagados := store.Int(c["num_pagados"])
		pagadosTxt := ""
		if numPagados > 0 {
			pagadosTxt = i18n.T(lang, "dashboard.paid", nil)
		}
		titulo := store.Str(c["titulo"])
		if titulo == "" {
			titulo = quickOffer
		}
		materia := store.Str(c["materia"])
		if materia == "" {
			materia = "–"
		}
		clases = append(clases, dashClase{
			ClaseId:     store.Int(c["claseId"]),
			Titulo:      titulo,
			Activa:      store.Bool(c["activa"]),
			Materia:     materia,
			PrecioStr:   precioStr,
			Moneda:      store.Str(c["codigo_moneda"]),
			AlumnosMin:  store.Int(c["alumnos_min"]),
			AlumnosMax:  store.Int(c["alumnos_max"]),
			NumSesiones: store.Int(c["num_sesiones"]),
			NumPagados:  numPagados,
			PagadosTxt:  pagadosTxt,
			CreatedAt:   dateFmt(store.Str(c["created_at"]), "02 Jan 2006"),
		})
	}

	sesRows, err := p.DB.QueryAll(ctx,
		`SELECT sc.sesionId, sc.inicio, sc.fin, sc.duracion_min,
		         sc.monto_local, sc.moneda_local, sc.simbolo_local, sc.pagado,
		         u.nombre AS estudiante, cp.titulo AS clase,
		         m.nombre AS materia
		  FROM sesiones_clase sc
		  JOIN clases_programadas cp ON cp.claseId = sc.claseId
		  JOIN usuarios u ON u.usuarioId = sc.estudianteId
		  LEFT JOIN materias m ON m.materiaId = cp.materiaId
		  WHERE cp.instructorId = ?
		  ORDER BY sc.inicio DESC
		  LIMIT 15`, uid)
	if err != nil {
		serverError(w, err)
		return
	}
	var sesiones []dashSesion
	for _, sc := range sesRows {
		claseTitulo := store.Str(sc["clase"])
		if claseTitulo == "" {
			claseTitulo = quickOffer
		}
		materia := store.Str(sc["materia"])
		if materia == "" {
			materia = "–"
		}
		montoLocal := store.Float(sc["monto_local"])
		montoStr := "–"
		simLocal := store.Str(sc["simbolo_local"])
		if simLocal == "" {
			simLocal = "$"
		}
		if montoLocal != 0 {
			montoStr = fmtMoney(simLocal, montoLocal)
		}
		sesiones = append(sesiones, dashSesion{
			Estudiante:  store.Str(sc["estudiante"]),
			Clase:       claseTitulo,
			Materia:     materia,
			Duracion:    fmtDur(store.Int(sc["duracion_min"])),
			MontoStr:    montoStr,
			MonedaLocal: store.Str(sc["moneda_local"]),
			Pagado:      store.Bool(sc["pagado"]),
			Fin:         store.Str(sc["fin"]) != "",
			Inicio:      dateFmt(store.Str(sc["inicio"]), "02 Jan 2006 15:04"),
		})
	}

	flashD := s.Get("flash_dashboard")
	s.Del("flash_dashboard")

	reservas := p.loadReservasInstructor(ctx, uid)

	nombre := store.Str(me["nombre"])
	rol := store.Str(me["rol"])
	calificacion := store.Float(me["calificacion"])
	hasRating := calificacion > 0
	pais := store.Str(me["pais"])

	data := map[string]any{
		"Lang":             lang,
		"NavData":          nav,
		"Nombre":           nombre,
		"Rol":              rol,
		"Calificacion":     formatNumber(calificacion, 1),
		"NumResenas":       store.Int(me["num_resenas"]),
		"HasRating":        hasRating,
		"Pais":             pais,
		"Stats":            stats,
		"Live":             live,
		"Earnings":         earnings,
		"Clases":           clases,
		"Sesiones":         sesiones,
		"HasClases":        len(clases) > 0,
		"HasSesiones":      len(sesiones) > 0,
		"HasEarnings":      len(earnings) > 0,
		"WelcomeTxt":       i18n.T(lang, "dashboard.welcome", map[string]string{"name": "<span class=\"text-white fw-semibold\">" + htmlEscape(nombre) + "</span>"}),
		"GananciasStr":     fmtMoney("$", stats.GananciasUSD),
		"TotalPostedTxt":   i18n.T(lang, "dashboard.total_posted", map[string]string{"count": itoa(int(stats.TotalClases))}),
		"SessionsTotalTxt": i18n.T(lang, "dashboard.sessions_total", map[string]string{"count": itoa(int(stats.TotalSesiones))}),
		"PaidOutOfTxt":     i18n.T(lang, "dashboard.paid_out_of", map[string]string{"count": itoa(int(stats.TotalSesiones))}),
		"FlashDashboard":   flashD,
		"Reservas":         reservas,
		"HasReservas":      len(reservas) > 0,
	}
	if err := p.Templates.RenderAuthed(w, "dashboard_profesor", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}
