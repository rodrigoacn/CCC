-- ═══════════════════════════════════════════════════════════════════════════════
--  ClassExpress — Migration for Tokens and Referrals System
--  Run this after database.sql to add new fields and tables
-- ═══════════════════════════════════════════════════════════════════════════════
USE classexpress_test;

-- ─────────────────────────────────────────────────────────────────────────────
--  1. ADD FIELDS TO usuarios TABLE
-- ─────────────────────────────────────────────────────────────────────────────
-- Check and add columns only if they don't exist
SET @dbname = DATABASE();
SELECT @exists = COUNT(*)
FROM information_schema.columns
WHERE table_schema = @dbname
  AND table_name = 'usuarios'
  AND column_name = 'username';

SET @sql = IF(@exists = 0,
  'ALTER TABLE usuarios ADD COLUMN username VARCHAR(50) DEFAULT ''''',
  'SELECT ''Column username already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = 0;
SELECT @exists = COUNT(*)
FROM information_schema.columns
WHERE table_schema = @dbname
  AND table_name = 'usuarios'
  AND column_name = 'tokens';

SET @sql = IF(@exists = 0,
  'ALTER TABLE usuarios ADD COLUMN tokens DECIMAL(10,2) DEFAULT 0.00',
  'SELECT ''Column tokens already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = 0;
SELECT @exists = COUNT(*)
FROM information_schema.columns
WHERE table_schema = @dbname
  AND table_name = 'usuarios'
  AND column_name = 'minutos_gratis';

SET @sql = IF(@exists = 0,
  'ALTER TABLE usuarios ADD COLUMN minutos_gratis INT DEFAULT 0',
  'SELECT ''Column minutos_gratis already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = 0;
SELECT @exists = COUNT(*)
FROM information_schema.columns
WHERE table_schema = @dbname
  AND table_name = 'usuarios'
  AND column_name = 'referido_por';

SET @sql = IF(@exists = 0,
  'ALTER TABLE usuarios ADD COLUMN referido_por VARCHAR(50) DEFAULT ''''',
  'SELECT ''Column referido_por already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = 0;
SELECT @exists = COUNT(*)
FROM information_schema.columns
WHERE table_schema = @dbname
  AND table_name = 'usuarios'
  AND column_name = 'num_referidos';

SET @sql = IF(@exists = 0,
  'ALTER TABLE usuarios ADD COLUMN num_referidos INT DEFAULT 0',
  'SELECT ''Column num_referidos already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = 0;
SELECT @exists = COUNT(*)
FROM information_schema.columns
WHERE table_schema = @dbname
  AND table_name = 'usuarios'
  AND column_name = 'minutos_espectador_gratis';

SET @sql = IF(@exists = 0,
  'ALTER TABLE usuarios ADD COLUMN minutos_espectador_gratis INT DEFAULT 0',
  'SELECT ''Column minutos_espectador_gratis already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @exists = 0;
SELECT @exists = COUNT(*)
FROM information_schema.columns
WHERE table_schema = @dbname
  AND table_name = 'usuarios'
  AND column_name = 'pais_id';

SET @sql = IF(@exists = 0,
  'ALTER TABLE usuarios ADD COLUMN pais_id INT DEFAULT NULL',
  'SELECT ''Column pais_id already exists''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─────────────────────────────────────────────────────────────────────────────
--  2. CREATE paises TABLE (for currency conversion)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS paises (
    paisId INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo_moneda VARCHAR(3) NOT NULL,
    simbolo VARCHAR(5) NOT NULL,
    tasa_usd DECIMAL(10,4) DEFAULT 1.0000
) ENGINE=InnoDB;

INSERT INTO paises (nombre, codigo_moneda, simbolo, tasa_usd) VALUES
('United States', 'USD', '$', 1.0000),
('Chile', 'CLP', '$', 950.0000),
('Argentina', 'ARS', '$', 850.0000),
('Mexico', 'MXN', '$', 17.0000),
('Colombia', 'COP', '$', 4000.0000),
('Peru', 'PEN', 'S/', 3.7000),
('Spain', 'EUR', '€', 0.9200),
('Brazil', 'BRL', 'R$', 5.0000);

-- ─────────────────────────────────────────────────────────────────────────────
--  3. CREATE referidos TABLE
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS referidos (
    referidoId INT AUTO_INCREMENT PRIMARY KEY,
    referidor_username VARCHAR(50) NOT NULL,
    referido_usuarioId INT NOT NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referido_usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  4. CREATE compras_tokens TABLE
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS compras_tokens (
    compraId INT AUTO_INCREMENT PRIMARY KEY,
    usuarioId INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    monto_usd DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(50) DEFAULT 'stripe',
    estado ENUM('pendiente','completado','fallido') DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  5. CREATE sesiones_clase TABLE (for tracking live sessions)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sesiones_clase (
    sesionId INT AUTO_INCREMENT PRIMARY KEY,
    claseId INT NOT NULL,
    estudianteId INT NOT NULL,
    instructorId INT NOT NULL,
    inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fin TIMESTAMP NULL,
    duracion_minutos INT DEFAULT 0,
    precio_usd DECIMAL(8,2) DEFAULT 0.00,
    monto_local DECIMAL(10,2) DEFAULT 0.00,
    moneda_local VARCHAR(3) DEFAULT 'USD',
    simbolo_local VARCHAR(5) DEFAULT '$',
    pagado TINYINT(1) DEFAULT 0,
    espectador TINYINT(1) DEFAULT 1,
    FOREIGN KEY (claseId) REFERENCES clases_programadas(claseId) ON DELETE CASCADE,
    FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE,
    FOREIGN KEY (instructorId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  6. CREATE pagos TABLE (for recording payments)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pagos (
    pagoId INT AUTO_INCREMENT PRIMARY KEY,
    sesionId INT NOT NULL,
    estudianteId INT NOT NULL,
    profesorId INT NOT NULL,
    monto_usd DECIMAL(8,2) NOT NULL,
    monto_local DECIMAL(10,2) NOT NULL,
    moneda_local VARCHAR(3) DEFAULT 'USD',
    simbolo_local VARCHAR(5) DEFAULT '$',
    metodo VARCHAR(50) DEFAULT 'tokens',
    estado ENUM('pendiente','completado','fallido') DEFAULT 'pendiente',
    comision_rodrigo DECIMAL(8,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesionId) REFERENCES sesiones_clase(sesionId) ON DELETE CASCADE,
    FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE,
    FOREIGN KEY (profesorId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  7. CREATE espectadores TABLE (for spectator management)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS espectadores (
    espectadorId INT AUTO_INCREMENT PRIMARY KEY,
    salaId INT NOT NULL,
    usuarioId INT NOT NULL,
    estado ENUM('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
    inicio_spectador TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    profesor_aprobo INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (salaId) REFERENCES salas(salaId) ON DELETE CASCADE,
    FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE,
    FOREIGN KEY (profesor_aprobo) REFERENCES usuarios(usuarioId) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  7. ADD FIELDS TO clases_programadas TABLE
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE clases_programadas
ADD COLUMN materiaId INT DEFAULT NULL,
ADD COLUMN precio_base DECIMAL(8,2) DEFAULT 0.00,
ADD COLUMN duracion_min INT DEFAULT 60,
ADD FOREIGN KEY (materiaId) REFERENCES materias(materiaId) ON DELETE SET NULL;

-- ─────────────────────────────────────────────────────────────────────────────
--  8. ADD salaId FIELD TO clases_programadas IF NOT EXISTS
-- ─────────────────────────────────────────────────────────────────────────────
-- Note: salaId already exists in the original schema, so we skip this

-- ─────────────────────────────────────────────────────────────────────────────
--  9. UPDATE EXISTING USERS WITH DEFAULT VALUES
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE usuarios SET username = CONCAT('user', usuarioId) WHERE username = '';
UPDATE usuarios SET pais_id = 1 WHERE pais_id IS NULL;
