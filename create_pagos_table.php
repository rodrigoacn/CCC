<?php
require_once 'db.php';

// Crear tabla pagos
$sql = "CREATE TABLE IF NOT EXISTS pagos (
    pagoId INT AUTO_INCREMENT PRIMARY KEY,
    sesionId INT NOT NULL,
    estudianteId INT NOT NULL,
    monto_usd DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    estado ENUM('pendiente', 'completado', 'fallido') NOT NULL DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sesionId) REFERENCES sesiones_clase(sesionId) ON DELETE CASCADE,
    FOREIGN KEY (estudianteId) REFERENCES usuarios(usuarioId) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $db = getDB();
    if ($db) {
        $db->exec($sql);
        echo "Tabla 'pagos' creada exitosamente.\n";
    } else {
        echo "Error: No se pudo conectar a la base de datos.\n";
    }
} catch (PDOException $e) {
    echo "Error al crear tabla: " . $e->getMessage() . "\n";
}
