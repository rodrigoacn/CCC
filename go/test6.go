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

type Pages struct{}
type Session struct{}

func (p *Pages) doSignIn(ctx context.Context, w http.ResponseWriter, r *http.Request, s *Session, lang string, ip string) (string, string) {
	return "", ""
}

func main() {}