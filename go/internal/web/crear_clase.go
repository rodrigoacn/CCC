package web

import (
	"net/http"
	"strings"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

// trimSpaces trims leading/trailing whitespace (PHP trim()).
func trimSpaces(s string) string {
	return strings.TrimSpace(s)
}

// nullableStr returns nil for empty strings so NULL is stored (like PHP's
// 'desc' => $descripcion ?: null).
func nullableStr(s string) any {
	if s == "" {
		return nil
	}
	return s
}

// HandleCrearClase ports crear_clase.php (create a full class listing).
func (p *Pages) HandleCrearClase(w http.ResponseWriter, r *http.Request) {
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

	materiaPref := store.Int(r.URL.Query().Get("materia"))

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

	errorMsg := ""
	titulo := ""
	descripcion := ""
	precioMin := "0"
	precioMax := "0"
	alumnosMin := "1"
	alumnosMax := "10"
	selectedMateria := materiaPref

	if r.Method == http.MethodPost {
		if !CSRFRequire(w, r, s) {
			return
		}
		titulo = trimSpaces(r.PostFormValue("titulo"))
		descripcion = trimSpaces(r.PostFormValue("descripcion"))
		precioMin = r.PostFormValue("precio_min")
		precioMax = r.PostFormValue("precio_max")
		alumnosMin = r.PostFormValue("alumnos_min")
		alumnosMax = r.PostFormValue("alumnos_max")
		selectedMateria = store.Int(r.PostFormValue("materiaId"))

		pmin := maxFloat(0, precioMin)
		pmax := maxFloat(0, precioMax)
		amin := maxInt(1, alumnosMin)
		amax := maxInt(1, alumnosMax)
		moneda := "USD"
		if teacher != nil {
			if c := store.Str(teacher["codigo_moneda"]); c != "" {
				moneda = c
			}
		}
		precioBase := pmin
		if precioBase <= 0 {
			precioBase = maxFloat(1, precioMax)
		}

		switch {
		case titulo == "":
			errorMsg = i18n.T(lang, "crear.title_required", nil)
		case pmax > 0 && pmax < pmin:
			errorMsg = i18n.T(lang, "crear.price_invalid", nil)
		case amax < amin:
			errorMsg = i18n.T(lang, "crear.students_invalid", nil)
		default:
			claseId, err := p.DB.Exec(ctx,
				`INSERT INTO clases_programadas
				     (instructorId, materiaId, titulo, descripcion, precio_min, precio_max,
				      precio_base, codigo_moneda, alumnos_min, alumnos_max, activa)
				 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)`,
				uid, nullableID(selectedMateria), titulo, nullableStr(descripcion),
				pmin, pmax, precioBase, moneda, amin, amax)
			if err == nil && claseId > 0 {
				redirect(w, r, "pre_sala.php?clase="+store.Str(claseId)+"&from=crear")
				return
			}
			errorMsg = i18n.T(lang, "crear.db_error", nil)
		}
	}

	var opts []materiaOption
	for _, m := range materias {
		id := store.Int(m["materiaId"])
		opts = append(opts, materiaOption{ID: id, Nombre: store.Str(m["nombre"]), Selected: selectedMateria == id})
	}

	simbolo := "$"
	moneda := "USD"
	pais := ""
	if teacher != nil {
		moneda = store.Str(teacher["codigo_moneda"])
		if moneda == "" {
			moneda = "USD"
		}
		simbolo = store.Str(teacher["simbolo"])
		if simbolo == "" {
			simbolo = "$"
		}
		pais = store.Str(teacher["pais"])
	}

	priceInfo := ""
	if teacher != nil {
		priceInfo = i18n.T(lang, "crear.price_info", map[string]string{
			"sym":  htmlEscape(simbolo + " " + moneda),
			"pais": htmlEscape(pais),
		})
	}

	data := map[string]any{
		"Lang":          lang,
		"NavData":       nav,
		"Error":         errorMsg,
		"Materias":      opts,
		"HasTeacher":    teacher != nil,
		"Simbolo":       simbolo,
		"Moneda":        moneda,
		"PriceInfo":     priceInfo,
		"Titulo":        titulo,
		"Descripcion":   descripcion,
		"PrecioMin":     precioMin,
		"PrecioMax":     precioMax,
		"AlumnosMin":    alumnosMin,
		"AlumnosMax":    alumnosMax,
	}
	if err := p.Templates.RenderAuthed(w, "crear_clase", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}
