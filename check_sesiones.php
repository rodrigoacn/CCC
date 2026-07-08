<?php
require_once 'db.php';

try {
    $db = getDB();
    if (!$db) {
        echo "Error: No se pudo conectar a la base de datos.\n";
        exit;
    }

    echo "Columnas en sesiones_clase:\n";
    $columns = $db->query("SHOW COLUMNS FROM sesiones_clase");
    while ($row = $columns->fetch()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
