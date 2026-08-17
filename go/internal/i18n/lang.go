// Package i18n provides the multi-language system ported from lang.php.
package i18n

import (
	_ "embed"
	"encoding/json"
	"fmt"
	"html/template"
	"sort"
	"strings"
)

//go:embed lang_data.json
var langJSON []byte

// LangData mirrors the $LANGUAGES / $TRANS structures from lang.php.
type LangData struct {
	Languages []Lang `json:"languages"`
	Trans     map[string]map[string]string `json:"trans"`
}

// Lang is a language descriptor {code,label}.
type Lang struct {
	Code  string `json:"code"`
	Label string `json:"label"`
}

var (
	data  LangData
	codes []string
)

func init() {
	if err := json.Unmarshal(langJSON, &data); err != nil {
		panic("i18n: error cargando lang_data.json: " + err.Error())
	}
	for _, l := range data.Languages {
		codes = append(codes, l.Code)
	}
}

// Languages returns the supported language list (code,label).
func Languages() []Lang {
	return data.Languages
}

// IsSupported reports whether code is a supported language.
func IsSupported(code string) bool {
	for _, c := range codes {
		if c == code {
			return true
		}
	}
	return false
}

// DetectLang resolves the active language following the same precedence as PHP:
// session -> cookie (ce_lang) -> browser Accept-Language -> es.
func DetectLang(sessionLang, cookieLang, acceptLang string) string {
	if IsSupported(sessionLang) {
		return sessionLang
	}
	if IsSupported(cookieLang) {
		return cookieLang
	}
	if len(acceptLang) >= 2 {
		b := acceptLang[:2]
		if IsSupported(b) {
			return b
		}
	}
	return "es"
}

// T returns the translated string for key in lang, falling back to en, then es,
// then the raw key. Params of the form {k} or :k are substituted.
func T(lang, key string, params map[string]string) string {
	var str string
	if langMap := data.Trans[lang]; langMap != nil {
		str = langMap[key]
	}
	if str == "" {
		str = data.Trans["en"][key]
	}
	if str == "" {
		str = data.Trans["es"][key]
	}
	if str == "" {
		str = key
	}
	if len(params) > 0 {
		keys := make([]string, 0, len(params))
		for k := range params {
			keys = append(keys, k)
		}
		sort.Slice(keys, func(i, j int) bool { return len(keys[i]) > len(keys[j]) })
		for _, k := range keys {
			v := params[k]
			str = strings.ReplaceAll(str, "{"+k+"}", v)
			str = strings.ReplaceAll(str, ":"+k, v)
		}
	}
	return str
}

// RenderLangSelector returns the <select> HTML for the language picker.
func RenderLangSelector(lang string) template.HTML {
	var b strings.Builder
	b.WriteString(`<select class="form-select form-select-sm lang-select" id="lang-select" style="background-color:#ffffff;color:#1e293b;border-color:#dbe2ee;font-size:.8rem;" onchange="CE_switchLang(this.value)">`)
	for _, l := range data.Languages {
		sel := ""
		if l.Code == lang {
			sel = " selected"
		}
		b.WriteString(`<option value="` + l.Code + `"` + sel + `>` + template.HTMLEscapeString(l.Label) + `</option>`)
	}
	b.WriteString(`</select>`)
	return template.HTML(b.String())
}

// RenderTranslationsJSON returns a JSON object of all translations per language
// (used by lang_api / data-i18n consumers).
func RenderTranslationsJSON() string {
	out := make(map[string]map[string]string, len(data.Languages))
	for _, l := range data.Languages {
		out[l.Code] = data.Trans[l.Code]
	}
	j, err := json.Marshal(out)
	if err != nil {
		return "{}"
	}
	return string(j)
}

// Translations returns the full map for a single language.
func Translations(lang string) map[string]string {
	if data.Trans[lang] == nil {
		return map[string]string{}
	}
	return data.Trans[lang]
}

// QuoteJSON escapes the translations payload for injection into a <script> tag,
// mirroring JSON_HEX_TAG | JSON_HEX_AMP from the PHP version.
func QuoteJSON() string {
	s := RenderTranslationsJSON()
	s = strings.ReplaceAll(s, "<", `\u003c`)
	s = strings.ReplaceAll(s, ">", `\u003e`)
	s = strings.ReplaceAll(s, "&", `\u0026`)
	return s
}

// String produces a human-readable dump summary (for debugging).
func String() string {
	return fmt.Sprintf("i18n: %d idiomas, %d entradas es, %d en", len(codes), len(data.Trans["es"]), len(data.Trans["en"]))
}
