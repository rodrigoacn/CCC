<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/lib/app/web_bootstrap.php';

// Auto-login via remember-me so returning users skip the login form
ce_remember_autologin();

// Not logged in → login page
if (!isset($_SESSION['usuarioId'])) {
    header('Location: login.php');
    exit;
}

$rol = $_SESSION['rol'] ?? 'estudiante';
header('Location: ' . ($rol !== 'estudiante' && $rol !== 'student' ? 'dashboard_profesor.php' : 'materias.php'));
exit;
