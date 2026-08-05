<?php
// ─────────────────────────────────────────────────────────────────────────────
//  lang_api.php — Returns all translations for a language as JSON
//  Used by the dynamic language switcher on web
// ─────────────────────────────────────────────────────────────────────────────
$_CE_LANG_API = true;
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lang.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$lang = $_GET['lang'] ?? $_POST['lang'] ?? '';
if (!$lang || !in_array($lang, array_column($LANGUAGES, 'code'))) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid language code']);
    exit;
}

// Save preference if AJAX request wants it
if (isset($_GET['save']) || isset($_POST['save'])) {
    setLang($lang);
}

echo json_encode([
    'ok' => true,
    'lang' => $lang,
    'translations' => $TRANS[$lang] ?? [],
], JSON_UNESCAPED_UNICODE);
