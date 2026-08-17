package web

import (
	"embed"
	"fmt"
	"html/template"
	"net/http"
	"sync"
)

//go:embed templates/*.html
var templatesFS embed.FS

// TemplateSet caches parsed page templates.
type TemplateSet struct {
	mu    sync.RWMutex
	cache map[string]*template.Template
}

// NewTemplateSet builds an empty template cache.
func NewTemplateSet() *TemplateSet {
	return &TemplateSet{cache: make(map[string]*template.Template)}
}

// Render executes the named template (without extension) with the shared
// funcmap and the given data.
func (ts *TemplateSet) Render(w http.ResponseWriter, name string, p *Pages, s *Session, lang string, data any) error {
	tpl, err := ts.get(name, p, s, lang)
	if err != nil {
		return err
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	return tpl.Execute(w, data)
}

// RenderAuthed executes the base layout with the page's "content" block.
func (ts *TemplateSet) RenderAuthed(w http.ResponseWriter, name string, p *Pages, s *Session, lang string, data any) error {
	t, err := ts.getAuthed(name, p, s, lang)
	if err != nil {
		return err
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	return t.ExecuteTemplate(w, "base", data)
}

func (ts *TemplateSet) getAuthed(name string, p *Pages, s *Session, lang string) (*template.Template, error) {
	ck := "authed:" + name + ":" + lang
	ts.mu.RLock()
	t, ok := ts.cache[ck]
	ts.mu.RUnlock()
	if ok {
		return t, nil
	}

	ts.mu.Lock()
	defer ts.mu.Unlock()
	if t, ok = ts.cache[ck]; ok {
		return t, nil
	}

	files := []string{"templates/base.html", "templates/" + name + ".html"}
	parsed, err := template.New(name + ".html").Funcs(p.Funcs(s, lang)).ParseFS(templatesFS, files...)
	if err != nil {
		return nil, fmt.Errorf("template %s: %w", name, err)
	}
	t = parsed.Lookup("base")
	if t == nil {
		return nil, fmt.Errorf("template %s: base no encontrado", name)
	}
	ts.cache["authed:"+name+":"+lang] = t
	return t, nil
}

func (ts *TemplateSet) get(name string, p *Pages, s *Session, lang string) (*template.Template, error) {
	ck := name + ":" + lang
	ts.mu.RLock()
	t, ok := ts.cache[ck]
	ts.mu.RUnlock()
	if ok {
		return t, nil
	}

	ts.mu.Lock()
	defer ts.mu.Unlock()
	if t, ok = ts.cache[ck]; ok {
		return t, nil
	}

	files := []string{"templates/" + name + ".html"}
	parsed, err := template.New(name + ".html").Funcs(p.Funcs(s, lang)).ParseFS(templatesFS, files...)
	if err != nil {
		return nil, fmt.Errorf("template %s: %w", name, err)
	}
	t = parsed.Lookup(name + ".html")
	if t == nil {
		return nil, fmt.Errorf("template %s: no encontrado", name)
	}
	ts.cache[name+":"+lang] = t
	return t, nil
}
