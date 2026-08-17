package web

import (
	"net/http"
	"regexp"
	"strconv"
	"strings"
	"time"

	"classexpress/internal/store"
)

var httpLinkRe = regexp.MustCompile(`(?i)^https?://`)

type langOption struct {
	Code  string
	Label string
}

var profileLangs = []langOption{
	{"es", "Español"}, {"en", "English"}, {"fr", "Français"}, {"de", "Deutsch"},
	{"pt", "Português"}, {"it", "Italiano"}, {"zh", "中文"}, {"ja", "日本語"}, {"ru", "Русский"},
}

// HandlePerfil ports perfil.php.
func (p *Pages) HandlePerfil(w http.ResponseWriter, r *http.Request) {
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

	user, err := p.DB.QueryOne(ctx,
		`SELECT u.*, p.nombre AS pais
		 FROM usuarios u
		 LEFT JOIN paises p ON p.paisId = u.pais_id
		 WHERE u.usuarioId = ?`, uid)
	if err != nil || user == nil {
		redirect(w, r, "login.php")
		return
	}

	nombre := store.Str(user["nombre"])
	if nombre == "" {
		nombre = "Usuario"
	}
	email := store.Str(user["email"])
	avatar := store.Str(user["avatar"])
	username := store.Str(user["username"])
	creditos := store.Float(user["creditos"])
	calificacion := store.Float(user["calificacion"])
	numResenas := store.Int(user["num_resenas"])
	biografia := store.Str(user["biografia"])
	pais := store.Str(user["pais"])
	sitioWebRaw := strings.TrimSpace(store.Str(user["sitio_web"]))

	initial := "?"
	if nombre != "" {
		initial = strings.ToUpper(nombre[:1])
	}

	idiomasRows, err := p.DB.QueryAll(ctx,
		`SELECT i.nombre FROM usuario_idiomas ui JOIN idiomas i ON i.idiomaId = ui.idiomaId WHERE ui.usuarioId = ?`, uid)
	var idiomas []string
	if err == nil {
		for _, row := range idiomasRows {
			idiomas = append(idiomas, store.Str(row["nombre"]))
		}
	}

	rol := store.Str(user["rol"])
	canSwitchRole := rol == "both" || rol == "instructor" || rol == "instructor_pendiente"
	switchLockedDays := 0
	if canSwitchRole {
		if lr := store.Str(user["last_role_switch"]); lr != "" {
			if t, err := time.Parse("2006-01-02 15:04:05", lr); err == nil {
				hours := int(time.Since(t).Hours())
				if hours < 24 {
					switchLockedDays = 1
				}
			}
		}
	}
	isSwitchLocked := switchLockedDays > 0

	curLang := s.Get("_lang")
	if curLang == "" {
		if c, err := r.Cookie("ce_lang"); err == nil {
			curLang = c.Value
		}
	}
	if curLang == "" {
		curLang = "en"
	}
	curLangLabel := "Español"
	for _, l := range profileLangs {
		if l.Code == curLang {
			curLangLabel = l.Label
			break
		}
	}

	// Language modal: all languages + user's selected ids.
	todosIdiomas, err := p.DB.QueryAll(ctx, "SELECT idiomaId, nombre FROM idiomas ORDER BY nombre ASC")
	if err != nil {
		serverError(w, err)
		return
	}
	userLangRows, _ := p.DB.QueryAll(ctx, "SELECT idiomaId FROM usuario_idiomas WHERE usuarioId = ?", uid)
	userLangIds := map[int64]bool{}
	for _, row := range userLangRows {
		userLangIds[store.Int(row["idiomaId"])] = true
	}

	data := map[string]any{
		"Lang":             lang,
		"NavData":          nav,
		"UID":              uid,
		"Nombre":           nombre,
		"Email":            email,
		"Username":         username,
		"Avatar":           avatar,
		"Initial":          initial,
		"IsTeacher":        nav.IsTeacher,
		"Creditos":         format0(creditos),
		"Calificacion":     format1(calificacion),
		"NumResenas":       numResenas,
		"ShowRating":       calificacion > 0,
		"Biografia":        biografia,
		"Pais":             pais,
		"Idiomas":          idiomas,
		"IdiomasJoined":    strings.Join(idiomas, ", "),
		"SitioWeb":         sitioWebRaw,
		"SitioWebLink":     httpLinkRe.MatchString(sitioWebRaw),
		"CanSwitchRole":    canSwitchRole,
		"IsSwitchLocked":   isSwitchLocked,
		"SwitchLockedDays": switchLockedDays,
		"CurLang":          curLang,
		"CurLangLabel":     curLangLabel,
		"Langs":            profileLangs,
		"SwitchSuccess":    Flash(s, "switch_success"),
		"SwitchError":      Flash(s, "error_switch"),
		"AvatarMsg":        Flash(s, "avatar_msg"),
		"BioMsg":           Flash(s, "bio_msg"),
		"DeleteError":      Flash(s, "error_delete"),
		"TodosIdiomas":     idiomaChecks(todosIdiomas, userLangIds),
	}
	if err := p.Templates.RenderAuthed(w, "perfil", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

type idiomaCheck struct {
	ID      int64
	Nombre  string
	Checked bool
}

func idiomaChecks(rows []map[string]any, selected map[int64]bool) []idiomaCheck {
	out := make([]idiomaCheck, 0, len(rows))
	for _, row := range rows {
		id := store.Int(row["idiomaId"])
		out = append(out, idiomaCheck{ID: id, Nombre: store.Str(row["nombre"]), Checked: selected[id]})
	}
	return out
}

func format0(f float64) string {
	return strconv.FormatFloat(f, 'f', 0, 64)
}

func format1(f float64) string {
	return strconv.FormatFloat(f, 'f', 1, 64)
}
