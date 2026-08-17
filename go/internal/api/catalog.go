package api

import (
	"math"
	"net/http"

	"classexpress/internal/store"
)

var subjectColors = map[string]string{
	"Mathematics":        "#2563EB",
	"History":            "#D97706",
	"Literature":         "#DC2626",
	"Chemistry":          "#7C3AED",
	"Biology":            "#059669",
	"Physics":            "#0284C7",
	"Geography":          "#0D9488",
	"Art and Music":      "#EA580C",
	"Physical Education": "#E11D48",
	"Foreign Languages":  "#DB2777",
	"Technology":         "#0891B2",
}

var subjectIcons = map[string]string{
	"Mathematics":        "calculator",
	"History":            "book-open",
	"Literature":         "feather",
	"Chemistry":          "zap",
	"Biology":            "activity",
	"Physics":            "cpu",
	"Geography":          "map",
	"Art and Music":      "pen-tool",
	"Physical Education": "heart",
	"Foreign Languages":  "globe",
	"Technology":         "monitor",
}

// subjects mirrors CatalogController::subjects.
func (a *API) subjects() *resp {
	rows, err := a.DB.QueryAll(ctx(nil),
		`SELECT m.materiaId AS id, m.nombre, m.imagen, m.pagina, m.orden,
			(SELECT COUNT(*) FROM clases_programadas cp WHERE cp.materiaId = m.materiaId AND cp.activa = true) AS clases_activas
		 FROM materias m ORDER BY m.nombre`)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	for _, s := range rows {
		name := store.Str(s["nombre"])
		s["color"] = subjectColors[name]
		if s["color"] == "" {
			s["color"] = "#66ddbd"
		}
		s["icono"] = subjectIcons[name]
		if s["icono"] == "" {
			s["icono"] = "book"
		}
		s["clases_activas"] = store.Int(s["clases_activas"])
	}
	return okOut(map[string]any{"subjects": rows})
}

// teachers mirrors CatalogController::teachers.
func (a *API) teachers(r *http.Request) *resp {
	sid := queryInt(r, "subject_id")

	sqlStr := `SELECT u.usuarioId AS id, u.nombre, u.email, u.rol, u.creditos,
		ROUND(COALESCE(AVG(u.calificacion), 4.0), 1) AS rating,
		COUNT(DISTINCT cp.claseId) AS clases_count
		FROM usuarios u
		LEFT JOIN clases_programadas cp ON cp.instructorId = u.usuarioId
		WHERE u.rol = 'instructor'`
	var args []any
	if sid > 0 {
		sqlStr += " AND cp.materiaId = ?"
		args = append(args, sid)
	}
	sqlStr += " GROUP BY u.usuarioId, u.nombre, u.email, u.rol, u.creditos ORDER BY rating DESC, clases_count DESC"

	rows, err := a.DB.QueryAll(ctx(r), sqlStr, args...)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"teachers": rows})
}

// classes mirrors CatalogController::classes.
func (a *API) classes(r *http.Request) *resp {
	sid := queryInt(r, "subject_id")
	search := r.URL.Query().Get("search")
	active := r.URL.Query().Get("active_only") == "true"
	sort := r.URL.Query().Get("sort")
	if sort == "" {
		sort = "relevance"
	}
	page := max(1, int(queryInt(r, "page")))
	limit := int(queryInt(r, "limit"))
	if limit < 10 {
		limit = 20
	}
	if limit > 50 {
		limit = 50
	}
	offset := (page - 1) * limit

	uid := a.optionalUID(r)

	sqlStr := `SELECT cp.claseId AS id, cp.titulo, cp.descripcion, cp.precio_base AS precio, cp.duracion_min AS duracion_minutos, cp.calificacion AS rating, cp.alumnos_max, cp.alumnos_activos, cp.activa, cp.created_at,
		m.materiaId AS materia_id, m.nombre AS materia,
		u.usuarioId AS profesor_id, u.nombre AS profesor,
		(SELECT s.activa FROM salas s WHERE s.claseId = cp.claseId AND s.activa = true LIMIT 1) AS sala_activa,
		(SELECT COALESCE(SUM(sc.segundos_acumulados), 0) FROM sesiones_clase sc WHERE sc.claseId = cp.claseId) AS total_visto`
	var args []any
	if uid > 0 {
		sqlStr += ", IF(f.id IS NOT NULL, 1, 0) AS es_amigo"
	} else {
		sqlStr += ", 0 AS es_amigo"
	}
	sqlStr += ` FROM clases_programadas cp
		JOIN materias m ON m.materiaId = cp.materiaId
		JOIN usuarios u ON u.usuarioId = cp.instructorId`
	if uid > 0 {
		sqlStr += " LEFT JOIN relaciones f ON f.seguidoId = u.usuarioId AND f.seguidorId = ? AND f.estado = 'following'"
		args = append(args, uid)
	}
	sqlStr += " WHERE cp.activa = true"

	if sid > 0 {
		sqlStr += " AND cp.materiaId = ?"
		args = append(args, sid)
	}
	if search != "" {
		sqlStr += " AND (cp.titulo LIKE ? OR u.nombre LIKE ? OR m.nombre LIKE ? OR cp.descripcion LIKE ?)"
		pat := "%" + search + "%"
		args = append(args, pat, pat, pat, pat)
	}
	if active {
		sqlStr += " AND EXISTS (SELECT 1 FROM salas s WHERE s.claseId = cp.claseId AND s.activa = true)"
	}

	countSQL := "SELECT COUNT(*) AS total FROM (" + sqlStr + ") AS _count"
	countRow, err := a.DB.QueryOne(ctx(r), countSQL, args...)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	total := store.Int(countRow["total"])

	switch sort {
	case "price_asc":
		sqlStr += " ORDER BY cp.precio_base ASC"
	case "price_desc":
		sqlStr += " ORDER BY cp.precio_base DESC"
	case "rating":
		sqlStr += " ORDER BY cp.calificacion DESC, total_visto DESC"
	case "popular":
		sqlStr += " ORDER BY total_visto DESC"
	case "newest":
		sqlStr += " ORDER BY cp.created_at DESC"
	default:
		if uid > 0 {
			sqlStr += " ORDER BY es_amigo DESC, sala_activa IS NULL, sala_activa DESC, total_visto DESC, cp.precio_base ASC"
		} else {
			sqlStr += " ORDER BY sala_activa IS NULL, sala_activa DESC, total_visto DESC, cp.precio_base ASC"
		}
	}
	sqlStr += " LIMIT ? OFFSET ?"
	args = append(args, limit, offset)

	rows, err := a.DB.QueryAll(ctx(r), sqlStr, args...)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	pages := int(math.Ceil(float64(total) / float64(limit)))
	return okOut(map[string]any{"classes": rows, "total": total, "page": page, "pages": pages})
}

// optionalUID resolves the token if present (es_amigo logic).
func (a *API) optionalUID(r *http.Request) int64 {
	token := bearerToken(r, map[string]any{})
	if token == "" {
		return 0
	}
	row, err := a.DB.QueryOne(ctx(r), "SELECT usuario_id FROM mobile_tokens WHERE token = ?", token)
	if err != nil || row == nil {
		return 0
	}
	return store.Int(row["usuario_id"])
}

// classDetail mirrors CatalogController::classDetail.
func (a *API) classDetail(r *http.Request) *resp {
	id := queryInt(r, "id")
	if id == 0 {
		return errOut(http.StatusBadRequest, "ID requerido")
	}

	clase, err := a.DB.QueryOne(ctx(r),
		`SELECT cp.claseId AS id, cp.instructorId AS profesor_id, cp.materiaId AS materia_id, cp.precio_base AS precio,
			cp.duracion_min AS duracion_minutos, cp.calificacion AS rating,
			cp.titulo, cp.descripcion, cp.alumnos_max, cp.activa, cp.created_at,
			m.nombre AS materia, u.nombre AS profesor,
			s.salaId AS sala_id, s.activa AS sala_activa
		 FROM clases_programadas cp
		 JOIN materias m ON m.materiaId = cp.materiaId
		 JOIN usuarios u ON u.usuarioId = cp.instructorId
		 LEFT JOIN salas s ON s.claseId = cp.claseId AND s.activa = true
		 WHERE cp.claseId = ?`, id)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if clase == nil {
		return errOut(http.StatusNotFound, "Clase no encontrada")
	}
	return okOut(map[string]any{"clase": clase})
}

// countries mirrors CatalogController::countries.
func (a *API) countries() *resp {
	rows, err := a.DB.QueryAll(ctx(nil),
		"SELECT paisid AS id, nombre, codigo_iso AS codigo, codigo_moneda, simbolo FROM paises ORDER BY nombre")
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"countries": rows})
}

func queryInt(r *http.Request, key string) int64 {
	return store.Int(r.URL.Query().Get(key))
}
