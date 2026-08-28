with open('C:\\xampp\\htdocs\\CCC\\go\\internal\\web\\auth.go', 'rb') as f:
    a = f.read()

idx = a.find(b'func (p *Pages) doSignIn')
content = a[:idx] + b'func (p *Pages) doSignIn(ctx context.Context, w http.ResponseWriter, r *http.Request, s *Session, lang string, ip string) (string, string) {\n\treturn "", ""\n}\n\nfunc main() {}'
with open('C:\\xampp\\htdocs\\CCC\\go\\test5.go', 'wb') as f:
    f.write(content)