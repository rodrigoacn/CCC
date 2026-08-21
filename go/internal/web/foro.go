package web

import (
	"net/http"
	"strings"

	"classexpress/internal/store"
)

type foroHilo struct {
	ID          int64
	Titulo      string
	Contenido   string
	Autor       string
	AutorId     int64
	Avatar      string
	MateriaId   int64
	MateriaName string
	Views       int64
	Respuestas  int64
	Likes       int64
	Pinned      bool
	Closed      bool
	Iliked      bool
	CreatedAt   string
	TimeAgo     string
}

type foroRespuesta struct {
	ID        int64
	HiloId    int64
	Contenido string
	Autor     string
	AutorId   int64
	Avatar    string
	EsMejor   bool
	Iliked    bool
	Likes     int64
	Archivos  []foroArchivo
	CreatedAt string
	TimeAgo   string
}

type foroArchivo struct {
	Nombre string
	URL    string
}

type foroMateria struct {
	ID     int64
	Nombre string
}

type foroReport struct {
	TargetType string
	TargetId   int64
}

// HandleForo ports foro.php: forum per subject with threads, replies, likes, reports.
func (p *Pages) HandleForo(w http.ResponseWriter, r *http.Request) {
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

	if !p.RequireCSRFOnPost(w, r, s) {
		return
	}

	if r.Method == http.MethodPost {
		p.foroHandlePost(w, r, s, uid, lang)
		return
	}

	materiaId := store.Int(r.URL.Query().Get("materia"))
	hiloId := store.Int(r.URL.Query().Get("hilo"))

	if hiloId > 0 {
		p.foroShowHilo(w, r, s, uid, lang, nav, materiaId, hiloId)
		return
	}
	p.foroListHilos(w, r, s, uid, lang, nav, materiaId)
}

func (p *Pages) foroListHilos(w http.ResponseWriter, r *http.Request, s *Session, uid int64, lang string, nav NavData, materiaId int64) {
	ctx := r.Context()

	materiasRows, _ := p.DB.QueryAll(ctx, "SELECT materiaId, nombre FROM materias ORDER BY orden ASC")
	var materias []foroMateria
	for _, mr := range materiasRows {
		materias = append(materias, foroMateria{ID: store.Int(mr["materiaId"]), Nombre: store.Str(mr["nombre"])})
	}

	var materiaName string
	if materiaId > 0 {
		for _, m := range materias {
			if m.ID == materiaId {
				materiaName = m.Nombre
				break
			}
		}
	}

	query := `SELECT h.hiloId, h.titulo, h.contenido, h.views, h.pinned, h.closed, h.created_at,
	                 u.nombre AS autor, u.avatar, u.usuarioId AS autor_id,
	                 m.nombre AS materia_name, m.materiaId AS materia_id,
	                 (SELECT COUNT(*) FROM foro_respuestas r WHERE r.hiloId = h.hiloId) AS num_respuestas,
	                 (SELECT COUNT(*) FROM foro_likes l WHERE l.hiloId = h.hiloId) AS num_likes
	          FROM foro_hilos h
	          JOIN usuarios u ON u.usuarioId = h.usuarioId
	          JOIN materias m ON m.materiaId = h.materiaId`
	var args []any
	if materiaId > 0 {
		query += " WHERE h.materiaId = ?"
		args = append(args, materiaId)
	}
	query += " ORDER BY h.pinned DESC, h.created_at DESC"

	rows, err := p.DB.QueryAll(ctx, query, args...)
	if err != nil {
		serverError(w, err)
		return
	}

	var hilos []foroHilo
	for _, row := range rows {
		likes := store.Int(row["num_likes"])
		liked := false
		if uid > 0 {
			lk, _ := p.DB.QueryOne(ctx, "SELECT likeId FROM foro_likes WHERE hiloId = ? AND usuarioId = ?", store.Int(row["hiloId"]), uid)
			liked = lk != nil
		}
		hilos = append(hilos, foroHilo{
			ID:          store.Int(row["hiloId"]),
			Titulo:      store.Str(row["titulo"]),
			Contenido:   store.Str(row["contenido"]),
			Autor:       store.Str(row["autor"]),
			AutorId:     store.Int(row["autor_id"]),
			Avatar:      store.Str(row["avatar"]),
			MateriaId:   store.Int(row["materia_id"]),
			MateriaName: store.Str(row["materia_name"]),
			Views:       store.Int(row["views"]),
			Respuestas:  store.Int(row["num_respuestas"]),
			Likes:       likes,
			Pinned:      store.Int(row["pinned"]) > 0,
			Closed:      store.Int(row["closed"]) > 0,
			Iliked:      liked,
			CreatedAt:   store.Str(row["created_at"]),
			TimeAgo:     TimeAgo(row["created_at"]),
		})
	}

	data := map[string]any{
		"Lang":        lang,
		"NavData":     nav,
		"MateriaId":   materiaId,
		"MateriaName": materiaName,
		"Materias":    materias,
		"Hilos":       hilos,
		"Count":       len(hilos),
		"UID":         uid,
		"View":        "list",
	}
	if err := p.Templates.RenderAuthed(w, "foro", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func (p *Pages) foroShowHilo(w http.ResponseWriter, r *http.Request, s *Session, uid int64, lang string, nav NavData, materiaId, hiloId int64) {
	ctx := r.Context()

	row, err := p.DB.QueryOne(ctx,
		`SELECT h.*, u.nombre AS autor, u.avatar, u.usuarioId AS autor_id,
		        m.nombre AS materia_name, m.materiaId AS materia_id,
		        (SELECT COUNT(*) FROM foro_respuestas r WHERE r.hiloId = h.hiloId) AS num_respuestas,
		        (SELECT COUNT(*) FROM foro_likes l WHERE l.hiloId = h.hiloId) AS num_likes
		 FROM foro_hilos h
		 JOIN usuarios u ON u.usuarioId = h.usuarioId
		 JOIN materias m ON m.materiaId = h.materiaId
		 WHERE h.hiloId = ?`, hiloId)
	if err != nil || row == nil {
		redirect(w, r, "foro.php?materia="+store.Str(materiaId))
		return
	}

	_, _ = p.DB.Exec(ctx, "UPDATE foro_hilos SET views = views + 1 WHERE hiloId = ?", hiloId)

	liked := false
	if uid > 0 {
		lk, _ := p.DB.QueryOne(ctx, "SELECT likeId FROM foro_likes WHERE hiloId = ? AND usuarioId = ?", hiloId, uid)
		liked = lk != nil
	}

	hilo := foroHilo{
		ID:          store.Int(row["hiloId"]),
		Titulo:      store.Str(row["titulo"]),
		Contenido:   store.Str(row["contenido"]),
		Autor:       store.Str(row["autor"]),
		AutorId:     store.Int(row["autor_id"]),
		Avatar:      store.Str(row["avatar"]),
		MateriaId:   store.Int(row["materia_id"]),
		MateriaName: store.Str(row["materia_name"]),
		Views:       store.Int(row["views"]) + 1,
		Respuestas:  store.Int(row["num_respuestas"]),
		Likes:       store.Int(row["num_likes"]),
		Pinned:      store.Int(row["pinned"]) > 0,
		Closed:      store.Int(row["closed"]) > 0,
		Iliked:      liked,
		CreatedAt:   store.Str(row["created_at"]),
		TimeAgo:     TimeAgo(row["created_at"]),
	}

	respRows, err := p.DB.QueryAll(ctx,
		`SELECT r.*, u.nombre AS autor, u.avatar, u.usuarioId AS autor_id
		 FROM foro_respuestas r
		 JOIN usuarios u ON u.usuarioId = r.usuarioId
		 WHERE r.hiloId = ?
		 ORDER BY r.es_mejor DESC, r.created_at ASC`, hiloId)
	if err != nil {
		serverError(w, err)
		return
	}

	var respuestas []foroRespuesta
	for _, rr := range respRows {
		respId := store.Int(rr["respuestaId"])
		rl := false
		if uid > 0 {
			lk, _ := p.DB.QueryOne(ctx, "SELECT likeId FROM foro_likes WHERE respuestaId = ? AND usuarioId = ?", respId, uid)
			rl = lk != nil
		}
		lkCount, _ := p.DB.QueryOne(ctx, "SELECT COUNT(*) AS c FROM foro_likes WHERE respuestaId = ?", respId)
		likes := int64(0)
		if lkCount != nil {
			likes = store.Int(lkCount["c"])
		}

		archRows, _ := p.DB.QueryAll(ctx, "SELECT nombre, url FROM foro_archivos WHERE respuestaId = ?", respId)
		var archivos []foroArchivo
		for _, ar := range archRows {
			archivos = append(archivos, foroArchivo{Nombre: store.Str(ar["nombre"]), URL: store.Str(ar["url"])})
		}

		respuestas = append(respuestas, foroRespuesta{
			ID:        respId,
			HiloId:    hiloId,
			Contenido: store.Str(rr["contenido"]),
			Autor:     store.Str(rr["autor"]),
			AutorId:   store.Int(rr["autor_id"]),
			Avatar:    store.Str(rr["avatar"]),
			EsMejor:   store.Int(rr["es_mejor"]) > 0,
			Iliked:    rl,
			Likes:     likes,
			Archivos:  archivos,
			CreatedAt: store.Str(rr["created_at"]),
			TimeAgo:   TimeAgo(rr["created_at"]),
		})
	}

	data := map[string]any{
		"Lang":       lang,
		"NavData":    nav,
		"Hilo":       hilo,
		"Respuestas": respuestas,
		"Count":      len(respuestas),
		"UID":        uid,
		"IsOwner":    hilo.AutorId == uid,
		"View":       "hilo",
	}
	if err := p.Templates.RenderAuthed(w, "foro", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func (p *Pages) foroHandlePost(w http.ResponseWriter, r *http.Request, s *Session, uid int64, lang string) {
	ctx := r.Context()
	action := r.PostFormValue("action")
	materiaId := store.Int(r.PostFormValue("materia_id"))
	hiloId := store.Int(r.PostFormValue("hilo_id"))

	switch action {
	case "create_hilo":
		titulo := strings.TrimSpace(r.PostFormValue("titulo"))
		contenido := strings.TrimSpace(r.PostFormValue("contenido"))
		if titulo == "" || contenido == "" || materiaId <= 0 {
			redirect(w, r, "foro.php?materia="+store.Str(materiaId))
			return
		}
		id, err := p.DB.Exec(ctx,
			"INSERT INTO foro_hilos (materiaId, usuarioId, titulo, contenido) VALUES (?, ?, ?, ?)",
			materiaId, uid, titulo, contenido)
		if err != nil {
			serverError(w, err)
			return
		}
		redirect(w, r, "foro.php?materia="+store.Str(materiaId)+"&hilo="+store.Str(id))

	case "reply":
		contenido := strings.TrimSpace(r.PostFormValue("contenido"))
		if contenido == "" || hiloId <= 0 {
			redirect(w, r, "foro.php?materia="+store.Str(materiaId)+"&hilo="+store.Str(hiloId))
			return
		}
		_, err := p.DB.Exec(ctx,
			"INSERT INTO foro_respuestas (hiloId, usuarioId, contenido) VALUES (?, ?, ?)",
			hiloId, uid, contenido)
		if err != nil {
			serverError(w, err)
			return
		}
		hRow, _ := p.DB.QueryOne(ctx, "SELECT materiaId FROM foro_hilos WHERE hiloId = ?", hiloId)
		mid := materiaId
		if hRow != nil {
			mid = store.Int(hRow["materiaId"])
		}
		redirect(w, r, "foro.php?materia="+store.Str(mid)+"&hilo="+store.Str(hiloId))

	case "like_hilo":
		if hiloId > 0 && uid > 0 {
			existing, _ := p.DB.QueryOne(ctx, "SELECT likeId FROM foro_likes WHERE hiloId = ? AND usuarioId = ?", hiloId, uid)
			if existing != nil {
				_, _ = p.DB.Exec(ctx, "DELETE FROM foro_likes WHERE hiloId = ? AND usuarioId = ?", hiloId, uid)
			} else {
				_, _ = p.DB.Exec(ctx, "INSERT INTO foro_likes (hiloId, usuarioId) VALUES (?, ?)", hiloId, uid)
			}
		}
		hRow, _ := p.DB.QueryOne(ctx, "SELECT materiaId FROM foro_hilos WHERE hiloId = ?", hiloId)
		mid := materiaId
		if hRow != nil {
			mid = store.Int(hRow["materiaId"])
		}
		redirect(w, r, "foro.php?materia="+store.Str(mid)+"&hilo="+store.Str(hiloId))

	case "like_resp":
		respId := store.Int(r.PostFormValue("respuesta_id"))
		if respId > 0 && uid > 0 {
			existing, _ := p.DB.QueryOne(ctx, "SELECT likeId FROM foro_likes WHERE respuestaId = ? AND usuarioId = ?", respId, uid)
			if existing != nil {
				_, _ = p.DB.Exec(ctx, "DELETE FROM foro_likes WHERE respuestaId = ? AND usuarioId = ?", respId, uid)
			} else {
				_, _ = p.DB.Exec(ctx, "INSERT INTO foro_likes (respuestaId, usuarioId) VALUES (?, ?)", respId, uid)
			}
		}
		hRow, _ := p.DB.QueryOne(ctx, "SELECT materiaId FROM foro_hilos WHERE hiloId = ?", hiloId)
		mid := materiaId
		if hRow != nil {
			mid = store.Int(hRow["materiaId"])
		}
		redirect(w, r, "foro.php?materia="+store.Str(mid)+"&hilo="+store.Str(hiloId))

	case "best_answer":
		respId := store.Int(r.PostFormValue("respuesta_id"))
		hRow, _ := p.DB.QueryOne(ctx, "SELECT materiaId, usuarioId FROM foro_hilos WHERE hiloId = ?", hiloId)
		if hRow == nil || store.Int(hRow["usuarioId"]) != uid {
			mid := materiaId
			if hRow != nil {
				mid = store.Int(hRow["materiaId"])
			}
			redirect(w, r, "foro.php?materia="+store.Str(mid)+"&hilo="+store.Str(hiloId))
			return
		}
		_, _ = p.DB.Exec(ctx, "UPDATE foro_respuestas SET es_mejor = 0 WHERE hiloId = ?", hiloId)
		_, _ = p.DB.Exec(ctx, "UPDATE foro_respuestas SET es_mejor = 1 WHERE respuestaId = ? AND hiloId = ?", respId, hiloId)
		mid := store.Int(hRow["materiaId"])
		redirect(w, r, "foro.php?materia="+store.Str(mid)+"&hilo="+store.Str(hiloId))

	case "close_thread":
		hRow, _ := p.DB.QueryOne(ctx, "SELECT materiaId, usuarioId, closed FROM foro_hilos WHERE hiloId = ?", hiloId)
		if hRow == nil || store.Int(hRow["usuarioId"]) != uid {
			mid := materiaId
			if hRow != nil {
				mid = store.Int(hRow["materiaId"])
			}
			redirect(w, r, "foro.php?materia="+store.Str(mid)+"&hilo="+store.Str(hiloId))
			return
		}
		newVal := int64(1)
		if store.Int(hRow["closed"]) > 0 {
			newVal = 0
		}
		_, _ = p.DB.Exec(ctx, "UPDATE foro_hilos SET closed = ? WHERE hiloId = ?", newVal, hiloId)
		mid := store.Int(hRow["materiaId"])
		redirect(w, r, "foro.php?materia="+store.Str(mid)+"&hilo="+store.Str(hiloId))

	case "report":
		targetType := r.PostFormValue("target_type")
		targetId := store.Int(r.PostFormValue("target_id"))
		motivo := strings.TrimSpace(r.PostFormValue("motivo"))
		if motivo == "" || targetId <= 0 || uid <= 0 {
			break
		}
		if targetType == "hilo" {
			_, _ = p.DB.Exec(ctx, "INSERT INTO foro_reports (hiloId, usuarioId, motivo) VALUES (?, ?, ?)", targetId, uid, motivo)
		} else if targetType == "respuesta" {
			_, _ = p.DB.Exec(ctx, "INSERT INTO foro_reports (respuestaId, usuarioId, motivo) VALUES (?, ?, ?)", targetId, uid, motivo)
		}
		hRow, _ := p.DB.QueryOne(ctx, "SELECT materiaId FROM foro_hilos WHERE hiloId = ?", hiloId)
		mid := materiaId
		if hRow != nil {
			mid = store.Int(hRow["materiaId"])
		}
		redirect(w, r, "foro.php?materia="+store.Str(mid)+"&hilo="+store.Str(hiloId))
	}
}
