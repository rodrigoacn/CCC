-- 007_es_invitado.sql
-- Add es_invitado flag to usuarios table for guest/quick-entry users

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS es_invitado TINYINT(1) NOT NULL DEFAULT 0 AFTER verificado;

CREATE INDEX IF NOT EXISTS idx_usuarios_es_invitado ON usuarios (es_invitado);