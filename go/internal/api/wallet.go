package api

import (
	"fmt"
	"net/http"

	"classexpress/internal/store"
)

// credits mirrors WalletController::credits.
func (a *API) credits(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	uid := store.Int(store.Coalesce(user["usuarioId"], store.Coalesce(user["id"], int64(0))))

	history, err := a.DB.QueryAll(ctx(r),
		`SELECT p.pagoId AS id, -COALESCE(p.monto_local, 0) AS monto, 'class' AS tipo, cp.titulo AS descripcion, p.created_at
		 FROM pagos p JOIN sesiones_clase sc ON sc.sesionId = p.sesionId JOIN clases_programadas cp ON cp.claseId = sc.claseId
		 WHERE p.estudianteId = ?
		 UNION ALL
		 SELECT ct.id AS id, ct.monto_usd AS monto, 'tokens' AS tipo, ct.cantidad AS descripcion, ct.created_at
		 FROM compras_tokens ct WHERE ct.usuario_id = ?
		 ORDER BY created_at DESC LIMIT 30`, uid, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	for _, h := range history {
		if store.Str(h["tipo"]) == "tokens" {
			h["descripcion"] = fmt.Sprintf("Compra de tokens: %d", store.Int(h["descripcion"]))
		}
	}

	userData, err := a.DB.QueryOne(ctx(r), "SELECT creditos, tokens FROM usuarios WHERE usuarioId = ?", uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	tokens := float64(0)
	if userData != nil {
		tokens = store.Float(userData["tokens"])
	}

	return okOut(map[string]any{
		"balance": store.Int(user["creditos"]),
		"tokens":  tokens,
		"history": history,
	})
}

// topup mirrors WalletController::topup.
func (a *API) topup(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	amount := bodyInt(body, "amount")
	if amount < 1 || amount > 1000 {
		return errOut(http.StatusBadRequest, "Monto inválido (1-1000)")
	}
	uid := store.Int(store.Coalesce(user["usuarioId"], store.Coalesce(user["id"], int64(0))))

	checkout, err := a.MP.CreatePreference(ctx(r), int(uid), "credits", int(amount), float64(amount), mpBaseURL(r))
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error creating checkout: "+err.Error())
	}
	return okOut(map[string]any{
		"checkout_url":  checkout["checkout_url"],
		"preference_id": checkout["preference_id"],
	})
}

// buyTokens mirrors WalletController::buyTokens.
func (a *API) buyTokens(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	amount := bodyInt(body, "amount")
	prices := map[int64]float64{10: 10, 25: 25, 50: 50, 100: 100, 200: 200}
	price, ok := prices[amount]
	if !ok {
		return errOut(http.StatusBadRequest, "Paquete inválido")
	}
	uid := store.Int(store.Coalesce(user["usuarioId"], store.Coalesce(user["id"], int64(0))))

	checkout, err := a.MP.CreatePreference(ctx(r), int(uid), "tokens", int(amount), price, mpBaseURL(r))
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error creating checkout: "+err.Error())
	}
	return okOut(map[string]any{
		"checkout_url":  checkout["checkout_url"],
		"preference_id": checkout["preference_id"],
	})
}

// createCheckout mirrors WalletController::createCheckout.
func (a *API) createCheckout(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	typ := store.Str(body["type"])
	quantity := bodyInt(body, "quantity")
	if typ != "credits" && typ != "tokens" {
		return errOut(http.StatusBadRequest, "Tipo inválido (credits/tokens)")
	}

	prices := map[int64]float64{10: 10, 25: 25, 50: 50, 100: 100, 200: 200}
	var amountUSD float64
	if typ == "tokens" {
		price, ok := prices[quantity]
		if !ok {
			return errOut(http.StatusBadRequest, "Paquete inválido")
		}
		amountUSD = price
	} else {
		if quantity < 1 || quantity > 1000 {
			return errOut(http.StatusBadRequest, "Cantidad inválida (1-1000)")
		}
		amountUSD = float64(quantity)
	}

	uid := store.Int(store.Coalesce(user["usuarioId"], store.Coalesce(user["id"], int64(0))))
	checkout, err := a.MP.CreatePreference(ctx(r), int(uid), typ, int(quantity), amountUSD, mpBaseURL(r))
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error creating checkout: "+err.Error())
	}
	return okOut(map[string]any{
		"checkout_url":  checkout["checkout_url"],
		"preference_id": checkout["preference_id"],
	})
}

// checkoutStatus mirrors WalletController::checkoutStatus.
func (a *API) checkoutStatus(r *http.Request) *resp {
	if _, errResp := a.authUser(r, map[string]any{}); errResp != nil {
		return errResp
	}
	extRef := r.URL.Query().Get("external_reference")
	if extRef == "" {
		return errOut(http.StatusBadRequest, "external_reference requerido")
	}
	return okOut(a.MP.CheckPaymentStatus(ctx(r), extRef))
}

// payment mirrors WalletController::payment.
func (a *API) payment(r *http.Request, body map[string]any) *resp {
	user, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	uid := store.Int(store.Coalesce(user["usuarioId"], store.Coalesce(user["id"], int64(0))))
	sesionID := bodyInt(body, "sesion_id")
	salaID := bodyInt(body, "sala_id")

	if sesionID != 0 {
		sesion, err := a.DB.QueryOne(ctx(r),
			`SELECT sc.*, cp.titulo, cp.instructorId, cp.claseId, cp.precio_base
			 FROM sesiones_clase sc
			 JOIN clases_programadas cp ON cp.claseId = sc.claseId
			 WHERE sc.sesionId = ? AND sc.estudianteId = ?`, sesionID, uid)
		if err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		if sesion == nil {
			return errOut(http.StatusNotFound, "Sesión no encontrada")
		}
		if store.Bool(sesion["pagado"]) {
			return errOut(http.StatusBadRequest, "La sesión ya fue pagada")
		}

		precio := store.Float(sesion["precio_usd"])
		if precio <= 0 {
			precio = store.Float(sesion["precio_base"])
		}
		if store.Int(user["creditos"]) < int64(precio) {
			return errOut(http.StatusPaymentRequired, "Créditos insuficientes")
		}

		if _, err := a.DB.Exec(ctx(r),
			"INSERT INTO pagos (sesionId, estudianteId, profesorId, monto_usd, estado) VALUES (?, ?, ?, ?, 'completado')",
			sesionID, uid, sesion["instructorId"], precio); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		if _, err := a.DB.Exec(ctx(r), "UPDATE sesiones_clase SET pagado = 1 WHERE sesionId = ?", sesionID); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		if _, err := a.DB.Exec(ctx(r), "UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ?", int64(precio), uid); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}

		updated, err := a.DB.QueryOne(ctx(r), "SELECT creditos FROM usuarios WHERE usuarioId = ?", uid)
		if err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		return okOut(map[string]any{
			"ok":                 true,
			"creditos_restantes": store.Int(updated["creditos"]),
			"recibo":             fmt.Sprintf("Pagaste %d crédito(s) por «%s»", int64(precio), store.Str(sesion["titulo"])),
		})
	}

	sala, err := a.DB.QueryOne(ctx(r),
		`SELECT s.*, cp.precio_base AS precio, cp.titulo, cp.instructorId, cp.claseId
		 FROM salas s
		 JOIN clases_programadas cp ON cp.claseId = s.claseId
		 WHERE s.salaId = ?`, salaID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if sala == nil {
		return errOut(http.StatusNotFound, "Sala no encontrada")
	}
	precio := store.Float(sala["precio"])
	if store.Int(user["creditos"]) < int64(precio) {
		return errOut(http.StatusPaymentRequired, "Créditos insuficientes")
	}

	sesion, err := a.DB.QueryOne(ctx(r),
		`SELECT sesionId FROM sesiones_clase
		 WHERE claseId = ? AND estudianteId = ? AND pagado = 0 AND fin IS NULL LIMIT 1`,
		sala["claseId"], uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}

	if _, err := a.DB.Exec(ctx(r), "UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ?", int64(precio), uid); err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if sesion != nil {
		if _, err := a.DB.Exec(ctx(r),
			"INSERT INTO pagos (sesionId, estudianteId, profesorId, monto_usd, estado) VALUES (?, ?, ?, ?, 'completado')",
			sesion["sesionId"], uid, sala["instructorId"], precio); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
		if _, err := a.DB.Exec(ctx(r), "UPDATE sesiones_clase SET pagado = 1 WHERE sesionId = ?", sesion["sesionId"]); err != nil {
			return errOut(http.StatusInternalServerError, "Error interno")
		}
	}

	updated, err := a.DB.QueryOne(ctx(r), "SELECT creditos FROM usuarios WHERE usuarioId = ?", uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	return okOut(map[string]any{
		"ok":                 true,
		"creditos_restantes": store.Int(updated["creditos"]),
		"recibo":             fmt.Sprintf("Pagaste %d crédito(s) por «%s»", int64(precio), store.Str(sala["titulo"])),
	})
}

// withdrawTokens mirrors WalletController::withdrawTokens.
func (a *API) withdrawTokens(r *http.Request, body map[string]any) *resp {
	auth, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	uid := store.Int(auth["id"])
	cantidad := bodyInt(body, "cantidad")
	cuenta := store.Str(body["cuenta_bancaria"])
	banco := store.Str(body["nombre_banco"])
	tipoCuenta := store.Str(body["tipo_cuenta"])
	if tipoCuenta == "" {
		tipoCuenta = "corriente"
	}
	paypalEmail := store.Str(body["paypal_email"])
	metodoRetiro := store.Str(body["metodo_retiro"])
	if metodoRetiro == "" {
		metodoRetiro = "banco"
	}

	user, err := a.DB.QueryOne(ctx(r), "SELECT rol, creditos FROM usuarios WHERE usuarioId = ?", uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if user == nil || (store.Str(user["rol"]) == "estudiante" || store.Str(user["rol"]) == "student") {
		return errOut(http.StatusForbidden, "Only teachers can withdraw tokens")
	}
	if cantidad <= 0 {
		return errOut(http.StatusBadRequest, "Invalid amount")
	}
	const minWithdraw = 10
	if cantidad < minWithdraw {
		return errOut(http.StatusBadRequest, fmt.Sprintf("Minimum withdrawal is %d tokens", minWithdraw))
	}
	if float64(cantidad) > store.Float(user["creditos"]) {
		return errOut(http.StatusBadRequest, "Insufficient balance")
	}
	if metodoRetiro == "paypal" && paypalEmail == "" {
		return errOut(http.StatusBadRequest, "PayPal email is required")
	} else if metodoRetiro == "banco" && (cuenta == "" || banco == "") {
		return errOut(http.StatusBadRequest, "Bank account and bank name are required")
	}

	pending, err := a.DB.QueryOne(ctx(r),
		"SELECT COUNT(*) AS cnt FROM retiros_tokens WHERE usuario_id = ? AND estado = 'pendiente'", uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if pending != nil && store.Int(pending["cnt"]) > 0 {
		return errOut(http.StatusBadRequest, "You already have a pending withdrawal request")
	}

exchangeRate := 950
	montoUsd := float64(cantidad)
	comision := float64(0)
	neto := montoUsd
	montoClp := int64(round2(float64(neto * float64(exchangeRate))))

	tx, err := a.DB.Begin(ctx(r))
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error processing withdrawal: "+err.Error())
	}
	defer tx.Rollback()

	res, err := tx.ExecContext(ctx(r), "UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ? AND creditos >= ?", cantidad, uid, cantidad)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error processing withdrawal: "+err.Error())
	}
	if n, _ := res.RowsAffected(); n == 0 {
		return errOut(http.StatusBadRequest, "Insufficient balance or concurrent request")
	}

	cuentaVal, bancoVal, tipoVal := "", "PayPal", "paypal"
	if metodoRetiro == "banco" {
		cuentaVal, bancoVal, tipoVal = cuenta, banco, tipoCuenta
	}
	if _, err := tx.ExecContext(ctx(r),
		`INSERT INTO retiros_tokens (usuario_id, cantidad, monto_usd, monto_clp, comision, neto_pagar, cuenta_bancaria, nombre_banco, tipo_cuenta, paypal_email, estado)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')`,
		uid, cantidad, montoUsd, montoClp, comision, neto, cuentaVal, bancoVal, tipoVal, paypalEmail); err != nil {
		return errOut(http.StatusInternalServerError, "Error processing withdrawal: "+err.Error())
	}
	if err := tx.Commit(); err != nil {
		return errOut(http.StatusInternalServerError, "Error processing withdrawal: "+err.Error())
	}

return okOut(map[string]any{
		"ok":              true,
		"message":         "Withdrawal request created",
		"tokens_deducted": cantidad,
		"comision":        0.0,
		"neto_pagar_usd":  neto,
		"neto_pagar_clp":  montoClp,
		"exchange_rate":   exchangeRate,
	})
}

// withdrawalHistory mirrors WalletController::withdrawalHistory.
func (a *API) withdrawalHistory(r *http.Request) *resp {
	user, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	uid := store.Int(user["id"])

	rows, err := a.DB.QueryAll(ctx(r),
		`SELECT id AS retiroId, cantidad, monto_usd, monto_clp, comision, neto_pagar, nombre_banco, tipo_cuenta, paypal_email, estado, admin_note, created_at, procesado_at
		 FROM retiros_tokens WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 50`, uid)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if rows == nil {
		rows = []map[string]any{}
	}
	return okOut(map[string]any{"ok": true, "withdrawals": rows})
}

// adminWithdrawals mirrors WalletController::adminWithdrawals.
func (a *API) adminWithdrawals(r *http.Request) *resp {
	auth, errResp := a.authUser(r, map[string]any{})
	if errResp != nil {
		return errResp
	}
	if store.Str(auth["rol"]) != "admin" {
		return errOut(http.StatusForbidden, "Admin only")
	}

	estado := r.URL.Query().Get("estado")
	sqlStr := "SELECT r.*, u.nombre, u.email FROM retiros_tokens r JOIN usuarios u ON u.usuarioId = r.usuario_id"
	var args []any
	if estado != "" {
		sqlStr += " WHERE r.estado = ?"
		args = append(args, estado)
	}
	sqlStr += " ORDER BY r.created_at DESC LIMIT 100"

	rows, err := a.DB.QueryAll(ctx(r), sqlStr, args...)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if rows == nil {
		rows = []map[string]any{}
	}
	return okOut(map[string]any{"ok": true, "withdrawals": rows})
}

// adminProcessWithdrawal mirrors WalletController::adminProcessWithdrawal.
func (a *API) adminProcessWithdrawal(r *http.Request, body map[string]any) *resp {
	auth, errResp := a.authUser(r, body)
	if errResp != nil {
		return errResp
	}
	uid := store.Int(auth["id"])
	if store.Str(auth["rol"]) != "admin" {
		return errOut(http.StatusForbidden, "Admin only")
	}

	retiroID := bodyInt(body, "retiro_id")
	action := store.Str(body["action"])
	note := store.Str(body["note"])
	if retiroID == 0 || (action != "approve" && action != "reject") {
		return errOut(http.StatusBadRequest, "Invalid parameters")
	}

	retiro, err := a.DB.QueryOne(ctx(r), "SELECT * FROM retiros_tokens WHERE id = ?", retiroID)
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error interno")
	}
	if retiro == nil {
		return errOut(http.StatusNotFound, "Withdrawal not found")
	}
	if store.Str(retiro["estado"]) != "pendiente" {
		return errOut(http.StatusBadRequest, "Withdrawal already processed")
	}

	newState := "completado"
	if action == "reject" {
		newState = "rechazado"
	}

	tx, err := a.DB.Begin(ctx(r))
	if err != nil {
		return errOut(http.StatusInternalServerError, "Error: "+err.Error())
	}
	defer tx.Rollback()

	if _, err := tx.ExecContext(ctx(r),
		"UPDATE retiros_tokens SET estado = ?, admin_note = ?, procesado_por = ?, procesado_at = NOW() WHERE id = ?",
		newState, note, uid, retiroID); err != nil {
		return errOut(http.StatusInternalServerError, "Error: "+err.Error())
	}

	if action == "reject" {
		if _, err := tx.ExecContext(ctx(r),
			"UPDATE usuarios SET creditos = creditos + ? WHERE usuarioId = ?",
			retiro["cantidad"], retiro["usuario_id"]); err != nil {
			return errOut(http.StatusInternalServerError, "Error: "+err.Error())
		}
	}

	if err := tx.Commit(); err != nil {
		return errOut(http.StatusInternalServerError, "Error: "+err.Error())
	}
	return okOut(map[string]any{"ok": true, "message": "Withdrawal " + newState})
}

