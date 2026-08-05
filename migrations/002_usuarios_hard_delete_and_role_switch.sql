-- Columns used by web remember-me/login (eliminado) and role-switch lock
-- (last_role_switch). Present in production; missing in the local dev schema.
ALTER TABLE usuarios
  ADD COLUMN IF NOT EXISTS eliminado TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS last_role_switch DATETIME NULL DEFAULT NULL;
