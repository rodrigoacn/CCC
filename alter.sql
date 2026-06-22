-- ═══════════════════════════════════════════════════════════════════════════════
--  ClassExpress — Schema Additions
--  Run AFTER database.sql and seed.sql
-- ═══════════════════════════════════════════════════════════════════════════════
USE ce;

-- ─────────────────────────────────────────────────────────────────────────────
--  LATAM Countries with local currencies and approximate USD exchange rate
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS paises (
    paisId        INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100)    NOT NULL,
    codigo_iso    CHAR(2)         NOT NULL UNIQUE,
    moneda        VARCHAR(60)     NOT NULL,
    codigo_moneda CHAR(3)         NOT NULL,
    simbolo       VARCHAR(5)      NOT NULL DEFAULT '$',
    tasa_usd      DECIMAL(10,4)   NOT NULL DEFAULT 1.0000  -- local units per 1 USD
) ENGINE=InnoDB;

INSERT INTO paises (nombre, codigo_iso, moneda, codigo_moneda, simbolo, tasa_usd) VALUES
    ('Argentina',            'AR', 'Peso Argentino',       'ARS', '$',   900.0000),
    ('Bolivia',              'BO', 'Boliviano',             'BOB', 'Bs',    6.9000),
    ('Brasil',               'BR', 'Real Brasileño',        'BRL', 'R$',    5.1000),
    ('Chile',                'CL', 'Peso Chileno',          'CLP', '$',   940.0000),
    ('Colombia',             'CO', 'Peso Colombiano',       'COP', '$',  3900.0000),
    ('Costa Rica',           'CR', 'Colón Costarricense',   'CRC', '₡',   520.0000),
    ('Cuba',                 'CU', 'Peso Cubano',           'CUP', '$',    24.0000),
    ('Ecuador',              'EC', 'Dólar',                 'USD', '$',     1.0000),
    ('El Salvador',          'SV', 'Dólar',                 'USD', '$',     1.0000),
    ('Guatemala',            'GT', 'Quetzal',               'GTQ', 'Q',     7.8000),
    ('Honduras',             'HN', 'Lempira',               'HNL', 'L',    24.6000),
    ('México',               'MX', 'Peso Mexicano',         'MXN', '$',    17.0000),
    ('Nicaragua',            'NI', 'Córdoba',               'NIO', 'C$',   36.5000),
    ('Panamá',               'PA', 'Dólar',                 'USD', '$',     1.0000),
    ('Paraguay',             'PY', 'Guaraní',               'PYG', '₲',  7300.0000),
    ('Perú',                 'PE', 'Sol',                   'PEN', 'S/',    3.7500),
    ('República Dominicana', 'DO', 'Peso Dominicano',       'DOP', '$',    57.0000),
    ('Uruguay',              'UY', 'Peso Uruguayo',         'UYU', '$',    38.0000),
    ('Venezuela',            'VE', 'Bolívar Soberano',      'VES', 'Bs',   36.0000);

-- ─────────────────────────────────────────────────────────────────────────────
--  Add country to users (safe — skips if column already exists)
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS pais_id INT NULL AFTER avatar,
    ADD CONSTRAINT IF NOT EXISTS fk_usuario_pais
        FOREIGN KEY (pais_id) REFERENCES paises(paisId) ON DELETE SET NULL;

-- Update seed users with countries
UPDATE usuarios SET pais_id = (SELECT paisId FROM paises WHERE codigo_iso = 'CL') WHERE usuarioId IN (1,2,3,4);
UPDATE usuarios SET pais_id = (SELECT paisId FROM paises WHERE codigo_iso = 'MX') WHERE usuarioId = 5;
UPDATE usuarios SET pais_id = (SELECT paisId FROM paises WHERE codigo_iso = 'AR') WHERE usuarioId = 6;

-- ─────────────────────────────────────────────────────────────────────────────
--  Add subject to clases_programadas
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE clases_programadas
    ADD COLUMN IF NOT EXISTS materiaId INT NULL AFTER titulo,
    ADD COLUMN IF NOT EXISTS descripcion TEXT NULL AFTER materiaId,
    ADD COLUMN IF NOT EXISTS precio_base DECIMAL(8,2) NOT NULL DEFAULT 10.00 AFTER descripcion,
    ADD COLUMN IF NOT EXISTS codigo_moneda CHAR(3) NOT NULL DEFAULT 'USD' AFTER precio_base,
    ADD COLUMN IF NOT EXISTS activa TINYINT(1) DEFAULT 1 AFTER codigo_moneda,
    ADD CONSTRAINT IF NOT EXISTS fk_clase_materia
        FOREIGN KEY (materiaId) REFERENCES materias(materiaId) ON DELETE SET NULL;

-- ─────────────────────────────────────────────────────────────────────────────
--  SESSION TRACKING — records each student's join/leave time per class
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sesiones_clase (
    sesionId        INT AUTO_INCREMENT PRIMARY KEY,
    claseId         INT         NOT NULL,
    estudianteId    INT         NOT NULL,
    salaId          INT         NULL,
    inicio          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fin             DATETIME    NULL,
    duracion_min    INT         NULL,            -- computed at disconnect
    precio_usd      DECIMAL(8,2) NULL,           -- agreed price in USD
    monto_local     DECIMAL(14,2) NULL,          -- converted to student's currency
    moneda_local    CHAR(3)     NULL,
    simbolo_local   VARCHAR(5)  NULL,
    pagado          TINYINT(1)  DEFAULT 0,
    FOREIGN KEY (claseId)      REFERENCES clases_programadas(claseId) ON DELETE CASCADE,
    FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId)         ON DELETE CASCADE,
    FOREIGN KEY (salaId)       REFERENCES salas(salaId)               ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  PAYMENTS — one record per finalized session
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pagos (
    pagoId          INT AUTO_INCREMENT PRIMARY KEY,
    sesionId        INT         NOT NULL UNIQUE,
    estudianteId    INT         NOT NULL,
    profesorId      INT         NOT NULL,
    monto_usd       DECIMAL(8,2)  NOT NULL,
    monto_local     DECIMAL(14,2) NOT NULL,
    moneda_local    CHAR(3)       NOT NULL,
    simbolo_local   VARCHAR(5)    NOT NULL DEFAULT '$',
    metodo          ENUM('tarjeta','transferencia','efectivo') DEFAULT 'tarjeta',
    estado          ENUM('pendiente','completado','rechazado')  DEFAULT 'completado',
    created_at      TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesionId)      REFERENCES sesiones_clase(sesionId) ON DELETE CASCADE,
    FOREIGN KEY (estudianteId)  REFERENCES usuarios(usuarioId),
    FOREIGN KEY (profesorId)    REFERENCES usuarios(usuarioId)
) ENGINE=InnoDB;
