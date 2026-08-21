package web

import (
	"context"
	"encoding/json"
	"log"
	"net/http"
	"net/mail"
	"net/url"
	"regexp"
	"strings"
	"time"

	"classexpress/internal/auth"
	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

func urlQueryEscape(s string) string {
	return url.QueryEscape(s)
}

func timeNow() time.Time { return time.Now() }

var (
	usernameRE = regexp.MustCompile(`^[a-zA-Z0-9_]+$`)
	emailRE    = regexp.MustCompile(`^[^@\s]+@[^@\s]+\.[^@\s]+$`)
)

func validEmail(s string) bool {
	if !emailRE.MatchString(s) || len(s) > 254 {
		return false
	}
	_, err := mail.ParseAddress(s)
	return err == nil
}

// FooterParams builds the params map for t('general.footer_text').
func FooterParams() map[string]string {
	return map[string]string{
		"bootstrap": `<a href="https://getbootstrap.com/" class="text-secondary">Bootstrap</a>`,
		"author":    `<a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA" class="text-secondary">@RodrigoConejeros</a>`,
	}
}

// redirect is a tiny helper for header('Location: ...') + exit.
func redirect(w http.ResponseWriter, r *http.Request, url string) {
	http.Redirect(w, r, url, http.StatusFound)
}

// HandleLogin ports login.php.
func (p *Pages) HandleLogin(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if p.ApplyLangParam(w, r, s) {
		return
	}
	lang := p.ResolveLang(s, r)

	// IP allowlist (only when the login page may be opened).
	if len(p.Cfg.LoginAllowedIPs) > 0 && s.Get("ce_emergency") == "" {
		clientIP := ClientIP(r)
		isOwner := false
		if uid := UID(s); uid > 0 {
			row, err := p.DB.QueryOne(ctx, "SELECT email FROM usuarios WHERE usuarioId = ?", uid)
			if err == nil && row != nil {
				em := strings.ToLower(strings.TrimSpace(store.Str(row["email"])))
				for _, o := range p.Cfg.AppOwnerEmails {
					if em == strings.ToLower(strings.TrimSpace(o)) {
						isOwner = true
						break
					}
				}
			}
		}
		allowed := false
		for _, a := range p.Cfg.LoginAllowedIPs {
			if a == clientIP {
				allowed = true
				break
			}
		}
		if !isOwner && !allowed {
			redirect(w, r, "landing.php")
			return
		}
	}

	errorLogin, errorSignup, successMsg := "", "", ""
	activeTab := "signin"
	ip := ClientIP(r)

	if r.Method == http.MethodPost {
		switch r.PostFormValue("action") {
		case "signin":
			activeTab = "signin"
			errorLogin, successMsg = p.doSignIn(ctx, w, r, s, lang, ip)
		case "resend_verify":
			activeTab = "signin"
			errorLogin, successMsg = p.doResendVerify(ctx, r, lang, ip)
		case "signup":
			activeTab = "signup"
			errorSignup, successMsg, activeTab = p.doSignUp(ctx, r, lang, ip)
		}
	}

	// Remember-me auto-login (only when not logged in).
	if !LoggedIn(s) {
		p.RememberAutoLogin(ctx, w, s, r)
	}

	// Already-logged-in users get redirected.
	if LoggedIn(s) {
		redirect(w, r, "materias.php")
		return
	}

	paises, err := p.DB.QueryAll(ctx, "SELECT paisId, nombre, codigo_moneda, simbolo FROM paises ORDER BY nombre ASC")
	if err != nil {
		paises = []map[string]any{}
	}
	idiomas, err := p.DB.QueryAll(ctx, "SELECT idiomaId, nombre FROM idiomas ORDER BY nombre ASC")
	if err != nil {
		idiomas = []map[string]any{}
	}

	adsFreeActive := false
	if row, err := p.DB.QueryOne(ctx,
		"SELECT id FROM ads_free_compras WHERE estado='activo' AND valido_hasta > NOW() ORDER BY valido_hasta DESC LIMIT 1"); err == nil && row != nil {
		adsFreeActive = true
	}

	data := map[string]any{
		"Lang":         lang,
		"ActiveTab":    activeTab,
		"ErrorLogin":   errorLogin,
		"ErrorSignup":  errorSignup,
		"SuccessMsg":   successMsg,
		"Paises":       paises,
		"Idiomas":      idiomas,
		"Email":        r.FormValue("email"),
		"Nombre":       r.FormValue("nombre"),
		"Username":     r.FormValue("username"),
		"EmailSignup":  r.FormValue("email_signup"),
		"PaisID":       r.FormValue("pais_id"),
		"LoginRol":     r.FormValue("login_rol"),
		"SelectedIdiomas": strings.Split(r.FormValue("idiomas"), ","),
		"Deleted":      r.URL.Query().Get("deleted") == "1",
		"AdsFreeActive": adsFreeActive,
		"AAAdUnitID": p.Cfg.AAAdUnitID,
		"FooterParams": FooterParams(),
	}
	if err := p.Templates.Render(w, "login", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func (p *Pages) doSignIn(ctx context.Context, w http.ResponseWriter, r *http.Request, s *Session, lang, ip string) (string, string) {
	email := strings.TrimSpace(r.PostFormValue("email"))
	password := r.PostFormValue("password")

	if !p.Rate.Allow(ctx, "login", ip, 10, 300) {
		return i18n.T(lang, "login.rate_limit", nil), ""
	}
	if email == "" || password == "" {
		return i18n.T(lang, "login.error_fields", nil), ""
	}
	row, err := p.DB.QueryOne(ctx,
		"SELECT usuarioId, nombre, rol, password, verificado, creditos, idioma_preferido, eliminado FROM usuarios WHERE email = ? LIMIT 1",
		email)
	if row == nil && err != nil {
		return i18n.T(lang, "login.error_db", nil), ""
	}
	if row == nil {
		return i18n.T(lang, "login.error_notfound", nil), ""
	}
	if store.Bool(row["eliminado"]) {
		return i18n.T(lang, "login.error_deleted", nil), ""
	}
	if !auth.PasswordMatches(store.Str(row["password"]), password) {
		return i18n.T(lang, "login.error_wrongpass", nil), ""
	}
	if !store.Bool(row["verificado"]) {
		return i18n.T(lang, "login.error_unverified", nil), ""
	}

	p.Sessions.Regenerate(w, r, s)
	uid := store.Str(row["usuarioId"])
	s.Set("usuarioId", uid)
	s.Set("nombre", store.Str(row["nombre"]))
	s.Set("rol", store.Str(row["rol"]))
	s.Set("creditos", store.Str(row["creditos"]))
	if pref := store.Str(row["idioma_preferido"]); pref != "" {
		s.Set("_lang", pref)
		http.SetCookie(w, &http.Cookie{Name: "ce_lang", Value: pref, Path: "/", MaxAge: 30 * 86400})
	}

	if r.PostFormValue("recuerdame") != "" {
		tok := NewRememberToken()
		_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET remember_token = ? WHERE usuarioId = ?", HashToken(tok), uid)
		http.SetCookie(w, &http.Cookie{Name: "ce_remember", Value: tok, Path: "/", MaxAge: 30 * 24 * 60 * 60, HttpOnly: true, Secure: IsHTTPS(r)})
		http.SetCookie(w, &http.Cookie{Name: CookieName, Value: s.ID, Path: "/", MaxAge: 30 * 24 * 60 * 60, HttpOnly: true, Secure: IsHTTPS(r), SameSite: http.SameSiteLaxMode})
	} else {
		_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET remember_token = NULL WHERE usuarioId = ?", uid)
		http.SetCookie(w, &http.Cookie{Name: "ce_remember", Value: "", Path: "/", MaxAge: -1, HttpOnly: true, Secure: IsHTTPS(r)})
	}

	loginRol := r.PostFormValue("login_rol")
	mode := "student"
	if loginRol == "instructor" {
		mode = "teacher"
	}
	http.SetCookie(w, &http.Cookie{Name: "ce_app_modo", Value: mode, Path: "/", MaxAge: 365 * 24 * 60 * 60, Secure: IsHTTPS(r)})
	redirect(w, r, "materias.php")
	return "", ""
}

func (p *Pages) doResendVerify(ctx context.Context, r *http.Request, lang, ip string) (string, string) {
	email := strings.TrimSpace(r.PostFormValue("email"))
	if !p.Rate.Allow(ctx, "resend_verify", ip, 3, 300) {
		return i18n.T(lang, "login.rate_limit", nil), ""
	}
	if validEmail(email) {
		row, err := p.DB.QueryOne(ctx, "SELECT usuarioId, nombre, verificado FROM usuarios WHERE email = ? LIMIT 1", email)
		if err == nil && row != nil && !store.Bool(row["verificado"]) {
			tok := auth.NewToken()
			_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET token_verificacion = ? WHERE usuarioId = ?", tok, store.Str(row["usuarioId"]))
			link := BaseURL(r) + "/verify.php?token=" + urlQueryEscape(tok)
			p.Mail.SendVerify(email, store.Str(row["nombre"]), link)
		}
	}
	return "", i18n.T(lang, "login.resend_sent", nil)
}

func (p *Pages) doSignUp(ctx context.Context, r *http.Request, lang, ip string) (string, string, string) {
	nombre := strings.TrimSpace(r.PostFormValue("nombre"))
	email := strings.TrimSpace(r.PostFormValue("email_signup"))
	username := strings.TrimSpace(r.PostFormValue("username"))
	password := r.PostFormValue("password_signup")
	confirm := r.PostFormValue("password_confirm")
	paisID := r.PostFormValue("pais_id")
	rol := r.PostFormValue("rol")
	if rol != "student" && rol != "instructor" {
		rol = "student"
	}

	if !p.Rate.Allow(ctx, "signup", ip, 5, 600) {
		return i18n.T(lang, "login.signup_rate_limit", nil), "", "signup"
	}
	if nombre == "" || email == "" || username == "" || password == "" || confirm == "" {
		return i18n.T(lang, "login.error_fields", nil), "", "signup"
	}
	if !validEmail(email) {
		return i18n.T(lang, "login.error_email", nil), "", "signup"
	}
	if len(username) < 3 {
		return i18n.T(lang, "login.error_user", nil), "", "signup"
	}
	if !usernameRE.MatchString(username) {
		return i18n.T(lang, "login.error_userchars", nil), "", "signup"
	}
	if len(password) < 6 {
		return i18n.T(lang, "login.error_passshort", nil), "", "signup"
	}
	if password != confirm {
		return i18n.T(lang, "login.error_pass", nil), "", "signup"
	}

	existing, err := p.DB.QueryOne(ctx, "SELECT usuarioId, verificado FROM usuarios WHERE email = ? LIMIT 1", email)
	if err != nil {
		return i18n.T(lang, "login.error_db", nil), "", "signup"
	}
	existingUser, err := p.DB.QueryOne(ctx, "SELECT usuarioId FROM usuarios WHERE username = ? LIMIT 1", username)
	if err != nil {
		return i18n.T(lang, "login.error_db", nil), "", "signup"
	}
	if existingUser != nil {
		return i18n.T(lang, "login.error_userexists", nil), "", "signup"
	}
	if existing != nil {
		if store.Bool(existing["verificado"]) {
			return i18n.T(lang, "login.error_emailexists", nil), "", "signup"
		}
		tok := auth.NewToken()
		_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET token_verificacion = ? WHERE usuarioId = ?", tok, store.Str(existing["usuarioId"]))
		link := BaseURL(r) + "/verify.php?token=" + urlQueryEscape(tok)
		sent := p.Mail.SendVerify(email, nombre, link)
		if sent {
			return "", i18n.T(lang, "login.verify_resent", nil), "signin"
		}
		return i18n.T(lang, "login.verify_send_error", nil), "", "signin"
	}

	hash, err := auth.HashPassword(password)
	if err != nil {
		return i18n.T(lang, "login.error_db", nil), "", "signup"
	}
	tok := auth.NewToken()
	var paisAny any
	if paisID == "" {
		paisAny = nil
	} else {
		paisAny = paisID
	}
	newID, err := p.DB.Exec(ctx,
		"INSERT INTO usuarios (nombre, email, password, rol, verificado, token_verificacion, pais_id, creditos, username, ultimoContenido, ultimaClase, ultimaSala) VALUES (?, ?, ?, ?, 0, ?, ?, 100, ?, '', '', '')",
		nombre, email, hash, rol, tok, paisAny, username)
	if err != nil {
		log.Printf("signup insert: %v", err)
		return i18n.T(lang, "login.error_db", nil), "", "signup"
	}
	idiomas := r.PostForm["idiomas"]
	for _, iid := range idiomas {
		_, _ = p.DB.Exec(ctx, "INSERT IGNORE INTO usuario_idiomas (usuarioId, idiomaId) VALUES (?, ?)", newID, store.Int(iid))
	}
	link := BaseURL(r) + "/verify.php?token=" + urlQueryEscape(tok)
	sent := p.Mail.SendVerify(email, nombre, link)
	if sent {
		return "", i18n.T(lang, "login.success_created", nil), "signin"
	}
	return i18n.T(lang, "login.verify_send_error", nil), "", "signin"
}

// HandleLogout ports logout.php.
func (p *Pages) HandleLogout(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s != nil {
		if uid := UID(s); uid > 0 {
			_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET remember_token = NULL WHERE usuarioId = ?", uid)
		}
		p.Sessions.Destroy(w, r, s)
	}
	http.SetCookie(w, &http.Cookie{Name: "ce_remember", Value: "", Path: "/", MaxAge: -1, HttpOnly: true, Secure: IsHTTPS(r)})
	redirect(w, r, "login.php")
}

// HandleLangAPI ports lang_api.php.
func (p *Pages) HandleLangAPI(w http.ResponseWriter, r *http.Request) {
	s := SessionFrom(r.Context())

	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.Header().Set("Access-Control-Allow-Origin", "*")

	lang := r.URL.Query().Get("lang")
	if lang == "" {
		lang = r.PostFormValue("lang")
	}
	if !i18n.IsSupported(lang) {
		w.WriteHeader(http.StatusBadRequest)
		_ = json.NewEncoder(w).Encode(map[string]string{"error": "Invalid language code"})
		return
	}
	if r.URL.Query().Get("save") != "" || r.PostFormValue("save") != "" {
		s.Set("_lang", lang)
		if uid := UID(s); uid > 0 {
			_, _ = p.DB.Exec(r.Context(), "UPDATE usuarios SET idioma_preferido = ? WHERE usuarioId = ?", lang, uid)
		}
		http.SetCookie(w, &http.Cookie{Name: "ce_lang", Value: lang, Path: "/", MaxAge: 30 * 86400})
	}
	_ = json.NewEncoder(w).Encode(map[string]any{
		"ok":           true,
		"lang":         lang,
		"translations": i18n.Translations(lang),
	})
}

// HandleVerify ports verify.php.
func (p *Pages) HandleVerify(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if p.ApplyLangParam(w, r, s) {
		return
	}
	lang := p.ResolveLang(s, r)

	status := "error"
	message := "Enlace de verificación inválido o expirado."
	token := strings.TrimSpace(r.URL.Query().Get("token"))
	if token != "" {
		row, err := p.DB.QueryOne(ctx,
			"SELECT usuarioId, nombre, verificado FROM usuarios WHERE token_verificacion = ? LIMIT 1", token)
		if err != nil || row == nil {
			message = "No se encontró el enlace de verificación. Puede que ya haya sido usado o sea inválido."
		} else if store.Bool(row["verificado"]) {
			status = "already"
			message = "Tu correo ya está verificado. Puedes iniciar sesión."
		} else {
			_, _ = p.DB.Exec(ctx,
				"UPDATE usuarios SET verificado = 1, token_verificacion = '' WHERE usuarioId = ?",
				store.Str(row["usuarioId"]))
			status = "success"
			message = "¡Correo verificado con éxito! Ahora puedes iniciar sesión."
		}
	}
	data := map[string]any{
		"Lang":         lang,
		"Status":       status,
		"Message":      message,
		"FooterParams": FooterParams(),
	}
	if err := p.Templates.Render(w, "verify", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

// HandleForgotPassword ports forgot_password.php.
func (p *Pages) HandleForgotPassword(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if p.ApplyLangParam(w, r, s) {
		return
	}
	lang := p.ResolveLang(s, r)

	errorMsg, success := "", ""
	if r.Method == http.MethodPost {
		if !CSRFRequire(w, r, s) {
			return
		}
		email := strings.TrimSpace(r.PostFormValue("email"))
		if email == "" || !validEmail(email) {
			errorMsg = i18n.T(lang, "forgot.invalid_email", nil)
		} else {
			row, err := p.DB.QueryOne(ctx, "SELECT usuarioId, nombre, verificado FROM usuarios WHERE email = ? LIMIT 1", email)
			if err == nil && row != nil && store.Bool(row["verificado"]) {
				tok := auth.NewToken()
				expiry := timeNow().Unix() + 3600
				_, _ = p.DB.Exec(ctx,
					"UPDATE usuarios SET reset_token = ?, reset_token_expiry = ? WHERE usuarioId = ?",
					tok, expiry, store.Str(row["usuarioId"]))
				link := BaseURL(r) + "/reset_password.php?token=" + urlQueryEscape(tok)
				p.Mail.SendReset(email, store.Str(row["nombre"]), link)
			}
			success = i18n.T(lang, "forgot.success", nil)
		}
	}
	data := map[string]any{
		"Lang":    lang,
		"Error":   errorMsg,
		"Success": success,
		"Email":   r.FormValue("email"),
	}
	if err := p.Templates.Render(w, "forgot_password", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

// HandleResetPassword ports reset_password.php.
func (p *Pages) HandleResetPassword(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if p.ApplyLangParam(w, r, s) {
		return
	}
	lang := p.ResolveLang(s, r)

	token := strings.TrimSpace(r.URL.Query().Get("token"))
	errorMsg, success := "", ""
	hasForm := false
	nombre := ""

	row, err := p.DB.QueryOne(ctx,
		"SELECT usuarioId, nombre, reset_token_expiry FROM usuarios WHERE reset_token = ? LIMIT 1", token)
	if err == nil && row != nil {
		if store.Int(row["reset_token_expiry"]) < timeNow().Unix() {
			row = nil
			errorMsg = i18n.T(lang, "reset.expired", nil)
		}
	}
	if token == "" || (row == nil && errorMsg == "") {
		errorMsg = i18n.T(lang, "reset.invalid_token", nil)
	}

	if r.Method == http.MethodPost && row != nil {
		if !CSRFRequire(w, r, s) {
			return
		}
		password := r.PostFormValue("password")
		confirm := r.PostFormValue("confirm")
		if len(password) < 6 {
			errorMsg = i18n.T(lang, "reset.password_short", nil)
		} else if password != confirm {
			errorMsg = i18n.T(lang, "reset.password_mismatch", nil)
		} else {
			hash, err := auth.HashPassword(password)
			if err != nil {
				serverError(w, err)
				return
			}
			_, _ = p.DB.Exec(ctx,
				"UPDATE usuarios SET password = ?, reset_token = '', reset_token_expiry = 0 WHERE usuarioId = ?",
				hash, store.Str(row["usuarioId"]))
			success = i18n.T(lang, "reset.success", nil)
			row = nil
		}
	}

	if row != nil {
		hasForm = true
		nombre = store.Str(row["nombre"])
	}
	data := map[string]any{
		"Lang":      lang,
		"Token":     token,
		"Error":     errorMsg,
		"Success":   success,
		"HasForm":   hasForm,
		"GreetingParams": map[string]string{
			"name": `<strong class="text-primary">` + nombre + `</strong>`,
		},
	}
	if err := p.Templates.Render(w, "reset_password", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

// HandleIndex ports index.php.
func (p *Pages) HandleIndex(w http.ResponseWriter, r *http.Request) {
	s := SessionFrom(r.Context())
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	p.RememberAutoLogin(r.Context(), w, s, r)
	if !LoggedIn(s) {
		redirect(w, r, "login.php")
		return
	}
	rol := s.Get("rol")
	if rol != "estudiante" && rol != "student" {
		redirect(w, r, "dashboard_profesor.php")
		return
	}
	redirect(w, r, "materias.php")
}

func serverError(w http.ResponseWriter, err error) {
	log.Printf("page error: %v", err)
	http.Error(w, "Error interno del servidor", http.StatusInternalServerError)
}

var errNoSession = &noSessionError{}

type noSessionError struct{}

func (e *noSessionError) Error() string { return "sesión no disponible" }
