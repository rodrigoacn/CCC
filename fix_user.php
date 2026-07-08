<?php
require_once 'db.php';

$email = 'rconejerosnavea@gmail.com';
$username = 'rodrigo';
$password = 'v6h470fdz0';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Verificar si existe por email
$byEmail = dbOne("SELECT usuarioId FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);

// Verificar si existe por username
$byUsername = dbOne("SELECT usuarioId FROM usuarios WHERE username = :username LIMIT 1", ['username' => $username]);

if ($byEmail) {
    // Actualizar usuario existente por email
    dbExec("UPDATE usuarios SET username = :username, password = :password, verificado = 1, rol = 'instructor' WHERE email = :email", 
           ['username' => $username, 'password' => $hash, 'email' => $email]);
    echo "Usuario actualizado (por email) exitosamente\n";
} elseif ($byUsername) {
    // Actualizar usuario existente por username (cambiar email)
    dbExec("UPDATE usuarios SET email = :email, password = :password, verificado = 1, rol = 'instructor' WHERE username = :username", 
           ['email' => $email, 'password' => $hash, 'username' => $username]);
    echo "Usuario actualizado (por username) exitosamente\n";
} else {
    // Crear nuevo usuario
    dbExec("INSERT INTO usuarios (nombre, email, password, username, rol, verificado, tokens, creditos, minutos_gratis, pais_id, created_at) 
           VALUES (:nombre, :email, :password, :username, :rol, 1, 0.00, 100.00, 0, NULL, NOW())", 
           ['nombre' => 'Rodrigo', 'email' => $email, 'password' => $hash, 'username' => $username, 'rol' => 'instructor']);
    echo "Usuario creado exitosamente\n";
}

echo "Email: " . $email . "\n";
echo "Username: " . $username . "\n";
echo "Contraseña: " . $password . "\n";
echo "Verificado: Sí\n";
echo "Rol: instructor\n";
