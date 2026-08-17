package api

import (
	"context"
	"encoding/json"
	"log"
	"net/http"
	"strings"

	"classexpress/internal/config"
	"classexpress/internal/mail"
	"classexpress/internal/mp"
	"classexpress/internal/store"
)

// API is the mobile API (api_mobile.php) front controller.
type API struct {
	DB   *store.DB
	Cfg  *config.Config
	Mail *mail.Sender
	MP   *mp.Gateway

	// SessionAuth authenticates requests via the web session cookie (the
	// sala.php page). When non-nil and the request carries a logged-in
	// PHPSESSID, the sala endpoints accept it like PHP's
	// $_SESSION['usuarioId'] and enforce CSRF on write actions.
	SessionAuth func(r *http.Request) *WebSession
}

// WebSession is a session-authenticated request resolved by SessionAuth.
type WebSession struct {
	UID          string
	ValidateCSRF func(r *http.Request) bool
}

// resp is a JSON response to write.
type resp struct {
	Status  int
	Payload any
}

func out(status int, payload any) *resp { return &resp{Status: status, Payload: payload} }
func okOut(payload any) *resp           { return &resp{Status: http.StatusOK, Payload: payload} }
func errOut(status int, msg string) *resp {
	return &resp{Status: status, Payload: map[string]string{"error": msg}}
}

// dispatch routes an api_mobile action to its handler.
func (a *API) dispatch(action string, r *http.Request, body map[string]any) *resp {
	switch action {
	case "login":
		return a.login(r, body)
	case "register":
		return a.register(r, body)
	case "resend_verification":
		return a.resendVerification(r, body)
	case "verify_email":
		return a.verifyEmail(r, body)
	case "forgot_password":
		return a.forgotPassword(r, body)
	case "reset_password":
		return a.resetPassword(body)
	case "profile":
		return a.profile(r)
	case "delete_account":
		return a.deleteAccount(r, body)
	case "switch_role":
		return a.switchRole(r, body)
	case "update_avatar":
		return a.updateAvatar(r, body)
	case "languages":
		return a.languages()
	case "update_languages":
		return a.updateLanguages(r, body)
	case "set_ui_language":
		return a.setUILanguage(r, body)

	case "subjects":
		return a.subjects()
	case "teachers":
		return a.teachers(r)
	case "classes":
		return a.classes(r)
	case "class_detail":
		return a.classDetail(r)
	case "countries":
		return a.countries()

	case "join_room":
		return a.joinRoom(r, body)
	case "leave_room":
		return a.leaveRoom(r, body)
	case "room_status":
		return a.roomStatus(r)
	case "send_message":
		return a.sendMessage(r, body)
	case "messages":
		return a.messages(r)
	case "signal":
		return a.signal(r, body)
	case "poll_signals":
		return a.pollSignals(r)
	case "room_students":
		return a.roomStudents(r)
	case "kick_student":
		return a.kickStudent(r, body)
	case "start_room":
		return a.startRoom(r, body)
	case "active_rooms":
		return a.activeRooms(r)
	case "session_status":
		return a.sessionStatus(r)
	case "rate_session":
		return a.rateSession(r, body)

	case "credits":
		return a.credits(r)
	case "topup":
		return a.topup(r, body)
	case "buy_tokens":
		return a.buyTokens(r, body)
	case "create_checkout":
		return a.createCheckout(r, body)
	case "checkout_status":
		return a.checkoutStatus(r)
	case "payment":
		return a.payment(r, body)
	case "withdraw_tokens":
		return a.withdrawTokens(r, body)
	case "withdrawal_history":
		return a.withdrawalHistory(r)
	case "admin_withdrawals":
		return a.adminWithdrawals(r)
	case "admin_process_withdrawal":
		return a.adminProcessWithdrawal(r, body)

	case "teacher_dashboard":
		return a.teacherDashboard(r)
	case "create_class":
		return a.createClass(r, body)
	case "class_action":
		return a.classAction(r, body)

	case "friends":
		return a.friends(r)
	case "follow":
		return a.follow(r, body)
	case "unfriend":
		return a.unfriend(r, body)
	case "send_dm":
		return a.sendDirectMessage(r, body)
	case "get_dms":
		return a.getDirectMessages(r)
	case "search_people":
		return a.searchPeople(r)
	case "user_profile":
		return a.userProfile(r, body)
	case "resenas_profesor":
		return a.resenasProfesor(r)

	default:
		return errOut(http.StatusNotFound, "Acción no encontrada")
	}
}

// salaDispatch routes an api_sala action to its handler.
func (a *API) salaDispatch(action string, r *http.Request, body map[string]any) *resp {
	switch action {
	case "join":
		return a.salaJoin(r, body)
	case "leave":
		return a.salaLeave(r, body)
	case "pay":
		return a.salaPay(r, body)
	case "chat":
		return a.salaChat(r, body)
	case "signal":
		return a.salaSignal(r, body)
	case "approve_spectator":
		return a.salaApproveSpectator(r, body)
	case "end_class":
		return a.salaEndClass(r, body)
	case "reject_spectator":
		return a.salaRejectSpectator(r, body)
	case "kick_student":
		return a.salaKickStudent(r, body)
	case "messages":
		return a.salaMessages(r)
	case "signals", "poll_signals":
		return a.salaSignals(r)
	case "get_spectators":
		return a.salaGetSpectators(r)
	case "students":
		return a.salaStudents(r)
	default:
		return salaErr("Unknown action")
	}
}

// SalaServeHTTP implements the api_sala contract (session via bearer token).
func (a *API) SalaServeHTTP(w http.ResponseWriter, r *http.Request) {
	a.cors(w, r)
	if r.Method == http.MethodOptions {
		w.WriteHeader(http.StatusNoContent)
		return
	}

	body := map[string]any{}
	ct := r.Header.Get("Content-Type")
	if strings.HasPrefix(ct, "application/x-www-form-urlencoded") || strings.HasPrefix(ct, "multipart/form-data") {
		if err := r.ParseForm(); err == nil {
			for k, vs := range r.PostForm {
				if len(vs) > 0 {
					body[k] = vs[0]
				}
			}
		}
	} else if r.Body != nil {
		dec := json.NewDecoder(r.Body)
		if err := dec.Decode(&body); err != nil {
			body = map[string]any{}
		}
	}
	if body == nil {
		body = map[string]any{}
	}

	action := r.URL.Query().Get("action")
	if action == "" {
		action = store.Str(body["action"])
	}

	// Session-authenticated (web page) requests must pass CSRF on write
	// actions, mirroring SalaApi::WRITE_ACTIONS + csrf_validate().
	if a.SessionAuth != nil {
		if ws := a.SessionAuth(r); ws != nil && isSalaWriteAction(action) && !ws.ValidateCSRF(r) {
			w.Header().Set("Content-Type", "application/json; charset=utf-8")
			w.WriteHeader(http.StatusForbidden)
			_ = json.NewEncoder(w).Encode(map[string]any{"ok": false, "error": "CSRF token invalid"})
			return
		}
	}

	out := a.salaDispatch(action, r, body)

	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(out.Status)
	_ = json.NewEncoder(w).Encode(out.Payload)
}

// ServeHTTP implements the api_mobile contract (CORS + dispatch).
func (a *API) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	a.cors(w, r)
	if r.Method == http.MethodOptions {
		w.WriteHeader(http.StatusNoContent)
		return
	}

	body := map[string]any{}
	ct := r.Header.Get("Content-Type")
	if strings.HasPrefix(ct, "application/x-www-form-urlencoded") || strings.HasPrefix(ct, "multipart/form-data") {
		if err := r.ParseForm(); err == nil {
			for k, vs := range r.PostForm {
				if len(vs) > 0 {
					body[k] = vs[0]
				}
			}
		}
	} else if r.Body != nil {
		dec := json.NewDecoder(r.Body)
		if err := dec.Decode(&body); err != nil {
			body = map[string]any{}
		}
	}
	if body == nil {
		body = map[string]any{}
	}

	action := r.URL.Query().Get("action")
	// Allow action in POST body as fallback (XAMPP/FastCGI strips query in some cases)
	if action == "" {
		action = store.Str(body["action"])
	}

	out := a.dispatch(action, r, body)

	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(out.Status)
	_ = json.NewEncoder(w).Encode(out.Payload)
}

func (a *API) cors(w http.ResponseWriter, r *http.Request) {
	origin := r.Header.Get("Origin")
	allowed := origin == "null" ||
		origin == "https://classexpress.app" ||
		origin == "https://classexpress.online" ||
		origin == "http://classexpress.online" ||
		isLocalhostOrigin(origin)
	if allowed {
		w.Header().Set("Access-Control-Allow-Origin", origin)
		w.Header().Set("Access-Control-Allow-Credentials", "true")
	} else {
		w.Header().Set("Access-Control-Allow-Origin", "http://localhost")
	}
	w.Header().Set("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
	w.Header().Set("Access-Control-Allow-Headers", "Authorization, Content-Type, Accept")
}

func isLocalhostOrigin(origin string) bool {
	return strings.HasPrefix(origin, "http://localhost") ||
		strings.HasPrefix(origin, "http://127.0.0.1") ||
		strings.HasPrefix(origin, "https://localhost") ||
		strings.HasPrefix(origin, "https://127.0.0.1")
}

// ctx returns the request context (or a background context when nil).
func ctx(r *http.Request) context.Context {
	if r == nil {
		return context.Background()
	}
	return r.Context()
}

// bearerToken extracts the mobile token from Authorization header, query or body.
func bearerToken(r *http.Request, body map[string]any) string {
	header := r.Header.Get("Authorization")
	if header == "" {
		header = r.Header.Get("X-Authorization")
	}
	if m := bearerRe.FindStringSubmatch(header); m != nil {
		return m[1]
	}
	if t := r.URL.Query().Get("token"); t != "" {
		return t
	}
	return store.Str(body["token"])
}

// authUser mirrors api_helpers::getAuthUser().
func (a *API) authUser(r *http.Request, body map[string]any) (map[string]any, *resp) {
	token := bearerToken(r, body)
	if token == "" {
		return nil, errOut(http.StatusUnauthorized, "No autorizado")
	}
	row, err := a.DB.QueryOne(ctx(r),
		"SELECT u.* FROM usuarios u JOIN mobile_tokens t ON t.usuario_id = u.usuarioId WHERE t.token = ? AND t.expires_at > NOW()",
		token)
	if err != nil {
		log.Printf("authUser: %v", err)
		return nil, errOut(http.StatusUnauthorized, "Token inválido o expirado")
	}
	if row == nil {
		return nil, errOut(http.StatusUnauthorized, "Token inválido o expirado")
	}
	row["id"] = row["usuarioId"]
	return row, nil
}

// uid returns the numeric user id from an auth row.
func uid(row map[string]any) int64 {
	return store.Int(row["usuarioId"])
}

// baseURLOf mirrors api_helpers::getBaseUrl().
func baseURLOf(r *http.Request) string {
	scheme := "http"
	if r.TLS != nil || strings.EqualFold(r.Header.Get("X-Forwarded-Proto"), "https") {
		scheme = "https"
	}
	host := r.Host
	if host == "" {
		host = "localhost"
	}
	return scheme + "://" + host + "/CCC"
}

// mpBaseURL mirrors mpGetBaseUrl().
func mpBaseURL(r *http.Request) string {
	host := r.Host
	if host != "" && !strings.Contains(host, "localhost") && !strings.HasPrefix(host, "127.0.0.1") {
		return "https://" + host + "/CCC"
	}
	return "https://classexpress.online/CCC"
}

// verifyLink mirrors buildVerifyLink().
func verifyLink(r *http.Request, token string) string {
	return baseURLOf(r) + "/verify.php?token=" + urlQueryEscape(token)
}

func urlQueryEscape(s string) string {
	return strings.ReplaceAll(s, " ", "%20")
}
