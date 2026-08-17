package api

import (
	"encoding/base64"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"

	"classexpress/internal/auth"
	"classexpress/internal/store"
)

// login mirrors AuthController::login.
func (a *API) login(r *http.Request, body map[string]any) *resp {
	email := strings.TrimSpace(bodyStr(body, "email"))
	password := bodyStr(body, "password")
	if email == "" || password == "" {
		return errOut(http.StatusBadRequest, "Email y contraseña requeridos")
	}

	user, err := a.DB.QueryOne(ctx(r), "SELECT * FROM usuarios WHERE email = ?", email)
	if err != nil || user == nil {
		return errOut(http.StatusUnauthorized, "Credenciales incorrectas")
	}
	if !auth.PasswordMatches(store.Str(user["password"]), password) {
		return errOut(http.StatusUnauthorized, "Credenciales incorrectas")
	}
	if !store.Bool(user["verificado"]) {
		return out(http.StatusForbidden, map[string]string{
			"error": "Cuenta no verificada. Revisa tu correo o solicita un nuevo enlace.",
			"code":  "NOT_VERIFIED",
		})
	}

	token := auth.NewToken()
	if _, err := a.DB.Exec(ctx(r), "INSERT IGNORE INTO mobile_tokens (usuario_id, token) VALUES (?, ?)", user["usuarioId"], token); err != nil {
		log.Printf("login insert token: %v", err)
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	return okOut(map[string]any{
		"token": token,
		"user":  a.formatUserMap(r, user),
	})
}

// register mirrors AuthController::register.
func (a *API) register(r *http.Request, body map[string]any) *resp {
	nombre := strings.TrimSpace(bodyStr(body, "nombre"))
	email := strings.TrimSpace(bodyStr(body, "email"))
	password := bodyStr(body, "password")
	paisID := bodyInt(body, "pais_id")
	rol := bodyStr(body, "rol")
	if rol != "estudiante" && rol != "instructor" {
		rol = "student"
	}

	if nombre == "" || email == "" || password == "" {
		return errOut(http.StatusBadRequest, "Todos los campos son requeridos")
	}
	if !validEmail(email) {
		return errOut(http.StatusBadRequest, "Email inválido")
	}
	if len(password) < 6 {
		return errOut(http.StatusBadRequest, "La contraseña debe tener al menos 6 caracteres")
	}

	exists, err := a.DB.QueryOne(ctx(r), "SELECT usuarioId, verificado FROM usuarios WHERE email = ?", email)
	if err == nil && exists != nil {
		if store.Bool(exists["verificado"]) {
			return errOut(http.StatusConflict, "Email ya registrado")
		}
		return out(http.StatusConflict, map[string]string{
			"error": "Email pendiente de verificación. Revisa tu correo o solicita un nuevo enlace.",
			"code":  "NOT_VERIFIED",
		})
	}

	hash, err := auth.HashPassword(password)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	token := auth.NewToken()

	baseUser := strings.ToLower(regNonAlnum.ReplaceAllString(strings.SplitN(email, "@", 2)[0], "_"))
	if len(baseUser) < 3 {
		baseUser = "usuario"
	}
	username := baseUser
	for suffix := 1; ; suffix++ {
		u, err := a.DB.QueryOne(ctx(r), "SELECT usuarioId FROM usuarios WHERE username = ?", username)
		if err != nil || u == nil {
			break
		}
		username = baseUser + itoa(int64(suffix))
	}

	var paisArg any
	if paisID > 0 {
		paisArg = paisID
	} else {
		paisArg = nil
	}

	nuevoID, err := a.DB.Exec(ctx(r),
		"INSERT INTO usuarios (nombre, email, username, password, rol, pais_id, creditos, verificado, token_verificacion, ultimoContenido, ultimaClase, ultimaSala) VALUES (?, ?, ?, ?, ?, ?, 100, 0, ?, '', '', '')",
		nombre, email, username, hash, rol, paisArg, token)
	if err != nil {
		log.Printf("register insert: %v", err)
		return errOut(http.StatusInternalServerError, "Error al crear la cuenta")
	}

	idiomas := asStringSlice(body["idiomas"])
	for _, iid := range idiomas {
		if _, err := a.DB.Exec(ctx(r), "INSERT IGNORE INTO usuario_idiomas (usuarioId, idiomaId) VALUES (?, ?)", nuevoID, store.Int(iid)); err != nil {
			log.Printf("register idioma: %v", err)
		}
	}

	a.Mail.SendVerify(email, nombre, verifyLink(r, token))

	return okOut(map[string]any{
		"needs_verification": true,
		"message":            "Cuenta creada. Revisa tu correo y verifica tu cuenta antes de iniciar sesión.",
		"email":              email,
	})
}

var regNonAlnum = regexp.MustCompile(`[^a-zA-Z0-9_]`)

// resendVerification mirrors AuthController::resendVerification.
func (a *API) resendVerification(r *http.Request, body map[string]any) *resp {
	email := strings.TrimSpace(bodyStr(body, "email"))
	if email == "" || !validEmail(email) {
		return errOut(http.StatusBadRequest, "Email inválido")
	}

	user, err := a.DB.QueryOne(ctx(r), "SELECT usuarioId, nombre, verificado FROM usuarios WHERE email = ?", email)
	if err == nil && user != nil && !store.Bool(user["verificado"]) {
		token := auth.NewToken()
		if _, err := a.DB.Exec(ctx(r), "UPDATE usuarios SET token_verificacion = ? WHERE usuarioid = ?", token, user["usuarioId"]); err == nil {
			a.Mail.SendVerify(email, store.Str(user["nombre"]), verifyLink(r, token))
		}
	}

	return okOut(map[string]any{"message": "Si el correo está pendiente de verificación, enviamos un nuevo enlace."})
}

// verifyEmail mirrors AuthController::verifyEmail.
func (a *API) verifyEmail(r *http.Request, body map[string]any) *resp {
	token := strings.TrimSpace(bodyStr(body, "token"))
	if token == "" {
		return errOut(http.StatusBadRequest, "Token requerido")
	}

	user, err := a.DB.QueryOne(ctx(r), "SELECT usuarioId, nombre, verificado FROM usuarios WHERE token_verificacion = ?", token)
	if err != nil || user == nil {
		return errOut(http.StatusBadRequest, "Enlace inválido o expirado")
	}
	if store.Bool(user["verificado"]) {
		return okOut(map[string]any{"message": "Tu correo ya estaba verificado. Puedes iniciar sesión.", "already_verified": true})
	}

	if _, err := a.DB.Exec(ctx(r), "UPDATE usuarios SET verificado = 1, token_verificacion = '' WHERE usuarioid = ?", user["usuarioId"]); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"message": "Correo verificado. Ya puedes iniciar sesión.", "verified": true})
}

// forgotPassword mirrors AuthController::forgotPassword.
func (a *API) forgotPassword(r *http.Request, body map[string]any) *resp {
	email := strings.TrimSpace(bodyStr(body, "email"))
	if email == "" || !validEmail(email) {
		return errOut(http.StatusBadRequest, "Email inválido")
	}

	row, err := a.DB.QueryOne(ctx(r), "SELECT usuarioId, nombre, verificado FROM usuarios WHERE email = ?", email)
	if err == nil && row != nil && store.Bool(row["verificado"]) {
		token := auth.NewToken()
		expiry := time.Now().Add(time.Hour).Unix()
		if _, err := a.DB.Exec(ctx(r), "UPDATE usuarios SET reset_token = ?, reset_token_expiry = ? WHERE usuarioId = ?", token, expiry, row["usuarioId"]); err == nil {
			link := baseURLOf(r) + "/reset_password.php?token=" + token
			a.Mail.SendReset(email, store.Str(row["nombre"]), link)
		}
	}

	return okOut(map[string]any{"message": "Si ese correo está registrado, recibirás un enlace para restablecer tu contraseña."})
}

// resetPassword mirrors AuthController::resetPassword.
func (a *API) resetPassword(body map[string]any) *resp {
	token := strings.TrimSpace(bodyStr(body, "token"))
	password := bodyStr(body, "password")
	confirm := bodyStr(body, "confirm")

	if token == "" {
		return errOut(http.StatusBadRequest, "Token requerido")
	}

	row, err := a.DB.QueryOne(ctx(nil), "SELECT usuarioId, reset_token_expiry FROM usuarios WHERE reset_token = ?", token)
	if err != nil || row == nil {
		return errOut(http.StatusBadRequest, "Enlace inválido o ya utilizado")
	}
	if store.Int(row["reset_token_expiry"]) < time.Now().Unix() {
		return errOut(http.StatusBadRequest, "El enlace ha expirado. Solicita uno nuevo.")
	}
	if len(password) < 6 {
		return errOut(http.StatusBadRequest, "La contraseña debe tener al menos 6 caracteres")
	}
	if password != confirm {
		return errOut(http.StatusBadRequest, "Las contraseñas no coinciden")
	}

	hash, err := auth.HashPassword(password)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if _, err := a.DB.Exec(ctx(nil), "UPDATE usuarios SET password = ?, reset_token = '', reset_token_expiry = 0 WHERE usuarioId = ?", hash, row["usuarioId"]); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"message": "Tu contraseña ha sido actualizada correctamente."})
}

// profile mirrors AuthController::profile.
func (a *API) profile(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	return okOut(map[string]any{"user": a.formatUserMap(r, user)})
}

// deleteAccount mirrors AuthController::deleteAccount.
func (a *API) deleteAccount(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	password := bodyStr(body, "password")
	if password == "" {
		return errOut(http.StatusBadRequest, "Contraseña requerida")
	}

	userData, err := a.DB.QueryOne(ctx(r), "SELECT password FROM usuarios WHERE usuarioId = ?", user["usuarioId"])
	if err != nil || userData == nil || !auth.PasswordMatches(store.Str(userData["password"]), password) {
		return errOut(http.StatusUnauthorized, "Contraseña incorrecta")
	}

	if _, err := a.DB.Exec(ctx(r), "DELETE FROM usuarios WHERE usuarioId = ?", user["usuarioId"]); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"ok": true, "message": "Cuenta eliminada correctamente"})
}

// switchRole mirrors AuthController::switchRole.
func (a *API) switchRole(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	password := bodyStr(body, "password")
	targetRole := bodyStr(body, "target_role")

	if password == "" {
		return errOut(http.StatusBadRequest, "Password required")
	}
	if targetRole != "student" && targetRole != "teacher" {
		return errOut(http.StatusBadRequest, "Invalid target role")
	}

	userData, err := a.DB.QueryOne(ctx(r), "SELECT password, rol, last_role_switch FROM usuarios WHERE usuarioId = ?", user["usuarioId"])
	if err != nil || userData == nil || !auth.PasswordMatches(store.Str(userData["password"]), password) {
		return errOut(http.StatusUnauthorized, "Wrong password")
	}

	rol := store.Str(userData["rol"])
	if rol != "both" && rol != "instructor" && rol != "instructor_pendiente" {
		return errOut(http.StatusBadRequest, "Cannot switch role")
	}

	if v, ok := userData["last_role_switch"]; ok && v != nil {
		lastSwitch, err := time.Parse("2006-01-02 15:04:05", store.Str(v))
		if err == nil {
			hoursSince := int(time.Since(lastSwitch).Hours())
			if hoursSince < 24 {
				remaining := 24 - hoursSince
				return out(http.StatusForbidden, map[string]any{
					"error":   "locked",
					"hours":   remaining,
					"days":    1,
					"message": "Locked for " + itoa(int64(remaining)) + " hours",
				})
			}
		}
	}

	if _, err := a.DB.Exec(ctx(r), "UPDATE usuarios SET last_role_switch = NOW() WHERE usuarioId = ?", user["usuarioId"]); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"ok": true, "message": "Role switched"})
}

var avatarDataRe = regexp.MustCompile(`^data:image/(\w+);base64,(.+)$`)

var extMap = map[string]string{"jpeg": "jpg", "jpg": "jpg", "png": "png", "gif": "gif", "webp": "webp"}

// updateAvatar mirrors AuthController::updateAvatar.
func (a *API) updateAvatar(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["usuarioId"])

	data := bodyStr(body, "avatar")
	if data == "" {
		return errOut(http.StatusBadRequest, "No se recibió la imagen")
	}

	m := avatarDataRe.FindStringSubmatch(data)
	if m == nil {
		return errOut(http.StatusBadRequest, "Formato inválido. Usa data:image/...;base64,...")
	}
	ext, ok := extMap[m[1]]
	if !ok {
		return errOut(http.StatusBadRequest, "Solo se permiten JPG, PNG, GIF y WEBP")
	}

	decoded, err := base64.StdEncoding.DecodeString(m[2])
	if err != nil || len(decoded) > 5*1024*1024 {
		return errOut(http.StatusBadRequest, "Imagen inválida o muy grande (máx. 5MB)")
	}

	dir := filepath.Join("uploads", "avatars")
	if err := os.MkdirAll(dir, 0755); err != nil {
		return errOut(http.StatusInternalServerError, "Error al guardar la imagen")
	}

	name := "avatar_" + itoa(uid) + "_" + itoa(time.Now().Unix()) + "." + ext
	if err := os.WriteFile(filepath.Join(dir, name), decoded, 0644); err != nil {
		return errOut(http.StatusInternalServerError, "Error al guardar la imagen")
	}

	path := "uploads/avatars/" + name
	if _, err := a.DB.Exec(ctx(r), "UPDATE usuarios SET avatar = ? WHERE usuarioId = ?", path, uid); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	return okOut(map[string]any{"ok": true, "avatar": baseURLOf(r) + "/" + path})
}

// languages mirrors AuthController::languages.
func (a *API) languages() *resp {
	rows, err := a.DB.QueryAll(ctx(nil), "SELECT idiomaId AS id, nombre FROM idiomas ORDER BY nombre ASC")
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"languages": rows})
}

// updateLanguages mirrors AuthController::updateLanguages.
func (a *API) updateLanguages(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	id := store.Int(user["id"])

	if _, err := a.DB.Exec(ctx(r), "DELETE FROM usuario_idiomas WHERE usuarioId = ?", id); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	for _, iid := range asStringSlice(body["idiomas"]) {
		if _, err := a.DB.Exec(ctx(r), "INSERT IGNORE INTO usuario_idiomas (usuarioId, idiomaId) VALUES (?, ?)", id, store.Int(iid)); err != nil {
			log.Printf("updateLanguages: %v", err)
		}
	}
	return okOut(map[string]any{"ok": true})
}

// setUILanguage mirrors AuthController::setUILanguage.
func (a *API) setUILanguage(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	lang := bodyStr(body, "lang")
	valid := map[string]bool{"es": true, "en": true, "fr": true, "de": true, "pt": true, "it": true, "zh": true, "ja": true, "ru": true, "ar": true, "hi": true, "ko": true}
	if !valid[lang] {
		return errOut(http.StatusBadRequest, "Invalid language code")
	}
	if _, err := a.DB.Exec(ctx(r), "UPDATE usuarios SET idioma_preferido = ? WHERE usuarioId = ?", lang, user["id"]); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{"ok": true, "lang": lang})
}

func asStringSlice(v any) []any {
	if s, ok := v.([]any); ok {
		return s
	}
	return nil
}

func itoa(n int64) string {
	return store.Str(n)
}
