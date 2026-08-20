-- ============================================================================
--  ClassExpress — Full Database Schema
--  Generated: 2026-08-17
--  Engine: InnoDB | Charset: utf8mb4
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
--  1. paises
-- ============================================================================
CREATE TABLE IF NOT EXISTS paises (
    paisId        INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    codigo_iso    VARCHAR(10)  NOT NULL DEFAULT '',
    codigo_moneda VARCHAR(10)  NOT NULL DEFAULT 'USD',
    simbolo       VARCHAR(5)   NOT NULL DEFAULT '$',
    tasa_usd      DECIMAL(12,4) NOT NULL DEFAULT 1.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  2. idiomas
-- ============================================================================
CREATE TABLE IF NOT EXISTS idiomas (
    idiomaId INT AUTO_INCREMENT PRIMARY KEY,
    nombre   VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  3. materias
-- ============================================================================
CREATE TABLE IF NOT EXISTS materias (
    materiaId INT AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(150) NOT NULL,
    imagen    VARCHAR(500) NOT NULL DEFAULT '',
    pagina    VARCHAR(255) NOT NULL DEFAULT '',
    orden     INT          NOT NULL DEFAULT 0,
    icono     VARCHAR(50)  NOT NULL DEFAULT 'book',
    color     VARCHAR(20)  NOT NULL DEFAULT '#66ddbd'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  4. usuarios
-- ============================================================================
CREATE TABLE IF NOT EXISTS usuarios (
    usuarioId         INT AUTO_INCREMENT PRIMARY KEY,
    nombre            VARCHAR(255) NOT NULL DEFAULT '',
    email             VARCHAR(255) NOT NULL DEFAULT '',
    username          VARCHAR(100) NOT NULL DEFAULT '',
    password          VARCHAR(255) NOT NULL DEFAULT '',
    rol               VARCHAR(20)  NOT NULL DEFAULT 'estudiante',
    verificado        TINYINT(1)   NOT NULL DEFAULT 0,
    avatar            VARCHAR(500) NOT NULL DEFAULT '',
    biografia         TEXT,
    pais_id           INT          DEFAULT NULL,
    creditos          DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    tokens            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    calificacion      DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
    num_resenas       INT          NOT NULL DEFAULT 0,
    idioma_preferido  VARCHAR(10)  NOT NULL DEFAULT 'es',
    ultimaMateria     INT          NOT NULL DEFAULT 0,
    ultimoContenido   VARCHAR(255) NOT NULL DEFAULT '',
    ultimaClase       VARCHAR(255) NOT NULL DEFAULT '',
    ultimaSala        VARCHAR(255) NOT NULL DEFAULT '',
    privacidad        VARCHAR(20)  NOT NULL DEFAULT 'private',
    token_verificacion VARCHAR(255) NOT NULL DEFAULT '',
    eliminado         TINYINT(1)   NOT NULL DEFAULT 0,
    last_role_switch  DATETIME     DEFAULT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usuarios_email (email),
    KEY idx_usuarios_username (username),
    KEY idx_usuarios_rol (rol),
    CONSTRAINT fk_usuarios_pais FOREIGN KEY (pais_id) REFERENCES paises(paisId) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  5. usuario_idiomas
-- ============================================================================
CREATE TABLE IF NOT EXISTS usuario_idiomas (
    usuarioId INT NOT NULL,
    idiomaId  INT NOT NULL,
    PRIMARY KEY (usuarioId, idiomaId),
    CONSTRAINT fk_ui_usuario  FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ui_idioma   FOREIGN KEY (idiomaId)  REFERENCES idiomas(idiomaId)  ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  6. mobile_tokens
-- ============================================================================
CREATE TABLE IF NOT EXISTS mobile_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT          NOT NULL,
    token       VARCHAR(64)  NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    expires_at  TIMESTAMP    DEFAULT (CURRENT_TIMESTAMP + INTERVAL 30 DAY),
    UNIQUE KEY uk_mt_token (token),
    CONSTRAINT fk_mt_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  7. clases_programadas
-- ============================================================================
CREATE TABLE IF NOT EXISTS clases_programadas (
    claseId         INT AUTO_INCREMENT PRIMARY KEY,
    titulo          VARCHAR(255)  NOT NULL DEFAULT '',
    descripcion     TEXT,
    materiaId       INT           NOT NULL,
    instructorId    INT           NOT NULL,
    precio_base     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    precio_min      DECIMAL(10,2) DEFAULT NULL,
    precio_max      DECIMAL(10,2) DEFAULT NULL,
    codigo_moneda   VARCHAR(10)   NOT NULL DEFAULT 'USD',
    alumnos_min     INT           NOT NULL DEFAULT 1,
    alumnos_max     INT           NOT NULL DEFAULT 10,
    duracion_min    INT           NOT NULL DEFAULT 60,
    calificacion    DECIMAL(3,2)  NOT NULL DEFAULT 0.00,
    alumnos_activos INT           NOT NULL DEFAULT 0,
    salaId          INT           DEFAULT NULL,
    activa          TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cp_instructor (instructorId),
    KEY idx_cp_materia (materiaId),
    KEY idx_cp_activa (activa),
    CONSTRAINT fk_cp_materia    FOREIGN KEY (materiaId)    REFERENCES materias(materiaId)       ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cp_instructor FOREIGN KEY (instructorId) REFERENCES usuarios(usuarioId)       ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  8. salas
-- ============================================================================
CREATE TABLE IF NOT EXISTS salas (
    salaId       INT AUTO_INCREMENT PRIMARY KEY,
    claseId      INT          NOT NULL,
    titulo       VARCHAR(255) NOT NULL DEFAULT '',
    curso        VARCHAR(255) NOT NULL DEFAULT '',
    instructorId INT          NOT NULL,
    activa       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_salas_clase (claseId),
    KEY idx_salas_instructor (instructorId),
    CONSTRAINT fk_salas_clase      FOREIGN KEY (claseId)      REFERENCES clases_programadas(claseId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_salas_instructor FOREIGN KEY (instructorId) REFERENCES usuarios(usuarioId)        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add circular FK from clases_programadas -> salas (after salas exists)
ALTER TABLE clases_programadas
    ADD CONSTRAINT fk_cp_sala FOREIGN KEY (salaId) REFERENCES salas(salaId) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================================================
--  9. sesiones_clase
-- ============================================================================
CREATE TABLE IF NOT EXISTS sesiones_clase (
    sesionId             INT AUTO_INCREMENT PRIMARY KEY,
    claseId              INT           NOT NULL,
    estudianteId         INT           NOT NULL,
    instructorId         INT           NOT NULL,
    salaId               INT           DEFAULT NULL,
    inicio               DATETIME      NOT NULL,
    fin                  DATETIME      DEFAULT NULL,
    duracion_min         INT           NOT NULL DEFAULT 0,
    segundos_acumulados  INT           NOT NULL DEFAULT 0,
    ultima_salida        DATETIME      DEFAULT NULL,
    precio_usd           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monto_local          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    moneda_local         VARCHAR(10)   NOT NULL DEFAULT 'USD',
    simbolo_local        VARCHAR(5)    NOT NULL DEFAULT '$',
    pagado               TINYINT(1)    NOT NULL DEFAULT 0,
    espectador           TINYINT(1)    NOT NULL DEFAULT 0,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sc_clase (claseId),
    KEY idx_sc_estudiante (estudianteId),
    KEY idx_sc_instructor (instructorId),
    KEY idx_sc_sala (salaId),
    KEY idx_sc_pagado (pagado),
    KEY idx_sc_fin (fin),
    CONSTRAINT fk_sc_clase      FOREIGN KEY (claseId)      REFERENCES clases_programadas(claseId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sc_estudiante FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId)         ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sc_instructor FOREIGN KEY (instructorId) REFERENCES usuarios(usuarioId)         ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sc_sala       FOREIGN KEY (salaId)       REFERENCES salas(salaId)               ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  10. pagos
-- ============================================================================
CREATE TABLE IF NOT EXISTS pagos (
    pagoId        INT AUTO_INCREMENT PRIMARY KEY,
    sesionId      INT           NOT NULL,
    estudianteId  INT           NOT NULL,
    profesorId    INT           NOT NULL,
    monto_usd     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monto_local   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    moneda_local  VARCHAR(10)   NOT NULL DEFAULT 'USD',
    simbolo_local VARCHAR(5)    NOT NULL DEFAULT '$',
    estado        VARCHAR(20)   NOT NULL DEFAULT 'pendiente',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pagos_sesion (sesionId),
    KEY idx_pagos_estudiante (estudianteId),
    KEY idx_pagos_profesor (profesorId),
    KEY idx_pagos_estado (estado),
    CONSTRAINT fk_pagos_sesion     FOREIGN KEY (sesionId)     REFERENCES sesiones_clase(sesionId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pagos_estudiante FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId)     ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pagos_profesor   FOREIGN KEY (profesorId)   REFERENCES usuarios(usuarioId)     ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  11. resenas
-- ============================================================================
CREATE TABLE IF NOT EXISTS resenas (
    resenaId    INT AUTO_INCREMENT PRIMARY KEY,
    profesorId  INT          NOT NULL,
    estudianteId INT         NOT NULL,
    sesionId    INT          DEFAULT NULL,
    calificacion INT         NOT NULL DEFAULT 5,
    comentario  TEXT,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_resenas_profesor (profesorId),
    KEY idx_resenas_estudiante (estudianteId),
    CONSTRAINT fk_resenas_profesor   FOREIGN KEY (profesorId)   REFERENCES usuarios(usuarioId)     ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_resenas_estudiante FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId)     ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_resenas_sesion     FOREIGN KEY (sesionId)     REFERENCES sesiones_clase(sesionId) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  12. relaciones (follow system)
-- ============================================================================
CREATE TABLE IF NOT EXISTS relaciones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    seguidorId  INT          NOT NULL,
    seguidoId   INT          NOT NULL,
    estado      VARCHAR(20)  NOT NULL DEFAULT 'following',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_relacion (seguidorId, seguidoId),
    KEY idx_rel_seguido (seguidoId),
    CONSTRAINT fk_rel_seguidor FOREIGN KEY (seguidorId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rel_seguido  FOREIGN KEY (seguidoId)  REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  13. mensajes_directos
-- ============================================================================
CREATE TABLE IF NOT EXISTS mensajes_directos (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    remitente_id     INT          NOT NULL,
    destinatario_id  INT          NOT NULL,
    mensaje          TEXT         NOT NULL,
    leido            TINYINT(1)   NOT NULL DEFAULT 0,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_md_remitente (remitente_id),
    KEY idx_md_destinatario (destinatario_id),
    KEY idx_md_leido (leido),
    CONSTRAINT fk_md_remitente    FOREIGN KEY (remitente_id)    REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_md_destinatario FOREIGN KEY (destinatario_id) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  14. mensajes_chat (in-room chat)
-- ============================================================================
CREATE TABLE IF NOT EXISTS mensajes_chat (
    mensajeId  INT AUTO_INCREMENT PRIMARY KEY,
    salaId     INT          NOT NULL,
    usuarioId  INT          NOT NULL,
    alias      VARCHAR(255) NOT NULL DEFAULT '',
    mensaje    TEXT         NOT NULL,
    enviado_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mc_sala (salaId),
    KEY idx_mc_usuario (usuarioId),
    KEY idx_mc_sala_msg (salaId, mensajeId),
    CONSTRAINT fk_mc_sala    FOREIGN KEY (salaId)    REFERENCES salas(salaId)       ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_mc_usuario FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  15. webrtc_signals
-- ============================================================================
CREATE TABLE IF NOT EXISTS webrtc_signals (
    signalid  INT AUTO_INCREMENT PRIMARY KEY,
    salaId    INT          NOT NULL,
    from_uid  INT          NOT NULL,
    to_uid    INT          DEFAULT NULL,
    tipo      VARCHAR(20)  NOT NULL DEFAULT '',
    payload   TEXT         NOT NULL,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ws_sala (salaId),
    KEY idx_ws_from (from_uid),
    KEY idx_ws_to (to_uid),
    KEY idx_ws_sala_id (salaId, signalid),
    CONSTRAINT fk_ws_sala   FOREIGN KEY (salaId)   REFERENCES salas(salaId)       ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ws_from   FOREIGN KEY (from_uid) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ws_to     FOREIGN KEY (to_uid)   REFERENCES usuarios(usuarioId) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  16. participantes_sala
-- ============================================================================
CREATE TABLE IF NOT EXISTS participantes_sala (
    salaId           INT        NOT NULL,
    usuarioId        INT        NOT NULL,
    camara_activa    TINYINT(1) NOT NULL DEFAULT 0,
    microfono_activo TINYINT(1) NOT NULL DEFAULT 0,
    created_at       DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (salaId, usuarioId),
    CONSTRAINT fk_ps_sala    FOREIGN KEY (salaId)    REFERENCES salas(salaId)       ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ps_usuario FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  17. espectadores
-- ============================================================================
CREATE TABLE IF NOT EXISTS espectadores (
    espectadorId   INT AUTO_INCREMENT PRIMARY KEY,
    salaId         INT          NOT NULL,
    usuarioId      INT          NOT NULL,
    estado         VARCHAR(20)  NOT NULL DEFAULT 'pendiente',
    profesor_aprobo INT         DEFAULT NULL,
    sobre_cupo     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_esp_sala (salaId),
    KEY idx_esp_usuario (usuarioId),
    KEY idx_esp_estado (estado),
    CONSTRAINT fk_esp_sala         FOREIGN KEY (salaId)         REFERENCES salas(salaId)       ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_esp_usuario      FOREIGN KEY (usuarioId)      REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_esp_profesor     FOREIGN KEY (profesor_aprobo) REFERENCES usuarios(usuarioId) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  18. sanciones
-- ============================================================================
CREATE TABLE IF NOT EXISTS sanciones (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    salaId        INT      NOT NULL,
    instructorId  INT      NOT NULL,
    estudianteId  INT      NOT NULL,
    comentario    TEXT     NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sanc_sala (salaId),
    CONSTRAINT fk_sanc_sala       FOREIGN KEY (salaId)       REFERENCES salas(salaId)       ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sanc_instructor FOREIGN KEY (instructorId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sanc_estudiante FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  19. compras_tokens
-- ============================================================================
CREATE TABLE IF NOT EXISTS compras_tokens (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT           NOT NULL,
    cantidad      INT           NOT NULL DEFAULT 0,
    monto_usd     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fee_rodrigo   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    metodo_pago   VARCHAR(50)   NOT NULL DEFAULT 'mercadopago',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ct_usuario (usuario_id),
    CONSTRAINT fk_ct_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  20. retiros_tokens
-- ============================================================================
CREATE TABLE IF NOT EXISTS retiros_tokens (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id     INT           NOT NULL,
    cantidad       INT           NOT NULL DEFAULT 0,
    monto_usd      DECIMAL(10,2) DEFAULT NULL,
    monto_clp      INT           DEFAULT NULL,
    comision       DECIMAL(10,2) DEFAULT NULL,
    neto_pagar     DECIMAL(10,2) DEFAULT NULL,
    cuenta_bancaria VARCHAR(255) NOT NULL DEFAULT '',
    nombre_banco   VARCHAR(255) NOT NULL DEFAULT '',
    tipo_cuenta    VARCHAR(20)   DEFAULT NULL,
    paypal_email   VARCHAR(150)  DEFAULT NULL,
    estado         VARCHAR(20)   NOT NULL DEFAULT 'pendiente',
    admin_note     VARCHAR(255)  DEFAULT NULL,
    procesado_por  INT           DEFAULT NULL,
    procesado_at   DATETIME      DEFAULT NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rt_usuario (usuario_id),
    KEY idx_rt_estado (estado),
    CONSTRAINT fk_rt_usuario    FOREIGN KEY (usuario_id)    REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rt_procesado  FOREIGN KEY (procesado_por) REFERENCES usuarios(usuarioId) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  21. checkout_sessions (MercadoPago)
-- ============================================================================
CREATE TABLE IF NOT EXISTS checkout_sessions (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id         INT           NOT NULL,
    type               VARCHAR(50)   NOT NULL DEFAULT '',
    quantity           INT           NOT NULL DEFAULT 0,
    amount_usd         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_local       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency           VARCHAR(10)   NOT NULL DEFAULT 'USD',
    preference_id      VARCHAR(255)  NOT NULL DEFAULT '',
    external_reference VARCHAR(255)  NOT NULL DEFAULT '',
    payment_id         VARCHAR(255)  DEFAULT NULL,
    status             VARCHAR(50)   NOT NULL DEFAULT 'pending',
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cs_usuario (usuario_id),
    KEY idx_cs_extref (external_reference),
    KEY idx_cs_pref (preference_id),
    CONSTRAINT fk_cs_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  22. ads_free_compras
-- ============================================================================
CREATE TABLE IF NOT EXISTS ads_free_compras (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    monto_clp   DECIMAL(10,2) NOT NULL DEFAULT 5000.00,
    valido_hasta DATETIME     NOT NULL,
    estado      ENUM('activo','expirado') NOT NULL DEFAULT 'activo',
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
--  23. schema_migrations (migration tracking)
-- ============================================================================
CREATE TABLE IF NOT EXISTS schema_migrations (
    version    VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
