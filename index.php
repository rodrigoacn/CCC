<?php
session_start();
if (!isset($_SESSION['usuarioId'])) {
    header('Location: login.php');
    exit;
}
$rol = $_SESSION['rol'] ?? 'estudiante';
header('Location: ' . ($rol !== 'estudiante' && $rol !== 'student' ? 'dashboard_profesor.php' : 'materias.php'));
exit;
