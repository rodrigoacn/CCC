package web

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"math/rand"
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

func FooterParams() map[string]string {
	return map[string]string{
		"bootstrap": `<a href="https://getbootstrap.com/" class="text-secondary">Bootstrap</a>`,
		"author":    `<a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA" class="text-secondary">@RodrigoConejeros</a>`,
	}
}

func redirect(w http.ResponseWriter, r *http.Request, url string) {
	http.Redirect(w, r, url, http.StatusFound)
}

type Pages struct{}
type Session struct{}

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
		case "quick":
			activeTab = "quick"
			errorSignup, successMsg, activeTab = p.doQuickEntry(ctx, r, lang, ip)
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
		"Nickname":     r.FormValue("nickname"),
		"EmailSignup":  r.FormValue("email_signup"),
		"PaisID":       r.FormValue("pais_id"),
		"LoginRol":     r.FormValue("login_rol"),
		"SelectedIdiomas": strings.Split(r.FormValue("idiomas"), ","),
		"Deleted":      r.URL.Query().Get("deleted") == "1",
		"AdsFreeActive": adsFreeActive,
		"AAAdUnitID": p.Cfg.AAAdUnitID,
		"FooterParams": FooterParams(),
	}
	if err := p.Templates.Render(w, "login", p, nil, lang, data); err != nil {
		serverError(w, err)
	}
}

func (p *Pages) doSignIn(ctx context.Context, w http.ResponseWriter, r *http.Request, s *Session, lang string, ip string) (string, string) {
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

	return "", i18n.T(lang, "login.success", nil)
}

func main() {}