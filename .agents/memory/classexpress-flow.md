---
name: ClassExpress DB & payment flow
description: Key constraints for the ClassExpress PHP app — function conflicts, payment flow, and page roles
---

## Critical constraint: getDB() must not be redeclared
`login.php` originally had its own inline `getDB()`. When `require_once 'db.php'` was added it caused a fatal redeclaration error. Fix: remove the local copy from login.php entirely — db.php is the single source.

**Why:** PHP fatal if the same function name is declared twice, even with require_once, because require_once only prevents double-file inclusion, not double-declaration when the function already exists in the caller.

**How to apply:** Any page that needs DB access should only do `require 'db.php'` — never define getDB() locally.

## Payment flow
Teacher → `example20.php` (create class with title) or `example19.php` (quick filter offer)
→ class stored in `clases_programadas` table
→ student finds it in `buscar.php`
→ joins via `sala.php?clase=X` (creates `sesiones_clase` row, starts timer)
→ on leave `pago.php?sesion=Y` shown
→ student pays in local LATAM currency (converted from teacher's currency via `paises.tasa_usd`)
→ `pagos` table records both USD and local amounts

## Page roles
- `example19.php` — quick class offer (price range + capacity, no title required)
- `example20.php` — full class creation (title required, description, subject, price in teacher's currency)
- `buscar.php` — student/teacher matching with subject filter
- `sala.php` — live classroom, WebRTC camera, real-time chat polling, triggers payment on leave
- `pago.php` — finalise payment in student's local LATAM currency; records to `pagos` table
- `api_sala.php` — JSON API: join/leave/chat/messages/pay actions
- `db.php` — PDO singleton, helpers: getDB(), dbOne(), dbAll(), dbExec()

## Stub JS files
`presentacion/odp_ajax.js` and `presentacion/js/scripts.js` must exist as empty stubs to avoid 404s.
