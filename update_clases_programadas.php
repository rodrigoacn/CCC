<?php
require_once 'db.php';

try {
    $db = getDB();
    if (!$db) {
        echo "Error: No se pudo conectar a la base de datos.\n";
        exit;
    }

    // Agregar columnas faltantes a la tabla clases_programadas
    $alterSql = "ALTER TABLE clases_programadas 
        ADD COLUMN IF NOT EXISTS precio_min DECIMAL(10, 2) DEFAULT 0.00 AFTER precio_base,
        ADD COLUMN IF NOT EXISTS precio_max DECIMAL(10, 2) DEFAULT 0.00 AFTER precio_min";
    
    $db->exec($alterSql);
    echo "Tabla 'clases_programadas' actualizada exitosamente con columnas precio_min y precio_max.\n";
    
} catch (PDOException $e) {
    echo "Error al actualizar tabla: " . $e->getMessage() . "\n";
}
