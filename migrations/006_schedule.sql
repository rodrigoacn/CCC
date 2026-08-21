-- ============================================================================
--  006 — Teacher availability schedules
-- ============================================================================

CREATE TABLE IF NOT EXISTS schedules (
    scheduleId    INT AUTO_INCREMENT PRIMARY KEY,
    usuarioId     INT          NOT NULL,
    dia_semana    VARCHAR(20)  NOT NULL,  -- 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'
    hora_inicio   TIME         NOT NULL,
    hora_fin      TIME         NOT NULL,
    is_primary    TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_s_usuario (usuarioId),
    CONSTRAINT fk_s_usuario FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índices para consultas rápidas por día
CREATE INDEX idx_s_dia ON schedules(dia_semana);
CREATE INDEX idx_s_prof ON schedules(usuarioId, dia_semana);