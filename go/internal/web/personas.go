package web

import (
	"encoding/json"
	"net/http"
	"strconv"
	"strings"
	"time"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

type personaItem struct {
	ID           int64
	Nombre       string
	Username     string
	EsProfesor   bool
	Initial      string
	Calificacion string
	NumResenas   int64
	ShowRating   bool
	IsMe         bool
	YoLoSigo     bool
}

type dmItem struct {
	ID      int64
	Mine    bool
	Mensaje string
	Hora    string
}

type searchResult struct {
	UsuarioId    int64   `json:"usuarioId"`
	Nombre       string  `json:"nombre"`
	Username     string  `json:"username"`
	Avatar       string  `json:"avatar"`
	Rol          string  `json:"rol"`
	Calificacion float64 `json:"calificacion"`
	NumResenas   int64   `json:"num_resenas"`
	Biografia    string  `json:"biografia"`
	Pais         string  `json:"pais"`
}

type dmJSON struct {
	ID              int64  `json:"id"`
	RemitenteID     int64  `json:"remitente_id"`
	DestinatarioID  int64  `json:"destinatario_id"`
	Mensaje         string `json:"mensaje"`
	CreatedAt       string `json:"created_at"`
	RemitenteNombre string `json:"remitente_nombre"`
}

type teacherClaseJSON struct {
	ID             int64   `json:"id"`
	Titulo         string  `json:"titulo"`
	PrecioBase     float64 `json:"precio_base"`
	Duracion       int64   `json:"duracion"`
	Activa         int64   `json:"activa"`
	Materia        string  `json:"materia"`
	Icono          string  `json:"icono"`
	Color          string  `json:"color"`
	AlumnosActivos int64   `json:"alumnos_activos"`
}

// HandlePersonas ports personas.php (social/people + direct messages).
func (p *Pages) HandlePersonas(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if !p.GuardPage(w, r, s) {
		return
	}
	uid := UID(s)

	switch {
	case r.Method == http.MethodPost && r.PostFormValue("action") != "":
		p.personasPost(w, r, s, uid)
	case r.Method == http.MethodGet && r.URL.Query().Get("action") == "get_new_dms":
		p.personasNewDMs(w, r, uid)
	case r.Method == http.MethodGet && r.URL.Query().Get("action") == "get_teacher_classes":
		p.personasTeacherClasses(w, r)
	default:
		p.renderPersonas(w, r, s, uid)
	}
}

func (p *Pages) personasPost(w http.ResponseWriter, r *http.Request, s *Session, uid int64) {
	ctx := r.Context()
	writeJSON := func(code int, v any) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(code)
		_ = json.NewEncoder(w).Encode(v)
	}
	if !CSRFValidate(r, s) {
		writeJSON(419, map[string]any{"ok": false, "error": "CSRF"})
		return
	}
	action := r.PostFormValue("action")
	targetId := store.Int(r.PostFormValue("usuario_id"))
	switch action {
	case "follow":
		exists, err := p.DB.QueryOne(ctx,
			"SELECT id FROM relaciones WHERE seguidorId = ? AND seguidoId = ?", uid, targetId)
		if err != nil {
			writeJSON(500, map[string]any{"ok": false, "error": "DB"})
			return
		}
		if exists != nil {
			_, _ = p.DB.Exec(ctx,
				"UPDATE relaciones SET estado = 'following' WHERE id = ?", store.Str(exists["id"]))
		} else {
			_, _ = p.DB.Exec(ctx,
				"INSERT INTO relaciones (seguidorId, seguidoId, estado) VALUES (?, ?, 'following')", uid, targetId)
		}
	case "unfollow":
		_, _ = p.DB.Exec(ctx,
			"DELETE FROM relaciones WHERE seguidorId = ? AND seguidoId = ?", uid, targetId)
	case "send_dm":
		msg := strings.TrimSpace(r.PostFormValue("mensaje"))
		if targetId > 0 && msg != "" {
			_, _ = p.DB.Exec(ctx,
				"INSERT INTO mensajes_directos (remitente_id, destinatario_id, mensaje) VALUES (?, ?, ?)",
				uid, targetId, msg)
		}
	case "search":
		q := strings.TrimSpace(r.PostFormValue("q"))
		if len(q) >= 1 {
			rows, err := p.DB.QueryAll(ctx,
				`SELECT u.usuarioId, u.nombre, u.username, u.avatar, u.rol, u.calificacion, u.num_resenas, u.biografia,
				        p.nombre AS pais
				 FROM usuarios u
				 LEFT JOIN paises p ON p.paisId = u.pais_id
				 WHERE u.nombre LIKE ? OR u.username LIKE ?
				 ORDER BY u.num_resenas DESC, u.calificacion DESC
				 LIMIT 20`, "%"+q+"%", "%"+q+"%")
			if err != nil {
				writeJSON(500, map[string]any{"ok": false, "error": "DB"})
				return
			}
			results := make([]searchResult, 0, len(rows))
			for _, row := range rows {
				results = append(results, searchResult{
					UsuarioId:    store.Int(row["usuarioId"]),
					Nombre:       store.Str(row["nombre"]),
					Username:     store.Str(row["username"]),
					Avatar:       store.Str(row["avatar"]),
					Rol:          store.Str(row["rol"]),
					Calificacion: store.Float(row["calificacion"]),
					NumResenas:   store.Int(row["num_resenas"]),
					Biografia:    store.Str(row["biografia"]),
					Pais:         store.Str(row["pais"]),
				})
			}
			writeJSON(200, map[string]any{"ok": true, "results": results})
			return
		}
	}
	writeJSON(200, map[string]any{"ok": true})
}

func (p *Pages) personasNewDMs(w http.ResponseWriter, r *http.Request, uid int64) {
	ctx := r.Context()
	writeJSON := func(code int, v any) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(code)
		_ = json.NewEncoder(w).Encode(v)
	}
	chatW := store.Int(r.URL.Query().Get("chat"))
	lastId := store.Int(r.URL.Query().Get("last_id"))
	if chatW <= 0 {
		writeJSON(200, map[string]any{"ok": false, "error": "No chat"})
		return
	}
	rows, err := p.DB.QueryAll(ctx,
		`SELECT md.id, md.remitente_id, md.destinatario_id, md.mensaje, md.created_at, u.nombre AS remitente_nombre
		 FROM mensajes_directos md
		 JOIN usuarios u ON u.usuarioId = md.remitente_id
		 WHERE ((md.remitente_id = ? AND md.destinatario_id = ?) OR (md.remitente_id = ? AND md.destinatario_id = ?))
		   AND md.id > ?
		 ORDER BY md.id ASC`, uid, chatW, chatW, uid, lastId)
	if err != nil {
		writeJSON(500, map[string]any{"ok": false, "error": "DB"})
		return
	}
	messages := make([]dmJSON, 0, len(rows))
	for _, row := range rows {
		messages = append(messages, dmJSON{
			ID:              store.Int(row["id"]),
			RemitenteID:     store.Int(row["remitente_id"]),
			DestinatarioID:  store.Int(row["destinatario_id"]),
			Mensaje:         store.Str(row["mensaje"]),
			CreatedAt:       store.Str(row["created_at"]),
			RemitenteNombre: store.Str(row["remitente_nombre"]),
		})
	}
	_, _ = p.DB.Exec(ctx,
		"UPDATE mensajes_directos SET leido = 1 WHERE destinatario_id = ? AND remitente_id = ? AND leido = 0",
		uid, chatW)
	writeJSON(200, map[string]any{"ok": true, "messages": messages})
}

func (p *Pages) personasTeacherClasses(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	writeJSON := func(code int, v any) {
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(code)
		_ = json.NewEncoder(w).Encode(v)
	}
	teacherId := store.Int(r.URL.Query().Get("teacher_id"))
	if teacherId <= 0 {
		writeJSON(200, map[string]any{"ok": false})
		return
	}
	rows, err := p.DB.QueryAll(ctx,
		`SELECT cp.claseId AS id, cp.titulo, cp.precio_base, cp.duracion_min AS duracion, cp.activa,
		        m.materiaId AS materia_id, m.nombre AS materia,
		        (SELECT COUNT(*) FROM sesiones_clase sc WHERE sc.claseId = cp.claseId AND sc.fin IS NULL) AS alumnos_activos
		 FROM clases_programadas cp
		 LEFT JOIN materias m ON m.materiaId = cp.materiaId
		 WHERE cp.instructorId = ? AND cp.activa = 1
		 ORDER BY cp.created_at DESC LIMIT 20`, teacherId)
	if err != nil {
		writeJSON(500, map[string]any{"ok": false, "error": "DB"})
		return
	}
	clases := make([]teacherClaseJSON, 0, len(rows))
	for _, row := range rows {
		mid := store.Int(row["materia_id"])
		clases = append(clases, teacherClaseJSON{
			ID:             store.Int(row["id"]),
			Titulo:         store.Str(row["titulo"]),
			PrecioBase:     store.Float(row["precio_base"]),
			Duracion:       store.Int(row["duracion"]),
			Activa:         store.Int(row["activa"]),
			Materia:        store.Str(row["materia"]),
			Icono:          subjectIcons[mid],
			Color:          subjectColors[mid],
			AlumnosActivos: store.Int(row["alumnos_activos"]),
		})
	}
	writeJSON(200, map[string]any{"ok": true, "clases": clases})
}

func (p *Pages) renderPersonas(w http.ResponseWriter, r *http.Request, s *Session, uid int64) {
	ctx := r.Context()
	lang := p.ResolveLang(s, r)
	page := CurrentPage(r)
	nav, redirect := p.MenuData(w, r, s, page, lang)
	if redirect {
		return
	}

	siguiendo, err := p.DB.QueryAll(ctx,
		`SELECT u.usuarioId, u.nombre, u.username, u.rol, u.avatar, u.calificacion, u.num_resenas, r.created_at AS seguido_desde
		 FROM relaciones r JOIN usuarios u ON u.usuarioId = r.seguidoId
		 WHERE r.seguidorId = ? AND r.estado = 'following'
		 ORDER BY r.created_at DESC`, uid)
	if err != nil {
		serverError(w, err)
		return
	}
	seguidores, err := p.DB.QueryAll(ctx,
		`SELECT u.usuarioId, u.nombre, u.username, u.rol, u.avatar, u.calificacion, u.num_resenas, r.created_at AS sigue_desde
		 FROM relaciones r JOIN usuarios u ON u.usuarioId = r.seguidorId
		 WHERE r.seguidoId = ? AND r.estado = 'following'
		 ORDER BY r.created_at DESC`, uid)
	if err != nil {
		serverError(w, err)
		return
	}

	siguiendoIds := map[int64]bool{}
	for _, row := range siguiendo {
		siguiendoIds[store.Int(row["usuarioId"])] = true
	}

	buildList := func(rows []map[string]any, asFollowing bool) []personaItem {
		list := make([]personaItem, 0, len(rows))
		for _, row := range rows {
			id := store.Int(row["usuarioId"])
			nombre := store.Str(row["nombre"])
			initial := "?"
			if nombre != "" {
				initial = strings.ToUpper(nombre[:1])
			}
			rol := store.Str(row["rol"])
			cal := store.Float(row["calificacion"])
			list = append(list, personaItem{
				ID:           id,
				Nombre:       nombre,
				Username:     store.Str(row["username"]),
				EsProfesor:   rol == "instructor" || rol == "both",
				Initial:      initial,
				Calificacion: formatOne(cal),
				NumResenas:   store.Int(row["num_resenas"]),
				ShowRating:   cal > 0,
				IsMe:         id == uid,
				YoLoSigo:     siguiendoIds[id],
			})
		}
		return list
	}

	tab := r.URL.Query().Get("tab")
	if tab != "seguidores" {
		tab = "siguiendo"
	}

	data := map[string]any{
		"Lang":             lang,
		"NavData":          nav,
		"Tab":              tab,
		"TabFollowing":     tab == "siguiendo",
		"TabFollowers":     tab == "seguidores",
		"FollowingCount":   len(siguiendo),
		"FollowersCount":   len(seguidores),
		"List":             buildList(mapSelector(siguiendo, seguidores, tab), tab == "siguiendo"),
		"QuickMsgs":        []string{},
		"CSRF":             CSRFToken(s),
		"UID":              uid,
		"HasChat":          false,
	}

	chatWith := store.Int(r.URL.Query().Get("chat"))
	if chatWith > 0 {
		chatUser, err := p.DB.QueryOne(ctx,
			"SELECT usuarioId, nombre, username, rol, avatar, biografia FROM usuarios WHERE usuarioId = ?", chatWith)
		if err != nil {
			serverError(w, err)
			return
		}
		if chatUser != nil {
			rol := store.Str(chatUser["rol"])
			esProfesor := rol == "instructor" || rol == "both"
			nombre := store.Str(chatUser["nombre"])
			initial := "?"
			if nombre != "" {
				initial = strings.ToUpper(nombre[:1])
			}
			dms, err := p.DB.QueryAll(ctx,
				`SELECT md.*, u.nombre AS remitente_nombre
				 FROM mensajes_directos md
				 JOIN usuarios u ON u.usuarioId = md.remitente_id
				 WHERE (md.remitente_id = ? AND md.destinatario_id = ?)
				    OR (md.remitente_id = ? AND md.destinatario_id = ?)
				 ORDER BY md.id ASC LIMIT 100`, uid, chatWith, chatWith, uid)
			if err != nil {
				serverError(w, err)
				return
			}
			_, _ = p.DB.Exec(ctx,
				"UPDATE mensajes_directos SET leido = 1 WHERE destinatario_id = ? AND remitente_id = ? AND leido = 0",
				uid, chatWith)
			items := make([]dmItem, 0, len(dms))
			lastId := int64(0)
			for _, row := range dms {
				id := store.Int(row["id"])
				if id > lastId {
					lastId = id
				}
				items = append(items, dmItem{
					ID:      id,
					Mine:    store.Int(row["remitente_id"]) == uid,
					Mensaje: store.Str(row["mensaje"]),
					Hora:    hmTime(store.Str(row["created_at"])),
				})
			}
			data["HasChat"] = true
			data["ChatUID"] = chatWith
			data["ChatNombre"] = nombre
			data["ChatUsername"] = store.Str(chatUser["username"])
			data["ChatInitial"] = initial
			data["ChatEsProfesor"] = esProfesor
			data["Dms"] = items
			data["DmEmpty"] = len(items) == 0
			data["LastDmId"] = lastId
		}
	}

	quick := []string{
		i18n.T(lang, "people.quick_1", nil),
		i18n.T(lang, "people.quick_2", nil),
		i18n.T(lang, "people.quick_3", nil),
		i18n.T(lang, "people.quick_4", nil),
		i18n.T(lang, "people.quick_5", nil),
		i18n.T(lang, "people.quick_6", nil),
	}
	data["QuickMsgs"] = quick

	if err := p.Templates.RenderAuthed(w, "personas", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func mapSelector(siguiendo, seguidores []map[string]any, tab string) []map[string]any {
	if tab == "seguidores" {
		return seguidores
	}
	return siguiendo
}

func formatOne(v float64) string {
	return strconv.FormatFloat(v, 'f', 1, 64)
}

func hmTime(v string) string {
	t, err := time.Parse("2006-01-02 15:04:05", v)
	if err != nil {
		return v
	}
	return t.Format("15:04")
}
