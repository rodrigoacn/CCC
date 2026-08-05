<?php
ob_start();
require 'menu.php';
require 'db.php';

$uid = (int)($_SESSION['usuarioId'] ?? 0);
if (!$uid) { header('Location: login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: perfil.php'); exit; }

$file = $_FILES['avatar'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['avatar_msg'] = 'Error al subir el archivo.';
    header('Location: perfil.php');
    exit;
}

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo   = finfo_open(FILEINFO_MIME_TYPE);
$mime    = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    $_SESSION['avatar_msg'] = 'Solo se permiten JPG, PNG, GIF y WEBP.';
    header('Location: perfil.php');
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    $_SESSION['avatar_msg'] = 'El archivo no debe superar 5MB.';
    header('Location: perfil.php');
    exit;
}

$dir = __DIR__ . '/uploads/avatars';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$ext   = match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', default => 'jpg' };
$name  = 'avatar_' . $uid . '_' . time() . '.' . $ext;
$dest  = $dir . '/' . $name;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $_SESSION['avatar_msg'] = 'Error al guardar el archivo.';
    header('Location: perfil.php');
    exit;
}

$path = 'uploads/avatars/' . $name;
dbExec("UPDATE usuarios SET avatar = :path WHERE usuarioId = :id", ['path' => $path, 'id' => $uid]);

$_SESSION['avatar_msg'] = 'Foto actualizada.';
header('Location: perfil.php');
exit;
