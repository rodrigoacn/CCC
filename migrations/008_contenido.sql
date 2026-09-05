-- ─────────────────────────────────────────────────────────────────────────────
--  008_contenido.sql — Contenido feed (publicaciones, multimedia, tests y likes)
--
--  Tipos de publicación (contenido_publicaciones.tipo):
--    texto   → publicación de texto simple (opcionalmente con enlace)
--    media   → foto / video / audio (con contenido_media)
--    test    → cuestionario de opción múltiple (contenido_preguntas/opciones)
--    opinion → "qué piensas de esto" (texto; la respuesta correcta se pide al
--              estilo test: el alumno indica su postura y se revela tras votar)
--
--  Los likes se guardan en contenido_likes (una fila por usuario/publicación).
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS contenido_publicaciones (
    publicacionId INT AUTO_INCREMENT PRIMARY KEY,
    usuarioId INT NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    texto LONGTEXT,
    enlace VARCHAR(500) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_publicaciones_usuario (usuarioId),
    KEY idx_publicaciones_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contenido_media (
    mediaId INT AUTO_INCREMENT PRIMARY KEY,
    publicacionId INT NOT NULL,
    tipo_media VARCHAR(20) NOT NULL, -- imagen | video | audio
    url VARCHAR(500) NOT NULL,
    nombre VARCHAR(255) DEFAULT NULL,
    KEY idx_media_publicacion (publicacionId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contenido_preguntas (
    preguntaId INT AUTO_INCREMENT PRIMARY KEY,
    publicacionId INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    texto_pregunta LONGTEXT,
    media_id INT DEFAULT NULL,
    KEY idx_preguntas_publicacion (publicacionId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contenido_opciones (
    opcionId INT AUTO_INCREMENT PRIMARY KEY,
    preguntaId INT NOT NULL,
    texto_opcion LONGTEXT,
    es_correcta TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_opciones_pregunta (preguntaId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contenido_respuestas (
    respuestaId INT AUTO_INCREMENT PRIMARY KEY,
    preguntaId INT NOT NULL,
    usuarioId INT NOT NULL,
    opcionId INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_respuesta (preguntaId, usuarioId),
    KEY idx_respuestas_opcion (opcionId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contenido_likes (
    likeId INT AUTO_INCREMENT PRIMARY KEY,
    publicacionId INT NOT NULL,
    usuarioId INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_like (publicacionId, usuarioId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
