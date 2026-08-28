package web

import (
	"context"
	"fmt"
	"net/http"
)

type Pages struct{}

type Session struct{}

func (p *Pages) doSignIn(ctx context.Context, w http.ResponseWriter, r *http.Request, s *Session, lang string, ip string) (string, string) {
	return "", ""
}

func main() {}