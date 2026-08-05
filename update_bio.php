<?php
require 'menu.php';
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php');
    exit;
}

csrf_require();
$uid = (int)$_SESSION['usuarioId'];
$bio = trim((string)($_POST['biografia'] ?? ''));

if (mb_strlen($bio) > 1000) {
    $_SESSION['bio_msg'] = t('profile.bio_too_long');
    header('Location: perfil.php');
    exit;
}

dbExec("UPDATE usuarios SET biografia = :b WHERE usuarioId = :id", ['b' => $bio ?: null, 'id' => $uid]);
$_SESSION['bio_msg'] = t('profile.bio_saved');
header('Location: perfil.php');
exit;
