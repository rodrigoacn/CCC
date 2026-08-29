package api

import (
	"net/http"
	"strings"

	"classexpress/internal/store"
)

// friends mirrors SocialController::friends.
func (a *API) friends(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["id"])

	siguiendo, err := a.DB.QueryAll(ctx(r),
		`SELECT u.usuarioId AS usuarioid, u.nombre, u.username, u.avatar, u.rol, u.creditos, u.calificacion, u.num_resenas, r.created_at AS seguido_desde
		 FROM relaciones r
		 JOIN usuarios u ON u.usuarioId = r.seguidoId
		 WHERE r.seguidorId = ? AND r.estado = 'following'
		 ORDER BY r.created_at DESC`, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	seguidores, err := a.DB.QueryAll(ctx(r),
		`SELECT u.usuarioId AS usuarioid, u.nombre, u.username, u.avatar, u.rol, u.creditos, u.calificacion, u.num_resenas, r.created_at AS sigue_desde
		 FROM relaciones r
		 JOIN usuarios u ON u.usuarioId = r.seguidorId
		 WHERE r.seguidoId = ? AND r.estado = 'following'
		 ORDER BY r.created_at DESC`, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	me, err := a.DB.QueryOne(ctx(r), "SELECT username FROM usuarios WHERE usuarioId = ?", uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	base := baseURLOf(r)
	for _, s := range siguiendo {
		if av := store.Str(s["avatar"]); av != "" {
			s["avatar"] = base + "/" + av
		}
	}
	for _, s := range seguidores {
		if av := store.Str(s["avatar"]); av != "" {
			s["avatar"] = base + "/" + av
		}
	}

	username := ""
	if me != nil {
		username = store.Str(me["username"])
	}
	return okOut(map[string]any{
		"siguiendo":  siguiendo,
		"seguidores": seguidores,
		"username":   username,
	})
}

// follow mirrors SocialController::follow.
func (a *API) follow(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["id"])
	targetID := bodyInt(body, "usuario_id")
	if targetID == 0 || targetID == uid {
		return errOut(http.StatusBadRequest, "Usuario inválido")
	}

	exists, err := a.DB.QueryOne(ctx(r),
		"SELECT id, estado FROM relaciones WHERE seguidorId = ? AND seguidoId = ?", uid, targetID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if exists != nil {
		if store.Str(exists["estado"]) == "following" {
			if _, err := a.DB.Exec(ctx(r), "DELETE FROM relaciones WHERE id = ?", exists["id"]); err != nil {
				return errOut(http.StatusInternalServerError, "Error interno")
			}
			return okOut(map[string]any{"ok": true, "siguiendo": false})
		}
		if _, err := a.DB.Exec(ctx(r), "UPDATE relaciones SET estado = 'following' WHERE id = ?", exists["id"]); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		return okOut(map[string]any{"ok": true, "siguiendo": true})
	}

	if _, err := a.DB.Exec(ctx(r),
		"INSERT INTO relaciones (seguidorId, seguidoId, estado) VALUES (?, ?, 'following')", uid, targetID); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"ok": true, "siguiendo": true})
}

// unfriend mirrors SocialController::unfriend.
func (a *API) unfriend(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["id"])
	targetID := bodyInt(body, "usuario_id")
	if targetID == 0 {
		return errOut(http.StatusBadRequest, "Usuario requerido")
	}

	if _, err := a.DB.Exec(ctx(r),
		"DELETE FROM relaciones WHERE (seguidorId = ? AND seguidoId = ?) OR (seguidorId = ? AND seguidoId = ?)",
		uid, targetID, targetID, uid); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"ok": true})
}

// sendDirectMessage mirrors SocialController::sendDirectMessage.
func (a *API) sendDirectMessage(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["id"])
	toID := bodyInt(body, "destinatario_id")
	msg := strings.TrimSpace(store.Str(body["mensaje"]))
	if toID == 0 || msg == "" {
		return errOut(http.StatusBadRequest, "Datos requeridos")
	}

	rel, err := a.DB.QueryOne(ctx(r),
		`SELECT id FROM relaciones
		 WHERE ((seguidorId = ? AND seguidoId = ?) OR (seguidorId = ? AND seguidoId = ?))
		   AND estado = 'following'`,
		uid, toID, toID, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if rel == nil {
		return errOut(http.StatusForbidden, "Solo puedes enviar mensajes a amigos")
	}

	lastID, err := a.DB.Exec(ctx(r),
		"INSERT INTO mensajes_directos (remitente_id, destinatario_id, mensaje) VALUES (?, ?, ?)", uid, toID, msg)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	row, err := a.DB.QueryOne(ctx(r),
		`SELECT md.*, u.nombre AS remitente_nombre
		 FROM mensajes_directos md
		 JOIN usuarios u ON u.usuarioId = md.remitente_id
		 WHERE md.id = ?`, lastID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"ok": true, "mensaje": row})
}

// getDirectMessages mirrors SocialController::getDirectMessages.
func (a *API) getDirectMessages(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["id"])
	conID := queryInt(r, "con")
	after := queryInt(r, "after")

	var where string
	var args []any
	if conID != 0 {
		where = "AND ((md.remitente_id = ? AND md.destinatario_id = ?) OR (md.remitente_id = ? AND md.destinatario_id = ?))"
		args = []any{uid, conID, conID, uid}
	} else {
		where = "AND (md.destinatario_id = ? OR md.remitente_id = ?)"
		args = []any{uid, uid}
	}
	if after != 0 {
		where += " AND md.id > ?"
		args = append(args, after)
	}

	sqlStr := `SELECT md.*, u.nombre AS remitente_nombre
		FROM mensajes_directos md
		JOIN usuarios u ON u.usuarioId = md.remitente_id
		WHERE 1=1 ` + where + `
		ORDER BY md.id DESC LIMIT 50`

	msgs, err := a.DB.QueryAll(ctx(r), sqlStr, args...)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	for i, j := 0, len(msgs)-1; i < j; i, j = i+1, j-1 {
		msgs[i], msgs[j] = msgs[j], msgs[i]
	}

	if conID != 0 {
		if _, err := a.DB.Exec(ctx(r),
			"UPDATE mensajes_directos SET leido = 1 WHERE destinatario_id = ? AND remitente_id = ? AND leido = 0",
			uid, conID); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
	}
	if msgs == nil {
		msgs = []map[string]any{}
	}
	return okOut(map[string]any{"mensajes": msgs})
}

// searchPeople mirrors SocialController::searchPeople.
func (a *API) searchPeople(r *http.Request) *resp {
	q := strings.TrimSpace(r.URL.Query().Get("q"))
	if len(q) < 1 {
		return errOut(http.StatusBadRequest, "Consulta muy corta")
	}

	users, err := a.DB.QueryAll(ctx(r),
		`SELECT u.usuarioId AS id, u.nombre, u.username, u.avatar, u.rol, u.calificacion, u.num_resenas, u.biografia,
			p.nombre AS pais
		 FROM usuarios u
		 LEFT JOIN paises p ON p.paisId = u.pais_id
		 WHERE u.nombre LIKE ? OR u.username LIKE ?
		 ORDER BY u.num_resenas DESC, u.calificacion DESC
		 LIMIT 30`,
		"%"+q+"%", "%"+q+"%")
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	base := baseURLOf(r)
	for _, u := range users {
		if av := store.Str(u["avatar"]); av != "" {
			u["avatar"] = base + "/" + av
		}
		u["rating"] = store.Float(u["calificacion"])
		u["reviews"] = store.Int(u["num_resenas"])
		delete(u, "calificacion")
		delete(u, "num_resenas")
	}
	if users == nil {
		users = []map[string]any{}
	}
	return okOut(map[string]any{"people": users})
}

// userProfile mirrors SocialController::userProfile.
func (a *API) userProfile(r *http.Request, body map[string]any) *resp {
	targetID := bodyInt(body, "usuario_id")
	if targetID == 0 {
		targetID = queryInt(r, "usuario_id")
	}
	if targetID == 0 {
		return errOut(http.StatusBadRequest, "Usuario requerido")
	}

	user, err := a.DB.QueryOne(ctx(r),
		`SELECT u.*, p.nombre AS pais, p.codigo_moneda, p.simbolo
		 FROM usuarios u
		 LEFT JOIN paises p ON p.paisId = u.pais_id
		 WHERE u.usuarioId = ?`, targetID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if user == nil {
		return errOut(http.StatusNotFound, "Usuario no encontrado")
	}

	idLang, err := a.DB.QueryAll(ctx(r),
		"SELECT i.nombre FROM usuario_idiomas ui JOIN idiomas i ON i.idiomaId = ui.idiomaId WHERE ui.usuarioId = ?", targetID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	idiomas := []string{}
	for _, row := range idLang {
		idiomas = append(idiomas, store.Str(row["nombre"]))
	}

	esProfesor := store.Str(user["rol"]) == "instructor" || store.Str(user["rol"]) == "both"

	resenas := []map[string]any{}
	if esProfesor {
		resenas, err = a.DB.QueryAll(ctx(r),
			`SELECT r.*, u.nombre AS estudiante_nombre, u.avatar AS estudiante_avatar
			 FROM resenas r
			 JOIN usuarios u ON u.usuarioId = r.estudianteId
			 WHERE r.profesorId = ?
			 ORDER BY r.created_at DESC LIMIT 50`, targetID)
		if err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		base := baseURLOf(r)
		for _, res := range resenas {
			if av := store.Str(res["estudiante_avatar"]); av != "" {
				res["estudiante_avatar"] = base + "/" + av
			}
		}
	}

	siguiendo := false
	if authUser, errResp := a.authUser(r, map[string]any{}); errResp == nil {
		if store.Int(authUser["id"]) != targetID {
			rel, err := a.DB.QueryOne(ctx(r),
				"SELECT id FROM relaciones WHERE seguidorId = ? AND seguidoId = ? AND estado = 'following'",
				store.Int(authUser["id"]), targetID)
			if err != nil {
				return errOut(http.StatusInternalServerError, "Error interno")
			}
			siguiendo = rel != nil
		}
	}

	clases := []map[string]any{}
	if esProfesor {
		clases, err = a.DB.QueryAll(ctx(r),
			`SELECT cp.claseId AS id, cp.titulo, cp.precio_base, cp.duracion_min AS duracion, cp.activa,
				m.nombre AS materia, m.icono, m.color,
				(SELECT COUNT(*) FROM sesiones_clase sc WHERE sc.claseId = cp.claseId AND sc.fin IS NULL) AS alumnos_activos
			 FROM clases_programadas cp
			 LEFT JOIN materias m ON m.materiaId = cp.materiaId
			 WHERE cp.instructorId = ? AND cp.activa = 1
			 ORDER BY cp.created_at DESC
			 LIMIT 10`, targetID)
		if err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
	}

	base := baseURLOf(r)
	avatar := ""
	if store.Str(user["avatar"]) != "" {
		avatar = base + "/" + store.Str(user["avatar"])
	}

	profile := map[string]any{
		"id":           store.Int(user["usuarioId"]),
		"nombre":       store.Str(user["nombre"]),
		"username":     store.Str(user["username"]),
		"email":        store.Str(user["email"]),
		"rol":          store.Str(user["rol"]),
		"avatar":       avatar,
		"biografia":    store.Str(user["biografia"]),
		"pais":         store.Str(user["pais"]),
		"idiomas":      idiomas,
		"calificacion": store.Float(user["calificacion"]),
		"num_resenas":  store.Int(user["num_resenas"]),
		"privacidad":   store.Str(store.Coalesce(user["privacidad"], "private")),
		"created_at":   store.Str(user["created_at"]),
		"resenas":      resenas,
		"siguiendo":    siguiendo,
		"clases":       clases,
	}
	if clases == nil {
		profile["clases"] = []map[string]any{}
	}
	if resenas == nil {
		profile["resenas"] = []map[string]any{}
	}

	return okOut(map[string]any{"profile": profile})
}

// resenasProfesor mirrors SocialController::resenasProfesor.
func (a *API) resenasProfesor(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	targetID := queryInt(r, "profesor_id")
	if targetID == 0 {
		targetID = store.Int(user["id"])
	}

	resenas, err := a.DB.QueryAll(ctx(r),
		`SELECT r.*, u.nombre AS estudiante_nombre, u.avatar AS estudiante_avatar
		 FROM resenas r
		 JOIN usuarios u ON u.usuarioId = r.estudianteId
		 WHERE r.profesorId = ?
		 ORDER BY r.created_at DESC LIMIT 50`, targetID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	base := baseURLOf(r)
	for _, res := range resenas {
		if av := store.Str(res["estudiante_avatar"]); av != "" {
			res["estudiante_avatar"] = base + "/" + av
		}
	}
	if resenas == nil {
		resenas = []map[string]any{}
	}
	return okOut(map[string]any{"resenas": resenas})
}

// getFollowerChat gets messages between current user and a follower (mutual friend).
func (a *API) getFollowerChat(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["id"])
	targetID := queryInt(r, "usuario_id")
	after := queryInt(r, "after")

	if targetID == 0 {
		return errOut(http.StatusBadRequest, "Usuario requerido")
	}

	// Verify they are mutual followers (friends)
	rel, err := a.DB.QueryOne(ctx(r),
		`SELECT id FROM relaciones
		 WHERE ((seguidorId = ? AND seguidoId = ?) OR (seguidorId = ? AND seguidoId = ?))
		   AND estado = 'following'`,
		uid, targetID, targetID, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if rel == nil {
		return errOut(http.StatusForbidden, "Solo puedes chatear con seguidores mutuos")
	}

	var where string
	var args []any
	if after != 0 {
		where = "AND md.id > ?"
		args = []any{uid, targetID, targetID, uid, after}
	} else {
		args = []any{uid, targetID, targetID, uid}
	}

	sqlStr := `SELECT md.*, u.nombre AS remitente_nombre, u.avatar AS remitente_avatar
		FROM mensajes_directos md
		JOIN usuarios u ON u.usuarioId = md.remitente_id
		WHERE 1=1
		AND ((md.remitente_id = ? AND md.destinatario_id = ?) OR (md.remitente_id = ? AND md.destinatario_id = ?))
		` + where + `
		ORDER BY md.id DESC LIMIT 50`

	msgs, err := a.DB.QueryAll(ctx(r), sqlStr, args...)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	for i, j := 0, len(msgs)-1; i < j; i, j = i+1, j-1 {
		msgs[i], msgs[j] = msgs[j], msgs[i]
	}
	if msgs == nil {
		msgs = []map[string]any{}
	}
	base := baseURLOf(r)
	for _, m := range msgs {
		if av := store.Str(m["remitente_avatar"]); av != "" {
			m["remitente_avatar"] = base + "/" + av
		}
	}
	return okOut(map[string]any{"mensajes": msgs})
}

// sendFollowerMessage sends a message to a follower (mutual friend).
func (a *API) sendFollowerMessage(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["id"])
	toID := bodyInt(body, "usuario_id")
	msg := strings.TrimSpace(store.Str(body["mensaje"]))
	if toID == 0 || msg == "" {
		return errOut(http.StatusBadRequest, "Datos requeridos")
	}

	// Verify mutual following
	rel, err := a.DB.QueryOne(ctx(r),
		`SELECT id FROM relaciones
		 WHERE ((seguidorId = ? AND seguidoId = ?) OR (seguidorId = ? AND seguidoId = ?))
		   AND estado = 'following'`,
		uid, toID, toID, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if rel == nil {
		return errOut(http.StatusForbidden, "Solo puedes chatear con seguidores mutuos")
	}

	lastID, err := a.DB.Exec(ctx(r),
		"INSERT INTO mensajes_directos (remitente_id, destinatario_id, mensaje) VALUES (?, ?, ?)", uid, toID, msg)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	row, err := a.DB.QueryOne(ctx(r),
		`SELECT md.*, u.nombre AS remitente_nombre, u.avatar AS remitente_avatar
		 FROM mensajes_directos md
		 JOIN usuarios u ON u.usuarioId = md.remitente_id
		 WHERE md.id = ?`, lastID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	base := baseURLOf(r)
	if av := store.Str(row["remitente_avatar"]); av != "" {
		row["remitente_avatar"] = base + "/" + av
	}
	return okOut(map[string]any{"ok": true, "mensaje": row})
}
