package web

import (
	"context"
	"net/http"
	"sort"
	"strconv"
	"strings"
	"time"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

// solicitudItem is one "se busca" card in the bounty feed.
type solicitudItem struct {
	SolicitudID       int64
	Titulo            string
	Descripcion       string
	Materia           string
	MateriaID         int64
	MateriaColor      string
	MateriaIcon       string
	Estudiante        string
	EstudianteID      int64
	EstudianteInitial string
	Ofertas           int64
	Alumnos           int64
	EsMia             bool
	YaOferto          bool
	MiPrecio          string
	Tiempo            string
	Bids              []ofertaItem
}

// ofertaItem is a teacher bid on a solicitud.
type ofertaItem struct {
	OfertaID          int64
	Instructor        string
	InstructorID      int64
	InstructorInitial string
	Precio            string
	Moneda            string
	Mensaje           string
}

// HandleClases ports the new "clases" bounty page ("se busca" de cazarrecompensas).
// Students post what they want to learn; guild teachers reply with an hourly
// price; the student accepts and both coordinate by direct message.
func (p *Pages) HandleClases(w http.ResponseWriter, r *http.Request) {
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
	uid := UID(s)

	if r.Method == http.MethodPost {
		p.clasesPost(w, r, s, lang, uid)
		return
	}

	page := CurrentPage(r)
	nav, stop := p.MenuData(w, r, s, page, lang)
	if stop {
		return
	}

	filterMateria := store.Int(r.URL.Query().Get("m"))
	if filterMateria < 0 {
		filterMateria = 0
	}

	rows, err := p.DB.QueryAll(ctx, `
		SELECT s.solicitudId, s.titulo, s.descripcion, s.materiaId, s.alumnos_interesados,
		       s.estado, s.created_at, s.updated_at,
		       m.nombre AS materia,
		       u.usuarioId AS estudiante_id, u.nombre AS estudiante,
		       (SELECT COUNT(*) FROM ofertas_solicitud o WHERE o.solicitudId = s.solicitudId AND o.estado <> 'descartada') AS num_ofertas,
		       (SELECT COUNT(*) FROM ofertas_solicitud o WHERE o.solicitudId = s.solicitudId AND o.instructorId = ?) AS mi_oferta
		FROM solicitudes_clase s
		JOIN usuarios u ON u.usuarioId = s.estudianteId
		LEFT JOIN materias m ON m.materiaId = s.materiaId
		WHERE s.estado = 'abierta'`+
		clasesMateriaClause(filterMateria)+`
		ORDER BY s.updated_at DESC, s.solicitudId DESC
		LIMIT 80`, clasesMateriaArgs(uid, filterMateria)...)
	if err != nil {
		serverError(w, err)
		return
	}

	items := make([]solicitudItem, 0, len(rows))
	for _, row := range rows {
		id := store.Int(row["solicitudId"])
		mid := store.Int(row["materiaId"])
		esMia := store.Int(row["estudiante_id"]) == uid
		it := solicitudItem{
			SolicitudID:       id,
			Titulo:            store.Str(row["titulo"]),
			Descripcion:       store.Str(row["descripcion"]),
			Materia:           store.Str(row["materia"]),
			MateriaID:         mid,
			MateriaColor:      subjectColors[mid],
			MateriaIcon:       subjectIcons[mid],
			Estudiante:        store.Str(row["estudiante"]),
			EstudianteID:      store.Int(row["estudiante_id"]),
			EstudianteInitial: stringInitial(store.Str(row["estudiante"])),
			Ofertas:           store.Int(row["num_ofertas"]),
			Alumnos:           store.Int(row["alumnos_interesados"]),
			EsMia:             esMia,
			YaOferto:          store.Int(row["mi_oferta"]) > 0,
			Tiempo:            clasesAgo(store.Str(row["created_at"])),
		}
		if esMia && it.Ofertas > 0 {
			it.Bids = p.loadSolicitudBids(ctx, id, lang)
		}
		items = append(items, it)
	}

	materias, err := p.DB.QueryAll(ctx, "SELECT materiaId, nombre FROM materias ORDER BY orden ASC")
	if err != nil {
		serverError(w, err)
		return
	}
	var chips []materiaOption
	var formOpts []materiaOption
	for _, m := range materias {
		mid := store.Int(m["materiaId"])
		nombre := store.Str(m["nombre"])
		chips = append(chips, materiaOption{ID: mid, Nombre: nombre, Selected: mid == filterMateria})
		formOpts = append(formOpts, materiaOption{ID: mid, Nombre: nombre})
	}

	data := map[string]any{
		"Lang":          lang,
		"NavData":       nav,
		"IsTeacher":     nav.IsTeacher,
		"Items":         items,
		"HasItems":      len(items) > 0,
		"Materias":      chips,
		"FormMaterias":  formOpts,
		"FilterMateria": filterMateria,
		"Msg":           Flash(s, "clases_msg"),
		"Err":           Flash(s, "clases_err"),
	}
	if err := p.Templates.RenderAuthed(w, "clases", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

// loadSolicitudBids returns the bids of a solicitud for its owner.
func (p *Pages) loadSolicitudBids(ctx context.Context, solicitudId int64, lang string) []ofertaItem {
	rows, err := p.DB.QueryAll(ctx, `
		SELECT o.ofertaId, o.precio_hora, o.codigo_moneda, o.mensaje, o.estado,
		       u.usuarioId AS instructor_id, u.nombre AS instructor
		FROM ofertas_solicitud o
		JOIN usuarios u ON u.usuarioId = o.instructorId
		WHERE o.solicitudId = ? AND o.estado <> 'descartada'
		ORDER BY o.created_at ASC, o.ofertaId ASC`, solicitudId)
	if err != nil {
		return nil
	}
	out := make([]ofertaItem, 0, len(rows))
	for _, row := range rows {
		precio := store.Float(row["precio_hora"])
		out = append(out, ofertaItem{
			OfertaID:          store.Int(row["ofertaId"]),
			Instructor:        store.Str(row["instructor"]),
			InstructorID:      store.Int(row["instructor_id"]),
			InstructorInitial: stringInitial(store.Str(row["instructor"])),
			Precio:            clasesMoney(precio, store.Str(row["codigo_moneda"])),
			Moneda:            store.Str(row["codigo_moneda"]),
			Mensaje:           store.Str(row["mensaje"]),
		})
	}
	return out
}

// clasesPost handles the crear/ofertar/aceptar actions of the bounty page.
func (p *Pages) clasesPost(w http.ResponseWriter, r *http.Request, s *Session, lang string, uid int64) {
	if !p.RequireCSRFOnPost(w, r, s) {
		return
	}
	action := r.PostFormValue("action")
	switch action {
	case "crear":
		p.clasesCrear(w, r, s, lang, uid)
	case "ofertar":
		p.clasesOfertar(w, r, s, lang, uid)
	case "aceptar":
		p.clasesAceptar(w, r, s, lang, uid)
	default:
		redirect(w, r, "clases.php")
	}
}

// clasesCrear publishes a new "se busca" (with phrase homogenization and
// guild-notification matching).
func (p *Pages) clasesCrear(w http.ResponseWriter, r *http.Request, s *Session, lang string, uid int64) {
	ctx := r.Context()
	titulo := strings.TrimSpace(r.PostFormValue("titulo"))
	descripcion := strings.TrimSpace(r.PostFormValue("descripcion"))
	materiaId := store.Int(r.PostFormValue("materiaId"))
	if materiaId < 0 {
		materiaId = 0
	}

	if len([]rune(titulo)) < 5 || len([]rune(titulo)) > 120 {
		s.Set("clases_err", i18n.T(lang, "classes.error", nil))
		redirect(w, r, "clases.php")
		return
	}
	if len([]rune(descripcion)) > 600 {
		descripcion = string([]rune(descripcion)[:600])
	}

	nombre := store.Str(s.Get("nombre"))
	if err := p.notifyGremio(ctx, uid, nombre, titulo, materiaId, r); err != nil {
		s.Set("clases_err", i18n.T(lang, "classes.error", nil))
		redirect(w, r, "clases.php")
		return
	}

	tokens := normalizeTokens(titulo + " " + descripcion + " " + materiaNombre(ctx, p, materiaId))
	tokenKey := strings.Join(tokens, " ")
	if len(tokenKey) > 500 {
		// keep the most relevant (leading) tokens.
		parts := strings.Split(tokenKey, " ")
		tokenKey = strings.Join(parts[:40], " ")
	}

	// Homogenize: merge with an already-open similar solicitud.
	mine := ""
	if merged, ok := p.mergeSimilarSolicitud(ctx, uid, materiaId, tokens, titulo); ok {
		mine = merged
	}
	if mine == "" {
		solicitudId, err := p.DB.Exec(ctx, `
			INSERT INTO solicitudes_clase (estudianteId, titulo, descripcion, materiaId, key_tokens, alumnos_interesados, estado)
			VALUES (?, ?, ?, ?, ?, 1, 'abierta')`,
			uid, titulo, descripcion, nullableID(materiaId), tokenKey)
		if err == nil && solicitudId > 0 {
			mine = "posted"
		}
	}

	if mine == "posted" {
		s.Set("clases_msg", i18n.T(lang, "classes.posted", nil))
	} else if mine == "merged" {
		s.Set("clases_msg", i18n.T(lang, "classes.already_open", nil))
	} else {
		s.Set("clases_err", i18n.T(lang, "classes.error", nil))
	}
	redirect(w, r, "clases.php")
}

// mergeSimilarSolicitud grows an existing open solicitud when its phrase is
// equivalent. Returns ("merged", true) when one was found.
func (p *Pages) mergeSimilarSolicitud(ctx context.Context, uid, materiaId int64, tokens []string, titulo string) (string, bool) {
	rows, err := p.DB.QueryAll(ctx, `
		SELECT solicitudId, materiaId, key_tokens FROM solicitudes_clase
		WHERE estado = 'abierta' AND estudianteId <> ? AND key_tokens <> ''`, uid)
	if err != nil {
		return "", false
	}
	for _, row := range rows {
		sid := store.Int(row["solicitudId"])
		other := strings.Fields(store.Str(row["key_tokens"]))
		overlap := 0
		otherSet := map[string]bool{}
		for _, t := range other {
			otherSet[t] = true
		}
		for _, t := range tokens {
			if otherSet[t] {
				overlap++
			}
		}
		sameMateria := int64(0)
		if store.Int(row["materiaId"]) == materiaId {
			sameMateria = 1
		}
		if overlap >= 2 || (sameMateria == 1 && overlap >= 1) {
			if _, err := p.DB.Exec(ctx,
				"UPDATE solicitudes_clase SET alumnos_interesados = alumnos_interesados + 1, updated_at = NOW() WHERE solicitudId = ?",
				sid); err == nil {
				return "merged", true
			}
		}
	}
	return "", false
}

// notifyGremio notifies teachers whose competencias match the request, or who
// teach the same materia. It returns an error only on query failure.
func (p *Pages) notifyGremio(ctx context.Context, uid int64, nombre, titulo string, materiaId int64, r *http.Request) error {
	ids := map[int64]bool{}
	push := func(rows []map[string]any, key string) {
		for _, row := range rows {
			id := store.Int(row[key])
			if id > 0 && id != uid {
				ids[id] = true
			}
		}
	}

	// (a) teachers whose competencias contain any token.
	tokens := normalizeTokens(titulo)
	if len(tokens) > 0 {
		q := "SELECT DISTINCT usuarioId FROM usuarios WHERE (rol = 'instructor' OR rol = 'both') AND ("
		parts := make([]string, 0, len(tokens))
		args := make([]any, 0, len(tokens))
		for _, t := range tokens {
			parts = append(parts, "competencias LIKE ?")
			args = append(args, "%"+t+"%")
		}
		q += strings.Join(parts, " OR ") + ")"
		if rows, err := p.DB.QueryAll(ctx, q, args...); err == nil {
			push(rows, "usuarioId")
		}
	}

	// (b) teachers with an active class in the same materia.
	if materiaId > 0 {
		if rows, err := p.DB.QueryAll(ctx,
			"SELECT DISTINCT instructorId AS usuarioId FROM clases_programadas WHERE materiaId = ? AND activa = true", materiaId); err == nil {
			push(rows, "usuarioId")
		}
	}

	link := "clases.php"
	if materiaId > 0 {
		link = "clases.php?m=" + itoa(int(materiaId))
	}
	cuerpo := "«" + titulo + "»"
	if nombre != "" {
		cuerpo = nombre + " busca: " + cuerpo
	}
	counter := 0
	for id := range ids {
		if counter >= 40 {
			break
		}
		p.addNotif(ctx, id, "se_busca", "🎯 Nueva misión para tu gremio", cuerpo, link)
		counter++
	}
	return nil
}

// clasesOfertar lets a guild teacher bid an hourly price on an open solicitud.
func (p *Pages) clasesOfertar(w http.ResponseWriter, r *http.Request, s *Session, lang string, uid int64) {
	ctx := r.Context()
	if !p.isInstructorDB(ctx, uid) {
		s.Set("clases_err", i18n.T(lang, "classes.error", nil))
		redirect(w, r, "clases.php")
		return
	}
	solicitudId := store.Int(r.PostFormValue("solicitud_id"))
	precio := store.Float(r.PostFormValue("precio_hora"))
	mensaje := strings.TrimSpace(r.PostFormValue("mensaje"))
	if len([]rune(mensaje)) > 500 {
		mensaje = string([]rune(mensaje)[:500])
	}
	if solicitudId <= 0 || precio <= 0 {
		s.Set("clases_err", i18n.T(lang, "classes.error", nil))
		redirect(w, r, "clases.php")
		return
	}

	var estudianteId int64
	row, err := p.DB.QueryOne(ctx,
		"SELECT estudianteId, estado, titulo FROM solicitudes_clase WHERE solicitudId = ?", solicitudId)
	if err != nil || row == nil {
		s.Set("clases_err", i18n.T(lang, "classes.error", nil))
		redirect(w, r, "clases.php")
		return
	}
	if store.Str(row["estado"]) != "abierta" {
		s.Set("clases_err", i18n.T(lang, "classes.error", nil))
		redirect(w, r, "clases.php")
		return
	}
	estudianteId = store.Int(row["estudianteId"])
	titulo := store.Str(row["titulo"])

	moneda := "USD"
	if t, err := p.DB.QueryOne(ctx,
		"SELECT u.rol, pa.codigo_moneda FROM usuarios u LEFT JOIN paises pa ON pa.paisId = u.pais_id WHERE u.usuarioId = ?", uid); err == nil && t != nil {
		if c := store.Str(t["codigo_moneda"]); c != "" {
			moneda = c
		}
	}

	existing, _ := p.DB.QueryOne(ctx,
		"SELECT ofertaId FROM ofertas_solicitud WHERE solicitudId = ? AND instructorId = ?", solicitudId, uid)
	if existing != nil {
		_, _ = p.DB.Exec(ctx,
			"UPDATE ofertas_solicitud SET precio_hora = ?, mensaje = ?, estado = 'pendiente', created_at = NOW() WHERE ofertaId = ?",
			precio, mensaje, store.Int(existing["ofertaId"]))
	} else {
		_, err = p.DB.Exec(ctx,
			"INSERT INTO ofertas_solicitud (solicitudId, instructorId, precio_hora, codigo_moneda, mensaje, estado) VALUES (?, ?, ?, ?, ?, 'pendiente')",
			solicitudId, uid, precio, moneda, mensaje)
		if err != nil {
			s.Set("clases_err", i18n.T(lang, "classes.error", nil))
			redirect(w, r, "clases.php")
			return
		}
	}

	profe := store.Str(s.Get("nombre"))
	if profe == "" {
		profe = "Un profesor del gremio"
	}
	if estudianteId > 0 {
		p.addNotif(ctx, estudianteId, "se_busca", "💰 Presupuesto del gremio",
			profe+" te envió un presupuesto para «"+titulo+"»", "clases.php")
	}
	s.Set("clases_msg", i18n.T(lang, "classes.bid_sent", nil))
	redirect(w, r, "clases.php")
}

// clasesAceptar lets the solicitud owner accept a bid; both parties are
// notified and redirected to the DM chat to coordinate.
func (p *Pages) clasesAceptar(w http.ResponseWriter, r *http.Request, s *Session, lang string, uid int64) {
	ctx := r.Context()
	ofertaId := store.Int(r.PostFormValue("oferta_id"))
	if ofertaId <= 0 {
		redirect(w, r, "clases.php")
		return
	}
	oferta, err := p.DB.QueryOne(ctx,
		"SELECT o.solicitudId, o.instructorId FROM ofertas_solicitud o WHERE o.ofertaId = ? AND o.estado <> 'descartada'", ofertaId)
	if err != nil || oferta == nil {
		s.Set("clases_err", i18n.T(lang, "classes.error", nil))
		redirect(w, r, "clases.php")
		return
	}
	solicitudId := store.Int(oferta["solicitudId"])
	instructorId := store.Int(oferta["instructorId"])

	row, err := p.DB.QueryOne(ctx,
		"SELECT estudianteId, estado, titulo FROM solicitudes_clase WHERE solicitudId = ?", solicitudId)
	if err != nil || row == nil {
		s.Set("clases_err", i18n.T(lang, "classes.error", nil))
		redirect(w, r, "clases.php")
		return
	}
	if store.Int(row["estudianteId"]) != uid || store.Str(row["estado"]) != "abierta" {
		s.Set("clases_err", i18n.T(lang, "classes.error", nil))
		redirect(w, r, "clases.php")
		return
	}
	titulo := store.Str(row["titulo"])

	_, _ = p.DB.Exec(ctx,
		"UPDATE ofertas_solicitud SET estado = 'aceptada' WHERE ofertaId = ?", ofertaId)
	_, _ = p.DB.Exec(ctx,
		"UPDATE solicitudes_clase SET estado = 'aceptada', aceptada_con = ?, updated_at = NOW() WHERE solicitudId = ?",
		ofertaId, solicitudId)

	alumno := store.Str(s.Get("nombre"))
	if instructorId > 0 {
		p.addNotif(ctx, instructorId, "se_busca", "🎉 ¡Te eligieron!",
			alumno+" aceptó tu presupuesto para «"+titulo+"». Coordinen la clase en el chat.", "personas.php?chat="+itoa(int(uid)))
	}
	s.Set("clases_msg", i18n.T(lang, "classes.taken", nil))
	redirect(w, r, "personas.php?chat="+itoa(int(instructorId)))
}

// normalizeTokens folds accents, lowercases, splits on non-word chars and drops
// Spanish/English stopwords. It returns unique tokens (sorted) for matching.
func normalizeTokens(text string) []string {
	t := strings.ToLower(accentsFold.Replace(text))
	parts := strings.FieldsFunc(t, func(r rune) bool {
		return !(r >= 'a' && r <= 'z') && !(r >= '0' && r <= '9')
	})
	var out []string
	seen := map[string]bool{}
	for _, p := range parts {
		if len(p) < 2 {
			continue
		}
		if stopwords[p] {
			continue
		}
		if seen[p] {
			continue
		}
		seen[p] = true
		out = append(out, p)
	}
	sort.Strings(out)
	if len(out) > 12 {
		out = out[:12]
	}
	return out
}

var accentsFold = strings.NewReplacer(
	"á", "a", "é", "e", "í", "i", "ó", "o", "ú", "u", "ü", "u", "ñ", "n",
	"à", "a", "è", "e", "ì", "i", "ò", "o", "ù", "u", "â", "a", "ê", "e",
	"ô", "o", "û", "u", "ç", "c", "ã", "a", "õ", "o",
)

var stopwords = map[string]bool{
	"quiero": true, "necesito": true, "busco": true, "una": true, "un": true,
	"de": true, "la": true, "el": true, "los": true, "las": true, "clase": true,
	"clases": true, "para": true, "por": true, "que": true, "con": true,
	"mi": true, "tu": true, "en": true, "y": true, "o": true,
	"al": true, "del": true, "me": true, "como": true, "curso": true,
	"profesor": true, "particular": true, "online": true, "aprender": true,
	"estoy": true, "tengo": true, "quisiera": true, "se": true,
	"hay": true, "ser": true, "es": true, "sobre": true, "todo": true,
	"want": true, "need": true, "look": true, "looking": true,
	"for": true, "an": true, "the": true, "to": true, "with": true,
	"my": true, "of": true, "and": true, "or": true, "from": true,
	"like": true, "class": true, "classes": true, "lessons": true,
	"learning": true, "learn": true, "help": true, "be": true, "this": true,
	"that": true, "is": true, "have": true, "on": true, "in": true,
}

func materiaNombre(ctx context.Context, p *Pages, materiaId int64) string {
	if materiaId <= 0 {
		return ""
	}
	row, err := p.DB.QueryOne(ctx, "SELECT nombre FROM materias WHERE materiaId = ?", materiaId)
	if err != nil || row == nil {
		return ""
	}
	return store.Str(row["nombre"])
}

func stringInitial(nombre string) string {
	if nombre == "" {
		return "?"
	}
	return strings.ToUpper(string([]rune(nombre)[:1]))
}

func clasesAgo(ts string) string {
	t, err := time.Parse("2006-01-02 15:04:05", ts)
	if err != nil {
		return ""
	}
	d := time.Since(t)
	switch {
	case d < time.Minute:
		return "hace un momento"
	case d < time.Hour:
		return "hace " + strconv.FormatInt(int64(d.Minutes()), 10) + " min"
	case d < 24*time.Hour:
		return "hace " + strconv.FormatInt(int64(d.Hours()), 10) + " h"
	default:
		return "hace " + strconv.FormatInt(int64(d.Hours()/24), 10) + " d"
	}
}

func clasesMoney(amount float64, moneda string) string {
	switch moneda {
	case "USD":
		return "$ " + strconv.FormatFloat(amount, 'f', 2, 64)
	default:
		return strconv.FormatFloat(amount, 'f', 2, 64) + " " + moneda
	}
}

func clasesMateriaClause(m int64) string {
	if m > 0 {
		return " AND s.materiaId = ?"
	}
	return ""
}

func clasesMateriaArgs(uid, m int64) []any {
	if m > 0 {
		return []any{uid, m}
	}
	return []any{uid}
}
