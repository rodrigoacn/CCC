# ClassExpress

An educational management web application built with PHP, Bootstrap 5, and jQuery. Students find teachers for online classes; teachers post class offers. On disconnect, students are charged in their LATAM local currency.

## Stack

- **Backend:** PHP 8.2 (built-in dev server)
- **Frontend:** Bootstrap 5.3.3, jQuery 3.7.1 (via CDN)
- **Database:** MySQL via PDO (optional — app runs in session-only mode if DB is unavailable)

## Project Structure

| File | Purpose |
|---|---|
| `index.php` | Redirects to `materias.php` |
| `materias.php` | Subjects dashboard — main landing page |
| `amigos.php` | Friends list |
| `calificar.php` | Rate a session / notifications |
| `matematicas.php` | Mathematics subject page |
| `historia.php` | History subject page |
| `literatura.php` | Language & Literature subject page |
| `quimica.php` | Chemistry subject page |
| `biologia.php` | Biology subject page |
| `fisica.php` | Physics subject page |
| `geografia.php` | Geography subject page |
| `arte.php` | Art & Music subject page |
| `educacion_fisica.php` | Physical Education subject page |
| `idiomas.php` | Foreign Languages subject page |
| `tecnologia.php` | Technology subject page |
| `profesores.php` | Teacher/instructor gallery |
| `perfil.php` | User profile |
| `checkout.php` | Cart / settings form |
| `aula_virtual.php` | Virtual classroom (webcam + chat) |
| `oferta_clase.php` | Teacher: quick class offer (price range, no title) |
| `crear_clase.php` | Teacher: full class creation form |
| `buscar.php` | Student ↔ teacher matching / search |
| `sala.php` | Live classroom with WebRTC camera & payment trigger |
| `pago.php` | Payment confirmation in student's LATAM local currency |
| `api_sala.php` | JSON API: join/leave/chat/messages/pay |
| `login.php` | Sign in / Sign up (with email verification & country) |
| `verify.php` | Email verification token handler |
| `menu.php` | Shared navbar + DB connection (included by all pages) |
| `db.php` | PDO singleton + helpers: `getDB()`, `dbOne()`, `dbAll()`, `dbExec()` |
| `script.js` | jQuery interactivity (progress tracking, webcam mock, chat) |
| `styles.css` | Custom styles extending Bootstrap |

## Running Locally

The app is served via PHP's built-in server on port 5000:

```bash
php -S 0.0.0.0:5000 -t /home/runner/workspace/
```

Visit `materias.php` (or just `/`) as the entry point.

## Database

The app tries to connect to a MySQL database named `ce` on localhost. If the DB is unavailable, it falls back to PHP sessions for state. Schema files: `database.sql`, `alter.sql`, `seed.sql`.

## Payment Flow

Teacher → `crear_clase.php` → `buscar.php` → student joins `sala.php` → on leave → `pago.php` (amount shown in student's local LATAM currency, e.g. CLP, ARS, MXN).

## User Preferences

- Keep all navigation links as relative `.php` paths (no hardcoded `file:///` or absolute paths)
