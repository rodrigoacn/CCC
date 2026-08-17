package web

import (
	"fmt"
	"net/http"
	"strings"
	"time"

	"classexpress/internal/store"
)

type resenaItem struct {
	EstudianteNombre  string
	EstudianteAvatar  string
	Initial           string
	Stars             string
	Comentario        string
	Fecha             string
}

type claseItem struct {
	ID             int64
	MateriaID      int64
	Titulo         string
	Materia        string
	Precio         string
	Duracion       int64
	AlumnosActivos int64
	Color          string
	Icono          string
}

// HandlePerfilUsuario ports perfil_usuario.php (public profile).
func (p *Pages) HandlePerfilUsuario(w http.ResponseWriter, r *http.Request) {
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
	targetId := store.Int(r.URL.Query().Get("id"))
	if targetId <= 0 {
		redirect(w, r, "personas.php")
		return
	}

	user, err := p.DB.QueryOne(ctx,
		`SELECT u.*, p.nombre AS pais, p.codigo_moneda, p.simbolo
		 FROM usuarios u
		 LEFT JOIN paises p ON p.paisId = u.pais_id
		 WHERE u.usuarioId = ?`, targetId)
	if err != nil || user == nil {
		data := map[string]any{"Lang": lang, "NavData": nav, "NotFound": true}
		if err := p.Templates.RenderAuthed(w, "perfil_usuario", p, s, lang, data); err != nil {
			serverError(w, err)
		}
		return
	}

	idiomasRows, err := p.DB.QueryAll(ctx,
		`SELECT i.nombre FROM usuario_idiomas ui JOIN idiomas i ON i.idiomaId = ui.idiomaId WHERE ui.usuarioId = ?`, targetId)
	var idiomas []string
	if err == nil {
		for _, row := range idiomasRows {
			idiomas = append(idiomas, store.Str(row["nombre"]))
		}
	}

	rol := store.Str(user["rol"])
	esProfesor := rol == "instructor" || rol == "both"
	esMiPerfil := targetId == uid

	yoLoSigo := false
	if !esMiPerfil {
		rel, err := p.DB.QueryOne(ctx,
			`SELECT id FROM relaciones WHERE seguidorId = ? AND seguidoId = ? AND estado = 'following'`, uid, targetId)
		yoLoSigo = err == nil && rel != nil
	}

	var resenas []resenaItem
	if esProfesor {
		rows, err := p.DB.QueryAll(ctx,
			`SELECT r.*, u.nombre AS estudiante_nombre, u.avatar AS estudiante_avatar
			 FROM resenas r
			 JOIN usuarios u ON u.usuarioId = r.estudianteId
			 WHERE r.profesorId = ?
			 ORDER BY r.created_at DESC LIMIT 50`, targetId)
		if err == nil {
			base := BaseURL(r)
			for _, row := range rows {
				nombre := store.Str(row["estudiante_nombre"])
				av := store.Str(row["estudiante_avatar"])
				if av != "" {
					av = base + "/" + av
				}
				initial := "?"
				if nombre != "" {
					initial = strings.ToUpper(nombre[:1])
				}
				rating := int(store.Int(row["rating"]))
				stars := strings.Repeat("★", rating) + strings.Repeat("☆", 5-rating)
				resenas = append(resenas, resenaItem{
					EstudianteNombre: nombre,
					EstudianteAvatar: av,
					Initial:          initial,
					Stars:            stars,
					Comentario:       store.Str(row["comentario"]),
					Fecha:            dateDMY(store.Str(row["created_at"])),
				})
			}
		}
	}

	var clases []claseItem
	if esProfesor {
		rows, err := p.DB.QueryAll(ctx,
			`SELECT cp.claseId AS id, cp.titulo, cp.precio_base, cp.duracion_min AS duracion, cp.activa,
			        m.materiaId AS materia_id, m.nombre AS materia,
			        (SELECT COUNT(*) FROM sesiones_clase sc WHERE sc.claseId = cp.claseId AND sc.fin IS NULL) AS alumnos_activos
			 FROM clases_programadas cp
			 LEFT JOIN materias m ON m.materiaId = cp.materiaId
			 WHERE cp.instructorId = ? AND cp.activa = 1
			 ORDER BY cp.created_at DESC LIMIT 20`, targetId)
		if err == nil {
			for _, row := range rows {
				mid := store.Int(row["materia_id"])
				clases = append(clases, claseItem{
					ID:             store.Int(row["id"]),
					MateriaID:      mid,
					Titulo:         store.Str(row["titulo"]),
					Materia:        store.Str(row["materia"]),
					Precio:         fmt.Sprintf("%.2f", store.Float(row["precio_base"])),
					Duracion:       store.Int(row["duracion"]),
					AlumnosActivos: store.Int(row["alumnos_activos"]),
					Color:          subjectColors[mid],
					Icono:          subjectIcons[mid],
				})
			}
		}
	}

	nombre := store.Str(user["nombre"])
	initial := "?"
	if nombre != "" {
		initial = strings.ToUpper(nombre[:1])
	}
	avatar := store.Str(user["avatar"])
	if avatar != "" {
		avatar = BaseURL(r) + "/" + avatar
	}
	sitioWebRaw := strings.TrimSpace(store.Str(user["sitio_web"]))

	data := map[string]any{
		"Lang":          lang,
		"NavData":       nav,
		"NotFound":      false,
		"TargetId":      targetId,
		"EsMiPerfil":    esMiPerfil,
		"YoLoSigo":      yoLoSigo,
		"Nombre":        nombre,
		"Username":      store.Str(user["username"]),
		"Avatar":        avatar,
		"Initial":       initial,
		"EsProfesor":    esProfesor,
		"Biografia":     store.Str(user["biografia"]),
		"Pais":          store.Str(user["pais"]),
		"IdiomasJoined": strings.Join(idiomas, ", "),
		"SitioWeb":      sitioWebRaw,
		"SitioWebLink":  httpLinkRe.MatchString(sitioWebRaw),
		"MiembroDesde":  dateMY(store.Str(user["created_at"])),
		"Calificacion":  fmt.Sprintf("%.1f", store.Float(user["calificacion"])),
		"NumResenas":    store.Int(user["num_resenas"]),
		"Resenas":       resenas,
		"Clases":        clases,
		"CSRF":          CSRFToken(s),
	}
	if err := p.Templates.RenderAuthed(w, "perfil_usuario", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func dateMY(v string) string {
	t, err := time.Parse("2006-01-02 15:04:05", v)
	if err != nil {
		return v
	}
	return t.Format("Jan 2006")
}

func dateDMY(v string) string {
	t, err := time.Parse("2006-01-02 15:04:05", v)
	if err != nil {
		return v
	}
	return t.Format("02/01/2006")
}
