-- Crear tabla retiros_tokens en la base de datos classexpress
USE classexpress;

CREATE TABLE IF NOT EXISTS retiros_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    cuenta_bancaria VARCHAR(100) NOT NULL,
    nombre_banco VARCHAR(100) NOT NULL,
    estado ENUM('pendiente', 'procesando', 'completado', 'rechazado') DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB;
