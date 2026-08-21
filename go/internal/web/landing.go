package web

import (
	"context"
	"encoding/json"
	"net/http"
	"regexp"
	"strings"
	"time"

	"classexpress/internal/store"
)

// baseCounters mirror the hardcoded baseline numbers in landing.php /
// landing_api.php: the platform's registered users before the landing form
// started tracking pre-registrations.
const (
	baseStudents = 154
	baseTeachers = 201
)

var emailRe = regexp.MustCompile(`^[^@\s]+@[^@\s]+\.[^@\s]+$`)

// landingCounts mirrors getCounts(): baseline numbers plus the current
// landing_preregistros tallies.
func landingCounts(ctx context.Context, db *store.DB) (students, teachers int) {
	students, teachers = baseStudents, baseTeachers
	if db == nil {
		return
	}
	rows, err := db.QueryAll(ctx, "SELECT rol, COUNT(*) AS cnt FROM landing_preregistros GROUP BY rol")
	if err != nil {
		return
	}
	for _, r := range rows {
		switch store.Str(r["rol"]) {
		case "estudiante":
			students += int(store.Int(r["cnt"]))
		case "instructor":
			teachers += int(store.Int(r["cnt"]))
		}
	}
	return
}

// HandleLanding redirects the public landing page to the login page:
// the landing is not used, everything goes through login.php.
func (p *Pages) HandleLanding(w http.ResponseWriter, r *http.Request) {
	redirect(w, r, "login.php")
}

// HandleLandingAPI ports landing_api.php: validates the pre-registration
// email/rol, unlocks login for the owner email and stores the signup.
func (p *Pages) HandleLandingAPI(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]string{"error": "Método no permitido"})
		return
	}

	var input struct {
		Action string `json:"action"`
		Email  string `json:"email"`
		Rol    string `json:"rol"`
		Monto  int    `json:"monto"`
	}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]string{"error": "JSON inválido."})
		return
	}

	email := strings.ToLower(strings.TrimSpace(input.Email))
	rol := input.Rol

	if !emailRe.MatchString(email) {
		writeJSON(w, http.StatusOK, map[string]string{"error": "Correo electrónico no válido."})
		return
	}
	if rol != "estudiante" && rol != "instructor" {
		writeJSON(w, http.StatusOK, map[string]string{"error": "Rol no válido."})
		return
	}

	// Owner emergency access: entering the owner access email in the signup
	// form unlocks the login page for this session (bypasses the IP allowlist).
	owner := strings.ToLower(strings.TrimSpace(p.Cfg.LoginOwnerAccessEmail))
	if owner != "" && email == owner {
		if s := SessionFrom(r.Context()); s != nil {
			s.Set("ce_emergency", "1")
		}
		writeJSON(w, http.StatusOK, map[string]any{
			"ok":       true,
			"redirect": "login.php",
			"message":  "Acceso desbloqueado. Redirigiendo al login...",
		})
		return
	}

	if p.DB == nil {
		writeJSON(w, http.StatusOK, map[string]string{"error": "Error de conexión. Intenta de nuevo."})
		return
	}
	ctx := r.Context()

	user, err := p.DB.QueryOne(ctx, "SELECT usuarioId, nombre, email FROM usuarios WHERE email = ? LIMIT 1", email)
	if err != nil {
		writeJSON(w, http.StatusOK, map[string]string{"error": "Error de conexión. Intenta de nuevo."})
		return
	}
	if user == nil {
		writeJSON(w, http.StatusOK, map[string]string{"error": "Este correo no existe en nuestros registros. Crea tu cuenta primero en la plataforma."})
		return
	}

	// Already pre-registered: return the current counters.
	existing, err := p.DB.QueryOne(ctx, "SELECT id FROM landing_preregistros WHERE email = ? LIMIT 1", email)
	if err == nil && existing != nil {
		students, teachers := landingCounts(ctx, p.DB)
		writeJSON(w, http.StatusOK, map[string]any{
			"ok":       true,
			"message":  "Ya estás registrado. ¡Pronto te contactaremos!",
			"students": students,
			"teachers": teachers,
		})
		return
	}

	if _, err := p.DB.Exec(ctx, "INSERT INTO landing_preregistros (email, rol) VALUES (?, ?)", email, rol); err != nil {
		writeJSON(w, http.StatusOK, map[string]string{"error": "Error de conexión. Intenta de nuevo."})
		return
	}

	students, teachers := landingCounts(ctx, p.DB)
	nombre := store.Str(user["nombre"])
	if nombre == "" {
		nombre = "Usuario"
	}
	if p.Mail != nil {
		p.Mail.Send(email, "¡Gracias por tu interés en ClassExpress!", landingThankYouEmail(nombre, rol))
	}

	writeJSON(w, http.StatusOK, map[string]any{
		"ok":       true,
		"message":  "¡Gracias, " + nombre + "! Te avisaremos cuando ClassExpress esté listo.",
		"students": students,
		"teachers": teachers,
	})
}

// landingThankYouEmail ports sendThankYouEmail()'s HTML body.
func landingThankYouEmail(nombre, rol string) string {
	rolLabel := "estudiante"
	if rol == "instructor" {
		rolLabel = "profesor"
	}
	year := time.Now().Format("2006")
	return `<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<div style="max-width:560px;margin:0 auto;padding:40px 24px;">
    <div style="text-align:center;margin-bottom:32px;">
        <h1 style="font-size:28px;color:#66ddbd;margin:0;">ClassExpress</h1>
    </div>
    <div style="background:#ffffff;border:1px solid #dbe2ee;border-radius:16px;padding:32px;">
        <h2 style="color:#1e293b;font-size:22px;margin:0 0 16px;">¡Hola ` + nombre + `!</h2>
        <p style="color:#64748b;font-size:16px;line-height:1.6;margin:0 0 20px;">
            Gracias por registrarte como <strong style="color:#66ddbd;">` + rolLabel + `</strong> en ClassExpress.
        </p>
        <p style="color:#64748b;font-size:16px;line-height:1.6;margin:0 0 20px;">
            Estamos preparando todo para el lanzamiento. Serás de los primeros en acceder a nuestra plataforma de
            <strong style="color:#1e293b;">clases particulares en tiempo real</strong> por videoconferencia.
        </p>
        <div style="background:#eef1f8;border-radius:12px;padding:20px;margin:20px 0;">
            <p style="color:#64748b;font-size:14px;margin:0 0 8px;">Lo que recibirás al lanzarte:</p>
            <ul style="color:#1e293b;font-size:14px;line-height:2;margin:0;padding-left:20px;">
                <li>100 créditos de bienvenida</li>
                <li>Acceso a todas las materias</li>
                <li>Videoconferencia HD en vivo</li>
                <li>Chat en tiempo real</li>
            </ul>
        </div>
        <p style="color:#64748b;font-size:16px;line-height:1.6;margin:20px 0 0;">
            Te contactaremos pronto con más novedades.
        </p>
    </div>
    <div style="text-align:center;padding:24px 0;">
        <p style="color:#94a3b8;font-size:12px;margin:0;">
            &copy; ` + year + ` ClassExpress — Bunny Software E.I.R.L.
        </p>
    </div>
</div>
</body>
</html>`
}
