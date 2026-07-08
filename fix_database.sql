-- ═══════════════════════════════════════════════════════════════════════════════
--  ClassExpress — Database Fix Script
--  Agrega tablas y columnas faltantes para el sistema completo
-- ═══════════════════════════════════════════════════════════════════════════════

USE classexpress;

-- ─────────────────────────────────────────────────────────────────────────────
--  1. Agregar columnas faltantes a usuarios
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE usuarios 
    ADD COLUMN IF NOT EXISTS username VARCHAR(100) UNIQUE AFTER email,
    ADD COLUMN IF NOT EXISTS tokens DECIMAL(10, 2) DEFAULT 0.00 AFTER verificado,
    ADD COLUMN IF NOT EXISTS creditos DECIMAL(10, 2) DEFAULT 0.00 AFTER tokens,
    ADD COLUMN IF NOT EXISTS minutos_gratis INT DEFAULT 0 AFTER creditos,
    ADD COLUMN IF NOT EXISTS pais_id INT NULL AFTER minutos_gratis,
    ADD COLUMN IF NOT EXISTS referido_por VARCHAR(100) DEFAULT '' AFTER pais_id;

-- ─────────────────────────────────────────────────────────────────────────────
--  2. Agregar columnas faltantes a clases_programadas
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE clases_programadas 
    ADD COLUMN IF NOT EXISTS descripcion TEXT AFTER titulo,
    ADD COLUMN IF NOT EXISTS precio_base DECIMAL(10, 2) DEFAULT 0.00 AFTER titulo,
    ADD COLUMN IF NOT EXISTS precio_min DECIMAL(10, 2) DEFAULT 0.00 AFTER precio_base,
    ADD COLUMN IF NOT EXISTS precio_max DECIMAL(10, 2) DEFAULT 0.00 AFTER precio_min,
    ADD COLUMN IF NOT EXISTS codigo_moneda VARCHAR(10) DEFAULT 'USD' AFTER precio_max,
    ADD COLUMN IF NOT EXISTS materiaId INT AFTER codigo_moneda,
    ADD COLUMN IF NOT EXISTS activa TINYINT(1) DEFAULT 1 AFTER alumnos_max;

-- Agregar foreign key para materiaId (si no existe)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.table_constraints 
                  WHERE constraint_schema = 'classexpress' 
                  AND table_name = 'clases_programadas' 
                  AND constraint_name = 'fk_clases_programadas_materiaId');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE clases_programadas ADD FOREIGN KEY (materiaId) REFERENCES materias(materiaId) ON DELETE SET NULL',
    'SELECT "Foreign key already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ─────────────────────────────────────────────────────────────────────────────
--  3. Crear tabla pagos
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pagos (
    pagoId INT AUTO_INCREMENT PRIMARY KEY,
    sesionId INT NOT NULL,
    estudianteId INT NOT NULL,
    profesorId INT NOT NULL DEFAULT 0,
    monto_usd DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    moneda_local VARCHAR(10) DEFAULT 'USD',
    simbolo_local VARCHAR(5) DEFAULT '$',
    monto_local DECIMAL(10, 2) DEFAULT 0.00,
    estado ENUM('pendiente', 'completado', 'fallido') NOT NULL DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sesionId) REFERENCES sesiones_clase(sesionId) ON DELETE CASCADE,
    FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
--  4. Crear tabla sesiones_clase
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sesiones_clase (
    sesionId INT AUTO_INCREMENT PRIMARY KEY,
    claseId INT NOT NULL,
    estudianteId INT NOT NULL,
    inicio TIMESTAMP NULL,
    fin TIMESTAMP NULL,
    duracion_min INT DEFAULT 0,
    pagado TINYINT(1) DEFAULT 0,
    monto_local DECIMAL(10, 2) DEFAULT 0.00,
    moneda_local VARCHAR(10) DEFAULT 'USD',
    simbolo_local VARCHAR(5) DEFAULT '$',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (claseId) REFERENCES clases_programadas(claseId) ON DELETE CASCADE,
    FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar columnas faltantes a sesiones_clase si ya existe
ALTER TABLE sesiones_clase 
    ADD COLUMN IF NOT EXISTS pagado TINYINT(1) DEFAULT 0 AFTER duracion_min,
    ADD COLUMN IF NOT EXISTS monto_local DECIMAL(10, 2) DEFAULT 0.00 AFTER pagado,
    ADD COLUMN IF NOT EXISTS moneda_local VARCHAR(10) DEFAULT 'USD' AFTER monto_local,
    ADD COLUMN IF NOT EXISTS simbolo_local VARCHAR(5) DEFAULT '$' AFTER moneda_local;

-- ─────────────────────────────────────────────────────────────────────────────
--  5. Crear tabla salas
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS salas (
    salaId INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    curso VARCHAR(255) DEFAULT '',
    instructorId INT,
    activa TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructorId) REFERENCES usuarios(usuarioId) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
--  6. Crear tabla participantes_sala
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS participantes_sala (
    id INT AUTO_INCREMENT PRIMARY KEY,
    salaId INT NOT NULL,
    usuarioId INT NOT NULL,
    camara_activa TINYINT(1) DEFAULT 0,
    microfono_activo TINYINT(1) DEFAULT 0,
    mano_levantada TINYINT(1) DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sala_usuario (salaId, usuarioId),
    FOREIGN KEY (salaId) REFERENCES salas(salaId) ON DELETE CASCADE,
    FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
--  7. Crear tabla paises
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS пaises (
    pais_id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    codigo_iso VARCHAR(3) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO пaises (nombre, codigo_iso) VALUES
    ('Argentina', 'ARG'),
    ('Bolivia', 'BOL'),
    ('Chile', 'CHL'),
    ('Colombia', 'COL'),
    ('Costa Rica', 'CRI'),
    ('Cuba', 'CUB'),
    ('Ecuador', 'ECU'),
    ('El Salvador', 'SLV'),
    ('España', 'ESP'),
    ('Estados Unidos', 'USA'),
    ('Guatemala', 'GTM'),
    ('Honduras', 'HND'),
    ('México', 'MEX'),
    ('Nicaragua', 'NIC'),
    ('Panamá', 'PAN'),
    ('Paraguay', 'PRY'),
    ('Perú', 'PER'),
    ('Puerto Rico', 'PRI'),
    ('República Dominicana', 'DOM'),
    ('Uruguay', 'URY'),
    ('Venezuela', 'VEN');

-- ─────────────────────────────────────────────────────────────────────────────
--  8. Crear tabla retiros_tokens
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS retiros_tokens (
    retiroId INT AUTO_INCREMENT PRIMARY KEY,
    usuarioId INT NOT NULL,
    monto DECIMAL(10, 2) NOT NULL,
    metodo_pago VARCHAR(50) NOT NULL,
    cuenta_destino VARCHAR(255) NOT NULL,
    estado ENUM('pendiente', 'completado', 'rechazado') DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuarioId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
--  9. Actualizar usuarios para agregar username único a usuarios existentes
-- ─────────────────────────────────────────────────────────────────────────────
UPDATE usuarios SET username = CONCAT('user', usuarioId) WHERE username IS NULL OR username = '';
