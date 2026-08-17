package web

import (
	"net/http"
	"strings"

	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

type historialItem struct {
	Monto    string
	Tipo     string
	Ref      string
	IsPos    bool
	Desc     string
	Fecha    string
	MontoFmt string
	Sign     string
}

// mpBaseURL mirrors mpGetBaseUrl().
func mpBaseURL(r *http.Request) string {
	host := r.Host
	if host != "" && !strings.Contains(host, "localhost") && !strings.HasPrefix(host, "127.0.0.1") {
		return "https://" + host + "/CCC"
	}
	return "https://classexpress.online/CCC"
}

// HandleCreditos ports creditos.php (wallet / credit and token purchase).
func (p *Pages) HandleCreditos(w http.ResponseWriter, r *http.Request) {
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

	errorMsg := ""
	if r.Method == http.MethodPost {
		if !CSRFRequire(w, r, s) {
			return
		}
		typ := r.PostFormValue("type")
		amount := store.Float(r.PostFormValue("amount"))
		if typ == "credits" {
			valid := []float64{10, 25, 50, 100, 200}
			qty := int64(amount)
			ok := false
			for _, v := range valid {
				if amount == v {
					ok = true
					break
				}
			}
			if !ok && (qty < 1 || qty > 1000) {
				errorMsg = i18n.T(lang, "creditos.invalid_amount", nil)
			} else {
				checkout, err := p.MP.CreatePreference(ctx, int(uid), "credits", int(qty), amount, mpBaseURL(r))
				if err != nil {
					errorMsg = i18n.T(lang, "creditos.checkout_error", nil) + err.Error()
				} else {
					redirect(w, r, store.Str(checkout["checkout_url"]))
					return
				}
			}
		} else if typ == "tokens" {
			packages := map[int64]float64{10: 10, 25: 25, 50: 50, 100: 100, 200: 200}
			cant := int64(amount)
			price, ok := packages[cant]
			if !ok {
				errorMsg = i18n.T(lang, "creditos.invalid_package", nil)
			} else {
				checkout, err := p.MP.CreatePreference(ctx, int(uid), "tokens", int(cant), price, mpBaseURL(r))
				if err != nil {
					errorMsg = i18n.T(lang, "creditos.checkout_error", nil) + err.Error()
				} else {
					redirect(w, r, store.Str(checkout["checkout_url"]))
					return
				}
			}
		}
	}

	user, err := p.DB.QueryOne(ctx,
		"SELECT u.creditos, u.tokens FROM usuarios u WHERE u.usuarioId = ?", uid)
	if err != nil {
		serverError(w, err)
		return
	}
	balance := store.Float(user["creditos"])
	tokens := store.Float(user["tokens"])

	rows, err := p.DB.QueryAll(ctx,
		`SELECT p.pagoId AS pid, -p.monto_local AS monto, 'class' AS tipo, cp.titulo AS ref, p.created_at
		 FROM pagos p JOIN sesiones_clase sc ON sc.sesionId = p.sesionId JOIN clases_programadas cp ON cp.claseId = sc.claseId
		 WHERE p.estudianteId = ?
		 UNION ALL
		 SELECT ct.id AS pid, ct.monto_usd AS monto, 'tokens' AS tipo, ct.cantidad AS ref, ct.created_at
		 FROM compras_tokens ct WHERE ct.usuario_id = ?
		 ORDER BY created_at DESC LIMIT 20`, uid, uid)
	if err != nil {
		serverError(w, err)
		return
	}

	historial := make([]historialItem, 0, len(rows))
	for _, row := range rows {
		monto := store.Float(row["monto"])
		isPos := monto > 0
		tipo := store.Str(row["tipo"])
		ref := store.Str(row["ref"])
		var desc string
		if tipo == "class" {
			desc = i18n.T(lang, "creditos.history_class", map[string]string{"title": ref})
		} else {
			desc = i18n.T(lang, "creditos.history_tokens", map[string]string{"qty": ref})
		}
		sign := ""
		if isPos {
			sign = "+"
		}
		historial = append(historial, historialItem{
			Monto:    formatNumber(monto, 0),
			IsPos:    isPos,
			Desc:     desc,
			Fecha:    dateDMY(store.Str(row["created_at"])),
			MontoFmt: sign + formatNumber(monto, 0),
		})
	}

	data := map[string]any{
		"Lang":        lang,
		"NavData":     nav,
		"Balance":     formatNumber(balance, 0),
		"Tokens":      formatNumber(tokens, 0),
		"Historial":   historial,
		"Empty":       len(historial) == 0,
		"Error":       errorMsg,
		"CreditPacks": []int64{10, 25, 50, 100, 200},
	}
	if err := p.Templates.RenderAuthed(w, "creditos", p, s, lang, data); err != nil {
		serverError(w, err)
	}
}
