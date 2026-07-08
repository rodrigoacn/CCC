<?php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php');
    exit;
}

if (!isset($_SESSION['usuarioId'])) {
    header('Location: login.php');
    exit;
}

$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm'] ?? '';

if (!$password) {
    $_SESSION['error_delete'] = 'Debes ingresar tu contraseña.';
    header('Location: perfil.php');
    exit;
}

if ($password !== $confirm) {
    $_SESSION['error_delete'] = 'Las contraseñas no coinciden.';
    header('Location: perfil.php');
    exit;
}

$user = dbOne("SELECT password FROM usuarios WHERE usuarioid = ?", [$_SESSION['usuarioId']]);

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['error_delete'] = 'Contraseña incorrecta.';
    header('Location: perfil.php');
    exit;
}

// Eliminar la cuenta
dbExec("DELETE FROM usuarios WHERE usuarioid = ?", [$_SESSION['usuarioId']]);

// Destruir sesión
session_unset();
session_destroy();

// Redirigir a login con mensaje
header('Location: login.php?deleted=1');
exit;
