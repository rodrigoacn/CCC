package api

import (
	"html"
	"math"
	"net/http"
	"strings"
	"time"

	"classexpress/internal/store"
)

// salaOK wraps a payload in {"ok": true, ...} (HTTP 200), matching api_sala.php.
func salaOK(data map[string]any) *resp {
	data["ok"] = true
	return &resp{Status: http.StatusOK, Payload: data}
}

// salaErr returns {"ok": false, "error": msg} (HTTP 200), matching api_sala.php.
func salaErr(msg string) *resp {
	return &resp{Status: http.StatusOK, Payload: map[string]any{"ok": false, "error": msg}}
}

// salaWriteActions mirrors SalaApi::WRITE_ACTIONS (signal is intentionally not
// listed, matching the PHP quirk where it writes without a CSRF check).
var salaWriteActions = map[string]bool{
	"join": true, "leave": true, "chat": true,
	"kick_student": true, "approve_spectator": true, "reject_spectator": true,
	"end_class": true, "start_class": true,
}

func isSalaWriteAction(action string) bool {
	return salaWriteActions[action]
}

// salaUID authenticates the request and returns the user id for api_sala.
func (a *API) salaUID(r *http.Request) (int64, *resp) {
	// Web session first (sala.php page), matching PHP's $_SESSION['usuarioId'].
	if a.SessionAuth != nil {
		if ws := a.SessionAuth(r); ws != nil {
			if id := store.Int(ws.UID); id > 0 {
				return id, nil
			}
		}
	}
	// Bearer token fallback (mobile clients).
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return 0, salaErr("Not authenticated")
	}
	return store.Int(user["id"]), nil
}

// checkRoomAccess mirrors SalaController::checkRoomAccess.
func (a *API) checkRoomAccess(r *http.Request, salaID, userID int64) bool {
	row, err := a.DB.QueryOne(ctx(r),
		`SELECT 1 FROM salas s
		 JOIN clases_programadas cp ON cp.claseId = s.claseId
		 LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId AND sc.estudianteId = ? AND sc.fin IS NULL
		 WHERE s.salaId = ? AND (cp.instructorId = ? OR sc.sesionId IS NOT NULL)
		 LIMIT 1`, userID, salaID, userID)
	if err != nil {
		return false
	}
	return row != nil
}

// htmlspecialchars mirrors PHP htmlspecialchars($s, ENT_QUOTES, 'UTF-8').
func htmlspecialchars(s string) string {
	return html.EscapeString(s)
}

// salaJoin mirrors SalaController::join.
func (a *API) salaJoin(r *http.Request, body map[string]any) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	claseID := bodyInt(body, "claseId")
	if claseID == 0 {
		return salaErr("Missing claseId")
	}

	clase, err := a.DB.QueryOne(ctx(r),
		`SELECT cp.*, u.nombre AS profesor_nombre, u.pais_id AS profesor_pais_id,
			p.simbolo AS prof_simbolo, p.codigo_moneda AS prof_moneda, p.tasa_usd AS prof_tasa,
			m.nombre AS materia_nombre
		 FROM clases_programadas cp
		 JOIN usuarios u ON u.usuarioId = cp.instructorId
		 LEFT JOIN paises p ON p.paisId = u.pais_id
		 LEFT JOIN materias m ON m.materiaId = cp.materiaId
		 WHERE cp.claseId = ? AND cp.activa = 1`, claseID)
	if err != nil {
		return salaErr("Error interno")
	}
	if clase == nil {
		return salaErr("Class not found or inactive")
	}

	psalaID := store.Int(clase["salaId"])
	if psalaID != 0 {
		existing, err := a.DB.QueryOne(ctx(r),
			"SELECT espectadorId, estado FROM espectadores WHERE salaId = ? AND usuarioId = ? AND estado = 'pendiente'",
			psalaID, uid)
		if err != nil {
			return salaErr("Error interno")
		}
		if existing != nil {
			return salaErr("Already in spectator queue, waiting for teacher approval")
		}
	}

	joined, err := a.DB.QueryOne(ctx(r),
		"SELECT COUNT(*) AS cnt FROM sesiones_clase WHERE claseId = ? AND fin IS NULL", claseID)
	if err != nil {
		return salaErr("Error interno")
	}
	joinedN := int64(0)
	if joined != nil {
		joinedN = store.Int(joined["cnt"])
	}
	overCapacity := joinedN >= store.Int(clase["alumnos_max"])

	precioBase := store.Float(clase["precio_base"])
	if precioBase > 0 {
		creditRow, err := a.DB.QueryOne(ctx(r), "SELECT creditos FROM usuarios WHERE usuarioId = ?", uid)
		if err != nil {
			return salaErr("Error interno")
		}
		if creditRow != nil && store.Float(creditRow["creditos"]) < precioBase {
			return salaErr("Insufficient credits")
		}
	}

	student, err := a.DB.QueryOne(ctx(r),
		`SELECT u.pais_id, p.simbolo, p.codigo_moneda, p.tasa_usd
		 FROM usuarios u LEFT JOIN paises p ON p.paisId = u.pais_id
		 WHERE u.usuarioId = ?`, uid)
	if err != nil {
		return salaErr("Error interno")
	}

	precioUSD := store.Float(clase["precio_base"])
	tasa := 1.0
	monedaLocal := "USD"
	simbolo := "$"
	if student != nil {
		tasa = store.Float(student["tasa_usd"])
		if tasa == 0 {
			tasa = 1
		}
		monedaLocal = store.Str(store.Coalesce(student["codigo_moneda"], "USD"))
		simbolo = store.Str(store.Coalesce(student["simbolo"], "$"))
	}
	montoLocal := round2(precioUSD * tasa)

	segundosAcumulados := int64(0)
	existing, err := a.DB.QueryOne(ctx(r),
		`SELECT sesionId, inicio, segundos_acumulados FROM sesiones_clase
		 WHERE claseId=? AND estudianteId=?
		   AND ultima_salida IS NOT NULL
		   AND ultima_salida >= NOW() - INTERVAL 5 MINUTE
		 ORDER BY ultima_salida DESC LIMIT 1`, claseID, uid)
	if err != nil {
		return salaErr("Error interno")
	}

	var sesionID int64
	if existing != nil {
		sesionID = store.Int(existing["sesionId"])
		segundosAcumulados = store.Int(existing["segundos_acumulados"])
		if _, err := a.DB.Exec(ctx(r),
			"UPDATE sesiones_clase SET inicio = NOW(), ultima_salida = NULL WHERE sesionId = ?", sesionID); err != nil {
			return salaErr("Error interno")
		}
	} else {
		activeExisting, err := a.DB.QueryOne(ctx(r),
			"SELECT sesionId FROM sesiones_clase WHERE claseId=? AND estudianteId=? AND fin IS NULL", claseID, uid)
		if err != nil {
			return salaErr("Error interno")
		}
		if activeExisting != nil {
			sesionID = store.Int(activeExisting["sesionId"])
		} else {
			var salaArg any
			if psalaID != 0 {
				salaArg = psalaID
			} else {
				salaArg = nil
			}
			sesionID, err = a.DB.Exec(ctx(r),
				`INSERT INTO sesiones_clase
					(claseId, estudianteId, instructorId, salaId, inicio, precio_usd, monto_local, moneda_local, simbolo_local, espectador)
				 VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, 1)`,
				claseID, uid, clase["instructorId"], salaArg, precioUSD, montoLocal, monedaLocal, simbolo)
			if err != nil {
				return salaErr("Error interno")
			}
		}
	}

	if psalaID != 0 {
		if _, err := a.DB.Exec(ctx(r),
			"INSERT INTO espectadores (salaId, usuarioId, estado) VALUES (?, ?, 'pendiente')",
			psalaID, uid); err != nil {
			return salaErr("Error interno")
		}
		if _, err := a.DB.Exec(ctx(r),
			"INSERT INTO participantes_sala (salaId, usuarioId, camara_activa, microfono_activo) VALUES (?, ?, 0, 0) ON DUPLICATE KEY UPDATE camara_activa=0, microfono_activo=0",
			psalaID, uid); err != nil {
			return salaErr("Error interno")
		}
	}

	return salaOK(map[string]any{
		"sesionId":            sesionID,
		"precio_usd":          precioUSD,
		"monto_local":         montoLocal,
		"moneda_local":        monedaLocal,
		"simbolo":             simbolo,
		"clase_titulo":        store.Str(clase["titulo"]),
		"espectador":          true,
		"overCapacity":        overCapacity,
		"segundos_acumulados": segundosAcumulados,
	})
}

// salaLeave mirrors SalaController::leave.
func (a *API) salaLeave(r *http.Request, body map[string]any) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	sesionID := bodyInt(body, "sesionId")
	intentional := bodyInt(body, "intentional")
	if sesionID == 0 {
		return salaErr("Missing sesionId")
	}

	sesion, err := a.DB.QueryOne(ctx(r),
		`SELECT s.*, cp.instructorId, cp.precio_base, cp.alumnos_max, cp.descuento_nuevo,
			est_p.codigo_moneda AS mon_local, est_p.simbolo AS sim_local, est_p.tasa_usd,
			prof.nombre AS prof_nombre
		 FROM sesiones_clase s
		 JOIN clases_programadas cp ON cp.claseId = s.claseId
		 JOIN usuarios est ON est.usuarioId = s.estudianteId
		 LEFT JOIN paises est_p ON est_p.paisId = est.pais_id
		 JOIN usuarios prof ON prof.usuarioId = cp.instructorId
		 WHERE s.sesionId = ? AND s.estudianteId = ?`, sesionID, uid)
	if err != nil {
		return salaErr("Error interno")
	}
	if sesion == nil {
		return salaErr("Session not found")
	}

	secs := sessionSecondsSince(store.Str(sesion["inicio"]))
	acumulado := store.Int(sesion["segundos_acumulados"]) + secs
	duracionMin := int64(math.Max(1, math.Round(float64(acumulado)/60)))

	allActive, err := a.DB.QueryOne(ctx(r),
		`SELECT COUNT(*) AS cnt FROM sesiones_clase
		 WHERE claseId = ?
		   AND ((fin IS NULL AND ultima_salida IS NULL)
		        OR (ultima_salida IS NOT NULL AND ultima_salida >= NOW() - INTERVAL 5 MINUTE))
		   AND espectador = 0`, sesion["claseId"])
	if err != nil {
		return salaErr("Error interno")
	}
	allActiveN := int64(1)
	if allActive != nil {
		allActiveN = store.Int(allActive["cnt"])
	}
	if allActiveN < 1 {
		allActiveN = 1
	}

	precioBaseUSD := store.Float(sesion["precio_base"])
	precioUSD := round2(precioBaseUSD / float64(allActiveN))
	tasa := store.Float(sesion["tasa_usd"])
	if tasa == 0 {
		tasa = 1
	}
	montoLocal := round2(precioUSD * tasa)
	monLocal := store.Str(store.Coalesce(sesion["mon_local"], "USD"))
	simLocal := store.Str(store.Coalesce(sesion["sim_local"], "$"))

	discountPct := store.Int(sesion["descuento_nuevo"])
	descuentoAplicado := false
	if discountPct > 0 && !a.salaStudentHasPaid(r, store.Int(sesion["claseId"]), uid) {
		precioUSD = round2(precioUSD * (1 - float64(discountPct)/100))
		montoLocal = round2(precioUSD * tasa)
		descuentoAplicado = true
	}

	if intentional != 0 {
		if _, err := a.DB.Exec(ctx(r),
			`UPDATE sesiones_clase
			 SET fin = NOW(), duracion_min = ?, segundos_acumulados = ?,
			     precio_usd = ?, monto_local = ?, moneda_local = ?, simbolo_local = ?
			 WHERE sesionId = ?`,
			duracionMin, acumulado, precioUSD, montoLocal, monLocal, simLocal, sesionID); err != nil {
			return salaErr("Error interno")
		}

		if precioUSD > 0 {
			_, _ = a.DB.Exec(ctx(r),
				`INSERT INTO pagos (sesionId, estudianteId, profesorId, monto_usd, monto_local, moneda_local, simbolo_local, estado)
				 VALUES (?, ?, ?, ?, ?, ?, ?, 'completado')`,
				sesionID, uid, store.Str(sesion["instructorId"]), precioUSD, montoLocal, monLocal, simLocal)
			_, _ = a.DB.Exec(ctx(r), "UPDATE sesiones_clase SET pagado = 1 WHERE sesionId = ?", sesionID)
			_, _ = a.DB.Exec(ctx(r), "UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ?", precioUSD, uid)
		}

		return salaOK(map[string]any{
			"sesionId":          sesionID,
			"duracion_min":      duracionMin,
			"precio_usd":        precioUSD,
			"monto_local":       montoLocal,
			"moneda_local":      monLocal,
			"simbolo":           simLocal,
			"prof_nombre":       store.Str(sesion["prof_nombre"]),
			"descuento_aplicado": descuentoAplicado,
			"descuento_pct":     discountPct,
			"redirect":          "calificar.php?sesion=" + store.Str(sesion["sesionId"]),
		})
	}

	if _, err := a.DB.Exec(ctx(r),
		"UPDATE sesiones_clase SET inicio = NOW(), segundos_acumulados = ?, ultima_salida = NOW() WHERE sesionId=?",
		acumulado, sesionID); err != nil {
		return salaErr("Error interno")
	}
	return salaOK(map[string]any{"paused": true, "sesionId": sesionID})
}

// salaStudentHasPaid reports whether the student has any previous paid session
// in the given class (i.e. is NOT a brand-new student for that class).
func (a *API) salaStudentHasPaid(r *http.Request, claseID, uid int64) bool {
	row, err := a.DB.QueryOne(ctx(r),
		`SELECT COUNT(*) AS cnt FROM sesiones_clase
		 WHERE claseId = ? AND estudianteId = ? AND pagado = 1`, claseID, uid)
	if err != nil || row == nil {
		return false
	}
	return store.Int(row["cnt"]) > 0
}

// salaChat mirrors SalaController::chat (Redis path skipped; MySQL fallback).
func (a *API) salaChat(r *http.Request, body map[string]any) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	salaID := bodyInt(body, "salaId")
	mensaje := strings.TrimSpace(store.Str(body["mensaje"]))
	if salaID == 0 || mensaje == "" {
		return salaErr("Missing data")
	}

	user, err := a.DB.QueryOne(ctx(r), "SELECT nombre FROM usuarios WHERE usuarioId=?", uid)
	if err != nil {
		return salaErr("Error interno")
	}
	alias := "Unknown"
	if user != nil {
		alias = store.Str(user["nombre"])
	}
	if alias == "" {
		alias = "Unknown"
	}
	escaped := htmlspecialchars(mensaje)

	msgID, err := a.DB.Exec(ctx(r),		`INSERT INTO mensajes_chat (salaId, usuarioId, alias, mensaje) VALUES (?, ?, ?, ?)`,
		salaID, uid, alias, escaped)
	if err != nil {
		return salaErr("Error interno")
	}
	return salaOK(map[string]any{
		"alias":     alias,
		"mensaje":   escaped,
		"mensajeId": msgID,
	})
}

// salaMessages mirrors SalaController::messages.
func (a *API) salaMessages(r *http.Request) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	salaID := queryInt(r, "salaId")
	if !a.checkRoomAccess(r, salaID, uid) {
		return salaErr("Access denied")
	}
	afterID := queryInt(r, "afterId")

	msgs, err := a.DB.QueryAll(ctx(r),		`SELECT mensajeId, alias, mensaje, enviado_at FROM mensajes_chat
		 WHERE salaId=? AND mensajeId > ? ORDER BY mensajeId ASC LIMIT 30`, salaID, afterID)
	if err != nil {
		return salaErr("Error interno")
	}
	if msgs == nil {
		msgs = []map[string]any{}
	}
	return salaOK(map[string]any{"messages": msgs})
}

// salaSignal mirrors SalaController::signal.
func (a *API) salaSignal(r *http.Request, body map[string]any) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	salaID := bodyInt(body, "salaId")
	if !a.checkRoomAccess(r, salaID, uid) {
		return salaErr("Access denied")
	}
	toUID := bodyInt(body, "toUid")
	tipo := store.Str(body["tipo"])
	if tipo != "offer" && tipo != "answer" && tipo != "candidate" && tipo != "bye" {
		tipo = ""
	}
	payload := store.Str(body["payload"])
	if salaID == 0 || tipo == "" || payload == "" {
		return salaErr("Missing signal data")
	}

	var toArg any
	if toUID != 0 {
		toArg = toUID
	} else {
		toArg = nil
	}
	sigID, err := a.DB.Exec(ctx(r),		`INSERT INTO webrtc_signals (salaId, from_uid, to_uid, tipo, payload)
		 VALUES (?, ?, ?, ?, ?)`, salaID, uid, toArg, tipo, payload)
	if err != nil {
		return salaErr("Error interno")
	}
	return salaOK(map[string]any{"signalId": sigID})
}

// salaSignals mirrors SalaController::signals and pollSignals.
func (a *API) salaSignals(r *http.Request) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	salaID := queryInt(r, "salaId")
	if !a.checkRoomAccess(r, salaID, uid) {
		return salaErr("Access denied")
	}
	afterID := queryInt(r, "afterId")

	rows, err := a.DB.QueryAll(ctx(r),		`SELECT signalid AS signalId, from_uid, tipo, payload FROM webrtc_signals
		 WHERE salaId=? AND signalid > ?
		   AND (to_uid IS NULL OR to_uid=?) AND from_uid != ?
		 ORDER BY signalid ASC LIMIT 20`, salaID, afterID, uid, uid)
	if err != nil {
		return salaErr("Error interno")
	}
	if rows == nil {
		rows = []map[string]any{}
	}
	return salaOK(map[string]any{"signals": rows})
}

// salaApproveSpectator mirrors SalaController::approveSpectator.
func (a *API) salaApproveSpectator(r *http.Request, body map[string]any) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	espectadorID := bodyInt(body, "espectadorId")
	salaID := bodyInt(body, "salaId")
	if espectadorID == 0 || salaID == 0 {
		return salaErr("Missing espectadorId or salaId")
	}

	clase, err := a.DB.QueryOne(ctx(r), "SELECT instructorId FROM salas WHERE salaId = ?", salaID)
	if err != nil {
		return salaErr("Error interno")
	}
	if clase == nil || store.Int(clase["instructorId"]) != uid {
		return salaErr("Not authorized")
	}

	if _, err := a.DB.Exec(ctx(r),
		"UPDATE espectadores SET estado = 'aprobado', profesor_aprobo = ? WHERE espectadorId = ?", uid, espectadorID); err != nil {
		return salaErr("Error interno")
	}

	claseFull, err := a.DB.QueryOne(ctx(r),
		"SELECT cp.alumnos_max FROM clases_programadas cp WHERE cp.salaId = ? LIMIT 1", salaID)
	if err != nil {
		return salaErr("Error interno")
	}
	activeCount, err := a.DB.QueryOne(ctx(r),
		`SELECT COUNT(*) AS cnt FROM sesiones_clase sc
		 JOIN clases_programadas cp ON cp.claseId = sc.claseId
		 JOIN salas s ON s.salaId = ?
		 WHERE sc.fin IS NULL AND sc.espectador = 0
		   AND (sc.ultima_salida IS NULL OR sc.ultima_salida >= NOW() - INTERVAL 5 MINUTE)`, salaID)
	if err != nil {
		return salaErr("Error interno")
	}
	activeN := int64(0)
	if activeCount != nil {
		activeN = store.Int(activeCount["cnt"])
	}
	if claseFull != nil && activeN >= store.Int(claseFull["alumnos_max"]) {
		if _, err := a.DB.Exec(ctx(r),
			"UPDATE espectadores SET sobre_cupo = 1 WHERE espectadorId = ?", espectadorID); err != nil {
			return salaErr("Error interno")
		}
	}

	espectador, err := a.DB.QueryOne(ctx(r),
		"SELECT usuarioId FROM espectadores WHERE espectadorId = ?", espectadorID)
	if err != nil {
		return salaErr("Error interno")
	}
	if espectador != nil {
		if _, err := a.DB.Exec(ctx(r),
			"UPDATE sesiones_clase SET espectador = 0 WHERE estudianteId = ? AND fin IS NULL",
			espectador["usuarioId"]); err != nil {
			return salaErr("Error interno")
		}
	}
	return salaOK(map[string]any{"message": "Spectator approved"})
}

// salaEndClass mirrors SalaController::endClass.
func (a *API) salaEndClass(r *http.Request, body map[string]any) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	claseID := bodyInt(body, "claseId")
	salaID := bodyInt(body, "salaId")
	if claseID == 0 || salaID == 0 {
		return salaErr("Missing claseId or salaId")
	}

	clase, err := a.DB.QueryOne(ctx(r),
		"SELECT instructorId FROM clases_programadas WHERE claseId = ?", claseID)
	if err != nil {
		return salaErr("Error interno")
	}
	if clase == nil || store.Int(clase["instructorId"]) != uid {
		return salaErr("Not authorized")
	}

	if _, err := a.DB.Exec(ctx(r),
		"UPDATE clases_programadas SET activa = 0 WHERE claseId = ?", claseID); err != nil {
		return salaErr("Error interno")
	}

	sessions, err := a.DB.QueryAll(ctx(r),
		`SELECT sc.sesionId, sc.precio_usd, sc.pagado, sc.fin, sc.ultima_salida, sc.segundos_acumulados,
		        sc.estudianteId, sc.monto_local, sc.moneda_local, sc.simbolo_local
		 FROM sesiones_clase sc
		 WHERE sc.claseId = ? AND sc.instructorId = ?`, claseID, uid)
	if err != nil {
		return salaErr("Error interno")
	}
	tokensGanados := 0.0
	for _, s := range sessions {
		if store.Bool(s["pagado"]) {
			tokensGanados += store.Float(s["precio_usd"])
		}
	}

	if _, err := a.DB.Exec(ctx(r),
		`UPDATE sesiones_clase
		 SET fin = NOW(), duracion_min = COALESCE(duracion_min, GREATEST(1, ROUND(segundos_acumulados/60)))
		 WHERE claseId = ? AND fin IS NULL`, claseID); err != nil {
		return salaErr("Error interno")
	}

	unpaid, err := a.DB.QueryAll(ctx(r),
		`SELECT sc.sesionId, sc.precio_usd, sc.estudianteId, sc.monto_local, sc.moneda_local, sc.simbolo_local
		 FROM sesiones_clase sc
		 WHERE sc.claseId = ? AND sc.pagado = 0 AND sc.fin IS NOT NULL`, claseID)
	if err == nil && unpaid != nil {
		for _, u := range unpaid {
			precio := store.Float(u["precio_usd"])
			if precio <= 0 {
				continue
			}
			_, _ = a.DB.Exec(ctx(r),
				`INSERT INTO pagos (sesionId, estudianteId, profesorId, monto_usd, monto_local, moneda_local, simbolo_local, estado)
				 VALUES (?, ?, ?, ?, ?, ?, ?, 'completado')`,
				u["sesionId"], u["estudianteId"], uid, precio, u["monto_local"], u["moneda_local"], u["simbolo_local"])
			_, _ = a.DB.Exec(ctx(r), "UPDATE sesiones_clase SET pagado = 1 WHERE sesionId = ?", u["sesionId"])
			_, _ = a.DB.Exec(ctx(r), "UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ?", precio, u["estudianteId"])
			tokensGanados += precio
		}
	}

	return salaOK(map[string]any{"tokens_ganados": tokensGanados})
}

// salaRejectSpectator mirrors SalaController::rejectSpectator.
func (a *API) salaRejectSpectator(r *http.Request, body map[string]any) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	espectadorID := bodyInt(body, "espectadorId")
	salaID := bodyInt(body, "salaId")
	if espectadorID == 0 || salaID == 0 {
		return salaErr("Missing espectadorId or salaId")
	}

	clase, err := a.DB.QueryOne(ctx(r), "SELECT instructorId FROM salas WHERE salaId = ?", salaID)
	if err != nil {
		return salaErr("Error interno")
	}
	if clase == nil || store.Int(clase["instructorId"]) != uid {
		return salaErr("Not authorized")
	}

	if _, err := a.DB.Exec(ctx(r),
		"UPDATE espectadores SET estado = 'rechazado', profesor_aprobo = ? WHERE espectadorId = ?", uid, espectadorID); err != nil {
		return salaErr("Error interno")
	}
	return salaOK(map[string]any{"message": "Spectator rejected"})
}

// salaGetSpectators mirrors SalaController::getSpectators.
func (a *API) salaGetSpectators(r *http.Request) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	salaID := queryInt(r, "salaId")
	if salaID == 0 {
		return salaErr("Missing salaId")
	}
	if !a.checkRoomAccess(r, salaID, uid) {
		return salaErr("Access denied")
	}

	spectators, err := a.DB.QueryAll(ctx(r),
		`SELECT e.*, u.nombre, u.username FROM espectadores e
		 JOIN usuarios u ON u.usuarioId = e.usuarioId
		 WHERE e.salaId = ? AND e.estado = 'pendiente'
		 ORDER BY e.created_at ASC`, salaID)
	if err != nil {
		return salaErr("Error interno")
	}
	if spectators == nil {
		spectators = []map[string]any{}
	}
	return salaOK(map[string]any{"spectators": spectators})
}

// salaStudents mirrors SalaController::students.
func (a *API) salaStudents(r *http.Request) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	salaID := queryInt(r, "salaId")
	if salaID == 0 {
		return salaErr("Missing salaId")
	}

	clase, err := a.DB.QueryOne(ctx(r), "SELECT instructorId FROM salas WHERE salaId = ?", salaID)
	if err != nil {
		return salaErr("Error interno")
	}
	if clase == nil || store.Int(clase["instructorId"]) != uid {
		return salaErr("Not authorized")
	}

	students, err := a.DB.QueryAll(ctx(r),
		`SELECT sc.sesionId, sc.estudianteId, sc.espectador, sc.pagado,
			sc.inicio, sc.segundos_acumulados, sc.ultima_salida,
			u.nombre, u.username, u.avatar, u.rol, u.biografia,
			p.nombre AS pais, p.codigo_moneda, p.simbolo,
			GROUP_CONCAT(DISTINCT i.nombre SEPARATOR ', ') AS idiomas
		 FROM sesiones_clase sc
		 JOIN usuarios u ON u.usuarioId = sc.estudianteId
		 LEFT JOIN paises p ON p.paisId = u.pais_id
		 LEFT JOIN usuario_idiomas ui ON ui.usuarioId = u.usuarioId
		 LEFT JOIN idiomas i ON i.idiomaId = ui.idiomaId
		 WHERE sc.claseId = (SELECT claseId FROM salas WHERE salaId = ?)
		   AND sc.fin IS NULL
		 GROUP BY sc.sesionId
		 ORDER BY sc.inicio ASC`, salaID)
	if err != nil {
		return salaErr("Error interno")
	}

	base := baseURLOf(r)
	for _, st := range students {
		av := store.Str(st["avatar"])
		if av != "" {
			st["avatar_url"] = base + "/" + av
		} else {
			st["avatar_url"] = ""
		}
		esGratis := 0
		if store.Int(st["segundos_acumulados"]) < 180 {
			esGratis = 1
		}
		st["es_gratis"] = esGratis
	}
	if students == nil {
		students = []map[string]any{}
	}
	return salaOK(map[string]any{"students": students})
}

// salaKickStudent mirrors SalaController::kickStudent.
func (a *API) salaKickStudent(r *http.Request, body map[string]any) *resp {
	uid, errResp := a.salaUID(r)
	if errResp != nil {
		return errResp
	}
	salaID := bodyInt(body, "salaId")
	estudianteID := bodyInt(body, "estudianteId")
	comentario := strings.TrimSpace(store.Str(body["comentario"]))
	if salaID == 0 || estudianteID == 0 || comentario == "" {
		return salaErr("Missing data")
	}

	clase, err := a.DB.QueryOne(ctx(r), "SELECT instructorId FROM salas WHERE salaId = ?", salaID)
	if err != nil {
		return salaErr("Error interno")
	}
	if clase == nil || store.Int(clase["instructorId"]) != uid {
		return salaErr("Not authorized")
	}

	if _, err := a.DB.Exec(ctx(r),
		"INSERT INTO sanciones (salaId, instructorId, estudianteId, comentario) VALUES (?, ?, ?, ?)",
		salaID, uid, estudianteID, comentario); err != nil {
		return salaErr("Error interno")
	}
	if _, err := a.DB.Exec(ctx(r),
		`UPDATE sesiones_clase sc
		 JOIN salas s ON s.claseId = sc.claseId
		 SET sc.fin = NOW(), sc.segundos_acumulados = COALESCE(sc.segundos_acumulados, 0)
		 WHERE s.salaId = ? AND sc.estudianteId = ? AND sc.fin IS NULL`,
		salaID, estudianteID); err != nil {
		return salaErr("Error interno")
	}
	return salaOK(map[string]any{"message": "Student expelled"})
}

// sessionSecondsSince returns the whole seconds elapsed since the given MySQL
// datetime string, clamped at 0.
func sessionSecondsSince(dt string) int64 {
	t, err := time.Parse("2006-01-02 15:04:05", dt)
	if err != nil {
		return 0
	}
	secs := int64(time.Since(t).Seconds())
	if secs < 0 {
		return 0
	}
	return secs
}
