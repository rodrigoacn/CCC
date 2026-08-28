package web

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"math/rand"
	"net/http"
	"net/mail"
	"net/url"
	"regexp"
	"strings"
	"time"

	"classexpress/internal/auth"
	"classexpress/internal/i18n"
	"classexpress/internal/store"
)

func urlQueryEscape(s string) string {
	return url.QueryEscape(s)
}

func timeNow() time.Time { return time.Now() }

var (
	usernameRE = regexp.MustCompile(`^[a-zA-Z0-9_]+$`)
	emailRE    = regexp.MustCompile(`^[^@\s]+@[^@\s]+\.[^@\s]+$`)
)

func validEmail(s string) bool {
	if !emailRE.MatchString(s) || len(s) > 254 {
		return false
	}
	_, err := mail.ParseAddress(s)
	return err == nil
}

func FooterParams() map[string]string {
	return map[string]string{
		"bootstrap": `<a href="https://getbootstrap.com/" class="text-secondary">Bootstrap</a>`,
		"author":    `<a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA" class="text-secondary">@RodrigoConejeros</a>`,
	}
}

func redirect(w http.ResponseWriter, r *http.Request, url string) {
	http.Redirect(w, r, url, http.StatusFound)
}

type Pages struct{}
type Session struct{}

func (p *Pages) HandleLogin(w http.ResponseWriter, r *http.Request) {
	ctx := r.Context()
	s := SessionFrom(ctx)
	if s == nil {
		serverError(w, errNoSession)
		return
	}
	lang := p.ResolveLang(s, r)
	_ = lang
}

func (p *Pages) doSignIn(ctx context.Context, w http.ResponseWriter, r *http.Request, s *Session, lang string, ip string) (string, string) {
	return "", ""
}

func main() {}