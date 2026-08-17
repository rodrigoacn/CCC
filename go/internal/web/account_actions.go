package web

import (
	"io"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"classexpress/internal/auth"
	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

// HandleUpdateBio ports update_bio.php.
func (p *Pages) HandleUpdateBio(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if !p.GuardPage(w, r, s) {
		return
	}
	if r.Method != http.MethodPost {
		redirect(w, r, "perfil.php")
		return
	}
	if !CSRFRequire(w, r, s) {
		return
	}
	lang := p.ResolveLang(s, r)
	uid := UID(s)
	bio := strings.TrimSpace(r.PostFormValue("biografia"))

	if len([]rune(bio)) > 1000 {
		s.Set("bio_msg", i18n.T(lang, "profile.bio_too_long", nil))
		redirect(w, r, "perfil.php")
		return
	}
	_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET biografia = ? WHERE usuarioId = ?", bio, uid)
	s.Set("bio_msg", i18n.T(lang, "profile.bio_saved", nil))
	redirect(w, r, "perfil.php")
}

// HandleUpdateLanguages ports update_languages.php.
func (p *Pages) HandleUpdateLanguages(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if !p.GuardPage(w, r, s) {
		return
	}
	if r.Method != http.MethodPost {
		redirect(w, r, "perfil.php")
		return
	}
	uid := UID(s)
	_, _ = p.DB.Exec(ctx, "DELETE FROM usuario_idiomas WHERE usuarioId = ?", uid)
	if err := r.ParseForm(); err == nil {
		for _, v := range r.PostForm["idiomas"] {
			if id := store.Int(v); id > 0 {
				_, _ = p.DB.Exec(ctx, "INSERT IGNORE INTO usuario_idiomas (usuarioId, idiomaId) VALUES (?, ?)", uid, id)
			}
		}
	}
	redirect(w, r, "perfil.php")
}

var avatarMimeExt = map[string]string{
	"image/jpeg": "jpg",
	"image/png":  "png",
	"image/gif":  "gif",
	"image/webp": "webp",
}

// HandleUploadAvatar ports upload_avatar.php.
func (p *Pages) HandleUploadAvatar(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if !p.GuardPage(w, r, s) {
		return
	}
	if r.Method != http.MethodPost {
		redirect(w, r, "perfil.php")
		return
	}
	uid := UID(s)

	file, header, err := r.FormFile("avatar")
	if err != nil {
		s.Set("avatar_msg", "Error al subir el archivo.")
		redirect(w, r, "perfil.php")
		return
	}
	defer file.Close()

	// Sniff MIME from content (mirrors finfo_file).
	buf := make([]byte, 512)
	n, _ := io.ReadFull(file, buf)
	mime := http.DetectContentType(buf[:n])
	if _, ok := avatarMimeExt[mime]; !ok {
		s.Set("avatar_msg", "Solo se permiten JPG, PNG, GIF y WEBP.")
		redirect(w, r, "perfil.php")
		return
	}
	if header.Size > 5*1024*1024 {
		s.Set("avatar_msg", "El archivo no debe superar 5MB.")
		redirect(w, r, "perfil.php")
		return
	}

	dir := filepath.Join(p.WebDir, "uploads", "avatars")
	if p.WebDir == "" {
		dir = filepath.Join(".", "uploads", "avatars")
	}
	if err := os.MkdirAll(dir, 0o755); err != nil {
		s.Set("avatar_msg", "Error al guardar el archivo.")
		redirect(w, r, "perfil.php")
		return
	}

	ext := avatarMimeExt[mime]
	name := "avatar_" + strconv.FormatInt(uid, 10) + "_" + strconv.FormatInt(time.Now().Unix(), 10) + "." + ext
	dest := filepath.Join(dir, name)

	// Copy the full contents from the current (post-sniff) position.
	out, err := os.Create(dest)
	if err != nil {
		s.Set("avatar_msg", "Error al guardar el archivo.")
		redirect(w, r, "perfil.php")
		return
	}
	if _, err := io.Copy(out, file); err != nil {
		out.Close()
		os.Remove(dest)
		s.Set("avatar_msg", "Error al guardar el archivo.")
		redirect(w, r, "perfil.php")
		return
	}
	out.Close()

	path := "uploads/avatars/" + name
	_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET avatar = ? WHERE usuarioId = ?", path, uid)
	s.Set("avatar_msg", "Foto actualizada.")
	redirect(w, r, "perfil.php")
}

// HandleDeleteAccount ports delete_account.php.
func (p *Pages) HandleDeleteAccount(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if r.Method != http.MethodPost {
		redirect(w, r, "perfil.php")
		return
	}
	if !p.GuardPage(w, r, s) {
		return
	}
	if !CSRFRequire(w, r, s) {
		return
	}
	lang := p.ResolveLang(s, r)
	uid := UID(s)

	password := r.PostFormValue("password")
	if password == "" {
		s.Set("error_delete", i18n.T(lang, "delete.password_required", nil))
		redirect(w, r, "perfil.php")
		return
	}
	row, err := p.DB.QueryOne(ctx, "SELECT password FROM usuarios WHERE usuarioid = ? AND eliminado = 0", uid)
	if err != nil || row == nil || !auth.PasswordMatches(store.Str(row["password"]), password) {
		s.Set("error_delete", i18n.T(lang, "delete.wrong_password", nil))
		redirect(w, r, "perfil.php")
		return
	}

	_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET eliminado = 1, remember_token = NULL, token_verificacion = '' WHERE usuarioid = ?", uid)
	p.Sessions.Destroy(w, r, s)
	http.SetCookie(w, &http.Cookie{Name: "ce_remember", Value: "", Path: "/", MaxAge: -1, HttpOnly: true, Secure: IsHTTPS(r)})
	redirect(w, r, "login.php?deleted=1")
}

// HandleSwitchRole ports switch_role.php.
func (p *Pages) HandleSwitchRole(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	if r.Method != http.MethodPost {
		redirect(w, r, "perfil.php")
		return
	}
	if !p.GuardPage(w, r, s) {
		return
	}
	lang := p.ResolveLang(s, r)
	uid := UID(s)

	if !CSRFValidate(r, s) {
		s.Set("error_switch", i18n.T(lang, "profile.switch_error", nil))
		redirect(w, r, "perfil.php")
		return
	}

	password := r.PostFormValue("password")
	if password == "" {
		s.Set("error_switch", i18n.T(lang, "profile.switch_wrong_password", nil))
		redirect(w, r, "perfil.php")
		return
	}

	row, err := p.DB.QueryOne(ctx, "SELECT password, rol, last_role_switch FROM usuarios WHERE usuarioId = ?", uid)
	if err != nil || row == nil {
		redirect(w, r, "login.php")
		return
	}
	if !auth.PasswordMatches(store.Str(row["password"]), password) {
		s.Set("error_switch", i18n.T(lang, "profile.switch_wrong_password", nil))
		redirect(w, r, "perfil.php")
		return
	}
	rol := store.Str(row["rol"])
	if rol != "both" && rol != "instructor" && rol != "instructor_pendiente" {
		s.Set("error_switch", i18n.T(lang, "profile.switch_error", nil))
		redirect(w, r, "perfil.php")
		return
	}
	if lr := store.Str(row["last_role_switch"]); lr != "" {
		if t, err := time.Parse("2006-01-02 15:04:05", lr); err == nil {
			if int(time.Since(t).Hours()) < 24 {
				s.Set("error_switch", i18n.T(lang, "profile.switch_locked", map[string]string{"days": "1"}))
				redirect(w, r, "perfil.php")
				return
			}
		}
	}

	targetRole := r.PostFormValue("target_role")
	var newCookie string
	switch targetRole {
	case "teacher":
		newCookie = "teacher"
	case "student":
		newCookie = "student"
	default:
		s.Set("error_switch", i18n.T(lang, "profile.switch_error", nil))
		redirect(w, r, "perfil.php")
		return
	}

	_, _ = p.DB.Exec(ctx, "UPDATE usuarios SET last_role_switch = NOW() WHERE usuarioId = ?", uid)
	http.SetCookie(w, &http.Cookie{Name: "ce_app_modo", Value: newCookie, Path: "/", MaxAge: 365 * 24 * 3600, SameSite: http.SameSiteLaxMode})
	s.Set("switch_success", i18n.T(lang, "profile.switch_success", nil))
	redirect(w, r, "perfil.php")
}
