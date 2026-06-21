# ClassExpress

An educational management web application built with PHP, Bootstrap 5, and jQuery. Students can track their progress across academic subjects, view syllabi, manage schedules, and use a virtual classroom interface.

## Stack

- **Backend:** PHP 8.2 (built-in dev server)
- **Frontend:** Bootstrap 5.3.3, jQuery 3.7.1 (via CDN)
- **Database:** MySQL via PDO (optional — app runs in session-only mode if DB is unavailable)

## Project Structure

- `example1.php` — Subjects dashboard (main landing page)
- `example2.php` — Friends page
- `example3.php` — Notifications
- `example4–15.php` — Subject content pages (Math, Biology, Chemistry, Physics, etc.)
- `example16.php` — User profile
- `example17.php` — Checkout/settings form
- `example18.php` — Virtual classroom (webcam + chat)
- `example19–20.php` — Additional pages
- `menu.php` — Shared navbar + DB connection (included by all pages)
- `script.js` — jQuery interactivity (progress tracking, webcam mock, chat)
- `styles.css` — Custom styles extending Bootstrap
- `presentacion/` — Stub JS files (odp_ajax.js, scripts.js)

## Running Locally

The app is served via PHP's built-in server on port 5000:

```bash
php -S 0.0.0.0:5000 -t /home/runner/workspace/
```

Visit `example1.php` as the entry point.

## Database

The app tries to connect to a MySQL database named `ce` on localhost. If the DB is unavailable, it falls back to PHP sessions for state and continues working normally.

## User Preferences

- Keep all navigation links as relative `.php` paths (no hardcoded `file:///` or absolute paths)
