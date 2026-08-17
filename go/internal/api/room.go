package api

import (
	"net/http"

	"classexpress/internal/store"
)

// joinRoom mirrors RoomController::joinRoom.
func (a *API) joinRoom(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	sid := bodyInt(body, "sala_id")
	if sid == 0 {
		return errOut(http.StatusBadRequest, "sala_id requerido")
	}

	sala, err := a.DB.QueryOne(ctx(r),
		`SELECT s.salaId AS id, s.claseId, s.activa, s.created_at, cp.claseId AS clase_id, cp.precio_base AS precio, cp.instructorId, cp.alumnos_max
		 FROM salas s
		 JOIN clases_programadas cp ON cp.claseId = s.claseId
		 WHERE s.salaId = ? AND s.activa = true`, sid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if sala == nil {
		return errOut(http.StatusNotFound, "Sala no encontrada o inactiva")
	}

	if store.Str(user["rol"]) == "estudiante" && store.Int(user["creditos"]) < store.Int(sala["precio"]) {
		return errOut(http.StatusPaymentRequired, "Créditos insuficientes")
	}

	uid := store.Int(user["id"])
	claseID := store.Int(sala["clase_id"])

	existing, err := a.DB.QueryOne(ctx(r),
		`SELECT sesionId FROM sesiones_clase
		 WHERE claseId = ? AND estudianteId = ?
		   AND ultima_salida IS NOT NULL
		   AND ultima_salida >= NOW() - INTERVAL 5 MINUTE
		 ORDER BY ultima_salida DESC LIMIT 1`, claseID, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	if existing != nil {
		if _, err := a.DB.Exec(ctx(r),
			"UPDATE sesiones_clase SET inicio = NOW(), ultima_salida = NULL WHERE sesionId = ?",
			existing["sesionId"]); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
	} else {
		active, err := a.DB.QueryOne(ctx(r),
			"SELECT sesionId FROM sesiones_clase WHERE claseId = ? AND estudianteId = ? AND fin IS NULL",
			claseID, uid)
		if err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		if active == nil {
			if _, err := a.DB.Exec(ctx(r),
				`INSERT INTO sesiones_clase (claseId, estudianteId, instructorId, salaId, inicio, precio_usd, espectador)
				 VALUES (?, ?, ?, ?, NOW(), ?, 1)`,
				claseID, uid, sala["instructorId"], sid, store.Float(sala["precio"])); err != nil {
				return errOut(http.StatusInternalServerError, "Error interno")
			}
		}
	}

	if _, err := a.DB.Exec(ctx(r),
		"INSERT INTO participantes_sala (salaId, usuarioId, joined_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE joined_at = NOW()",
		sid, uid); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	return okOut(map[string]any{"sala": sala})
}

// leaveRoom mirrors RoomController::leaveRoom.
func (a *API) leaveRoom(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	sid := bodyInt(body, "sala_id")
	uid := store.Int(user["id"])

	rol := store.Str(user["rol"])
	if rol == "instructor" || rol == "both" {
		if _, err := a.DB.Exec(ctx(r), "UPDATE salas SET activa = false WHERE salaId = ?", sid); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		if _, err := a.DB.Exec(ctx(r), "UPDATE participantes_sala SET activo = false, left_at = NOW() WHERE salaId = ?", sid); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		return okOut(map[string]any{"ok": true, "closed": true})
	}

	if _, err := a.DB.Exec(ctx(r),
		`UPDATE sesiones_clase
		 SET segundos_acumulados = segundos_acumulados + TIMESTAMPDIFF(SECOND, inicio, NOW()),
		     ultima_salida = NOW(),
		     inicio = NOW()
		 WHERE salaId = ? AND estudianteId = ? AND fin IS NULL
		   AND (ultima_salida IS NULL OR ultima_salida < NOW() - INTERVAL 5 MINUTE)`,
		sid, uid); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	if _, err := a.DB.Exec(ctx(r),
		"UPDATE participantes_sala SET activo = false, left_at = NOW() WHERE salaId = ? AND usuarioId = ?",
		sid, uid); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"ok": true})
}

// roomStatus mirrors RoomController::roomStatus.
func (a *API) roomStatus(r *http.Request) *resp {
	if _, errResp := a.authUser(r, map[string]any{}); errResp != nil {
		return errResp
	}
	sid := queryInt(r, "sala_id")

	sala, err := a.DB.QueryOne(ctx(r),
		`SELECT s.salaId AS id, s.claseId, s.activa, s.created_at, cp.titulo AS clase, cp.precio_base AS precio FROM salas s
		 JOIN clases_programadas cp ON cp.claseId = s.claseId WHERE s.salaId = ?`, sid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if sala == nil {
		return errOut(http.StatusNotFound, "Sala no encontrada")
	}

	participantes, err := a.DB.QueryAll(ctx(r),
		`SELECT u.usuarioId AS id, u.nombre, u.rol FROM participantes_sala p
		 JOIN usuarios u ON u.usuarioId = p.usuarioId
		 WHERE p.salaId = ? AND p.activo = true`, sid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	messages, err := a.DB.QueryAll(ctx(r),
		`SELECT m.*, u.nombre AS usuario FROM mensajes_chat m
		 JOIN usuarios u ON u.usuarioId = m.usuarioId
		 WHERE m.salaId = ? ORDER BY m.enviado_at ASC LIMIT 100`, sid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	return okOut(map[string]any{"sala": sala, "participantes": participantes, "messages": messages})
}

// sendMessage mirrors RoomController::sendMessage.
func (a *API) sendMessage(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	sid := bodyInt(body, "sala_id")
	msg := store.Str(body["mensaje"])
	if sid == 0 || msg == "" {
		return errOut(http.StatusBadRequest, "Datos requeridos")
	}

	if _, err := a.DB.Exec(ctx(r),
		"INSERT INTO mensajes_chat (salaId, usuarioId, mensaje) VALUES (?, ?, ?)",
		sid, user["id"], msg); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	row, err := a.DB.QueryOne(ctx(r),
		`SELECT m.mensajeId AS id, m.usuarioId AS usuario_id, m.salaId, m.mensaje, m.enviado_at AS created_at, u.nombre AS usuario FROM mensajes_chat m
		 JOIN usuarios u ON u.usuarioId = m.usuarioId
		 WHERE m.salaId = ? ORDER BY m.mensajeId DESC LIMIT 1`, sid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"mensaje": row})
}

// messages mirrors RoomController::messages.
func (a *API) messages(r *http.Request) *resp {
	if _, errResp := a.authUser(r, map[string]any{}); errResp != nil {
		return errResp
	}
	sid := queryInt(r, "sala_id")
	after := queryInt(r, "after")

	sqlStr := `SELECT m.mensajeId AS id, m.usuarioId AS usuario_id, m.salaId, m.mensaje, m.enviado_at AS created_at, u.nombre AS usuario FROM mensajes_chat m
		JOIN usuarios u ON u.usuarioId = m.usuarioId
		WHERE m.salaId = ?`
	args := []any{sid}
	if after > 0 {
		sqlStr += " AND m.mensajeId > ?"
		args = append(args, after)
	}
	sqlStr += " ORDER BY m.enviado_at ASC LIMIT 50"

	rows, err := a.DB.QueryAll(ctx(r), sqlStr, args...)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"messages": rows})
}

// signal mirrors RoomController::signal.
func (a *API) signal(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	salaID := bodyInt(body, "sala_id")
	tipo := store.Str(body["tipo"])
	if tipo != "offer" && tipo != "answer" && tipo != "candidate" && tipo != "bye" {
		tipo = ""
	}
	payload := bodyStr(body, "payload")
	toUID := bodyInt(body, "to_uid")
	var toArg any
	if toUID > 0 {
		toArg = toUID
	} else {
		toArg = nil
	}

	if salaID == 0 || tipo == "" || payload == "" {
		return errOut(http.StatusBadRequest, "Datos de señal requeridos")
	}

	fromUID := store.Int(user["usuarioId"])
	inRoom, err := a.DB.QueryOne(ctx(r),
		"SELECT 1 FROM participantes_sala WHERE salaId = ? AND usuarioId = ? AND activo = true",
		salaID, fromUID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if inRoom == nil {
		return errOut(http.StatusForbidden, "No estás en esta sala")
	}

	if _, err := a.DB.Exec(ctx(r),
		"INSERT INTO webrtc_signals (salaId, from_uid, to_uid, tipo, payload) VALUES (?, ?, ?, ?, ?)",
		salaID, fromUID, toArg, tipo, payload); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"ok": true})
}

// pollSignals mirrors RoomController::pollSignals.
func (a *API) pollSignals(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	salaID := queryInt(r, "sala_id")
	afterID := queryInt(r, "after_id")
	uid := store.Int(user["usuarioId"])

	if salaID == 0 {
		return errOut(http.StatusBadRequest, "sala_id requerido")
	}

	rows, err := a.DB.QueryAll(ctx(r),
		`SELECT signalid AS "signalId", from_uid, tipo, payload FROM webrtc_signals
		 WHERE salaId = ? AND signalid > ?
		   AND (to_uid IS NULL OR to_uid = ?)
		   AND from_uid != ?
		 ORDER BY signalid ASC LIMIT 20`,
		salaID, afterID, uid, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if rows == nil {
		rows = []map[string]any{}
	}
	return okOut(map[string]any{"signals": rows})
}

// roomStudents mirrors RoomController::roomStudents.
func (a *API) roomStudents(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	salaID := queryInt(r, "salaId")
	if salaID == 0 {
		return errOut(http.StatusBadRequest, "Sala requerida")
	}

	row, err := a.DB.QueryOne(ctx(r), "SELECT instructorId FROM salas WHERE salaId = ?", salaID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if row == nil || store.Int(row["instructorId"]) != store.Int(user["id"]) {
		return errOut(http.StatusForbidden, "No autorizado")
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
		return errOut(http.StatusInternalServerError, "Error interno")
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

	return okOut(map[string]any{"students": students})
}

// kickStudent mirrors RoomController::kickStudent.
func (a *API) kickStudent(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	salaID := bodyInt(body, "salaId")
	estudianteID := bodyInt(body, "estudianteId")
	comentario := store.Str(body["comentario"])

	if salaID == 0 || estudianteID == 0 || comentario == "" {
		return errOut(http.StatusBadRequest, "Missing data")
	}

	clase, err := a.DB.QueryOne(ctx(r), "SELECT instructorId FROM salas WHERE salaId = ?", salaID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if clase == nil || store.Int(clase["instructorId"]) != store.Int(user["id"]) {
		return errOut(http.StatusForbidden, "Not authorized")
	}

	if _, err := a.DB.Exec(ctx(r),
		"INSERT INTO sanciones (salaId, instructorId, estudianteId, comentario) VALUES (?, ?, ?, ?)",
		salaID, user["id"], estudianteID, comentario); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	if _, err := a.DB.Exec(ctx(r),
		`UPDATE sesiones_clase sc
		 JOIN salas s ON s.claseId = sc.claseId
		 SET sc.fin = NOW(), sc.segundos_acumulados = COALESCE(sc.segundos_acumulados, 0)
		 WHERE s.salaId = ? AND sc.estudianteId = ? AND sc.fin IS NULL`,
		salaID, estudianteID); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	return okOut(map[string]any{"ok": true, "message": "Student expelled"})
}

// startRoom mirrors RoomController::startRoom.
func (a *API) startRoom(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	rol := store.Str(user["rol"])
	if rol != "instructor" && rol != "both" {
		return errOut(http.StatusForbidden, "Solo instructores")
	}

	claseID := bodyInt(body, "clase_id")
	clase, err := a.DB.QueryOne(ctx(r),
		"SELECT * FROM clases_programadas WHERE claseId = ? AND instructorId = ?",
		claseID, user["id"])
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if clase == nil {
		return errOut(http.StatusNotFound, "Clase no encontrada")
	}

	if _, err := a.DB.Exec(ctx(r), "UPDATE salas SET activa = false WHERE claseId = ? AND activa = true", claseID); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if _, err := a.DB.Exec(ctx(r),
		"INSERT INTO salas (claseId, titulo, curso, instructorId) VALUES (?, ?, '', ?)",
		claseID, store.Str(clase["titulo"]), store.Str(clase["instructorId"])); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	sala, err := a.DB.QueryOne(ctx(r),
		"SELECT salaId AS id, claseId, activa, created_at FROM salas WHERE claseId = ? ORDER BY salaId DESC LIMIT 1", claseID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	if _, err := a.DB.Exec(ctx(r),
		"INSERT INTO participantes_sala (salaId, usuarioId, activo) VALUES (?, ?, true) ON DUPLICATE KEY UPDATE activo = true",
		sala["id"], user["id"]); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	return okOut(map[string]any{"sala": sala})
}

// activeRooms mirrors RoomController::activeRooms.
func (a *API) activeRooms(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	rooms, err := a.DB.QueryAll(ctx(r),
		`SELECT s.salaId AS id, s.claseId, s.activa, s.created_at, cp.titulo AS clase, cp.precio_base AS precio
		 FROM salas s JOIN clases_programadas cp ON cp.claseId = s.claseId
		 WHERE cp.instructorId = ? AND s.activa = true`, user["usuarioId"])
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"rooms": rooms})
}

// sessionStatus mirrors RoomController::sessionStatus.
func (a *API) sessionStatus(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["usuarioId"])
	sid := queryInt(r, "sesion_id")

	sesion, err := a.DB.QueryOne(ctx(r),
		`SELECT sc.sesionId, sc.claseId, sc.estudianteId, sc.pagado, sc.fin,
			sc.precio_usd, sc.monto_local,
			cp.titulo, cp.precio_base, cp.instructorId, cp.materiaId,
			prof.nombre AS instructor_nombre, prof.avatar AS instructor_avatar
		 FROM sesiones_clase sc
		 JOIN clases_programadas cp ON cp.claseId = sc.claseId
		 JOIN usuarios prof ON prof.usuarioId = cp.instructorId
		 WHERE sc.sesionId = ? AND sc.estudianteId = ?`, sid, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if sesion == nil {
		return errOut(http.StatusNotFound, "Sesión no encontrada")
	}

	precio := store.Float(sesion["precio_usd"])
	if precio <= 0 {
		precio = store.Float(sesion["precio_base"])
	}

	return okOut(map[string]any{
		"sesion": map[string]any{
			"sesionId":          store.Int(sesion["sesionId"]),
			"claseId":           store.Int(sesion["claseId"]),
			"pagado":            store.Bool(sesion["pagado"]),
			"fin":               store.Str(sesion["fin"]),
			"precio":            precio,
			"titulo":            store.Str(sesion["titulo"]),
			"instructorId":      store.Int(sesion["instructorId"]),
			"instructor_nombre": store.Str(sesion["instructor_nombre"]),
			"instructor_avatar": store.Str(sesion["instructor_avatar"]),
			"materiaId":         store.Int(sesion["materiaId"]),
		},
		"balance": store.Int(user["creditos"]),
	})
}

// rateSession mirrors RoomController::rateSession.
func (a *API) rateSession(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["id"])
	salaID := bodyInt(body, "sala_id")
	rating := bodyInt(body, "rating")
	comentario := store.Str(body["comentario"])

	if salaID == 0 || rating < 1 || rating > 5 {
		return errOut(http.StatusBadRequest, "Datos inválidos")
	}

	row, err := a.DB.QueryOne(ctx(r),
		"SELECT cp.instructorId FROM salas s JOIN clases_programadas cp ON cp.claseId = s.claseId WHERE s.salaId = ?",
		salaID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if row == nil {
		return errOut(http.StatusNotFound, "Sala no encontrada")
	}
	profID := store.Int(row["instructorId"])

	sesion, err := a.DB.QueryOne(ctx(r),
		`SELECT sc.sesionId FROM sesiones_clase sc
		 JOIN salas s ON s.claseId = sc.claseId
		 WHERE s.salaId = ? AND sc.estudianteId = ? AND sc.fin IS NOT NULL
		 ORDER BY sc.fin DESC LIMIT 1`, salaID, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	sesionID := int64(0)
	if sesion != nil {
		sesionID = store.Int(sesion["sesionId"])
	}

	if sesionID != 0 {
		existing, err := a.DB.QueryOne(ctx(r), "SELECT resenaId FROM resenas WHERE sesionId = ?", sesionID)
		if err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		if existing != nil {
			if _, err := a.DB.Exec(ctx(r), "UPDATE resenas SET rating = ?, comentario = ? WHERE sesionId = ?", rating, comentario, sesionID); err != nil {
				return errOut(http.StatusInternalServerError, "Error interno")
			}
		} else {
			if _, err := a.DB.Exec(ctx(r),
				"INSERT INTO resenas (sesionId, estudianteId, profesorId, rating, comentario) VALUES (?, ?, ?, ?, ?)",
				sesionID, uid, profID, rating, comentario); err != nil {
				return errOut(http.StatusInternalServerError, "Error interno")
			}
		}
	}

	prof, err := a.DB.QueryOne(ctx(r), "SELECT calificacion, num_resenas FROM usuarios WHERE usuarioId = ?", profID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	curAvg := store.Float(prof["calificacion"])
	curCount := store.Int(prof["num_resenas"])
	newCount := curCount + 1
	newAvg := (curAvg*float64(curCount) + float64(rating)) / float64(max(1, newCount))

	if _, err := a.DB.Exec(ctx(r),
		"UPDATE usuarios SET calificacion = ?, num_resenas = ? WHERE usuarioId = ?",
		round2(newAvg), newCount, profID); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"ok": true})
}
