package web

import (
	"fmt"
	"net/http"
	"strings"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

// materiaPageMap maps materiaId -> subject landing page (breadcrumb links).
var materiaPageMap = map[int64]string{
	1:  "matematicas.php",
	2:  "biologia.php",
	3:  "quimica.php",
	4:  "fisica.php",
	5:  "historia.php",
	6:  "geografia.php",
	7:  "literatura.php",
	8:  "idiomas.php",
	9:  "arte.php",
	10: "tecnologia.php",
	11: "educacion_fisica.php",
}

// slugToTitle mirrors slugToTitle(): ucwords(str_replace('-', ' ', slug)).
func slugToTitle(slug string) string {
	parts := strings.Fields(strings.ReplaceAll(slug, "-", " "))
	for i, w := range parts {
		if w == "" {
			continue
		}
		parts[i] = strings.ToUpper(w[:1]) + w[1:]
	}
	return strings.Join(parts, " ")
}

// spotsText renders the class availability badge text (parity with profesores.php).
func spotsText(lang string, spots int64, active bool) string {
	switch {
	case spots <= 0:
		return i18n.T(lang, "profesores.full", nil)
	case active && spots == 1:
		return i18n.T(lang, "profesores.spot_left", map[string]string{"n": "1"})
	case active:
		return i18n.T(lang, "profesores.spots_left", map[string]string{"n": fmt.Sprint(spots)})
	default:
		return i18n.T(lang, "profesores.open", nil)
	}
}

type profClase struct {
	ClaseId       int64
	ProfesorID    int64
	Titulo        string
	Descripcion   string
	MateriaNombre string
	ProfNombre    string
	Avatar        string
	HasAvatar     bool
	Pais          string
	Calificacion  float64
	NumResenas    int64
	Simbolo       string
	CodigoMoneda  string
	Precio        string
	Activos       int64
	Spots         int64
	Full          bool
	Active        bool
	Stars         string
	Posted        string
	LiveNow       string
	SpotsText     string
}

type profDemo struct {
	Nombre       string
	Rol          string
	Rating       float64
	Resenas      int64
	Time         string
	Pais         string
	Stars        string
	StartsIn     string
	MateriaNombre string
}

// stars renders the ★☆ string from a rating (str_repeat pattern in PHP).
func stars(rating float64) string {
	n := int(rating + 0.5)
	if n < 0 {
		n = 0
	}
	if n > 5 {
		n = 5
	}
	return strings.Repeat("\u2605", n) + strings.Repeat("\u2606", 5-n)
}

// HandleProfesores ports profesores.php (subject teacher/class finder).
func (p *Pages) HandleProfesores(w http.ResponseWriter, r *http.Request) {
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

	materiaId := store.Int(r.URL.Query().Get("materia"))
	var temas []string
	if raw := strings.TrimSpace(r.URL.Query().Get("temas")); raw != "" {
		for _, t := range strings.Split(raw, ",") {
			if t = strings.TrimSpace(t); t != "" {
				temas = append(temas, t)
			}
		}
	} else if sess := s.Get("temas_tecnologia"); sess != "" {
		for _, t := range strings.Split(sess, ",") {
			if t = strings.TrimSpace(t); t != "" {
				temas = append(temas, t)
			}
		}
	}
	if len(temas) > 5 {
		temas = temas[:5]
	}
	temasTitles := make([]string, 0, len(temas))
	for _, t := range temas {
		temasTitles = append(temasTitles, slugToTitle(t))
	}

	var materiaInfo map[string]any
	if materiaId > 0 {
		var err error
		materiaInfo, err = p.DB.QueryOne(ctx,
			"SELECT materiaId, nombre, imagen FROM materias WHERE materiaId = ?", materiaId)
		if err != nil {
			materiaInfo = nil
		}
	}
	materiaNombre := ""
	if materiaInfo != nil {
		materiaNombre = store.Str(materiaInfo["nombre"])
	}

	var (
		clases  []profClase
		demo    []profDemo
		hasRows bool
	)
	rows, err := p.DB.QueryAll(ctx,
		`SELECT cp.claseId, cp.titulo, cp.descripcion, cp.precio_base, cp.codigo_moneda,
		        cp.alumnos_min, cp.alumnos_max, cp.solo_yo, cp.created_at,
		        u.usuarioId AS profId, u.nombre AS prof_nombre, u.avatar,
		        u.calificacion, u.num_resenas, u.biografia,
		        pa.nombre AS pais, pa.simbolo, pa.codigo_moneda AS mon_prof,
		        m.nombre AS materia_nombre, m.imagen AS materia_img,
		        COUNT(sc.sesionId) AS alumnos_activos
		 FROM clases_programadas cp
		 JOIN usuarios u          ON u.usuarioId  = cp.instructorId
		 LEFT JOIN paises pa       ON pa.paisId    = u.pais_id
		 LEFT JOIN materias m      ON m.materiaId  = cp.materiaId
		 LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId AND sc.fin IS NULL
		 WHERE cp.activa = 1`+claseMateriaWhere(materiaId)+`
		 GROUP BY cp.claseId, cp.titulo, cp.descripcion, cp.precio_base, cp.codigo_moneda,
		          cp.alumnos_min, cp.alumnos_max, cp.solo_yo, cp.created_at,
		          u.usuarioId, u.nombre, u.avatar, u.calificacion, u.num_resenas, u.biografia,
		          pa.nombre, pa.simbolo, pa.codigo_moneda, m.nombre, m.imagen
		 ORDER BY alumnos_activos DESC, cp.created_at DESC`,
		claseMateriaArgs(materiaId)...)
	if err == nil {
		hasRows = len(rows) > 0
		for _, row := range rows {
			claseId := store.Int(row["claseId"])
			activos := store.Int(row["alumnos_activos"])
			max := store.Int(row["alumnos_max"])
			spots := max - activos
			active := activos > 0
			rating := store.Float(row["calificacion"])
		clases = append(clases, profClase{
			ClaseId:       claseId,
			ProfesorID:    store.Int(row["profId"]),
			Titulo:        store.Str(row["titulo"]),
			Descripcion:   truncUTF8(store.Str(row["descripcion"]), 100),
			MateriaNombre: store.Str(row["materia_nombre"]),
			ProfNombre:    store.Str(row["prof_nombre"]),
			Avatar:        store.Str(row["avatar"]),
			HasAvatar:     store.Str(row["avatar"]) != "",
			Pais:          store.Str(row["pais"]),
			Calificacion:  rating,
			NumResenas:    store.Int(row["num_resenas"]),
			Simbolo:       store.Str(row["simbolo"]),
			CodigoMoneda:  store.Str(row["codigo_moneda"]),
			Precio:        fmt.Sprintf("%.2f", store.Float(row["precio_base"])),
			Activos:       activos,
			Spots:         spots,
			Full:          spots <= 0,
			Active:        active,
			Stars:         stars(rating),
			Posted:        TimeAgo(row["created_at"]),
			LiveNow:       i18n.T(lang, "profesores.live_now", map[string]string{"count": fmt.Sprint(activos)}),
			SpotsText:     spotsText(lang, spots, active),
		})
	}
	}

	if !hasRows {
		// Demo / seed teachers (static fallback so the page is never empty).
		seed := []struct {
			nombre, rol, pais, time string
			rating                  float64
			resenas                 int64
		}{
			{"Alexander V.", "Director", "Chile", "00:05", 4.8, 123},
			{"Liam S.", "Instructor", "Mexico", "10:00", 4.2, 98},
			{"Elena R.", "Assistant", "Argentina", "15:00", 3.5, 47},
			{"Marcus T.", "Researcher", "Colombia", "05:00", 4.9, 210},
			{"Sophia L.", "Adviser", "Peru", "08:00", 3.8, 61},
			{"Diana P.", "Director", "Brazil", "12:00", 4.5, 175},
		}
		for _, d := range seed {
			demo = append(demo, profDemo{
				Nombre:        d.nombre,
				Rol:           i18n.T(lang, "profesores.role_"+strings.ToLower(d.rol), nil),
				Rating:        d.rating,
				Resenas:       d.resenas,
				Time:          d.time,
				Pais:          d.pais,
				Stars:         stars(d.rating),
				StartsIn:      i18n.T(lang, "profesores.starts_in", map[string]string{"time": d.time}),
				MateriaNombre: materiaNombre,
			})
		}
	}

	materiaPage := "materias.php"
	if materiaId > 0 {
		if m, ok := materiaPageMap[materiaId]; ok {
			materiaPage = m
		}
	}

	data := map[string]any{
		"Lang":          lang,
		"NavData":       nav,
		"MateriaId":     materiaId,
		"MateriaInfo":   materiaInfo != nil,
		"MateriaNombre": materiaNombre,
		"MateriaImagen": "",
		"MateriaPage":   materiaPage,
		"Temas":         temas,
		"TemasTitles":   temasTitles,
		"HasContext":    materiaInfo != nil || len(temas) > 0,
		"LookingFor":    i18n.T(lang, "profesores.looking_for", map[string]string{"subject": materiaNombre}),
		"BuscarHref":    "buscar.php" + mapIf(materiaId > 0, "?materia="+fmt.Sprint(materiaId), ""),
		"CrearHref":     "crear_clase.php" + mapIf(materiaId > 0, "?materia="+fmt.Sprint(materiaId), ""),
		"HasRows":       hasRows,
		"Clases":        clases,
		"Demo":          demo,
	}
	if materiaInfo != nil {
		data["MateriaImagen"] = store.Str(materiaInfo["imagen"])
	}
	if err := p.Templates.Render(w, "profesores", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func claseMateriaWhere(id int64) string {
	if id > 0 {
		return " AND cp.materiaId = ?"
	}
	return ""
}

func claseMateriaArgs(id int64) []any {
	if id > 0 {
		return []any{id}
	}
	return nil
}

func mapIf(cond bool, a, b string) string {
	if cond {
		return a
	}
	return b
}

// truncUTF8 truncates a UTF-8 string to n runes (mb_substr fallback).
func truncUTF8(s string, n int) string {
	r := []rune(s)
	if len(r) <= n {
		return s
	}
	return string(r[:n])
}
