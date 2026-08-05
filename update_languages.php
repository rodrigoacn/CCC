<?php
require 'menu.php';
require 'db.php';

$uid = (int)$_SESSION['usuarioId'];
$idiomas = $_POST['idiomas'] ?? [];
if (!is_array($idiomas)) $idiomas = [];

getDB()->prepare("DELETE FROM usuario_idiomas WHERE usuarioId = ?")->execute([$uid]);
if (!empty($idiomas)) {
    $stmt = getDB()->prepare("INSERT IGNORE INTO usuario_idiomas (usuarioId, idiomaId) VALUES (?, ?)");
    foreach ($idiomas as $iid) {
        $stmt->execute([$uid, (int)$iid]);
    }
}

header('Location: perfil.php');
exit;
