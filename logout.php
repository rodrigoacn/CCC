<?php
require_once __DIR__ . '/lib/app/web_bootstrap.php';
require_once 'db.php';
ce_start_session();
// Clear remember-me token from DB
$uid = ce_uid();
if ($uid) {
    dbExec("UPDATE usuarios SET remember_token = NULL WHERE usuarioId = :id", ['id'=>$uid]);
}
session_unset();
session_destroy();
setcookie('ce_remember', '', time() - 3600, '/', '', !empty($_SERVER['HTTPS']), true);
header('Location: login.php');
exit;
