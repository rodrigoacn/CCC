-- ============================================================================
--  005 — Forum tables
-- ============================================================================

CREATE TABLE IF NOT EXISTS foro_hilos (
    hiloId      INT AUTO_INCREMENT PRIMARY KEY,
    materiaId   INT          NOT NULL,
    usuarioId   INT          NOT NULL,
    titulo      VARCHAR(255) NOT NULL,
    contenido   TEXT         NOT NULL,
    views       INT          NOT NULL DEFAULT 0,
    pinned      TINYINT(1)   NOT NULL DEFAULT 0,
    closed      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fh_materia  (materiaId),
    KEY idx_fh_usuario  (usuarioId),
    CONSTRAINT fk_fh_materia FOREIGN KEY (materiaId) REFERENCES materias(materiaId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fh_usuario FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS foro_respuestas (
    respuestaId  INT AUTO_INCREMENT PRIMARY KEY,
    hiloId       INT          NOT NULL,
    usuarioId    INT          NOT NULL,
    contenido    TEXT         NOT NULL,
    es_mejor     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fr_hilo    (hiloId),
    KEY idx_fr_usuario (usuarioId),
    CONSTRAINT fk_fr_hilo    FOREIGN KEY (hiloId)    REFERENCES foro_hilos(hiloId)    ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fr_usuario FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS foro_likes (
    likeId      INT AUTO_INCREMENT PRIMARY KEY,
    hiloId      INT     DEFAULT NULL,
    respuestaId INT     DEFAULT NULL,
    usuarioId   INT     NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_fl_hilo    (hiloId, usuarioId),
    UNIQUE KEY uk_fl_resp    (respuestaId, usuarioId),
    CONSTRAINT fk_fl_hilo      FOREIGN KEY (hiloId)      REFERENCES foro_hilos(hiloId)            ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fl_respuesta FOREIGN KEY (respuestaId) REFERENCES foro_respuestas(respuestaId)   ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fl_usuario   FOREIGN KEY (usuarioId)   REFERENCES usuarios(usuarioId)            ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS foro_archivos (
    archivoId   INT AUTO_INCREMENT PRIMARY KEY,
    respuestaId INT          NOT NULL,
    nombre      VARCHAR(255) NOT NULL,
    url         VARCHAR(500) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fa_respuesta (respuestaId),
    CONSTRAINT fk_fa_respuesta FOREIGN KEY (respuestaId) REFERENCES foro_respuestas(respuestaId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS foro_reports (
    reportId    INT AUTO_INCREMENT PRIMARY KEY,
    hiloId      INT          DEFAULT NULL,
    respuestaId INT          DEFAULT NULL,
    usuarioId   INT          NOT NULL,
    motivo      VARCHAR(500) NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_frep_hilo      (hiloId),
    KEY idx_frep_respuesta (respuestaId),
    CONSTRAINT fk_frep_hilo      FOREIGN KEY (hiloId)      REFERENCES foro_hilos(hiloId)            ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_frep_respuesta FOREIGN KEY (respuestaId) REFERENCES foro_respuestas(respuestaId)   ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_frep_usuario   FOREIGN KEY (usuarioId)   REFERENCES usuarios(usuarioId)            ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
