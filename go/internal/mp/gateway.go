package mp

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"
	"time"

	"classexpress/internal/config"
	"classexpress/internal/store"
)

// API base for MercadoPago Checkout Pro.
const apiBase = "https://api.mercadopago.com"

const statementDescriptor = "CLASSEXPRESS"

// Gateway talks to MercadoPago and the checkout_sessions table.
type Gateway struct {
	cfg *config.Config
	db  *store.DB
}

// New builds the gateway.
func New(cfg *config.Config, db *store.DB) *Gateway {
	return &Gateway{cfg: cfg, db: db}
}

// CreatePreference mirrors MercadoPagoGateway::createPreference.
func (g *Gateway) CreatePreference(ctx context.Context, usuarioID int, typ string, quantity int, amountUSD float64, baseURL string) (map[string]any, error) {
	currencyID := g.cfg.MPDefaultCurrency
	unitPrice := amountUSD
	if currencyID == "" {
		currencyID = "CLP"
	}
	if currencyID == "CLP" {
		unitPrice = float64(usdToCLP(amountUSD, g.cfg.MPClpPerUSD))
	}

	title := fmt.Sprintf("ClassExpress - %d Créditos", quantity)
	if typ == "tokens" {
		title = fmt.Sprintf("ClassExpress - %d MonedasCE", quantity)
	}

	externalRef := fmt.Sprintf("ce_%d_%s_%d_%d", usuarioID, typ, quantity, time.Now().Unix())

	prefData := map[string]any{
		"items": []map[string]any{{
			"id":          "ce_" + typ,
			"title":       title,
			"description": fmt.Sprintf("Compra de %d %s en ClassExpress", quantity, typ),
			"category_id": "digital_content",
			"quantity":    1,
			"unit_price":  unitPrice,
			"currency_id": currencyID,
		}},
		"external_reference":   externalRef,
		"statement_descriptor": statementDescriptor,
		"binary_mode":          false,
		"back_urls": map[string]string{
			"success": fmt.Sprintf("%s/mp_success.php", baseURL),
			"failure": fmt.Sprintf("%s/mp_failure.php", baseURL),
			"pending": fmt.Sprintf("%s/mp_pending.php", baseURL),
		},
		"notification_url": fmt.Sprintf("%s/mp_webhook.php", baseURL),
	}
	if strings.HasPrefix(baseURL, "https://") {
		prefData["auto_return"] = "approved"
	}

	user, err := g.db.QueryOne(ctx, "SELECT nombre, email FROM usuarios WHERE usuarioId = ?", usuarioID)
	if err == nil && user != nil {
		prefData["payer"] = map[string]string{
			"name":  store.Str(user["nombre"]),
			"email": store.Str(user["email"]),
		}
	}

	body, err := json.Marshal(prefData)
	if err != nil {
		return nil, err
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, apiBase+"/checkout/preferences", bytes.NewReader(body))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+g.cfg.MPAccessToken)

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("MercadoPago error: %w", err)
	}
	defer resp.Body.Close()

	var result map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, fmt.Errorf("MercadoPago error: %w", err)
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		msg := result["message"]
		if msg == nil {
			msg = fmt.Sprintf("HTTP %d", resp.StatusCode)
		}
		return nil, fmt.Errorf("MercadoPago error: %v", msg)
	}

	preferenceID := store.Str(result["id"])
	initPoint := store.Str(result["init_point"])
	sandboxInit := store.Str(result["sandbox_init_point"])

	if _, err := g.db.Exec(ctx,
		`INSERT INTO checkout_sessions
			(usuario_id, type, quantity, amount_usd, amount_local, currency,
			 preference_id, external_reference, status)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')`,
		nullableInt(usuarioID, typ), typ, quantity, amountUSD, unitPrice, currencyID, preferenceID, externalRef,
	); err != nil {
		return nil, err
	}

	out := map[string]any{
		"preference_id": preferenceID,
		"checkout_url":  initPoint,
		"sandbox_url":   nil,
	}
	if sandboxInit != "" {
		out["sandbox_url"] = sandboxInit
	}
	return out, nil
}

// CheckPaymentStatus mirrors MercadoPagoGateway::checkPaymentStatus.
func (g *Gateway) CheckPaymentStatus(ctx context.Context, externalRef string) map[string]any {
	session, err := g.db.QueryOne(ctx,
		"SELECT * FROM checkout_sessions WHERE external_reference = ?", externalRef)
	if err != nil || session == nil {
		return map[string]any{"status": "not_found"}
	}
	out := map[string]any{
		"status":   store.Str(session["status"]),
		"type":     store.Str(session["type"]),
		"quantity": int(store.Int(session["quantity"])),
	}
	if v, ok := session["payment_id"]; ok && v != nil {
		out["payment_id"] = store.Str(v)
	} else {
		out["payment_id"] = nil
	}
	return out
}

// GetPayment fetches a payment by its MercadoPago ID.
func (g *Gateway) GetPayment(ctx context.Context, paymentID int) (map[string]any, error) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet,
		fmt.Sprintf("%s/v1/payments/%d", apiBase, paymentID), nil)
	if err != nil {
		return nil, err
	}
	req.Header.Set("Authorization", "Bearer "+g.cfg.MPAccessToken)
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		return nil, fmt.Errorf("MercadoPago error: %w", err)
	}
	defer resp.Body.Close()
	var result map[string]any
	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return nil, err
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return nil, fmt.Errorf("MercadoPago error: HTTP %d", resp.StatusCode)
	}
	return result, nil
}

// ProcessWebhook mirrors MercadoPagoGateway::processWebhook. Returns the
// processed payment (or nil when there is nothing to do).
func (g *Gateway) ProcessWebhook(ctx context.Context, body map[string]any) (map[string]any, error) {
	paymentID := 0
	if data, ok := body["data"].(map[string]any); ok {
		if id, ok := data["id"].(float64); ok {
			paymentID = int(id)
		}
	} else if res, ok := body["resource"].(string); ok {
		idx := strings.LastIndex(res, "/v1/payments/")
		if idx == -1 {
			idx = strings.Index(res, "payments/")
		}
		if idx >= 0 {
			paymentID = atoiTrailing(res[idx:])
		}
	}
	if paymentID == 0 {
		return nil, nil
	}

	payment, err := g.GetPayment(ctx, paymentID)
	if err != nil || payment == nil {
		return nil, err
	}

	status := store.Str(payment["status"])
	extRef := store.Str(payment["external_reference"])
	if extRef == "" {
		return nil, nil
	}

	session, err := g.db.QueryOne(ctx,
		"SELECT * FROM checkout_sessions WHERE external_reference = ?", extRef)
	if err != nil || session == nil {
		return nil, err
	}

	already := store.Str(session["status"])
	if already == "approved" || already == "rejected" || already == "refunded" {
		return payment, nil
	}

	if _, err := g.db.Exec(ctx,
		"UPDATE checkout_sessions SET payment_id = ?, status = ? WHERE id = ?",
		paymentID, status, store.Str(session["id"])); err != nil {
		return nil, err
	}

	if status == "approved" {
		if err := g.FulfillOrder(ctx, int(store.Int(session["usuario_id"])),
			store.Str(session["type"]), int(store.Int(session["quantity"]))); err != nil {
			return nil, err
		}
	}
	return payment, nil
}

// FulfillOrder mirrors MercadoPagoGateway::fulfillOrder.
func (g *Gateway) FulfillOrder(ctx context.Context, usuarioID int, typ string, quantity int) error {
	if typ == "credits" {
		if _, err := g.db.Exec(ctx,
			"UPDATE usuarios SET creditos = creditos + ? WHERE usuarioId = ?", quantity, usuarioID); err != nil {
			return err
		}
	} else if typ == "tokens" {
		if _, err := g.db.Exec(ctx,
			"UPDATE usuarios SET tokens = tokens + ? WHERE usuarioId = ?", quantity, usuarioID); err != nil {
			return err
		}
	}

	fee := round2(float64(quantity) * 0.05)
	_, err := g.db.Exec(ctx,
		`INSERT INTO compras_tokens (usuario_id, cantidad, monto_usd, fee_rodrigo, metodo_pago)
		 VALUES (?, ?, ?, ?, 'mercadopago')`,
		usuarioID, quantity, quantity, fee)
	return err
}

// atoiTrailing extracts the trailing integer from a string like
// "/v1/payments/12345" or "payments/12345".
func atoiTrailing(s string) int {
	start := -1
	for i := len(s) - 1; i >= 0; i-- {
		if s[i] < '0' || s[i] > '9' {
			start = i + 1
			break
		}
	}
	if start < 0 {
		start = 0
	}
	out := 0
	for _, ch := range s[start:] {
		if ch < '0' || ch > '9' {
			break
		}
		out = out*10 + int(ch-'0')
	}
	return out
}

func round2(v float64) float64 {
	return float64(int64(v*100+0.5)) / 100
}

// nullableInt returns the usuario ID.
func nullableInt(usuarioID int, typ string) any {
	return usuarioID
}

func usdToCLP(usd float64, clpPerUSD float64) int {
	if clpPerUSD <= 0 {
		clpPerUSD = 950
	}
	clp := usd * clpPerUSD
	return int(float64(int64(clp + 0.5)))
}
