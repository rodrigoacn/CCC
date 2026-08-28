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

type Pages struct{}
type Session struct{}

func (p *Pages) doSignIn(ctx context.Context, w http.ResponseWriter, r *http.Request, s *Session, lang string, ip string) (string, string) {
	return "", ""
}

func (p *Pages) doResendVerify(ctx context.Context, r *http.Request, lang string, ip string) (string, string) {
	return "", ""
}

func (p *Pages) doSignUp(ctx context.Context, r *http.Request, lang string, ip string) (string, string, string) {
	return "", "", ""
}

func (p *Pages) doQuickEntry(ctx context.Context, r *http.Request, lang string, ip string) (string, string, string) {
	return "", "", ""
}

func main() {}