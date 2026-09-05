package web

import (
	"encoding/json"
	"fmt"
	"html/template"
	"io"
	"mime/multipart"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

// dmTestQ is one question of a fused DM test.
type dmTestQ struct {
	Q       string   `json:"q"`
	Options []string `json:"options"`
	Correct int      `json:"correct"`
	Picked  int      `json:"picked"`
}

// dmTest is the parsed test payload of a DM message.
type dmTest struct {
	Questions []dmTestQ `json:"questions"`
}

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
	ID             int64   `json:"id"`
	Mine           bool    `json:"mine"`
	RemitenteID    int64   `json:"remitente_id"`
	DestinatarioID int64   `json:"destinatario_id"`
	Mensaje        string  `json:"mensaje"`
	Hora           string  `json:"hora"`
	CreatedAt      string  `json:"created_at"`
	Tipo           string  `json:"tipo"`
	MediaURL       string  `json:"media_url"`
	MediaNombre    string  `json:"media_nombre"`
	Test           *dmTest `json:"test,omitempty"`
	Respondido     bool    `json:"respondido"`
	RespondidoPor  int64   `json:"respondido_por"`
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
	ID              int64   `json:"id"`
	RemitenteID     int64   `json:"remitente_id"`
	DestinatarioID  int64   `json:"destinatario_id"`
	Mensaje         string  `json:"mensaje"`
	Tipo            string  `json:"tipo"`
	MediaURL        string  `json:"media_url"`
	MediaNombre     string  `json:"media_nombre"`
	Test            *dmTest `json:"test,omitempty"`
	Respondido      bool    `json:"respondido"`
	RespondidoPor   int64   `json:"respondido_por"`
	CreatedAt       string  `json:"created_at"`
	RemitenteNombre string  `json:"remitente_nombre"`
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
		p.personasSendDM(w, r, uid, targetId, writeJSON)
	case "responder_test":
		p.personasRespondTest(w, r, uid, writeJSON)
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

// personasSendDM inserts a direct message supporting the fused content types:
// texto, enlace, opinion, media (file upload) and test.
func (p *Pages) personasSendDM(w http.ResponseWriter, r *http.Request, uid, targetId int64, writeJSON func(int, any)) {
	if targetId <= 0 {
		writeJSON(400, map[string]any{"ok": false, "error": "Sin destino"})
		return
	}
	tipo := r.PostFormValue("tipo")
	if tipo == "" {
		tipo = "texto"
	}
	switch tipo {
	case "texto", "enlace", "opinion":
		msg := strings.TrimSpace(r.PostFormValue("mensaje"))
		if msg == "" {
			writeJSON(200, map[string]any{"ok": false})
			return
		}
		_, err := p.DB.Exec(r.Context(),
			"INSERT INTO mensajes_directos (remitente_id, destinatario_id, mensaje, tipo) VALUES (?, ?, ?, ?)",
			uid, targetId, msg, tipo)
		if err != nil {
			writeJSON(500, map[string]any{"ok": false, "error": "DB"})
			return
		}
	case "media":
		file, fh, err := r.FormFile("archivo")
		if err != nil {
			writeJSON(200, map[string]any{"ok": false})
			return
		}
		defer file.Close()
		url, nombre, err := p.saveDMFile(fh)
		if err != nil {
			writeJSON(500, map[string]any{"ok": false, "error": "Archivo"})
			return
		}
		msg := strings.TrimSpace(r.PostFormValue("mensaje"))
		_, err = p.DB.Exec(r.Context(),
			"INSERT INTO mensajes_directos (remitente_id, destinatario_id, mensaje, tipo, media_url, media_nombre) VALUES (?, ?, ?, 'media', ?, ?)",
			uid, targetId, msg, url, nombre)
		if err != nil {
			writeJSON(500, map[string]any{"ok": false, "error": "DB"})
			return
		}
	case "test":
		testData := strings.TrimSpace(r.PostFormValue("test_data"))
		if testData == "" || !json.Valid([]byte(testData)) {
			writeJSON(200, map[string]any{"ok": false})
			return
		}
		msg := strings.TrimSpace(r.PostFormValue("mensaje"))
		_, err := p.DB.Exec(r.Context(),
			"INSERT INTO mensajes_directos (remitente_id, destinatario_id, mensaje, tipo, test_data) VALUES (?, ?, ?, 'test', ?)",
			uid, targetId, msg, testData)
		if err != nil {
			writeJSON(500, map[string]any{"ok": false, "error": "DB"})
			return
		}
	default:
		writeJSON(200, map[string]any{"ok": false})
		return
	}
	writeJSON(200, map[string]any{"ok": true})
}

// saveDMFile stores an uploaded media file under uploads/dms and returns its
// URL and original name.
func (p *Pages) saveDMFile(fh *multipart.FileHeader) (string, string, error) {
	f, err := fh.Open()
	if err != nil {
		return "", "", err
	}
	defer f.Close()

	if fh.Size > 50*1024*1024 {
		return "", "", fmt.Errorf("archivo demasiado grande")
	}

	dir := filepath.Join(p.WebDir, "uploads", "dms")
	if p.WebDir == "" {
		dir = filepath.Join(".", "uploads", "dms")
	}
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return "", "", err
	}

	ext := sanitizeExt(filepath.Ext(fh.Filename))
	if ext == "" {
		ext = ".bin"
	}
	name := "dm_" + strconv.FormatInt(time.Now().UnixNano(), 10) + ext
	dest := filepath.Join(dir, name)

	out, err := os.Create(dest)
	if err != nil {
		return "", "", err
	}
	if _, err := io.Copy(out, f); err != nil {
		out.Close()
		os.Remove(dest)
		return "", "", err
	}
	out.Close()

	return "uploads/dms/" + name, fh.Filename, nil
}

// personasRespondTest records the answers to a test DM message. Once the
// recipient responds, the correct answers are revealed to both.
func (p *Pages) personasRespondTest(w http.ResponseWriter, r *http.Request, uid int64, writeJSON func(int, any)) {
	msgID := store.Int(r.PostFormValue("mensaje_id"))
	if msgID <= 0 || uid <= 0 {
		writeJSON(400, map[string]any{"ok": false})
		return
	}
	row, err := p.DB.QueryOne(r.Context(),
		"SELECT remitente_id, destinatario_id, test_data FROM mensajes_directos WHERE id = ? AND tipo = 'test'", msgID)
	if err != nil || row == nil {
		writeJSON(200, map[string]any{"ok": false})
		return
	}
	sender := store.Int(row["remitente_id"])
	dest := store.Int(row["destinatario_id"])
	// Solo los dos integrantes de la conversación pueden responder.
	if sender == dest || (uid != sender && uid != dest) {
		writeJSON(200, map[string]any{"ok": false})
		return
	}
	// Solo un respuesta: si ya está respondido, no aceptar cambios.
	already, _ := p.DB.QueryOne(r.Context(),
		"SELECT id FROM mensajes_directos WHERE id = ? AND respondido_por IS NOT NULL", msgID)
	if already != nil {
		writeJSON(200, map[string]any{"ok": false, "error": "Ya respondido"})
		return
	}

	qids := strings.TrimSpace(r.PostFormValue("opciones"))
	if qids == "" || !json.Valid([]byte(qids)) {
		writeJSON(200, map[string]any{"ok": false})
		return
	}
	_, err = p.DB.Exec(r.Context(),
		"UPDATE mensajes_directos SET respuesta_elegida = ?, respondido_por = ? WHERE id = ?",
		qids, uid, msgID)
	if err != nil {
		writeJSON(500, map[string]any{"ok": false, "error": "DB"})
		return
	}
	writeJSON(200, map[string]any{"ok": true})
}

// parseDmTest decodes test_data and overlays the picked option indices from
// respuesta_elegida so the client can reveal correct answers.
func parseDmTest(testData, respData string) *dmTest {
	t := &dmTest{}
	if testData == "" {
		return t
	}
	if err := json.Unmarshal([]byte(testData), t); err != nil {
		return t
	}
	if respData != "" {
		var picked []int
		if json.Unmarshal([]byte(respData), &picked) == nil {
			for i := 0; i < len(t.Questions) && i < len(picked); i++ {
				t.Questions[i].Picked = picked[i]
			}
		}
	}
	return t
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
		`SELECT md.id, md.remitente_id, md.destinatario_id, md.mensaje, md.tipo, md.media_url, md.media_nombre,
		        md.test_data, md.respuesta_elegida, md.respondido_por, md.created_at, u.nombre AS remitente_nombre
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
			Tipo:            store.Str(row["tipo"]),
			MediaURL:        store.Str(row["media_url"]),
			MediaNombre:     store.Str(row["media_nombre"]),
			Test:            parseDmTest(store.Str(row["test_data"]), store.Str(row["respuesta_elegida"])),
			Respondido:      store.Int(row["respondido_por"]) > 0,
			RespondidoPor:   store.Int(row["respondido_por"]),
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
					ID:             id,
					Mine:           store.Int(row["remitente_id"]) == uid,
					RemitenteID:    store.Int(row["remitente_id"]),
					DestinatarioID: store.Int(row["destinatario_id"]),
					Mensaje:        store.Str(row["mensaje"]),
					Hora:           hmTime(store.Str(row["created_at"])),
					CreatedAt:      store.Str(row["created_at"]),
					Tipo:           store.Str(row["tipo"]),
					MediaURL:       store.Str(row["media_url"]),
					MediaNombre:    store.Str(row["media_nombre"]),
					Test:           parseDmTest(store.Str(row["test_data"]), store.Str(row["respuesta_elegida"])),
					Respondido:     store.Int(row["respondido_por"]) > 0,
					RespondidoPor:  store.Int(row["respondido_por"]),
				})
			}
			data["HasChat"] = true
			data["ChatUID"] = chatWith
			data["ChatNombre"] = nombre
			data["ChatUsername"] = store.Str(chatUser["username"])
			data["ChatInitial"] = initial
			data["ChatEsProfesor"] = esProfesor
			data["DmsJSON"] = template.JS(mustJSON(items))
			data["MediaBase"] = ""
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

func mustJSON(v any) string {
	b, err := json.Marshal(v)
	if err != nil {
		return "[]"
	}
	return string(b)
}

func isVideoExt(ext string) bool {
	switch strings.ToLower(ext) {
	case ".mp4", ".webm", ".ogv", ".mov", ".m4v", ".mpg", ".mpeg", ".avi", ".mkv":
		return true
	}
	return false
}

func isAudioExt(ext string) bool {
	switch strings.ToLower(ext) {
	case ".mp3", ".ogg", ".oga", ".wav", ".m4a", ".aac", ".flac", ".wma":
		return true
	}
	return false
}

func sanitizeExt(ext string) string {
	ext = strings.ToLower(ext)
	if isVideoExt(ext) || isAudioExt(ext) {
		return ext
	}
	switch ext {
	case ".jpg", ".jpeg", ".png", ".gif", ".webp", ".svg", ".bmp", ".avif":
		return ext
	}
	return ""
}
