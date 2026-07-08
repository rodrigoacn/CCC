<?php
require_once 'db.php';

try {
    $db = getDB();
    if (!$db) {
        echo "Error: No se pudo conectar a la base de datos.\n";
        exit;
    }

    // Agregar columnas faltantes a la tabla pagos
    $alterSql = "ALTER TABLE pagos 
        ADD COLUMN IF NOT EXISTS moneda_local VARCHAR(10) DEFAULT 'USD',
        ADD COLUMN IF NOT EXISTS simbolo_local VARCHAR(5) DEFAULT '$',
        ADD COLUMN IF NOT EXISTS monto_local DECIMAL(10, 2) DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS profesorId INT NOT NULL DEFAULT 0 AFTER estudianteId";
    
    $db->exec($alterSql);
    echo "Tabla 'pagos' actualizada exitosamente con columnas faltantes.\n";
    
} catch (PDOException $e) {
    echo "Error al actualizar tabla: " . $e->getMessage() . "\n";
}
