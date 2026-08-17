package web

import (
	"fmt"
	"net/http"
	"strings"

	"classexpress/internal/mail"
	"classexpress/internal/store"
)

// formatNumber mirrors number_format($v, $dec, '.', ',').
func formatNumber(v float64, dec int) string {
	s := fmt.Sprintf("%."+fmt.Sprint(dec)+"f", v)
	neg := strings.HasPrefix(s, "-")
	if neg {
		s = s[1:]
	}
	parts := strings.SplitN(s, ".", 2)
	intPart := parts[0]
	var b strings.Builder
	n := len(intPart)
	for i := 0; i < n; i++ {
		if i > 0 && (n-i)%3 == 0 {
			b.WriteByte(',')
		}
		b.WriteByte(intPart[i])
	}
	out := b.String()
	if len(parts) == 2 {
		out += "." + parts[1]
	}
	if neg {
		return "-" + out
	}
	return out
}

// HandlePago ports pago.php (finalise a finished session payment).
func (p *Pages) HandlePago(w http.ResponseWriter, r *http.Request) {
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
	if sesionId <= 0 {
		redirect(w, r, "buscar.php")
		return
	}

	sesion, err := p.DB.QueryOne(ctx,
		`SELECT s.*,
		        cp.titulo AS clase_titulo, cp.instructorId,
		        prof.nombre AS prof_nombre, prof.avatar AS prof_avatar,
		        est_p.nombre  AS pais_estudiante,
		        est_p.simbolo AS simbolo_est,
		        est_p.codigo_moneda AS mon_est,
		        prof_p.nombre  AS pais_prof,
		        prof_p.simbolo AS simbolo_prof,
		        prof_p.codigo_moneda AS mon_prof,
		        m.nombre AS materia
		 FROM sesiones_clase s
		 JOIN clases_programadas cp ON cp.claseId = s.claseId
		 JOIN usuarios prof ON prof.usuarioId  = cp.instructorId
		 LEFT JOIN paises est_p  ON est_p.paisId  = (SELECT pais_id FROM usuarios WHERE usuarioId = s.estudianteId)
		 LEFT JOIN paises prof_p ON prof_p.paisId = prof.pais_id
		 LEFT JOIN materias m    ON m.materiaId   = cp.materiaId
		 WHERE s.sesionId = ? AND s.estudianteId = ?`, sesionId, uid)
	if err != nil || sesion == nil {
		redirect(w, r, "buscar.php")
		return
	}

	alreadyPaid := store.Bool(sesion["pagado"])
	precioUSD := store.Float(sesion["precio_usd"])
	montoLocal := store.Float(sesion["monto_local"])
	simbolo := store.Str(sesion["simbolo_local"])
	if simbolo == "" {
		simbolo = store.Str(sesion["simbolo_est"])
	}
	if simbolo == "" {
		simbolo = "$"
	}
	monLocal := store.Str(sesion["moneda_local"])
	if monLocal == "" {
		monLocal = store.Str(sesion["mon_est"])
	}
	if monLocal == "" {
		monLocal = "USD"
	}
	duracion := store.Int(sesion["duracion_min"])

	success := false
	payError := ""
	if r.Method == http.MethodPost && !alreadyPaid {
		if !CSRFRequire(w, r, s) {
			return
		}
		metodo := r.PostFormValue("metodo")
		if metodo != "tarjeta" && metodo != "transferencia" && metodo != "efectivo" {
			metodo = "tarjeta"
		}

		if _, err := p.DB.Exec(ctx,
			`INSERT INTO pagos
			     (sesionId, estudianteId, profesorId, monto_usd, monto_local,
			      moneda_local, simbolo_local, metodo, estado)
			 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completado')`,
			sesionId, uid, store.Str(sesion["instructorId"]), precioUSD, montoLocal,
			monLocal, simbolo, metodo); err != nil {
			payError = "Payment could not be recorded. Please try again."
		} else {
			_, _ = p.DB.Exec(ctx,
				"UPDATE sesiones_clase SET pagado = 1 WHERE sesionId = ?", sesionId)
			_, _ = p.DB.Exec(ctx,
				"UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ?", precioUSD, uid)

			if info, err := p.DB.QueryOne(ctx,
				"SELECT email, nombre FROM usuarios WHERE usuarioId = ?", uid); err == nil && info != nil {
				p.Mail.SendSessionReceipt(store.Str(info["email"]), store.Str(info["nombre"]),
					mail.SessionReceipt{
						Simbolo:     simbolo,
						MontoLocal:  formatNumber(montoLocal, 2),
						MonedaLocal: monLocal,
						MontoUSD:    formatNumber(precioUSD, 2),
						Profesor:    store.Str(sesion["prof_nombre"]),
						Clase:       store.Str(sesion["clase_titulo"]),
						DuracionMin: int(duracion),
					})
			}

			success = true
			alreadyPaid = true
			// Keep credit balance fresh in session.
			if urow, err := p.DB.QueryOne(ctx,
				"SELECT creditos FROM usuarios WHERE usuarioId = ?", uid); err == nil && urow != nil {
				s.Set("creditos", store.Str(urow["creditos"]))
			}
		}
	}

	data := map[string]any{
		"Lang":          lang,
		"NavData":       nav,
		"Success":       success,
		"AlreadyPaid":   alreadyPaid,
		"PayError":      payError,
		"SesionId":      sesionId,
		"ClaseTitulo":   store.Str(sesion["clase_titulo"]),
		"ProfNombre":    store.Str(sesion["prof_nombre"]),
		"PaisProf":      store.Str(sesion["pais_prof"]),
		"Materia":       store.Str(sesion["materia"]),
		"MateriaPresent": store.Str(sesion["materia"]) != "",
		"Duracion":      duracion,
		"PrecioUSD":     formatNumber(precioUSD, 2),
		"MontoLocal":    formatNumber(montoLocal, 2),
		"Simbolo":       simbolo,
		"MonLocal":      monLocal,
		"PaisEstudiante": store.Str(sesion["pais_estudiante"]),
		"Metodo":        r.PostFormValue("metodo"),
	}
	if err := p.Templates.Render(w, "pago", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}
