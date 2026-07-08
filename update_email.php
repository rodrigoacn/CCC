<?php
require_once 'db.php';

$username = 'rodrigo';
$newEmail = 'rconejerosnavea@gmail.com';

$existing = dbOne("SELECT usuarioId, username, email FROM usuarios WHERE username = :username LIMIT 1", ['username' => $username]);

if ($existing) {
    dbExec("UPDATE usuarios SET email = :email WHERE usuarioId = :id", ['email' => $newEmail, 'id' => $existing['usuarioid']]);
    echo "Email actualizado exitosamente para usuario: " . $existing['username'] . "\n";
    echo "Nuevo email: " . $newEmail . "\n";
} else {
    echo "Usuario no encontrado: " . $username . "\n";
}
