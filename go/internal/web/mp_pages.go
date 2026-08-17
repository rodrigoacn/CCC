package web

import (
	"crypto/hmac"
	"crypto/sha256"
	"crypto/subtle"
	"encoding/hex"
	"encoding/json"
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

	secret := p.Cfg.MPWebhookSecret
	if secret != "" {
		signature := r.Header.Get("X-Signature")
		timestamp := r.Header.Get("X-Timestamp")
		bodyBytes, _ := io.ReadAll(r.Body)
		expected := hmacSHA256(timestamp+":"+string(bodyBytes), secret)
		if subtle.ConstantTimeCompare([]byte(expected), []byte(signature)) != 1 {
			writeJSON(w, http.StatusUnauthorized, map[string]any{"error": "Invalid signature"})
			return
		}
		r.Body.Close()
		r.Body = io.NopCloser(strings.NewReader(string(bodyBytes)))
	}

	var notification map[string]any
	if err := json.NewDecoder(r.Body).Decode(&notification); err != nil {
		notification = map[string]any{}
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
