# ClassExpress

An educational management web application built with PHP 8.2, Bootstrap 5, and jQuery. Students find teachers for online classes; teachers post class offers. On disconnect, students are charged in their LATAM local currency via a credits system.

## Stack

- **Backend:** PHP 8.2 (built-in dev server on port 5000)
- **Frontend:** Bootstrap 5.3.3, jQuery 3.7.1, Bootstrap Icons (via CDN)
- **Database:** PostgreSQL via PDO (Replit's built-in DB — `PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD`)
- **Video:** WebRTC peer-to-peer (`RTCPeerConnection` + Google STUN, DB-based signaling)
- **Email:** PHP `mail()` with multipart HTML/plain-text templates

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
| `oferta_clase.php` | Teacher: quick class offer |
| `crear_clase.php` | Teacher: full class creation form |
| `buscar.php` | Student ↔ teacher matching / search |
| `sala.php` | Live classroom — real WebRTC video + chat + credits check |
| `pago.php` | Payment confirmation (LATAM local currency, deducts credits, sends receipt email) |
| `creditos.php` | Credits wallet — balance, top-up, payment history |
| `api_sala.php` | JSON API: join / leave / chat / messages / WebRTC signals |
| `login.php` | Sign in / Sign up (email verification, country selection) |
| `verify.php` | Email verification token handler |
| `forgot_password.php` | Password reset — email entry form |
| `reset_password.php` | Password reset — new password form (1-hour token) |
| `dashboard_profesor.php` | Teacher dashboard: earnings, active classes, recent sessions |
| `menu.php` | Shared navbar + Bootstrap Icons CDN |
| `db.php` | PostgreSQL PDO singleton + helpers: `getDB()`, `dbOne()`, `dbAll()`, `dbExec()` |
| `email_helper.php` | HTML email templates: verify, reset password, session receipt |
| `styles.css` | Custom styles extending Bootstrap |

## Running

```bash
php -S 0.0.0.0:5000 -t /home/runner/workspace/
```

Visit `/` (redirects to `materias.php`) as the entry point.

## Database

PostgreSQL provided by Replit. All result keys are normalized to lowercase via `array_change_key_case(CASE_LOWER)` in `dbOne()`/`dbAll()`. Session keys (`$_SESSION['usuarioId']` etc.) remain camelCase.

Schema / seed files: `database.sql`, `alter.sql`, `seed.sql`.

**Key tables:** `usuarios`, `paises`, `materias`, `clases_programadas`, `sesiones_clase`, `salas`, `participantes_sala`, `mensajes_chat`, `pagos`, `webrtc_signals`

## Full User Flow

```
Student                                Teacher
  │                                       │
  ├── login.php (sign up → email verify)  ├── login.php
  ├── materias.php → subject page         ├── crear_clase.php / oferta_clase.php
  ├── profesores.php (browse teachers)    ├── dashboard_profesor.php
  ├── buscar.php (find a class)           │
  ├── sala.php → WebRTC video join        ├── sala.php → "Start Hosting"
  ├── [session ends → leave]             │
  └── pago.php (credits deducted,        └── dashboard shows new session
       receipt email sent)
```

## Credits System

- 1 credit = $1 USD
- Students need credits ≥ class price to join
- Credits deducted on payment confirmation
- Top-up at `creditos.php` (demo mode — real Stripe can be added)
- New users receive 100 credits on creation

## Auth Seed Users

| Email | Password | Role |
|---|---|---|
| `alexander@classexpress.app` | `demo1234` | instructor |
| `rodrigo@classexpress.app` | `demo1234` | director |
| `charles@classexpress.app` | `demo1234` | student |

## User Preferences

- Keep all navigation links as relative `.php` paths (no hardcoded `file:///` or absolute paths)
