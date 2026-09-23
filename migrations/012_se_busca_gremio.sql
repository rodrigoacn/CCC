-- 012 · Sistema "Se busca" + gremio de profesores
--  · solicitudes_clase : pedidos de alumnos ("quiero una clase de X").
--  · ofertas_solicitud : presupuestos por hora que los profes del gremio
--    envían a un pedido.
--  · usuarios.competencias : competencias/habilidades del profesor para el
--    emparejamiento de avisos.

CREATE TABLE IF NOT EXISTS solicitudes_clase (
    solicitudId         INT AUTO_INCREMENT PRIMARY KEY,
    estudianteId        INT          NOT NULL,
    titulo              VARCHAR(255) NOT NULL DEFAULT '',
    descripcion         TEXT,
    materiaId           INT          DEFAULT NULL,
    key_tokens          VARCHAR(500) NOT NULL DEFAULT '',
    alumnos_interesados INT          NOT NULL DEFAULT 1,
    estado              VARCHAR(20)  NOT NULL DEFAULT 'abierta',
    aceptada_con        INT          DEFAULT NULL,
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sc_estado (estado),
    KEY idx_sc_materia (materiaId),
    KEY idx_sc_estudiante (estudianteId),
    CONSTRAINT fk_sc_estudiante FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sc_materia    FOREIGN KEY (materiaId)    REFERENCES materias(materiaId)   ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ofertas_solicitud (
    ofertaId      INT AUTO_INCREMENT PRIMARY KEY,
    solicitudId   INT          NOT NULL,
    instructorId  INT          NOT NULL,
    precio_hora   DECIMAL(10,2) NOT NULL DEFAULT 0,
    codigo_moneda VARCHAR(10)  NOT NULL DEFAULT 'USD',
    mensaje       TEXT,
    estado        VARCHAR(20)  NOT NULL DEFAULT 'pendiente',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_os_solicitud (solicitudId),
    KEY idx_os_instructor (instructorId),
    CONSTRAINT fk_os_solicitud  FOREIGN KEY (solicitudId)  REFERENCES solicitudes_clase(solicitudId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_os_instructor FOREIGN KEY (instructorId) REFERENCES usuarios(usuarioId)           ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE usuarios ADD COLUMN competencias TEXT;