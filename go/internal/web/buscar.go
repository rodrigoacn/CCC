package web

import (
	"fmt"
	"net/http"
	"strconv"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

type buscarSubject struct {
	ID       int64
	Nombre   string
	Color    string
	Selected bool
}

type claseBuscar struct {
	ClaseId      int64
	Live         bool
	Amigo        bool
	Titulo       string
	Profesor     string
	Materia      string
	Mins         int64
	Capacity     int64
	Enrolled     int64
	Rating       string
	ShowRating   bool
	Precio       string
	Section      string
}

// HandleBuscar ports buscar.php (student class search).
func (p *Pages) HandleBuscar(w http.ResponseWriter, r *http.Request) {
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

	subjectId := store.Int(r.URL.Query().Get("s"))
	if subjectId >= 1 && subjectId <= 11 {
		q := r.URL.Query()
		q.Set("materia", strconv.FormatInt(subjectId, 10))
		r.URL.RawQuery = q.Encode()
	}

	page := CurrentPage(r)
	nav, stop := p.MenuData(w, r, s, page, lang)
	if stop {
		return
	}
	uid := UID(s)

	search := r.URL.Query().Get("q")
	activeOnly := r.URL.Query().Get("live") == "1"
	sort := r.URL.Query().Get("sort")
	if sort == "" {
		sort = "relevance"
	}

	subjects, err := p.DB.QueryAll(ctx, "SELECT materiaId AS id, nombre FROM materias ORDER BY nombre")
	if err != nil || len(subjects) == 0 {
		names := []string{"Mathematics", "Biology", "Chemistry", "Physics", "History", "Geography", "Literature", "Foreign Languages", "Art and Music", "Technology", "Physical Education"}
		subjects = []map[string]any{}
		for i, n := range names {
			subjects = append(subjects, map[string]any{"id": i + 1, "nombre": n})
		}
	}

	subjectList := make([]buscarSubject, 0, len(subjects))
	for _, row := range subjects {
		id := store.Int(row["id"])
		subjectList = append(subjectList, buscarSubject{
			ID:       id,
			Nombre:   store.Str(row["nombre"]),
			Color:    subjectColors[id],
			Selected: id == subjectId,
		})
	}

	sqlq := `SELECT cp.claseId AS claseId, cp.titulo, cp.descripcion, cp.precio_base, cp.duracion_min, cp.calificacion, cp.alumnos_max, cp.alumnos_activos,
	               m.nombre AS materia,
	               u.nombre AS profesor,
	               (SELECT s.activa FROM salas s WHERE s.claseId = cp.claseId AND s.activa = true LIMIT 1) AS sala_activa,
	               (SELECT COALESCE(SUM(sc.segundos_acumulados), 0) FROM sesiones_clase sc WHERE sc.claseId = cp.claseId) AS total_visto,
	               (SELECT COUNT(*) FROM relaciones r WHERE r.seguidoId = cp.instructorId AND r.seguidorId = ? AND r.estado = 'following') AS es_amigo
	        FROM clases_programadas cp
	        JOIN materias m ON m.materiaId = cp.materiaId
	        JOIN usuarios u ON u.usuarioId = cp.instructorId
	        WHERE cp.activa = true`
	args := []any{uid}
	if search != "" {
		sqlq += " AND (cp.titulo LIKE ? OR u.nombre LIKE ? OR m.nombre LIKE ? OR cp.descripcion LIKE ?)"
		args = append(args, "%"+search+"%", "%"+search+"%", "%"+search+"%", "%"+search+"%")
	}
	if subjectId > 0 {
		sqlq += " AND cp.materiaId = ?"
		args = append(args, subjectId)
	}
	if activeOnly {
		sqlq += " AND EXISTS (SELECT 1 FROM salas s WHERE s.claseId = cp.claseId AND s.activa = true)"
	}

	var orderBy string
	switch sort {
	case "price_asc":
		orderBy = "cp.precio_base ASC"
	case "price_desc":
		orderBy = "cp.precio_base DESC"
	case "rating":
		orderBy = "cp.calificacion DESC, total_visto DESC"
	case "popular":
		orderBy = "total_visto DESC"
	case "newest":
		orderBy = "cp.created_at DESC"
	default:
		orderBy = "es_amigo DESC, sala_activa IS NULL, sala_activa DESC, total_visto DESC, cp.precio_base ASC"
	}
	sqlq += " ORDER BY " + orderBy + " LIMIT 50"

	rows, err := p.DB.QueryAll(ctx, sqlq, args...)
	if err != nil {
		serverError(w, err)
		return
	}

	classes := make([]claseBuscar, 0, len(rows))
	shownFriend := false
	for _, row := range rows {
		claseId := store.Int(row["claseId"])
		live := store.Bool(row["sala_activa"])
		amigo := store.Int(row["es_amigo"]) > 0
		rating := store.Float(row["calificacion"])
		section := ""
		if amigo && !shownFriend {
			section = "friend"
			shownFriend = true
		} else if !amigo && shownFriend {
			section = "more"
			shownFriend = false
		}
		classes = append(classes, claseBuscar{
			ClaseId:    claseId,
			Live:       live,
			Amigo:      amigo,
			Titulo:     store.Str(row["titulo"]),
			Profesor:   store.Str(row["profesor"]),
			Materia:    store.Str(row["materia"]),
			Mins:       store.Int(row["duracion_min"]),
			Capacity:   store.Int(row["alumnos_max"]),
			Enrolled:   store.Int(row["alumnos_activos"]),
			Rating:     fmt.Sprintf("%.1f", rating),
			ShowRating: rating > 0,
			Precio:     fmt.Sprintf("%.0f", store.Float(row["precio_base"])),
			Section:    section,
		})
	}

	sortOpts := map[string]string{
		"relevance":  i18n.T(lang, "buscar.sort_relevance", nil),
		"popular":    i18n.T(lang, "buscar.sort_popular", nil),
		"rating":     i18n.T(lang, "buscar.sort_rating", nil),
		"price_asc":  i18n.T(lang, "buscar.sort_price_low", nil),
		"price_desc": i18n.T(lang, "buscar.sort_price_high", nil),
		"newest":     i18n.T(lang, "buscar.sort_newest", nil),
	}
	type sortItem struct {
		Key    string
		Label  string
		Active bool
	}
	sortList := []sortItem{}
	order := []string{"relevance", "popular", "rating", "price_asc", "price_desc", "newest"}
	for _, k := range order {
		sortList = append(sortList, sortItem{Key: k, Label: sortOpts[k], Active: sort == k})
	}

	data := map[string]any{
		"Lang":       lang,
		"NavData":    nav,
		"Search":     search,
		"HasSearch":  search != "",
		"ActiveOnly": activeOnly,
		"Sort":       sort,
		"SubjectId":  subjectId,
		"Subjects":   subjectList,
		"SortList":   sortList,
		"Classes":    classes,
		"Count":      len(classes),
		"HasResults": len(classes) > 0,
	}
	if err := p.Templates.RenderAuthed(w, "buscar", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}
