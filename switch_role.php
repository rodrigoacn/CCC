<?php
require_once __DIR__ . '/lib/app/web_bootstrap.php';
require_once 'db.php';
ce_start_session();
require_once 'lang.php';
require_once __DIR__ . '/lib/csrf.php';

require_once __DIR__ . '/lib/security_headers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php');
    exit;
}

ce_require_login('login.php');

$uid = ce_uid();

if (!csrf_validate()) {
    $_SESSION['error_switch'] = t('profile.switch_error');
    header('Location: perfil.php');
    exit;
}

$password = $_POST['password'] ?? '';
if (!$password) {
    $_SESSION['error_switch'] = t('profile.switch_wrong_password');
    header('Location: perfil.php');
    exit;
}

$user = dbOne("SELECT password, rol, last_role_switch FROM usuarios WHERE usuarioId = ?", [$uid]);
if (!$user) {
    header('Location: login.php');
    exit;
}

if (!password_verify($password, $user['password'])) {
    $_SESSION['error_switch'] = t('profile.switch_wrong_password');
    header('Location: perfil.php');
    exit;
}

if (!in_array($user['rol'], ['both', 'instructor', 'instructor_pendiente'])) {
    $_SESSION['error_switch'] = t('profile.switch_error');
    header('Location: perfil.php');
    exit;
}

if ($user['last_role_switch']) {
    $lastSwitch = strtotime($user['last_role_switch']);
    $now = time();
    $hoursSince = floor(($now - $lastSwitch) / 3600);
    if ($hoursSince < 24) {
        $remaining = 24 - $hoursSince;
        $_SESSION['error_switch'] = t('profile.switch_locked', ['days' => 1]);
        header('Location: perfil.php');
        exit;
    }
}

$targetRole = $_POST['target_role'] ?? '';
if ($targetRole === 'teacher') {
    $newCookie = 'teacher';
} elseif ($targetRole === 'student') {
    $newCookie = 'student';
} else {
    $_SESSION['error_switch'] = t('profile.switch_error');
    header('Location: perfil.php');
    exit;
}

dbExec("UPDATE usuarios SET last_role_switch = NOW() WHERE usuarioId = ?", [$uid]);
setcookie('ce_app_modo', $newCookie, [
    'expires'  => time() + (365 * 24 * 3600),
    'path'     => '/',
    'httponly'  => false,
    'samesite' => 'Lax',
]);

$_SESSION['switch_success'] = t('profile.switch_success');
header('Location: perfil.php');
exit;
