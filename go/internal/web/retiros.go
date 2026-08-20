package web

import (
	"fmt"
	"net/http"
	"net/url"
	"strings"
	"time"

	"classexpress/internal/store"
)

const (
	retiroExchangeRate = 950
	retiroComisionPct  = 15
	retiroMinWithdraw  = 10
)

type retiroItem struct {
	Fecha    string
	Cantidad string
	MontoCLP string
	Banco    string
	Badge    string
	Estado   string
}

// HandleRetiro ports retiro.php (teacher withdrawal page).
func (p *Pages) HandleRetiro(w http.ResponseWriter, r *http.Request) {
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
	uid := UID(s)

	user, err := p.DB.QueryOne(ctx, "SELECT rol, creditos FROM usuarios WHERE usuarioId = ?", uid)
	if err != nil {
		serverError(w, err)
		return
	}
	rol := ""
	if user != nil {
		rol = store.Str(user["rol"])
	}
	if rol == "estudiante" || rol == "student" {
		redirect(w, r, "materias.php")
		return
	}

	tokens := int(store.Float(user["creditos"]))
	msg, errorMsg := "", ""

	if r.Method == http.MethodPost {
		if !CSRFRequire(w, r, s) {
			return
		}
		cantidad := int(store.Float(r.PostFormValue("cantidad")))
		cuenta := strings.TrimSpace(r.PostFormValue("cuenta_bancaria"))
		banco := strings.TrimSpace(r.PostFormValue("nombre_banco"))
		tipoCuenta := strings.TrimSpace(r.PostFormValue("tipo_cuenta"))
		if tipoCuenta == "" {
			tipoCuenta = "corriente"
		}
		paypalEmail := strings.TrimSpace(r.PostFormValue("paypal_email"))
		metodo := strings.TrimSpace(r.PostFormValue("metodo_retiro"))
		if metodo == "" {
			metodo = "banco"
		}

		switch {
		case cantidad < retiroMinWithdraw:
			errorMsg = fmt.Sprintf("Minimum withdrawal is %d CoinsCE.", retiroMinWithdraw)
		case cantidad > tokens:
			errorMsg = "Insufficient balance."
		case metodo == "paypal" && paypalEmail == "":
			errorMsg = "PayPal email is required."
		case metodo == "banco" && (cuenta == "" || banco == ""):
			errorMsg = "Bank account and bank name are required."
		default:
			pending, err := p.DB.QueryOne(ctx,
				"SELECT COUNT(*) AS cnt FROM retiros_tokens WHERE usuario_id = ? AND estado = 'pendiente'", uid)
			if err != nil {
				errorMsg = "Error: " + err.Error()
			} else if store.Int(pending["cnt"]) > 0 {
				errorMsg = "You already have a pending withdrawal request."
			} else {
				tx, err := p.DB.Begin(ctx)
				if err != nil {
					errorMsg = "Error: " + err.Error()
				} else {
					res, err := tx.ExecContext(ctx,
						"UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ? AND creditos >= ?",
						cantidad, uid, cantidad)
					var affected int64
					if err == nil {
						affected, _ = res.RowsAffected()
					}
					if err != nil {
						_ = tx.Rollback()
						errorMsg = "Error: " + err.Error()
					} else if affected == 0 {
						_ = tx.Rollback()
						errorMsg = "Insufficient balance."
					} else {
montoUsd := float64(cantidad)
					comision := float64(0)
					neto := montoUsd
					montoClp := int(float64(int64(neto*float64(retiroExchangeRate)+0.5)))
						cuentaVal, bancoVal, tipoVal := "", "PayPal", "paypal"
						if metodo == "banco" {
							cuentaVal, bancoVal, tipoVal = cuenta, banco, tipoCuenta
						}
						_, insErr := tx.ExecContext(ctx,
							`INSERT INTO retiros_tokens (usuario_id, cantidad, monto_usd, monto_clp, comision, neto_pagar, cuenta_bancaria, nombre_banco, tipo_cuenta, paypal_email, estado)
							 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')`,
							uid, cantidad, montoUsd, montoClp, comision, neto, cuentaVal, bancoVal, tipoVal, paypalEmail)
						if insErr != nil {
							_ = tx.Rollback()
							errorMsg = "Error: " + insErr.Error()
						} else {
							if err := tx.Commit(); err != nil {
								errorMsg = "Error: " + err.Error()
							} else {
								msg = "Withdrawal request created successfully."
								tokens -= cantidad
							}
						}
					}
				}
			}
		}
	}

	rows, err := p.DB.QueryAll(ctx,
		"SELECT * FROM retiros_tokens WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 20", uid)
	if err != nil {
		serverError(w, err)
		return
	}

	historial := make([]retiroItem, 0, len(rows))
	for _, row := range rows {
		historial = append(historial, retiroItem{
			Fecha:    dateMDY(store.Str(row["created_at"])),
			Cantidad: store.Str(row["cantidad"]),
			MontoCLP: groupThousands(store.Int(row["monto_clp"])),
			Banco:    orDefault(store.Str(row["nombre_banco"]), "PayPal"),
			Estado:   retiroBadge(store.Str(row["estado"])),
		})
	}

	data := map[string]any{
		"Lang":       lang,
		"NavData":    nav,
		"Tokens":     tokens,
		"TokensCLP":  groupThousands(int64(tokens * retiroExchangeRate)),
		"Msg":        msg,
		"Error":      errorMsg,
		"MinWith":    retiroMinWithdraw,
		"ShowForm":   tokens >= retiroMinWithdraw,
		"Historial":  historial,
		"Rate":       retiroExchangeRate,
		"ComisionPct": retiroComisionPct,
		"Banks": []string{
			"Banco Estado", "Banco de Chile", "Banco Santander", "Banco BCI",
			"Banco Scotiabank", "Banco Itaú", "Banco Falabella", "Banco Ripley",
			"Banco Consorcio", "Transbank", "MACH", "Cuenta RUT", "Otro",
		},
		"AccountTypes": []string{"corriente", "ahorro", "rut", "cvu"},
	}
	if err := p.Templates.RenderAuthed(w, "retiro", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

type retiroAdminItem struct {
	ID        int64
	Nombre    string
	Email     string
	Cantidad  string
	MontoCLP  string
	Banco     string
	Cuenta    string
	Fecha     string
	Badge     string
	Estado    string
	Pendiente bool
	Note      string
}

// HandleAdminRetiros ports admin_retiros.php (admin approval page).
func (p *Pages) HandleAdminRetiros(w http.ResponseWriter, r *http.Request) {
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
	uid := UID(s)

	user, err := p.DB.QueryOne(ctx, "SELECT rol FROM usuarios WHERE usuarioId = ?", uid)
	if err != nil {
		serverError(w, err)
		return
	}
	if user == nil || store.Str(user["rol"]) != "admin" {
		redirect(w, r, "materias.php")
		return
	}

	if r.Method == http.MethodPost {
		if !CSRFRequire(w, r, s) {
			return
		}
		retiroID := store.Int(r.PostFormValue("retiro_id"))
		action := r.PostFormValue("action")
		note := strings.TrimSpace(r.PostFormValue("note"))
		msg := ""
		if retiroID > 0 && (action == "approve" || action == "reject") {
			retiro, err := p.DB.QueryOne(ctx, "SELECT * FROM retiros_tokens WHERE id = ?", retiroID)
			if err == nil && retiro != nil && store.Str(retiro["estado"]) == "pendiente" {
				newState := "rechazado"
				if action == "approve" {
					newState = "completado"
				}
				tx, err := p.DB.Begin(ctx)
				if err != nil {
					msg = "Error: " + err.Error()
				} else {
					_, uErr := tx.ExecContext(ctx,
						"UPDATE retiros_tokens SET estado = ?, admin_note = ?, procesado_por = ?, procesado_at = NOW() WHERE id = ?",
						newState, note, uid, retiroID)
					if uErr != nil {
						_ = tx.Rollback()
						msg = "Error: " + uErr.Error()
					} else {
						if action == "reject" {
							_, rErr := tx.ExecContext(ctx,
								"UPDATE usuarios SET creditos = creditos + ? WHERE usuarioId = ?",
								store.Str(retiro["cantidad"]), store.Str(retiro["usuario_id"]))
							if rErr != nil {
								_ = tx.Rollback()
								msg = "Error: " + rErr.Error()
							}
						}
						if err := tx.Commit(); err != nil {
							msg = "Error: " + err.Error()
						} else {
							msg = fmt.Sprintf("Withdrawal #%d %s.", retiroID, newState)
						}
					}
				}
			}
		}
		redirect(w, r, "admin_retiros.php?msg="+url.QueryEscape(msg))
		return
	}

	filter := r.URL.Query().Get("filter")
	sqlq := "SELECT r.*, u.nombre, u.email FROM retiros_tokens r JOIN usuarios u ON u.usuarioId = r.usuario_id"
	var args []any
	if filter != "" {
		sqlq += " WHERE r.estado = ?"
		args = append(args, filter)
	}
	sqlq += " ORDER BY r.created_at DESC LIMIT 100"
	rows, err := p.DB.QueryAll(ctx, sqlq, args...)
	if err != nil {
		serverError(w, err)
		return
	}

	statsRow, err := p.DB.QueryOne(ctx,
		`SELECT COUNT(*) AS total,
		        SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END) AS pending,
		        SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) AS approved,
		        SUM(CASE WHEN estado='rechazado' THEN 1 ELSE 0 END) AS rejected,
		        SUM(CASE WHEN estado='pendiente' THEN monto_usd ELSE 0 END) AS pending_usd
		 FROM retiros_tokens`)
	if err != nil || statsRow == nil {
		statsRow = map[string]any{}
	}

	items := make([]retiroAdminItem, 0, len(rows))
	for _, row := range rows {
		estado := store.Str(row["estado"])
		cuenta := orDefault(store.Str(row["cuenta_bancaria"]), store.Str(row["paypal_email"]))
		items = append(items, retiroAdminItem{
			ID:        store.Int(row["id"]),
			Nombre:    store.Str(row["nombre"]),
			Email:     store.Str(row["email"]),
			Cantidad:  store.Str(row["cantidad"]),
			MontoCLP:  groupThousands(store.Int(row["monto_clp"])),
			Banco:     orDefault(store.Str(row["nombre_banco"]), "PayPal"),
			Cuenta:    cuenta,
			Fecha:     dateMDHM(store.Str(row["created_at"])),
			Badge:     retiroBadge(estado),
			Estado:    estado,
			Pendiente: estado == "pendiente",
			Note:      store.Str(row["admin_note"]),
		})
	}

	msg := r.URL.Query().Get("msg")
	data := map[string]any{
		"Lang":       lang,
		"NavData":    nav,
		"Msg":        msg,
		"Filter":     filter,
		"Withdrawals": items,
		"Empty":      len(items) == 0,
		"StatsTotal": store.Int(statsRow["total"]),
		"StatsPending": store.Int(statsRow["pending"]),
		"StatsApproved": store.Int(statsRow["approved"]),
		"StatsRejected": store.Int(statsRow["rejected"]),
		"StatsPendingUSD": store.Str(statsRow["pending_usd"]),
	}
	if err := p.Templates.RenderAuthed(w, "admin_retiros", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}

func round2(v float64) float64 {
	return float64(int64(v*100+0.5)) / 100
}

func groupThousands(v int64) string {
	neg := v < 0
	if neg {
		v = -v
	}
	s := fmt.Sprintf("%d", v)
	var b strings.Builder
	n := len(s)
	for i, ch := range s {
		if i > 0 && (n-i)%3 == 0 {
			b.WriteByte(',')
		}
		b.WriteRune(ch)
	}
	if neg {
		return "-" + b.String()
	}
	return b.String()
}

func orDefault(v, def string) string {
	if v == "" {
		return def
	}
	return v
}

func retiroBadge(estado string) string {
	switch estado {
	case "pendiente":
		return "Pending"
	case "completado", "aprobado":
		return "Completed"
	case "procesando":
		return "Processing"
	default:
		return "Rejected"
	}
}

func dateMDY(v string) string {
	return dateFmt(v, "Jan 02, 2006")
}

func dateMDHM(v string) string {
	return dateFmt(v, "Jan 02, 15:04")
}

func dateFmt(v, layout string) string {
	t, err := time.Parse("2006-01-02 15:04:05", v)
	if err != nil {
		return v
	}
	return t.Format(layout)
}
