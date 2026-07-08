<?php
require_once 'db.php';

$email = 'rconejerosnavea@gmail.com';
$password = 'v6h470fdz0';

$user = dbOne("SELECT usuarioId, nombre, username, email, password, rol, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);

if (!$user) {
    echo "ERROR: Usuario no encontrado con email: " . $email . "\n";
    echo "Verificando por username...\n";
    
    $byUsername = dbOne("SELECT usuarioId, nombre, username, email, password, rol, verificado FROM usuarios WHERE username = 'rodrigo' LIMIT 1");
    if ($byUsername) {
        echo "Usuario encontrado por username:\n";
        echo "  ID: " . $byUsername['usuarioid'] . "\n";
        echo "  Email actual: " . $byUsername['email'] . "\n";
        echo "  Username: " . $byUsername['username'] . "\n";
        echo "  Rol: " . $byUsername['rol'] . "\n";
        echo "  Verificado: " . ($byUsername['verificado'] ? 'Sí' : 'No') . "\n";
    } else {
        echo "Usuario no encontrado por username tampoco\n";
    }
    exit;
}

echo "Usuario encontrado:\n";
echo "  ID: " . $user['usuarioid'] . "\n";
echo "  Nombre: " . $user['nombre'] . "\n";
echo "  Username: " . $user['username'] . "\n";
echo "  Email: " . $user['email'] . "\n";
echo "  Rol: " . $user['rol'] . "\n";
echo "  Verificado: " . ($user['verificado'] ? 'Sí' : 'No') . "\n";
echo "  Password hash: " . $user['password'] . "\n";

echo "\nVerificando contraseña...\n";
if (password_verify($password, $user['password'])) {
    echo "✓ Contraseña CORRECTA\n";
} else {
    echo "✗ Contraseña INCORRECTA\n";
    echo "Generando nuevo hash para '" . $password . "':\n";
    echo password_hash($password, PASSWORD_DEFAULT) . "\n";
}
