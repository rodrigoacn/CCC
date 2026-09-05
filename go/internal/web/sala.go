package web

import (
	"fmt"
	"html/template"
	"math"
	"net/http"
	"regexp"
	"strconv"

	"classexpress/internal/store"
)

var salaFromRe = regexp.MustCompile(`[^a-zA-Z0-9_-]`)

// salaMatColors mirrors the subject color palette from sala.php.
var salaMatColors = map[int64][2]string{
	1:  {"#2563EB", "#1D4ED8"},
	2:  {"#059669", "#047857"},
	3:  {"#7C3AED", "#6D28D9"},
	4:  {"#0284C7", "#0369A1"},
	5:  {"#D97706", "#B45309"},
	6:  {"#0D9488", "#0F766E"},
	7:  {"#DC2626", "#B91C1C"},
	8:  {"#DB2777", "#BE185D"},
	9:  {"#EA580C", "#C2410C"},
	10: {"#0891B2", "#0E7490"},
	11: {"#E11D48", "#BE123C"},
}

// salaChatMsg is a chat row rendered into the room (mirrors $chat from sala.php).
type salaChatMsg struct {
	MensajeId int64
	Alias     string
	Mensaje   string
}

// HandleSala ports sala.php (the live WebRTC classroom).
func (p *Pages) HandleSala(w http.ResponseWriter, r *http.Request) {
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
	claseId := store.Int(r.URL.Query().Get("clase"))
	from := salaFromRe.ReplaceAllString(r.URL.Query().Get("from"), "")

	if claseId <= 0 {
		redirect(w, r, "buscar.php")
		return
	}

	// Adopt the class's subject color so the room UI is themed.
	row, err := p.DB.QueryOne(ctx,
		"SELECT materiaId FROM clases_programadas WHERE claseId = ? AND activa = true", claseId)
	if err != nil {
		serverError(w, err)
		return
	}
	if row == nil {
		redirect(w, r, "buscar.php")
		return
	}
	materiaId := store.Int(row["materiaId"])

	clase, err := p.DB.QueryOne(ctx,
		`SELECT cp.*, u.nombre AS profesor, u.usuarioId AS prof_uid, u.avatar AS prof_avatar,
		        u.calificacion, u.num_resenas, u.pais_id AS prof_pais_id,
		        pa.nombre AS pais_prof, pa.simbolo AS simbolo_prof, pa.codigo_moneda AS moneda_prof,
		        m.nombre AS materia
		 FROM clases_programadas cp
		 JOIN usuarios u ON u.usuarioId = cp.instructorId
		 LEFT JOIN paises pa ON pa.paisId = u.pais_id
		 LEFT JOIN materias m ON m.materiaId = cp.materiaId
		 WHERE cp.claseId = ? AND cp.activa = true`, claseId)
	if err != nil {
		serverError(w, err)
		return
	}
	if clase == nil {
		redirect(w, r, "buscar.php")
		return
	}

	student, err := p.DB.QueryOne(ctx,
		`SELECT u.nombre, u.creditos, pa.nombre AS pais, pa.simbolo, pa.codigo_moneda, pa.tasa_usd
		 FROM usuarios u LEFT JOIN paises pa ON pa.paisId = u.pais_id
		 WHERE u.usuarioId = ?`, uid)
	if err != nil {
		serverError(w, err)
		return
	}
	if student == nil {
		serverError(w, errNoSession)
		return
	}

	precioUSD := store.Float(clase["precio_base"])
	tasa := store.Float(student["tasa_usd"])
	if tasa == 0 {
		tasa = 1
	}
	montoLocal := math.Round(precioUSD*tasa*100) / 100
	monedaLocal := store.Str(student["codigo_moneda"])
	if monedaLocal == "" {
		monedaLocal = "USD"
	}
	simbolo := store.Str(student["simbolo"])
	if simbolo == "" {
		simbolo = "$"
	}
	creditos := store.Float(student["creditos"])
	instructorId := store.Int(clase["instructorId"])
	isTeacher := uid == instructorId

	// Students can only enter an existing live room; otherwise they go back to
	// pre_sala.php (which shows the reservation flow when the class is off-air).
	if !isTeacher && !p.claseEstaEnVivo(ctx, claseId) {
		redirect(w, r, "pre_sala.php?clase="+store.Str(claseId)+"&from="+from)
		return
	}

	salaId := store.Int(clase["salaId"])
	if salaId == 0 {
		salaId, err = p.DB.Exec(ctx,
			"INSERT INTO salas (claseId, titulo, curso, instructorId) VALUES (?, ?, ?, ?)",
			claseId, store.Str(clase["titulo"]), store.Str(clase["materia"]), instructorId)
		if err != nil {
			serverError(w, err)
			return
		}
		_, _ = p.DB.Exec(ctx,
			"UPDATE clases_programadas SET salaId = ? WHERE claseId = ?", salaId, claseId)
	}

	chatRows, err := p.DB.QueryAll(ctx,
		"SELECT mensajeId, alias, mensaje FROM mensajes_chat WHERE salaId = ? ORDER BY mensajeId DESC LIMIT 30", salaId)
	if err != nil {
		serverError(w, err)
		return
	}
	chat := make([]salaChatMsg, 0, len(chatRows))
	for i := len(chatRows) - 1; i >= 0; i-- {
		chat = append(chat, salaChatMsg{
			MensajeId: store.Int(chatRows[i]["mensajeId"]),
			Alias:     store.Str(chatRows[i]["alias"]),
			Mensaje:   store.Str(chatRows[i]["mensaje"]),
		})
	}

	activos := int64(0)
	if actRow, err := p.DB.QueryOne(ctx,
		"SELECT COUNT(*) AS cnt FROM sesiones_clase WHERE claseId = ? AND fin IS NULL", claseId); err == nil && actRow != nil {
		activos = store.Int(actRow["cnt"])
	}
	alumnosMax := store.Int(clase["alumnos_max"])
	spotsLeft := alumnosMax - activos
	if spotsLeft < 0 {
		spotsLeft = 0
	}
	lastMsgId := int64(0)
	if len(chat) > 0 {
		lastMsgId = chat[len(chat)-1].MensajeId
	}

	salaP, salaPb := "#66ddbd", "#4CBFA3"
	if c, ok := salaMatColors[materiaId]; ok {
		salaP, salaPb = c[0], c[1]
	}

	isTeacherJS := template.JS("false")
	if isTeacher {
		isTeacherJS = template.JS("true")
	}

	data := map[string]any{
		"Lang":          lang,
		"NavData":       nav,
		"ClaseId":       claseId,
		"SalaId":        salaId,
		"CsrfToken":     CSRFToken(s),
		"UID":           uid,
		"IsTeacher":     isTeacher,
		"IsTeacherJS":   isTeacherJS,
		"FromJSON":      template.JS(strconv.Quote(from)),
		"From":          from,
		"ProfUID":       instructorId,
		"LastMsgId":     lastMsgId,
		"Titulo":        store.Str(clase["titulo"]),
		"Materia":       store.Str(clase["materia"]),
		"Profesor":      store.Str(clase["profesor"]),
		"Simbolo":       simbolo,
		"MontoLocal":    fmt.Sprintf("%.2f", montoLocal),
		"MonedaLocal":   monedaLocal,
		"PrecioUSD":     fmt.Sprintf("%.2f", precioUSD),
		"Creditos":      fmt.Sprintf("%.2f", creditos),
		"PrecioTxt":     simbolo + fmt.Sprintf("%.2f", montoLocal) + " " + monedaLocal,
		"AlumnosMax":    alumnosMax,
		"Activos":       activos,
		"SpotsLeft":     spotsLeft,
		"JoinDisabled":  spotsLeft <= 0 || creditos < precioUSD,
		"ClassFull":     spotsLeft <= 0,
		"NeedCredits":   creditos < precioUSD,
		"NeedCreditsT":  !isTeacher && creditos < precioUSD,
		"SalaP":         salaP,
		"SalaPb":        salaPb,
		"Chat":          chat,
		"ChatEmpty":     len(chat) == 0,
		"ShowPriceInfo": !isTeacher,
	}
	if err := p.Templates.RenderAuthed(w, "sala", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}
