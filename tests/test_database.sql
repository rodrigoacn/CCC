-- ═══════════════════════════════════════════════════════════════════════════════
--  ClassExpress — Test Database Setup
--  This file creates a fresh test database with all required tables and data
-- ═══════════════════════════════════════════════════════════════════════════════

DROP DATABASE IF EXISTS classexpress_test;
CREATE DATABASE classexpress_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE classexpress_test;

-- ─────────────────────────────────────────────────────────────────────────────
--  1. USERS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE usuarios (
    usuarioId INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    verificado TINYINT(1) DEFAULT 0,
    telefono VARCHAR(20),
    sitio_web VARCHAR(255),
    biografia TEXT,
    privacidad ENUM('visible', 'private') DEFAULT 'private',
    rol ENUM('student', 'instructor', 'assistant', 'director') DEFAULT 'student',
    calificacion DECIMAL(3,2) DEFAULT 0.00,
    num_resenas INT DEFAULT 0,
    avatar VARCHAR(255),
    ultimoContenido VARCHAR(50),
    ultimaClase VARCHAR(50),
    ultimaSala VARCHAR(50),
    username VARCHAR(50) UNIQUE DEFAULT '',
    tokens DECIMAL(10,2) DEFAULT 0.00,
    minutos_gratis INT DEFAULT 0,
    referido_por VARCHAR(50) DEFAULT '',
    num_referidos INT DEFAULT 0,
    minutos_espectador_gratis INT DEFAULT 0,
    pais_id INT DEFAULT NULL,
    creditos DECIMAL(10,2) DEFAULT 0.00
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  2. PAISES
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE paises (
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
('Spain', 'EUR', '€', 0.9200),
('Colombia', 'COP', '$', 4100.0000);

-- ─────────────────────────────────────────────────────────────────────────────
--  3. MATERIAS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE materias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    icono VARCHAR(50),
    color VARCHAR(7),
    clases_activas INT DEFAULT 0
) ENGINE=InnoDB;

INSERT INTO materias (nombre, icono, color) VALUES
('Matemáticas', 'calculator', '#EF4444'),
('Historia', 'book-open', '#F59E0B'),
('Física', 'cpu', '#3B82F6'),
('Química', 'zap', '#10B981');

-- ─────────────────────────────────────────────────────────────────────────────
--  4. CLASES PROGRAMADAS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE clases_programadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(200) NOT NULL,
    materia_id INT,
    profesor_id INT,
    precio DECIMAL(10,2) NOT NULL,
    descripcion TEXT,
    duracion_minutos INT,
    activa TINYINT(1) DEFAULT 1,
    rating DECIMAL(3,2) DEFAULT 0.00,
    FOREIGN KEY (materia_id) REFERENCES materias(id),
    FOREIGN KEY (profesor_id) REFERENCES usuarios(usuarioId)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  5. SALAS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE salas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clase_id INT,
    activa TINYINT(1) DEFAULT 1,
    FOREIGN KEY (clase_id) REFERENCES clases_programadas(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  6. PARTICIPANTES SALA
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE participantes_sala (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NOT NULL,
    usuario_id INT NOT NULL,
    rol ENUM('instructor', 'estudiante') DEFAULT 'estudiante',
    activo TINYINT(1) DEFAULT 1,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (sala_id, usuario_id),
    FOREIGN KEY (sala_id) REFERENCES salas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioId)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  7. ESPECTADORES
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE espectadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NOT NULL,
    usuario_id INT NOT NULL,
    estado ENUM('pendiente', 'aprobado', 'rechazado') DEFAULT 'pendiente',
    solicitud_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (sala_id, usuario_id),
    FOREIGN KEY (sala_id) REFERENCES salas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioId)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  8. PAGOS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    descripcion VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioId)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  9. SESIONES CLASE
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE sesiones_clase (
    id INT AUTO_INCREMENT PRIMARY KEY,
    claseId INT,
    estudianteId INT,
    instructorId INT,
    precio_usd DECIMAL(10,2),
    pagado TINYINT(1) DEFAULT 0,
    inicio TIMESTAMP NULL,
    fin TIMESTAMP NULL,
    duracion INT,
    FOREIGN KEY (claseId) REFERENCES clases_programadas(id),
    FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId),
    FOREIGN KEY (instructorId) REFERENCES usuarios(usuarioId)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  10. REFERIDOS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE referidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referidor_id INT NOT NULL,
    referido_id INT NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (referido_id),
    FOREIGN KEY (referidor_id) REFERENCES usuarios(usuarioId),
    FOREIGN KEY (referido_id) REFERENCES usuarios(usuarioId)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  11. MOBILE TOKENS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE mobile_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 30 DAY),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  12. WEBCRTC SIGNALS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE webrtc_signals (
    signalid INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NOT NULL,
    from_uid INT NOT NULL,
    to_uid INT,
    tipo VARCHAR(20) NOT NULL,
    payload TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sala_id) REFERENCES salas(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  13. MENSAJES CHAT
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE mensajes_chat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sala_id INT NOT NULL,
    usuario_id INT NOT NULL,
    mensaje TEXT NOT NULL,
    enviado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sala_id) REFERENCES salas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioId)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  14. COMPRAS TOKENS
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE compras_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    monto_usd DECIMAL(10,2) NOT NULL,
    metodo_pago VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioId)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────────────────────
--  15. TEST DATA
-- ─────────────────────────────────────────────────────────────────────────────
-- Insert test users
INSERT INTO usuarios (usuarioId, nombre, email, password, verificado, rol, creditos, username, num_referidos, minutos_espectador_gratis) VALUES
(1, 'Rodrigo Conejeros', 'rodrigo@test.com', '$2y$10$xO0OYVm5BXkNQl/pUJmqbOgFcRN5sOEZqQpqtxU8VZ5tblZlmiBDe', 1, 'director', 1000.00, 'rodrigo', 0, 0),
(2, 'Profesor Test', 'profesor@test.com', '$2y$10$sTR39Z5Np2D8HHwY07w6B.hguF3TKnMrYGgvnvw.RscGak6YjxuPK', 1, 'instructor', 500.00, 'profesor', 3, 0),
(3, 'Estudiante Test', 'estudiante@test.com', '$2y$10$Ex7uUpnIZqUUy9v3rTmwYubHC1Z6kl3fSMPgkIYW6RrNcdM1ilPsS', 1, 'student', 100.00, 'estudiante', 0, 0);

-- Insert test class
INSERT INTO clases_programadas (titulo, materia_id, profesor_id, precio, descripcion, duracion_minutos, activa) VALUES
('Clase de Test', 1, 2, 50.00, 'Clase de prueba', 60, 1);
