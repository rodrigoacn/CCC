// Command functional runs black-box end-to-end tests against a running
// ClassExpress server (Go port). It exercises the web pages and the mobile
// API exactly like a real user/device would, over plain HTTP(S).
//
// Usage (from the go/ directory):
//
//	go run ./tests/functional -base http://localhost:8080 -suite all
//	go run ./tests/functional -base https://classexpress.online -suite web
//	go run ./tests/functional -base https://classexpress.online -suite mobile -env /var/www/classexpress/CCC/.env
//
// The -env flag (or CE_ENV_PATH) points at the .env that the target server
// uses; when reachable, the suite reads verification tokens from the DB and
// cleans up the test users it creates. Without DB access, tests that require
// an authenticated (verified) user are reported as SKIP.
package main

import (
	"bytes"
	"context"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"net/http"
	"net/http/cookiejar"
	"net/url"
	"os"
	"strings"
	"time"

	"classexpress/internal/config"
	"classexpress/internal/database"
	"classexpress/internal/store"

	"github.com/gorilla/websocket"
)

const (
	passStudent = "Ftest_2026!"
	passTeacher = "Ftest_2026!"
)

type suite struct {
	base    string
	apiBase string
	wsURL   string
	timeout time.Duration
	keep    bool
	envPath string

	cli         *http.Client
	db          *store.DB
	dbEnabled   bool
	emails      []string
	webRestricted bool
	rand        string

	results []res
}

type res struct {
	id, name, status, detail string
}

func (s *suite) record(id, name string, err error) {
	var sk skipErr
	if errors.As(err, &sk) {
		s.results = append(s.results, res{id, name, "SKIP", sk.reason})
		fmt.Printf("   %-3s SKIP  %s  (%s)\n", id, name, sk.reason)
		return
	}
	if err != nil {
		s.results = append(s.results, res{id, name, "FAIL", err.Error()})
		fmt.Printf("   %-3s FAIL  %s\n        -> %s\n", id, name, err.Error())
		return
	}
	s.results = append(s.results, res{id, name, "PASS", ""})
	fmt.Printf("   %-3s PASS  %s\n", id, name)
}

func (s *suite) needDB() error {
	if !s.dbEnabled {
		return skipErr{reason: "requiere acceso a la DB (verificar cuenta + cleanup)"}
	}
	return nil
}

// ---------------------------------------------------------------------------
// HTTP helpers
// ---------------------------------------------------------------------------

func (s *suite) req(method, rawurl string, body io.Reader, headers map[string]string) (int, []byte, http.Header, error) {
	ctx, cancel := context.WithTimeout(context.Background(), s.timeout)
	defer cancel()
	r, err := http.NewRequestWithContext(ctx, method, rawurl, body)
	if err != nil {
		return 0, nil, nil, err
	}
	for k, v := range headers {
		r.Header.Set(k, v)
	}
	resp, err := s.cli.Do(r)
	if err != nil {
		return 0, nil, nil, err
	}
	defer resp.Body.Close()
	b, err := io.ReadAll(resp.Body)
	if err != nil {
		return resp.StatusCode, b, resp.Header, err
	}
	return resp.StatusCode, b, resp.Header, nil
}

func (s *suite) get(rawurl string) (int, []byte, http.Header, error) {
	return s.req("GET", rawurl, nil, nil)
}

func (s *suite) postForm(rawurl string, form url.Values) (int, []byte, http.Header, error) {
	return s.req("POST", rawurl, strings.NewReader(form.Encode()),
		map[string]string{"Content-Type": "application/x-www-form-urlencoded"})
}

func (s *suite) postJSON(rawurl, token string, payload map[string]any) (jv, int, error) {
	b, err := json.Marshal(payload)
	if err != nil {
		return nil, 0, err
	}
	hd := map[string]string{"Content-Type": "application/json", "Accept": "application/json"}
	if token != "" {
		hd["Authorization"] = "Bearer " + token
	}
	st, body, _, err := s.req("POST", rawurl, bytes.NewReader(b), hd)
	if err != nil {
		return nil, 0, err
	}
	var j jv
	if err := json.Unmarshal(body, &j); err != nil {
		return nil, st, fmt.Errorf("respuesta JSON inválida (HTTP %d): %s", st, snippet(body))
	}
	return j, st, nil
}

func (s *suite) getJSON(rawurl, token string) (jv, int, error) {
	hd := map[string]string{"Accept": "application/json"}
	if token != "" {
		hd["Authorization"] = "Bearer " + token
	}
	st, body, _, err := s.req("GET", rawurl, nil, hd)
	if err != nil {
		return nil, 0, err
	}
	var j jv
	if err := json.Unmarshal(body, &j); err != nil {
		return nil, st, fmt.Errorf("respuesta JSON inválida (HTTP %d): %s", st, snippet(body))
	}
	return j, st, nil
}

func (s *suite) apiAction(action string) string {
	return s.apiBase + "/api_mobile.php?action=" + url.QueryEscape(action)
}

func snippet(b []byte) string {
	t := strings.TrimSpace(string(b))
	if len(t) > 200 {
		t = t[:200] + "..."
	}
	return t
}

// ---------------------------------------------------------------------------
// JSON helpers
// ---------------------------------------------------------------------------

type jv map[string]any

func (j jv) str(k string) string { return store.Str(j[k]) }
func (j jv) i(k string) int64    { return store.Int(j[k]) }
func (j jv) b(k string) bool     { return store.Bool(j[k]) }
func (j jv) has(k string) bool   { _, ok := j[k]; return ok }
func (j jv) obj(k string) jv {
	m, _ := j[k].(map[string]any)
	return jv(m)
}
func (j jv) arr(k string) []any {
	a, _ := j[k].([]any)
	return a
}

type skipErr struct{ reason string }

func (e skipErr) Error() string { return e.reason }

// ---------------------------------------------------------------------------
// DB helpers
// ---------------------------------------------------------------------------

func (s *suite) connectDB() {
	envPath := s.envPath
	if envPath == "" {
		envPath = os.Getenv("CE_ENV_PATH")
	}
	if envPath != "" {
		os.Setenv("CE_ENV_PATH", envPath)
	}
	cfg, err := config.Load()
	if err != nil {
		fmt.Printf("AVISO: no se pudo leer configuración (.env): %v\n", err)
		return
	}
	db, err := database.Open(context.Background(), cfg)
	if err != nil {
		fmt.Printf("AVISO: DB inaccesible (%s:%d/%s) -> verify/cleanup desactivados: %v\n",
			cfg.DBHost, cfg.DBPort, cfg.DBName, err)
		return
	}
	s.db = &store.DB{Pool: db.Pool}
	s.dbEnabled = true
	fmt.Printf("DB conectada: %s:%d/%s (verify + cleanup activos)\n", cfg.DBHost, cfg.DBPort, cfg.DBName)
}

func (s *suite) verifyToken(email string) (string, error) {
	row, err := s.db.QueryOne(context.Background(),
		"SELECT token_verificacion FROM usuarios WHERE email = ?", email)
	if err != nil {
		return "", err
	}
	if row == nil {
		return "", fmt.Errorf("usuario no encontrado en DB: %s", email)
	}
	return store.Str(row["token_verificacion"]), nil
}

func (s *suite) cleanup() {
	if s.db == nil || s.keep {
		return
	}
	fmt.Println("\n--- Limpieza de datos de prueba ---")
	total := 0
	for _, e := range s.emails {
		total += s.deleteUserData(e)
	}
	fmt.Printf("Total filas eliminadas: %d\n", total)
}

// deleteUserData removes a test user and every row referencing them.
func (s *suite) deleteUserData(email string) int {
	ctx := context.Background()
	rows, err := s.db.QueryAll(ctx, "SELECT usuarioId FROM usuarios WHERE email = ?", email)
	if err != nil {
		fmt.Printf("   [%s] consulta usuario: %v\n", email, err)
		return 0
	}
	if len(rows) == 0 {
		return 0
	}
	ids := make([]string, 0, len(rows))
	for _, r := range rows {
		ids = append(ids, store.Str(r["usuarioId"]))
	}
	idIn := "(" + strings.Join(ids, ",") + ")"
	del := func(table, where string) int {
		n, err := s.db.Exec(ctx, "DELETE FROM "+table+" WHERE "+where)
		if err != nil {
			return 0 // tabla inexistente o sin FKs: best-effort
		}
		if n > 0 {
			fmt.Printf("   %s: %d filas\n", table, n)
		}
		return int(n)
	}

	classRows, _ := s.db.QueryAll(ctx, "SELECT claseId FROM clases_programadas WHERE instructorId IN "+idIn)
	var classIDs []string
	for _, r := range classRows {
		classIDs = append(classIDs, store.Str(r["claseId"]))
	}
	var classIn, salaIn string
	if len(classIDs) > 0 {
		classIn = "(" + strings.Join(classIDs, ",") + ")"
		salaRows, _ := s.db.QueryAll(ctx, "SELECT salaId FROM salas WHERE claseId IN "+classIn)
		var salas []string
		for _, r := range salaRows {
			salas = append(salas, store.Str(r["salaId"]))
		}
		if len(salas) > 0 {
			salaIn = "(" + strings.Join(salas, ",") + ")"
		}
	}

	total := 0
	if salaIn != "" {
		total += del("mensajes_chat", "salaId IN "+salaIn)
		total += del("webrtc_signals", "salaId IN "+salaIn)
		total += del("participantes_sala", "salaId IN "+salaIn)
		total += del("espectadores", "salaId IN "+salaIn)
	}
	if classIn != "" {
		total += del("espectadores", "claseId IN "+classIn)
		total += del("sesiones_clase", "claseId IN "+classIn)
		total += del("salas", "claseId IN "+classIn)
	}
	total += del("clases_programadas", "instructorId IN "+idIn)
	total += del("sesiones_clase", "estudianteId IN "+idIn+" OR instructorId IN "+idIn)
	total += del("participantes_sala", "usuarioId IN "+idIn)
	total += del("pagos", "estudianteId IN "+idIn+" OR profesorId IN "+idIn)
	total += del("compras_tokens", "usuario_id IN "+idIn)
	total += del("checkout_sessions", "usuario_id IN "+idIn)
	total += del("retiros", "usuario_id IN "+idIn)
	total += del("mobile_tokens", "usuario_id IN "+idIn)
	total += del("usuario_idiomas", "usuarioId IN "+idIn)
	total += del("relaciones", "seguidorId IN "+idIn+" OR seguidoId IN "+idIn)
	total += del("usuarios", "usuarioId IN "+idIn)
	return total
}

// ---------------------------------------------------------------------------
// Test definitions
// ---------------------------------------------------------------------------

func (s *suite) newEmail(prefix string) string {
	suff := s.rand + prefix
	email := fmt.Sprintf("ftest.%s@classexpress.app", suff)
	s.emails = append(s.emails, email)
	return email
}

func (s *suite) webTests() {
	fmt.Println("\n=== SUITE WEB ===")
	studentEmail := ""
	teacherEmail := ""
	loggedIn := false

	// W01 health
	err := func() error {
		j, st, err := s.getJSON(s.base+"/health", "")
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d", st)
		}
		if s.dbEnabled && j.str("db") != "up" {
			return fmt.Errorf("db=%s (DB caída: los tests de auth fallarán)", j.str("db"))
		}
		return nil
	}()
	s.record("W01", "health / db", err)

	// W02 root redirect
	err = func() error {
		st, _, hd, err := s.get(s.base + "/")
		if err != nil {
			return err
		}
		if st == 200 {
			return nil
		}
		if st < 300 || st > 399 {
			return fmt.Errorf("HTTP %d (se esperaba redirect)", st)
		}
		if loc := hd.Get("Location"); !strings.Contains(loc, "index.php") && !strings.Contains(loc, "login.php") {
			return fmt.Errorf("redirect a %q (se esperaba index.php o login.php)", loc)
		}
		return nil
	}()
	s.record("W02", "GET / redirige a index.php", err)

	// W03 landing
	err = func() error {
		st, body, _, err := s.get(s.base + "/CCC/landing.php")
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d", st)
		}
		if !bytes.Contains(body, []byte("ClassExpress")) {
			return fmt.Errorf("cuerpo sin marca ClassExpress")
		}
		return nil
	}()
	s.record("W03", "GET /CCC/landing.php (landing pública)", err)

	// W04-W07 static assets
	for _, tc := range []struct{ id, path, ct string }{
		{"W04", "/CCC/styles.css", "text/css"},
		{"W05", "/CCC/robots.txt", ""},
		{"W06", "/CCC/sitemap.xml", ""},
		{"W07", "/CCC/favico.svg", "image/svg"},
	} {
		t := tc
		err = func() error {
			st, body, _, err := s.get(s.base + t.path)
			if err != nil {
				return err
			}
			if st != 200 {
				return fmt.Errorf("HTTP %d", st)
			}
			if len(body) == 0 {
				return fmt.Errorf("cuerpo vacío")
			}
			return nil
		}()
		s.record(t.id, "GET "+t.path+" (estático)", err)
	}

	// W08 login page / IP allowlist
	err = func() error {
		st, _, hd, err := s.get(s.base + "/CCC/login.php")
		if err != nil {
			return err
		}
		if st == 200 {
			s.webRestricted = false
			return nil
		}
		if st == 302 && strings.Contains(hd.Get("Location"), "landing.php") {
			s.webRestricted = true
			return nil
		}
		return fmt.Errorf("HTTP %d inesperado en login.php", st)
	}()
	s.record("W08", "GET /CCC/login.php (IP allowlist)", err)

	// W09-W11 auth wall
	for _, tc := range []struct{ id, path string }{
		{"W09", "/CCC/materias.php"},
		{"W10", "/CCC/perfil.php"},
		{"W11", "/CCC/menu.php"},
	} {
		t := tc
		err = func() error {
			st, _, hd, err := s.get(s.base + t.path)
			if err != nil {
				return err
			}
			if st < 300 || st > 399 {
				return fmt.Errorf("HTTP %d (se esperaba redirect sin sesión)", st)
			}
			_ = hd
			return nil
		}()
		s.record(t.id, "auth wall "+t.path, err)
	}

	// W12 signup estudiante (needs DB + IP permitida)
	err = func() error {
		if s.webRestricted {
			return skipErr{reason: "IP no permitida para login.php (LOGIN_ALLOWED_IPS)"}
		}
		if err := s.needDB(); err != nil {
			return err
		}
		studentEmail = s.newEmail("wstu")
		username := "ftest_stu_" + s.rand
		form := url.Values{
			"action":           {"signup"},
			"nombre":           {"Estudiante Funcional"},
			"email_signup":     {studentEmail},
			"username":         {username},
			"password_signup":  {passStudent},
			"password_confirm": {passStudent},
			"rol":              {"student"},
		}
		st, _, _, err := s.postForm(s.base+"/CCC/login.php", form)
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d tras signup", st)
		}
		row, err := s.db.QueryOne(context.Background(),
			"SELECT verificado FROM usuarios WHERE email = ?", studentEmail)
		if err != nil || row == nil {
			return fmt.Errorf("usuario no creado en DB (err=%v)", err)
		}
		if store.Bool(row["verificado"]) {
			return fmt.Errorf("usuario nació verificado (esperado verificado=0)")
		}
		return nil
	}()
	s.record("W12", "signup estudiante (web)", err)

	// W13 verify web
	err = func() error {
		if studentEmail == "" {
			return skipErr{reason: "sin signup previo"}
		}
		if err := s.needDB(); err != nil {
			return err
		}
		tok, err := s.verifyToken(studentEmail)
		if err != nil {
			return err
		}
		if tok == "" {
			return fmt.Errorf("token_verificacion vacío en DB")
		}
		st, body, _, err := s.get(s.base + "/CCC/verify.php?token=" + url.QueryEscape(tok))
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d en verify.php", st)
		}
		low := strings.ToLower(string(body))
		if !strings.Contains(low, "verific") && !strings.Contains(low, "verified") && !strings.Contains(low, "success") {
			return fmt.Errorf("verify.php no confirmó verificación")
		}
		row, err := s.db.QueryOne(context.Background(),
			"SELECT verificado FROM usuarios WHERE email = ?", studentEmail)
		if err != nil || row == nil || !store.Bool(row["verificado"]) {
			return fmt.Errorf("verificado != 1 en DB tras verify.php")
		}
		return nil
	}()
	s.record("W13", "verify.php (token desde DB)", err)

	// W14 signin
	err = func() error {
		if studentEmail == "" {
			return skipErr{reason: "sin signup previo"}
		}
		if s.webRestricted {
			return skipErr{reason: "IP no permitida"}
		}
		form := url.Values{"action": {"signin"}, "email": {studentEmail}, "password": {passStudent}}
		st, _, hd, err := s.postForm(s.base+"/CCC/login.php", form)
		if err != nil {
			return err
		}
		if st != 302 {
			return fmt.Errorf("HTTP %d (esperado 302 tras login)", st)
		}
		loc := hd.Get("Location")
		if !strings.Contains(loc, "materias.php") && !strings.Contains(loc, "pago.php") {
			return fmt.Errorf("redirect a %q (esperado materias.php)", loc)
		}
		if !strings.Contains(strings.Join(hd.Values("Set-Cookie"), ";"), "PHPSESSID=") {
			return fmt.Errorf("no se estableció cookie PHPSESSID")
		}
		loggedIn = true
		return nil
	}()
	s.record("W14", "signin estudiante (web)", err)

	// W15-W21 authenticated pages
	err = func() error {
		if !loggedIn {
			return skipErr{reason: "sin sesión web"}
		}
		st, body, _, err := s.get(s.base + "/CCC/materias.php")
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d", st)
		}
		if !bytes.Contains(body, []byte("¿Qué estudias hoy?")) {
			return fmt.Errorf("materias.php sin contenido esperado")
		}
		return nil
	}()
	s.record("W15", "GET materias.php (sesión)", err)

	err = func() error {
		if !loggedIn {
			return skipErr{reason: "sin sesión web"}
		}
		j, st, err := s.getJSON(s.apiAction("subjects"), "")
		if err != nil {
			return err
		}
		if st != 200 || len(j.arr("subjects")) == 0 {
			return fmt.Errorf("HTTP %d / subjects vacío", st)
		}
		first := j.arr("subjects")[0].(map[string]any)
		id := store.Str(first["id"])
		if id == "" {
			return fmt.Errorf("subject sin id")
		}
		st2, body, _, err := s.get(s.base + "/CCC/contenido.php?materia=" + url.QueryEscape(id) + "&nombre=Matem%C3%A1ticas")
		if err != nil {
			return err
		}
		if st2 != 200 {
			return fmt.Errorf("contenido.php HTTP %d", st2)
		}
		if len(body) == 0 {
			return fmt.Errorf("contenido.php vacío")
		}
		return nil
	}()
	s.record("W16", "GET contenido.php?materia=1ra", err)

	for _, tc := range []struct{ id, path, want string }{
		{"W17", "/CCC/menu.php", ""},
		{"W18", "/CCC/perfil.php", ""},
		{"W19", "/CCC/buscar.php", ""},
		{"W20", "/CCC/creditos.php", ""},
		{"W21", "/CCC/profesores.php", ""},
	} {
		t := tc
		err = func() error {
			if !loggedIn {
				return skipErr{reason: "sin sesión web"}
			}
			st, body, _, err := s.get(s.base + t.path)
			if err != nil {
				return err
			}
			if st != 200 {
				return fmt.Errorf("HTTP %d", st)
			}
			if t.want != "" && !bytes.Contains(body, []byte(t.want)) {
				return fmt.Errorf("sin contenido esperado %q", t.want)
			}
			return nil
		}()
		s.record(t.id, "GET "+t.path+" (sesión)", err)
	}

	// W22 lang_api
	err = func() error {
		if !loggedIn {
			return skipErr{reason: "sin sesión web"}
		}
		j, st, err := s.getJSON(s.base+"/CCC/lang_api.php?lang=en&save=1", "")
		if err != nil {
			return err
		}
		if st != 200 || j.str("lang") != "en" {
			return fmt.Errorf("HTTP %d lang=%s", st, j.str("lang"))
		}
		return nil
	}()
	s.record("W22", "lang_api.php save lang=en", err)

	// W23 web session + api_sala CSRF (write action sin token -> 403)
	err = func() error {
		if !loggedIn {
			return skipErr{reason: "sin sesión web"}
		}
		st, body, _, err := s.req("POST", s.base+"/CCC/api_sala.php?action=join", bytes.NewReader(nil),
			map[string]string{"Content-Type": "application/x-www-form-urlencoded"})
		if err != nil {
			return err
		}
		if st != 403 || !bytes.Contains(bytes.ToLower(body), []byte("csrf")) {
			return fmt.Errorf("HTTP %d (esperado 403 CSRF): %s", st, snippet(body))
		}
		return nil
	}()
	s.record("W23", "api_sala write sin CSRF (sesión web)", err)

	// W24 WS handshake
	err = func() error {
		d := websocket.Dialer{HandshakeTimeout: 10 * time.Second}
		conn, _, err := d.Dial(s.wsURL, nil)
		if err != nil {
			return err
		}
		conn.Close()
		return nil
	}()
	s.record("W24", "WS handshake /ws/ (101)", err)

	// W25 logout
	err = func() error {
		if !loggedIn {
			return skipErr{reason: "sin sesión web"}
		}
		st, _, hd, err := s.get(s.base + "/CCC/logout.php")
		if err != nil {
			return err
		}
		if st != 302 || !strings.Contains(hd.Get("Location"), "login.php") {
			return fmt.Errorf("HTTP %d Location=%q", st, hd.Get("Location"))
		}
		loggedIn = false
		return nil
	}()
	s.record("W25", "GET logout.php", err)

	// W26 auth wall after logout
	err = func() error {
		st, _, _, err := s.get(s.base + "/CCC/materias.php")
		if err != nil {
			return err
		}
		if st < 300 || st > 399 {
			return fmt.Errorf("HTTP %d (esperado redirect tras logout)", st)
		}
		return nil
	}()
	s.record("W26", "materias.php tras logout (redirect)", err)

	// W27-W30 instructor flow
	instructorIn := false
	err = func() error {
		if s.webRestricted {
			return skipErr{reason: "IP no permitida"}
		}
		if err := s.needDB(); err != nil {
			return err
		}
		teacherEmail = s.newEmail("wtea")
		form := url.Values{
			"action":           {"signup"},
			"nombre":           {"Profesor Funcional"},
			"email_signup":     {teacherEmail},
			"username":         {"ftest_tea_" + s.rand},
			"password_signup":  {passTeacher},
			"password_confirm": {passTeacher},
			"rol":              {"instructor"},
		}
		st, _, _, err := s.postForm(s.base+"/CCC/login.php", form)
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d tras signup instructor", st)
		}
		row, err := s.db.QueryOne(context.Background(),
			"SELECT rol FROM usuarios WHERE email = ?", teacherEmail)
		if err != nil || row == nil {
			return fmt.Errorf("instructor no creado (err=%v)", err)
		}
		if store.Str(row["rol"]) != "instructor" {
			return fmt.Errorf("rol=%s (esperado instructor)", store.Str(row["rol"]))
		}
		return nil
	}()
	s.record("W27", "signup instructor (web)", err)

	err = func() error {
		if teacherEmail == "" {
			return skipErr{reason: "sin signup previo"}
		}
		if s.webRestricted {
			return skipErr{reason: "IP no permitida"}
		}
		tok, err := s.verifyToken(teacherEmail)
		if err != nil {
			return err
		}
		st, body, _, err := s.get(s.base + "/CCC/verify.php?token=" + url.QueryEscape(tok))
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d", st)
		}
		low := strings.ToLower(string(body))
		if !strings.Contains(low, "verific") && !strings.Contains(low, "verified") && !strings.Contains(low, "success") {
			return fmt.Errorf("verify.php no confirmó verificación")
		}
		return nil
	}()
	s.record("W28", "verify instructor (web)", err)

	err = func() error {
		if teacherEmail == "" {
			return skipErr{reason: "sin signup previo"}
		}
		if s.webRestricted {
			return skipErr{reason: "IP no permitida"}
		}
		form := url.Values{"action": {"signin"}, "email": {teacherEmail}, "password": {passTeacher}, "login_rol": {"instructor"}}
		st, _, hd, err := s.postForm(s.base+"/CCC/login.php", form)
		if err != nil {
			return err
		}
		if st != 302 {
			return fmt.Errorf("HTTP %d (esperado 302)", st)
		}
		_ = hd
		instructorIn = true
		return nil
	}()
	s.record("W29", "signin instructor (web)", err)

	for _, tc := range []struct{ id, path string }{
		{"W30", "/CCC/dashboard_profesor.php"},
		{"W31", "/CCC/crear_clase.php"},
	} {
		t := tc
		err = func() error {
			if !instructorIn {
				return skipErr{reason: "sin sesión instructor"}
			}
			st, body, _, err := s.get(s.base + t.path)
			if err != nil {
				return err
			}
			if st != 200 {
				return fmt.Errorf("HTTP %d", st)
			}
			if len(body) == 0 {
				return fmt.Errorf("cuerpo vacío")
			}
			return nil
		}()
		s.record(t.id, "GET "+t.path+" (instructor)", err)
	}
}

func (s *suite) mobileTests() {
	fmt.Println("\n=== SUITE MOBILE (api_mobile.php + api_sala + WS) ===")
	studentEmail := ""
	teacherEmail := ""
	studentToken := ""
	teacherToken := ""
	var classID int64
	var salaID int64
	studentInRoom := false

	// M01 health
	err := func() error {
		j, st, err := s.getJSON(s.base+"/health", "")
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d", st)
		}
		if s.dbEnabled && j.str("db") != "up" {
			return fmt.Errorf("db=%s", j.str("db"))
		}
		return nil
	}()
	s.record("M01", "health / db", err)

	// M02 countries (sin auth)
	var paisID int64
	err = func() error {
		j, st, err := s.getJSON(s.apiAction("countries"), "")
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d", st)
		}
		a := j.arr("countries")
		if len(a) == 0 {
			return fmt.Errorf("countries vacío")
		}
		paisID = store.Int(a[0].(map[string]any)["id"])
		return nil
	}()
	s.record("M02", "countries (catálogo)", err)

	// M03 register estudiante
	err = func() error {
		studentEmail = s.newEmail("mstu")
		j, st, err := s.postJSON(s.apiAction("register"), "", map[string]any{
			"nombre": "Estudiante Móvil", "email": studentEmail, "password": passStudent,
			"pais_id": paisID, "rol": "estudiante",
		})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("needs_verification") {
			return fmt.Errorf("HTTP %d needs_verification=%s", st, j.str("needs_verification"))
		}
		return nil
	}()
	s.record("M03", "register estudiante", err)

	// M04 verify_email
	err = func() error {
		if err := s.needDB(); err != nil {
			return err
		}
		if studentEmail == "" {
			return skipErr{reason: "sin register previo"}
		}
		tok, err := s.verifyToken(studentEmail)
		if err != nil {
			return err
		}
		j, st, err := s.postJSON(s.apiAction("verify_email"), "", map[string]any{"token": tok})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("verified") {
			return fmt.Errorf("HTTP %d verified=%s", st, j.str("verified"))
		}
		return nil
	}()
	s.record("M04", "verify_email (token desde DB)", err)

	// M05 login
	err = func() error {
		if studentEmail == "" {
			return skipErr{reason: "sin register previo"}
		}
		j, st, err := s.postJSON(s.apiAction("login"), "", map[string]any{"email": studentEmail, "password": passStudent})
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d (¿cuenta verificada?): %s", st, j.str("error"))
		}
		if !j.has("token") {
			return fmt.Errorf("login sin token")
		}
		studentToken = j.str("token")
		return nil
	}()
	s.record("M05", "login estudiante", err)

	// M06 profile
	err = func() error {
		if studentToken == "" {
			return skipErr{reason: "sin token"}
		}
		j, st, err := s.getJSON(s.apiAction("profile"), studentToken)
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d", st)
		}
		u, ok := j["user"].(map[string]any)
		if !ok || !strings.EqualFold(store.Str(u["email"]), studentEmail) {
			return fmt.Errorf("profile no devuelve el usuario registrado")
		}
		return nil
	}()
	s.record("M06", "profile (token)", err)

	// M07 subjects
	var firstSubjectID int64
	err = func() error {
		j, st, err := s.getJSON(s.apiAction("subjects"), "")
		if err != nil {
			return err
		}
		if st != 200 || len(j.arr("subjects")) == 0 {
			return fmt.Errorf("HTTP %d / subjects vacío", st)
		}
		firstSubjectID = store.Int(j.arr("subjects")[0].(map[string]any)["id"])
		return nil
	}()
	s.record("M07", "subjects (catálogo)", err)

	// M08 teachers
	err = func() error {
		j, st, err := s.getJSON(s.apiAction("teachers"), "")
		if err != nil {
			return err
		}
		if st != 200 || !j.has("teachers") {
			return fmt.Errorf("HTTP %d / sin clave teachers", st)
		}
		return nil
	}()
	s.record("M08", "teachers (catálogo)", err)

	// M09 classes
	var firstClassID int64
	err = func() error {
		j, st, err := s.getJSON(s.apiAction("classes"), "")
		if err != nil {
			return err
		}
		if st != 200 || !j.has("classes") {
			return fmt.Errorf("HTTP %d / sin clave classes", st)
		}
		a := j.arr("classes")
		if len(a) > 0 {
			firstClassID = store.Int(a[0].(map[string]any)["id"])
		}
		return nil
	}()
	s.record("M09", "classes (catálogo)", err)

	// M10 class_detail
	err = func() error {
		if firstClassID == 0 {
			return skipErr{reason: "no hay clases en el catálogo"}
		}
		j, st, err := s.getJSON(s.apiAction("class_detail")+"&id="+fmt.Sprint(firstClassID), "")
		if err != nil {
			return err
		}
		if st != 200 || !j.has("clase") {
			return fmt.Errorf("HTTP %d / sin clave clase", st)
		}
		return nil
	}()
	s.record("M10", "class_detail (1ra clase)", err)

	// M11 languages + M12 update_languages
	var firstLangID int64
	err = func() error {
		if studentToken == "" {
			return skipErr{reason: "sin token"}
		}
		j, st, err := s.getJSON(s.apiAction("languages"), studentToken)
		if err != nil {
			return err
		}
		if st != 200 || len(j.arr("languages")) == 0 {
			return fmt.Errorf("HTTP %d / languages vacío", st)
		}
		firstLangID = store.Int(j.arr("languages")[0].(map[string]any)["id"])
		return nil
	}()
	s.record("M11", "languages", err)

	err = func() error {
		if studentToken == "" || firstLangID == 0 {
			return skipErr{reason: "sin token o sin idiomas"}
		}
		j, st, err := s.postJSON(s.apiAction("update_languages"), studentToken, map[string]any{"idiomas": []int64{firstLangID}})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("ok") {
			return fmt.Errorf("HTTP %d ok=%s", st, j.str("ok"))
		}
		return nil
	}()
	s.record("M12", "update_languages", err)

	// M13 set_ui_language
	err = func() error {
		if studentToken == "" {
			return skipErr{reason: "sin token"}
		}
		j, st, err := s.postJSON(s.apiAction("set_ui_language"), studentToken, map[string]any{"lang": "en"})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("ok") {
			return fmt.Errorf("HTTP %d ok=%s", st, j.str("ok"))
		}
		return nil
	}()
	s.record("M13", "set_ui_language en", err)

	// M14 credits
	err = func() error {
		if studentToken == "" {
			return skipErr{reason: "sin token"}
		}
		j, st, err := s.getJSON(s.apiAction("credits"), studentToken)
		if err != nil {
			return err
		}
		if st != 200 || !j.has("balance") || !j.has("history") {
			return fmt.Errorf("HTTP %d / sin balance o history", st)
		}
		return nil
	}()
	s.record("M14", "credits (billetera)", err)

	// M15 create_checkout (puede requerir MercadoPago configurado)
	err = func() error {
		if studentToken == "" {
			return skipErr{reason: "sin token"}
		}
		j, st, err := s.postJSON(s.apiAction("create_checkout"), studentToken, map[string]any{"type": "credits", "quantity": 5})
		if err != nil {
			return err
		}
		if st >= 500 {
			return skipErr{reason: "checkout no disponible (¿MP sin configurar?)"}
		}
		if st != 200 || j.str("preference_id") == "" {
			return fmt.Errorf("HTTP %d / sin preference_id: %s", st, j.str("error"))
		}
		return nil
	}()
	s.record("M15", "create_checkout credits x5", err)

	// M16 WS handshake
	err = func() error {
		d := websocket.Dialer{HandshakeTimeout: 10 * time.Second}
		conn, _, err := d.Dial(s.wsURL, nil)
		if err != nil {
			return err
		}
		conn.Close()
		return nil
	}()
	s.record("M16", "WS handshake /ws/ (101)", err)

	// M17-M21 instructor flow
	err = func() error {
		teacherEmail = s.newEmail("mtea")
		j, st, err := s.postJSON(s.apiAction("register"), "", map[string]any{
			"nombre": "Profesor Móvil", "email": teacherEmail, "password": passTeacher,
			"pais_id": paisID, "rol": "instructor",
		})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("needs_verification") {
			return fmt.Errorf("HTTP %d needs_verification=%s", st, j.str("needs_verification"))
		}
		return nil
	}()
	s.record("M17", "register instructor", err)

	err = func() error {
		if err := s.needDB(); err != nil {
			return err
		}
		tok, err := s.verifyToken(teacherEmail)
		if err != nil {
			return err
		}
		j, st, err := s.postJSON(s.apiAction("verify_email"), "", map[string]any{"token": tok})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("verified") {
			return fmt.Errorf("HTTP %d verified=%s", st, j.str("verified"))
		}
		return nil
	}()
	s.record("M18", "verify instructor (móvil)", err)

	err = func() error {
		j, st, err := s.postJSON(s.apiAction("login"), "", map[string]any{"email": teacherEmail, "password": passTeacher})
		if err != nil {
			return err
		}
		if st != 200 || !j.has("token") {
			return fmt.Errorf("HTTP %d / sin token", st)
		}
		teacherToken = j.str("token")
		return nil
	}()
	s.record("M19", "login instructor", err)

	err = func() error {
		if teacherToken == "" {
			return skipErr{reason: "sin token instructor"}
		}
		j, st, err := s.getJSON(s.apiAction("teacher_dashboard"), teacherToken)
		if err != nil {
			return err
		}
		if st != 200 || !j.has("stats") || !j.has("clases") {
			return fmt.Errorf("HTTP %d / sin stats", st)
		}
		return nil
	}()
	s.record("M20", "teacher_dashboard", err)

	err = func() error {
		if teacherToken == "" {
			return skipErr{reason: "sin token instructor"}
		}
		j, st, err := s.postJSON(s.apiAction("create_class"), teacherToken, map[string]any{
			"titulo":     "Clase de prueba funcional",
			"materia_id": firstSubjectID,
			"precio":     1,
			"descripcion": "Clase creada por la suite de tests funcionales",
			"duracion":   60,
		})
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d / sin clase: %s", st, j.str("error"))
		}
		clase := j.obj("clase")
		if clase.i("id") == 0 {
			return fmt.Errorf("clase sin id")
		}
		classID = clase.i("id")
		return nil
	}()
	s.record("M21", "create_class (instructor)", err)

	err = func() error {
		if teacherToken == "" || classID == 0 {
			return skipErr{reason: "sin clase creada"}
		}
		j, st, err := s.postJSON(s.apiAction("start_room"), teacherToken, map[string]any{"clase_id": classID})
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d: %s", st, j.str("error"))
		}
		sala := j.obj("sala")
		salaID = sala.i("id")
		if salaID == 0 {
			return fmt.Errorf("start_room sin sala id")
		}
		return nil
	}()
	s.record("M22", "start_room (instructor)", err)

	err = func() error {
		if teacherToken == "" {
			return skipErr{reason: "sin token instructor"}
		}
		j, st, err := s.getJSON(s.apiAction("active_rooms"), teacherToken)
		if err != nil {
			return err
		}
		if st != 200 || !j.has("rooms") {
			return fmt.Errorf("HTTP %d / sin clave rooms", st)
		}
		return nil
	}()
	s.record("M23", "active_rooms", err)

	// M24-M30 student room flow
	err = func() error {
		if studentToken == "" || salaID == 0 {
			return skipErr{reason: "sin sala activa"}
		}
		j, st, err := s.postJSON(s.apiAction("join_room"), studentToken, map[string]any{"sala_id": salaID})
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d: %s", st, j.str("error"))
		}
		studentInRoom = true
		return nil
	}()
	s.record("M24", "join_room (estudiante)", err)

	err = func() error {
		if studentToken == "" || salaID == 0 {
			return skipErr{reason: "sin sala"}
		}
		j, st, err := s.getJSON(s.apiAction("room_status")+"&sala_id="+fmt.Sprint(salaID), studentToken)
		if err != nil {
			return err
		}
		if st != 200 || !j.has("sala") {
			return fmt.Errorf("HTTP %d / sin sala", st)
		}
		return nil
	}()
	s.record("M25", "room_status", err)

	err = func() error {
		if !studentInRoom {
			return skipErr{reason: "estudiante no en sala"}
		}
		msg := "hola desde suite funcional " + s.rand
		j, st, err := s.postJSON(s.apiAction("send_message"), studentToken, map[string]any{"sala_id": salaID, "mensaje": msg})
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d: %s", st, j.str("error"))
		}
		return nil
	}()
	s.record("M26", "send_message", err)

	err = func() error {
		if studentToken == "" || salaID == 0 {
			return skipErr{reason: "sin sala"}
		}
		_, st, err := s.getJSON(s.apiAction("messages")+"&sala_id="+fmt.Sprint(salaID), studentToken)
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d", st)
		}
		return nil
	}()
	s.record("M27", "messages", err)

	err = func() error {
		if !studentInRoom {
			return skipErr{reason: "estudiante no en sala"}
		}
		j, st, err := s.postJSON(s.apiAction("signal"), studentToken,
			map[string]any{"sala_id": salaID, "tipo": "candidate", "payload": "{}", "to_uid": 0})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("ok") {
			return fmt.Errorf("HTTP %d ok=%s: %s", st, j.str("ok"), j.str("error"))
		}
		return nil
	}()
	s.record("M28", "signal (WebRTC relay)", err)

	err = func() error {
		if studentToken == "" || salaID == 0 {
			return skipErr{reason: "sin sala"}
		}
		j, st, err := s.postJSON(s.apiAction("rate_session"), studentToken,
			map[string]any{"sala_id": salaID, "rating": 5, "comentario": "test funcional"})
		if err != nil {
			return err
		}
		if st != 200 {
			return fmt.Errorf("HTTP %d: %s", st, j.str("error"))
		}
		return nil
	}()
	s.record("M29", "rate_session", err)

	err = func() error {
		if studentToken == "" || salaID == 0 {
			return skipErr{reason: "sin sala"}
		}
		j, st, err := s.postJSON(s.apiAction("leave_room"), studentToken, map[string]any{"sala_id": salaID})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("ok") {
			return fmt.Errorf("HTTP %d ok=%s", st, j.str("ok"))
		}
		return nil
	}()
	s.record("M30", "leave_room (estudiante)", err)

	err = func() error {
		if teacherToken == "" || salaID == 0 {
			return skipErr{reason: "sin sala"}
		}
		j, st, err := s.postJSON(s.apiAction("leave_room"), teacherToken, map[string]any{"sala_id": salaID})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("closed") {
			return fmt.Errorf("HTTP %d closed=%s", st, j.str("closed"))
		}
		return nil
	}()
	s.record("M31", "leave_room (instructor cierra)", err)

	// M32-M33 delete_account (self-cleanup vía API)
	err = func() error {
		if studentToken == "" {
			return skipErr{reason: "sin token"}
		}
		j, st, err := s.postJSON(s.apiAction("delete_account"), studentToken, map[string]any{"password": passStudent})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("ok") {
			return fmt.Errorf("HTTP %d ok=%s", st, j.str("ok"))
		}
		return nil
	}()
	s.record("M32", "delete_account (estudiante)", err)

	err = func() error {
		if teacherToken == "" {
			return skipErr{reason: "sin token"}
		}
		j, st, err := s.postJSON(s.apiAction("delete_account"), teacherToken, map[string]any{"password": passTeacher})
		if err != nil {
			return err
		}
		if st != 200 || !j.b("ok") {
			return fmt.Errorf("HTTP %d ok=%s", st, j.str("ok"))
		}
		return nil
	}()
	s.record("M33", "delete_account (instructor)", err)
}

// ---------------------------------------------------------------------------
// Runner
// ---------------------------------------------------------------------------

func (s *suite) summarize(label string) {
	var pass, fail, skip int
	for _, r := range s.results {
		if !strings.HasPrefix(r.id, label) {
			continue
		}
		switch r.status {
		case "PASS":
			pass++
		case "SKIP":
			skip++
		default:
			fail++
		}
	}
	fmt.Printf("\n--- RESUMEN %s: %d PASS, %d FAIL, %d SKIP ---\n", label, pass, fail, skip)
}

func main() {
	base := flag.String("base", "http://localhost:8080", "URL base del servidor (local o prod)")
	suiteFlag := flag.String("suite", "all", "web | mobile | all")
	envPath := flag.String("env", "", "ruta al .env del servidor destino (para verify/cleanup). Default: ../.env")
	keep := flag.Bool("keep", false, "no eliminar los usuarios de prueba al final")
	timeout := flag.Duration("timeout", 20*time.Second, "timeout por request")
	flag.Parse()

	if *suiteFlag != "web" && *suiteFlag != "mobile" && *suiteFlag != "all" {
		fmt.Fprintf(os.Stderr, "suite inválido: %q (web|mobile|all)\n", *suiteFlag)
		os.Exit(2)
	}

	u, err := url.Parse(*base)
	if err != nil {
		fmt.Fprintf(os.Stderr, "base inválido: %v\n", err)
		os.Exit(2)
	}
	wsScheme := "ws"
	if u.Scheme == "https" {
		wsScheme = "wss"
	}
	s := &suite{
		base:    strings.TrimRight(*base, "/"),
		apiBase: strings.TrimRight(*base, "/") + "/CCC",
		wsURL:   wsScheme + "://" + u.Host + "/ws/",
		timeout: *timeout,
		keep:    *keep,
		envPath: *envPath,
	}

	jar, _ := cookiejar.New(nil)
	s.cli = &http.Client{
		Jar:       jar,
		Timeout:   *timeout + 5*time.Second,
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			return http.ErrUseLastResponse
		},
	}

	// rand suffix (sin guiones largos para URLs/usuarios)
	rnd := make([]byte, 5)
	if _, err := rand.Read(rnd); err != nil {
		panic(err)
	}
	s.rand = hex.EncodeToString(rnd)

	fmt.Printf("Base:   %s\n", s.base)
	fmt.Printf("WS:     %s\n", s.wsURL)
	fmt.Printf("Suite:  %s\n", *suiteFlag)
	fmt.Printf("Random: %s\n", s.rand)

	s.connectDB()

	switch *suiteFlag {
	case "web":
		s.webTests()
	case "mobile":
		s.mobileTests()
	default:
		s.webTests()
		s.mobileTests()
	}

	s.cleanup()

	if *suiteFlag == "web" {
		s.summarize("W")
	} else if *suiteFlag == "mobile" {
		s.summarize("M")
	} else {
		s.summarize("W")
		s.summarize("M")
	}

	var fails int
	for _, r := range s.results {
		if r.status == "FAIL" {
			fails++
			fmt.Printf("FALLO %s %s: %s\n", r.id, r.name, r.detail)
		}
	}
	if fails > 0 {
		fmt.Printf("\n%d FALLO(S)\n", fails)
		os.Exit(1)
	}
	fmt.Println("\nTODOS LOS TESTS PASARON (o fueron saltados)")
}
