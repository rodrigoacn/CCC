<?php
require_once __DIR__ . '/lib/app/web_bootstrap.php';
require_once 'db.php';
ce_start_session();
require_once 'lang.php';
require_once __DIR__ . '/lib/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php');
    exit;
}

ce_require_login('login.php');

csrf_require();

$password = $_POST['password'] ?? '';

if (!$password) {
    $_SESSION['error_delete'] = t('delete.password_required');
    header('Location: perfil.php');
    exit;
}

$user = dbOne("SELECT password FROM usuarios WHERE usuarioid = ? AND eliminado = 0", [ce_uid()]);

if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['error_delete'] = t('delete.wrong_password');
    header('Location: perfil.php');
    exit;
}

// Marcar la cuenta como eliminada (soft delete) e invalidar sesiones persistentes
dbExec("UPDATE usuarios SET eliminado = 1, remember_token = NULL, token_verificacion = '' WHERE usuarioid = ?", [$_SESSION['usuarioId']]);

// Limpiar cookie de recordar sesión
setcookie('ce_remember', '', time() - 3600, '/', '', !empty($_SERVER['HTTPS']), true);

// Destruir sesión
session_unset();
session_destroy();

// Redirigir a login con mensaje
header('Location: login.php?deleted=1');
exit;
