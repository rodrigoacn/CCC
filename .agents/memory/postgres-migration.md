---
name: PostgreSQL migration
description: ClassExpress migrated from MySQL to Replit's built-in PostgreSQL; key differences and fixes applied.
---

## Rule
Use Replit's managed PostgreSQL (env vars PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD) — MySQL is not available.

**Why:** MySQL is not installed/running on this Replit instance. Replit's native DB is PostgreSQL.

## How to apply
- `db.php` uses `getenv('PGHOST')` etc. to build a `pgsql:` DSN.
- `dbOne()` and `dbAll()` apply `array_change_key_case($row, CASE_LOWER)` — PostgreSQL returns all unquoted identifiers as lowercase, so PHP array keys are lowercase (e.g. `$row['usuarioid']`, not `$row['usuarioId']`).
- `$_SESSION` keys (e.g. `$_SESSION['usuarioId']`) are PHP-controlled and stay camelCase.
- Schema is in PostgreSQL syntax: SERIAL instead of AUTO_INCREMENT, SMALLINT instead of TINYINT(1), no ENGINE=InnoDB, no ENUM (use VARCHAR), expanded GROUP BY.
- All seed data loaded via `executeSql()` from the code_execution sandbox.
