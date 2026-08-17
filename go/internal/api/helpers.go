package api

import (
	"math"
	"net/http"
	"net/mail"
	"regexp"
	"strings"

	"classexpress/internal/store"
)

var bearerRe = regexp.MustCompile(`(?i)^Bearer\s+(.+)$`)

// validEmail mirrors filter_var($email, FILTER_VALIDATE_EMAIL).
func validEmail(s string) bool {
	if s == "" {
		return false
	}
	addr, err := mail.ParseAddress(s)
	if err != nil {
		return false
	}
	return addr.Address == s
}

// getPendingPaymentSessionID mirrors api_helpers::getPendingPaymentSessionID().
func (a *API) getPendingPaymentSessionID(r *http.Request, userID int64) any {
	row, err := a.DB.QueryOne(ctx(r),
		"SELECT sesionId FROM sesiones_clase WHERE estudianteId = ? AND pagado = 0 AND fin IS NOT NULL ORDER BY fin ASC LIMIT 1",
		userID)
	if err != nil || row == nil {
		return nil
	}
	return int(store.Int(row["sesionId"]))
}

// formatUserMap mirrors api_helpers::formatUser().
func (a *API) formatUserMap(r *http.Request, u map[string]any) map[string]any {
	id := store.Int(u["usuarioId"])
	base := baseURLOf(r)

	languages := a.userLanguages(r, id)

	avatar := ""
	if av := store.Str(u["avatar"]); av != "" {
		avatar = strings.TrimRight(base, "/") + "/" + strings.TrimLeft(av, "/")
	}

	var lastSwitch any
	if v, ok := u["last_role_switch"]; ok && v != nil {
		lastSwitch = store.Str(v)
	}

	return map[string]any{
		"id":                   id,
		"nombre":               store.Str(u["nombre"]),
		"email":                store.Str(u["email"]),
		"username":             store.Str(u["username"]),
		"rol":                  store.Str(u["rol"]),
		"creditos":             store.Int(u["creditos"]),
		"verificado":           store.Bool(u["verificado"]),
		"avatar":               avatar,
		"biografia":            store.Str(u["biografia"]),
		"pais_id":              store.Int(u["pais_id"]),
		"idiomas":              languages,
		"calificacion":         store.Float(u["calificacion"]),
		"num_resenas":          store.Int(u["num_resenas"]),
		"idioma_preferido":     coalesceStr(u["idioma_preferido"], "es"),
		"ultima_materia":       store.Int(u["ultimaMateria"]),
		"last_role_switch":     lastSwitch,
		"pendingPaymentSessionId": a.getPendingPaymentSessionID(r, id),
	}
}

func (a *API) userLanguages(r *http.Request, userID int64) []string {
	rows, err := a.DB.QueryAll(ctx(r),
		"SELECT i.nombre FROM usuario_idiomas ui JOIN idiomas i ON i.idiomaId = ui.idiomaId WHERE ui.usuarioId = ?",
		userID)
	if err != nil {
		return []string{}
	}
	out := []string{}
	for _, row := range rows {
		out = append(out, store.Str(row["nombre"]))
	}
	return out
}

func coalesceStr(v any, fallback string) string {
	if v == nil {
		return fallback
	}
	if s := store.Str(v); s != "" {
		return s
	}
	return fallback
}

// bodyStr reads a string from the body.
func bodyStr(body map[string]any, key string) string {
	return store.Str(body[key])
}

// bodyInt reads an int from the body.
func bodyInt(body map[string]any, key string) int64 {
	return store.Int(body[key])
}

// round2 mirrors PHP round($v, 2).
func round2(v float64) float64 {
	return math.Round(v*100) / 100
}
