package web

import (
	"net/http"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

type materiaOption struct {
	ID       int64
	Nombre   string
	Selected bool
}

// HandleOfertaClase ports oferta_clase.php (create a class offer).
func (p *Pages) HandleOfertaClase(w http.ResponseWriter, r *http.Request) {
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

	errorMsg, successMsg := "", ""

	if r.Method == http.MethodPost {
		if !p.RequireCSRFOnPost(w, r, s) {
			return
		}
		precioMin := maxFloat(0, r.PostFormValue("precio_min"))
		precioMax := maxFloat(0, r.PostFormValue("precio_max"))
		alumnosMin := maxInt(1, r.PostFormValue("alumnos_min"))
		alumnosMax := maxInt(1, r.PostFormValue("alumnos_max"))
		materiaId := store.Int(r.PostFormValue("materiaId"))
		soloYo := 0
		if r.PostFormValue("solo_yo") != "" {
			soloYo = 1
		}

		teacher, err := p.DB.QueryOne(ctx,
			`SELECT u.rol, pa.codigo_moneda FROM usuarios u
			 LEFT JOIN paises pa ON pa.paisId = u.pais_id WHERE u.usuarioId = ?`, uid)
		moneda := "USD"
		if err == nil && teacher != nil {
			if c := store.Str(teacher["codigo_moneda"]); c != "" {
				moneda = c
			}
		}

		precioBase := precioMin
		if precioBase <= 0 {
			precioBase = maxFloat(1, r.PostFormValue("precio_max"))
		}

		if precioMax > 0 && precioMax < precioMin {
			errorMsg = i18n.T(lang, "oferta.error_price", nil)
		} else if alumnosMax < alumnosMin {
			errorMsg = i18n.T(lang, "oferta.error_students", nil)
		} else {
			_, err := p.DB.Exec(ctx,
				`INSERT INTO clases_programadas
				     (instructorId, materiaId, precio_min, precio_max, precio_base,
				      codigo_moneda, alumnos_min, alumnos_max, solo_yo, activa)
				 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)`,
				uid, nullableID(materiaId), precioMin, precioMax, precioBase,
				moneda, alumnosMin, alumnosMax, soloYo)
			if err == nil {
				successMsg = i18n.T(lang, "oferta.success", nil)
			} else {
				errorMsg = i18n.T(lang, "oferta.error_db", nil)
			}
		}
	}

	materias, err := p.DB.QueryAll(ctx, "SELECT materiaId, nombre FROM materias ORDER BY orden ASC")
	if err != nil {
		serverError(w, err)
		return
	}
	teacher, err := p.DB.QueryOne(ctx,
		`SELECT u.rol, pa.nombre AS pais, pa.simbolo, pa.codigo_moneda, pa.tasa_usd
		 FROM usuarios u LEFT JOIN paises pa ON pa.paisId = u.pais_id
		 WHERE u.usuarioId = ?`, uid)
	if err != nil {
		serverError(w, err)
		return
	}

	var opts []materiaOption
	selected := store.Int(r.PostFormValue("materiaId"))
	for _, m := range materias {
		id := store.Int(m["materiaId"])
		opts = append(opts, materiaOption{ID: id, Nombre: store.Str(m["nombre"]), Selected: selected == id})
	}

	moneda := "USD"
	simbolo := "$"
	pais := ""
	if teacher != nil {
		moneda = store.Str(teacher["codigo_moneda"])
		if moneda == "" {
			moneda = "USD"
		}
		simbolo = store.Str(teacher["simbolo"])
		pais = store.Str(teacher["pais"])
	}

	data := map[string]any{
		"Lang":          lang,
		"NavData":       nav,
		"Error":         errorMsg,
		"Success":       successMsg,
		"Materias":      opts,
		"Pais":          pais,
		"Simbolo":       simbolo,
		"Moneda":        moneda,
		"HasTeacher":    teacher != nil,
		"PrecioMin":     r.PostFormValue("precio_min"),
		"PrecioMax":     r.PostFormValue("precio_max"),
		"AlumnosMin":    r.PostFormValue("alumnos_min"),
		"AlumnosMax":    r.PostFormValue("alumnos_max"),
		"SoloYo":        r.PostFormValue("solo_yo") != "",
	}
	if err := p.Templates.RenderAuthed(w, "oferta_clase", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func nullableID(v int64) any {
	if v <= 0 {
		return nil
	}
	return v
}

func maxFloat(def float64, v string) float64 {
	f := store.Float(v)
	if f < def {
		return def
	}
	return f
}

func maxInt(def int64, v string) int64 {
	i := store.Int(v)
	if i < def {
		return def
	}
	return i
}
