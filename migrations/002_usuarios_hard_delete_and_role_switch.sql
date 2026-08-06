-- Columns used by web remember-me/login (eliminado) and role-switch lock
-- (last_role_switch). Present in production; missing in the local dev schema.
-- Plain ALTER for MySQL 8 + MariaDB compatibility. The migrations/apply.php
-- runner ignores "duplicate column" errors, so this is safe to re-run.
ALTER TABLE usuarios
  ADD COLUMN eliminado TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN last_role_switch DATETIME NULL DEFAULT NULL;
