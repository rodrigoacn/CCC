<?php
require_once 'db.php';

try {
    $db = getDB();
    if (!$db) {
        echo "Error: No se pudo conectar a la base de datos.\n";
        exit;
    }

    // Verificar tablas necesarias
    $tables = ['usuarios', 'materias', 'clases_programadas', 'sesiones_clase', 'pagos'];
    
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() > 0) {
            echo "✓ Tabla '$table' existe\n";
        } else {
            echo "✗ Tabla '$table' NO existe\n";
        }
    }

    // Verificar columnas en clases_programadas
    echo "\nColumnas en clases_programadas:\n";
    $columns = $db->query("SHOW COLUMNS FROM clases_programadas");
    while ($row = $columns->fetch()) {
        echo "  - " . $row['Field'] . "\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
