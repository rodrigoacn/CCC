<?php
require_once 'db.php';

try {
    $db = getDB();
    if (!$db) {
        echo "Error: No se pudo conectar a la base de datos.\n";
        exit;
    }

    // Leer el archivo SQL
    $sqlFile = __DIR__ . '/fix_simple.sql';
    if (!file_exists($sqlFile)) {
        echo "Error: No se encontró el archivo fix_simple.sql\n";
        exit;
    }

    $sql = file_get_contents($sqlFile);
    
    // Ejecutar el SQL
    $db->exec($sql);
    
    echo "✓ Base de datos actualizada exitosamente.\n";
    echo "✓ Tablas y columnas faltantes creadas.\n";
    
} catch (PDOException $e) {
    echo "Error al actualizar base de datos: " . $e->getMessage() . "\n";
}
