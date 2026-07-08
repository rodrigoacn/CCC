<?php
require_once 'db.php';

$email = 'rconejerosnavea@gmail.com';
$username = 'rodrigo';

$byEmail = dbOne("SELECT usuarioId, username, email, rol, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);
$byUsername = dbOne("SELECT usuarioId, username, email, rol, verificado FROM usuarios WHERE username = :username LIMIT 1", ['username' => $username]);

echo "=== Buscando por email ($email) ===\n";
if ($byEmail) {
    echo "Encontrado: ID=" . $byEmail['usuarioid'] . ", Username=" . $byEmail['username'] . ", Rol=" . $byEmail['rol'] . ", Verificado=" . ($byEmail['verificado'] ? 'Sí' : 'No') . "\n";
} else {
    echo "No encontrado\n";
}

echo "\n=== Buscando por username ($username) ===\n";
if ($byUsername) {
    echo "Encontrado: ID=" . $byUsername['usuarioid'] . ", Email=" . $byUsername['email'] . ", Rol=" . $byUsername['rol'] . ", Verificado=" . ($byUsername['verificado'] ? 'Sí' : 'No') . "\n";
} else {
    echo "No encontrado\n";
}

// Si existe por username pero no por email, actualizar
if ($byUsername && !$byEmail) {
    echo "\n=== Actualizando email ===\n";
    dbExec("UPDATE usuarios SET email = :email WHERE usuarioId = :id", ['email' => $email, 'id' => $byUsername['usuarioid']]);
    echo "Email actualizado a: " . $email . "\n";
}

// Asegurar que el usuario esté verificado
$finalUser = dbOne("SELECT usuarioId, username, email, rol, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);
if ($finalUser && !$finalUser['verificado']) {
    echo "\n=== Marcando cuenta como verificada ===\n";
    dbExec("UPDATE usuarios SET verificado = 1 WHERE usuarioId = :id", ['id' => $finalUser['usuarioid']]);
    echo "Cuenta verificada exitosamente\n";
} elseif ($finalUser && $finalUser['verificado']) {
    echo "\n=== Cuenta ya está verificada ===\n";
}
