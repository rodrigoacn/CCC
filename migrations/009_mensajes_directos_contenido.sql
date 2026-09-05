-- ─────────────────────────────────────────────────────────────────────────────
--  009_mensajes_directos_contenido.sql
--  Fusiona tipos de contenido en el chat directo (personas.php / mensajes_directos)
--  para permitir enviar fotos, videos, audio, enlaces, "qué piensas" y tests
--  (la respuesta correcta se revela después de responder).
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE mensajes_directos ADD COLUMN IF NOT EXISTS tipo VARCHAR(20) NOT NULL DEFAULT 'texto' AFTER mensaje;
ALTER TABLE mensajes_directos ADD COLUMN IF NOT EXISTS media_url VARCHAR(500) DEFAULT NULL AFTER tipo;
ALTER TABLE mensajes_directos ADD COLUMN IF NOT EXISTS media_nombre VARCHAR(255) DEFAULT NULL AFTER media_url;
ALTER TABLE mensajes_directos ADD COLUMN IF NOT EXISTS test_data TEXT AFTER media_nombre;
ALTER TABLE mensajes_directos ADD COLUMN IF NOT EXISTS respuesta_elegida TEXT AFTER test_data;
ALTER TABLE mensajes_directos ADD COLUMN IF NOT EXISTS respondido_por INT AFTER respuesta_elegida;
