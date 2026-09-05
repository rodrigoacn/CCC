-- ─────────────────────────────────────────────────────────────────────────────
--  010_descuento_alumno_nuevo.sql
--  Permite al profesor definir un porcentaje de descuento para alumnos nuevos
--  (primera vez que un estudiante se une a esa clase). Se guarda como entero
--  de porcentaje en clases_programadas.descuento_nuevo (0 = sin descuento).
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE clases_programadas ADD COLUMN IF NOT EXISTS descuento_nuevo INT NOT NULL DEFAULT 0;
