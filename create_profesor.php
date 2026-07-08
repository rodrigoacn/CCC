<?php
require_once 'db.php';

$username = 'rodrigo';
$password = 'v6h470fdz0';
$email = 'rodrigo@classexpress.app';

$existing = dbOne("SELECT usuarioId, username, email, rol, verificado FROM usuarios WHERE username = :username OR email = :email LIMIT 1", ['username' => $username, 'email' => $email]);

if ($existing) {
    echo "Usuario existe: " . $existing['username'] . " (ID: " . $existing['usuarioid'] . ", Rol: " . $existing['rol'] . ", Verificado: " . ($existing['verificado'] ? 'Sí' : 'No') . ")\n";
    
    // Actualizar a profesor y verificar
    $hash = password_hash($password, PASSWORD_DEFAULT);
    dbExec("UPDATE usuarios SET password = :password, rol = 'instructor', verificado = 1 WHERE usuarioId = :id", ['password' => $hash, 'id' => $existing['usuarioid']]);
    echo "Usuario actualizado a profesor y verificado.\n";
} else {
    echo "Usuario no existe. Creando...\n";
    $hash = password_hash($password, PASSWORD_DEFAULT);
    dbExec("INSERT INTO usuarios (nombre, email, password, username, rol, verificado, tokens, creditos, minutos_gratis, pais_id, created_at) VALUES (:nombre, :email, :password, :username, :rol, 1, 0.00, 100.00, 0, NULL, NOW())", ['nombre' => 'Rodrigo', 'email' => $email, 'password' => $hash, 'username' => $username, 'rol' => 'instructor']);
    echo "Profesor creado exitosamente.\n";
}

echo "\nHash generado: " . password_hash($password, PASSWORD_DEFAULT) . "\n";
