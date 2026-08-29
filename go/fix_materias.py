with open('C:\\xampp\\htdocs\\CCC\\go\\internal\\web\\materias.go', 'r', encoding='utf-8') as f:
    content = f.read()

old = """\t// Continuar card
\tultimaMateria := int64(0)
\tif s != nil && LoggedIn(s) {
\t\tif row, _ := p.DB.QueryOne(r.Context(), \"SELECT ultimaMateria FROM usuarios WHERE usuarioId = ?\", UID(s)); row != nil {
\t\t\tultimaMateria = store.Int(row[\"ultimaMateria\"])
\t\t}
\t}

\tfirst := \"Usuario\"
\tif s != nil && LoggedIn(s) {"""

new = """\t// Continuar card
\tif s != nil && LoggedIn(s) {"""

content = content.replace(old, new)

with open('C:\\xampp\\htdocs\\CCC\\go\\internal\\web\\materias.go', 'w', encoding='utf-8') as f:
    f.write(content)
print('Done')