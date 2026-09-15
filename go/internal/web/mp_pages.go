package web

import (
	"crypto/hmac"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"strings"

	"classexpress/internal/store"
)

func hmacSHA256(data, key string) string {
	mac := hmac.New(sha256.New, []byte(key))
	mac.Write([]byte(data))
	return hex.EncodeToString(mac.Sum(nil))
}

// mpSignatureValid validates MercadoPago's signed-notification header
// (x-signature: ts=<ts>,v1=<hash>). Per MP docs the signed manifest is
// "id:<DATA_ID>;request-id:<X_REQUEST_ID>;ts:<TS>;" with the webhook secret.
func mpSignatureValid(secret string, r *http.Request, body map[string]any) bool {
	sig := r.Header.Get("X-Signature")
	reqID := r.Header.Get("X-Request-Id")
	if sig == "" {
		return false
	}
	ts := ""
	v1 := ""
	for _, part := range strings.Split(sig, ",") {
		kv := strings.SplitN(part, "=", 2)
		if len(kv) != 2 {
			continue
		}
		switch kv[0] {
		case "ts":
			ts = kv[1]
		case "v1":
			v1 = kv[1]
		}
	}
	if ts == "" || v1 == "" {
		return false
	}
	data, _ := body["data"].(map[string]any)
	id := int64(0)
	if f, ok := data["id"].(float64); ok {
		id = int64(f)
	}
	manifest := fmt.Sprintf("id:%d;request-id:%s;ts:%s;", id, reqID, ts)
	expected := hmacSHA256(manifest, secret)
	return subtle.ConstantTimeCompare([]byte(expected), []byte(v1)) == 1
}

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

// HandleMPSuccess ports mp_success.php (MP Checkout Pro success return).
func (p *Pages) HandleMPSuccess(w http.ResponseWriter, r *http.Request) {
	p.handleMPStatus(w, r, "success")
}

// HandleMPPending ports mp_pending.php.
func (p *Pages) HandleMPPending(w http.ResponseWriter, r *http.Request) {
	p.handleMPStatus(w, r, "pending")
}

// HandleMPFailure ports mp_failure.php.
func (p *Pages) HandleMPFailure(w http.ResponseWriter, r *http.Request) {
	p.handleMPStatus(w, r, "failure")
}

func (p *Pages) handleMPStatus(w http.ResponseWriter, r *http.Request, kind string) {
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

	collectionID := store.Int(r.URL.Query().Get("collection_id"))
	if collectionID == 0 {
		collectionID = store.Int(r.URL.Query().Get("payment_id"))
	}
	extRef := strings.TrimSpace(r.URL.Query().Get("external_reference"))
	status := strings.TrimSpace(r.URL.Query().Get("status"))

	var session map[string]any
	fulfilled := false
	if extRef != "" {
		session, _ = p.DB.QueryOne(ctx,
			"SELECT * FROM checkout_sessions WHERE external_reference = ?", extRef)
		if session != nil {
			sessStatus := store.Str(session["status"])
			if sessStatus == "pending" && status == "approved" {
				payment, err := p.MP.ProcessWebhook(ctx, map[string]any{
					"type":   "payment",
					"action": "payment.created",
					"data":   map[string]any{"id": float64(collectionID)},
				})
				if err != nil {
					log.Printf("mp_success: ProcessWebhook error: %v", err)
				}
				if payment != nil {
					session, _ = p.DB.QueryOne(ctx,
						"SELECT * FROM checkout_sessions WHERE external_reference = ?", extRef)
					fulfilled = true
				}
			} else if sessStatus == "approved" {
				fulfilled = true
			}
		}
	}

	userName := ""
	typeLabel := ""
	quantity := int64(0)
	if session != nil {
		if u, err := p.DB.QueryOne(ctx,
			"SELECT nombre FROM usuarios WHERE usuarioId = ?",
			store.Int(session["usuario_id"])); err == nil && u != nil {
			userName = store.Str(u["nombre"])
		}
		if store.Str(session["type"]) == "credits" {
			typeLabel = "Créditos"
		} else {
			typeLabel = "MonedasCE"
		}
		quantity = store.Int(session["quantity"])
	}
	if typeLabel == "" {
		typeLabel = "tu compra"
	}

	approved := status == "approved" && fulfilled
	showSuccess := kind == "success" && approved
	showPending := kind == "pending" || (kind == "success" && status == "pending")
	showFailure := !showSuccess && !showPending

	if kind == "failure" && extRef != "" {
		if s2, err := p.DB.QueryOne(ctx,
			"SELECT * FROM checkout_sessions WHERE external_reference = ?", extRef); err == nil && s2 != nil {
			_, _ = p.DB.Exec(ctx,
				"UPDATE checkout_sessions SET status = 'rejected' WHERE id = ? AND status = 'pending'",
				store.Str(s2["id"]))
		}
	}

	data := map[string]any{
		"Lang":       lang,
		"NavData":    nav,
		"Kind":       kind,
		"ShowSuccess": showSuccess,
		"ShowPending": showPending,
		"ShowFailure": showFailure,
		"UserName":   userName,
		"Quantity":   quantity,
		"TypeLabel":  typeLabel,
	}
	if err := p.Templates.RenderAuthed(w, "mp_"+kind, p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

// HandleMPWebhook ports mp_webhook.php (MercadoPago IPN). Public endpoint.
func (p *Pages) HandleMPWebhook(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	if r.Method != http.MethodPost {
		writeJSON(w, http.StatusMethodNotAllowed, map[string]any{"error": "Method not allowed"})
		return
	}

	bodyBytes, err := io.ReadAll(r.Body)
	if err != nil {
		r.Body.Close()
		writeJSON(w, http.StatusBadRequest, map[string]any{"error": "Bad body"})
		return
	}
	r.Body.Close()

	var notification map[string]any
	if err := json.Unmarshal(bodyBytes, &notification); err != nil {
		notification = map[string]any{}
	}

	// Firma de notificaciones firmadas de MercadoPago. Cuando el secret está
	// configurado, toda petición sin firma válida se descarta.
	secret := p.Cfg.MPWebhookSecret
	if secret == "" {
		log.Printf("MP Webhook: AVISO MPWebhookSecret no configurado; se aceptan notificaciones sin firma")
	} else if !mpSignatureValid(secret, r, notification) {
		writeJSON(w, http.StatusUnauthorized, map[string]any{"error": "Invalid signature"})
		return
	}

	if store.Str(notification["type"]) != "payment" {
		writeJSON(w, http.StatusOK, map[string]any{"ok": true, "ignored": true})
		return
	}

	result, err := p.MP.ProcessWebhook(r.Context(), notification)
	if err != nil {
		log.Printf("MP Webhook error: %v", err)
		writeJSON(w, http.StatusOK, map[string]any{"ok": false, "error": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "processed": result != nil})
}
