package web

import (
	"bytes"
	"net/http"
)

// bufWriter buffers the response so headers (e.g. session cookies set by Save)
// can be added after the handler runs but before the body is sent.
type bufWriter struct {
	http.ResponseWriter
	status    int
	body      bytes.Buffer
	committed bool
}

func newBufWriter(w http.ResponseWriter) *bufWriter {
	return &bufWriter{ResponseWriter: w, status: http.StatusOK}
}

func (b *bufWriter) WriteHeader(code int) {
	if b.committed {
		return
	}
	b.status = code
	b.committed = true
}

func (b *bufWriter) Write(p []byte) (int, error) {
	if !b.committed {
		b.status = http.StatusOK
		b.committed = true
	}
	return b.body.Write(p)
}

func (b *bufWriter) Flush() {
	// Buffered; nothing to flush until commit.
	if f, ok := b.ResponseWriter.(http.Flusher); ok {
		f.Flush()
	}
}

// commit sends buffered headers and body to the real ResponseWriter.
func (b *bufWriter) commit() {
	b.ResponseWriter.WriteHeader(b.status)
	_, _ = b.ResponseWriter.Write(b.body.Bytes())
}

// WithSession loads the session, runs the handler, persists the session and
// flushes the buffered response with the session cookie attached.
func (p *Pages) WithSession(h http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		s := p.Sessions.Get(w, r)
		bw := newBufWriter(w)
		ctx := withSessionCtx(r.Context(), s)
		h(bw, r.WithContext(ctx))
		if !s.Destroy {
			p.Sessions.Save(bw, r, s)
		}
		bw.commit()
	}
}
