-- ─────────────────────────────────────────────────────────────────────────────
--  011_clase_disponibilidad_reservas.sql
--  Disponibilidad por clase + reservas con confirmación del profesor.
--  · clase_disponibilidad : bloques semanales de horario que el profesor
--    define para cada clase (dia_semana, hora_inicio, hora_fin).
--  · reservas_clase       : solicitudes de un estudiante sobre un bloque de esa
--    disponibilidad, con estado pendiente/confirmada/rechazada/cancelada.
--  · notificaciones_web   : avisos in-app (nueva reserva, respuesta del profe,
--    aviso de hora de clase) para la app instalable. Se usa una tabla propia
--    porque "notificaciones" ya existe con otro esquema (feed de la app móvil).
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS clase_disponibilidad (
    dispId      INT AUTO_INCREMENT PRIMARY KEY,
    claseId     INT          NOT NULL,
    dia_semana  VARCHAR(20)  NOT NULL,
    hora_inicio TIME         NOT NULL,
    hora_fin    TIME         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cd_clase (claseId),
    CONSTRAINT fk_cd_clase FOREIGN KEY (claseId) REFERENCES clases_programadas(claseId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservas_clase (
    reservaId    INT           AUTO_INCREMENT PRIMARY KEY,
    claseId      INT           NOT NULL,
    estudianteId INT           NOT NULL,
    instructorId INT           NOT NULL,
    fecha        DATE          NOT NULL,
    hora_inicio  TIME          NOT NULL,
    hora_fin     TIME          NOT NULL,
    estado       VARCHAR(20)   NOT NULL DEFAULT 'pendiente',
    mensaje      TEXT          DEFAULT NULL,
    avisada      TINYINT(1)    NOT NULL DEFAULT 0,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rc_clase (claseId),
    KEY idx_rc_estudiante (estudianteId),
    KEY idx_rc_instructor (instructorId),
    KEY idx_rc_estado (estado),
    CONSTRAINT fk_rc_clase      FOREIGN KEY (claseId)      REFERENCES clases_programadas(claseId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rc_estudiante FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId)          ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rc_instructor FOREIGN KEY (instructorId) REFERENCES usuarios(usuarioId)          ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notificaciones_web (
    notifId     INT          AUTO_INCREMENT PRIMARY KEY,
    usuarioId   INT          NOT NULL,
    tipo        VARCHAR(30)  NOT NULL DEFAULT 'info',
    titulo      VARCHAR(255) NOT NULL DEFAULT '',
    cuerpo      TEXT         DEFAULT NULL,
    enlace      VARCHAR(255) DEFAULT NULL,
    leida       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_usuario (usuarioId, leida),
    CONSTRAINT fk_notif_usuario FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;