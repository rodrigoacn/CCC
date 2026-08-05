<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php';
require_once 'lang.php';
require_once __DIR__ . '/lib/app/web_bootstrap.php';

require_once __DIR__ . '/lib/security_headers.php';

// â”€â”€ Remember-me auto-login â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
ce_remember_autologin();

// â”€â”€ Auth guard â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Every page that includes menu.php requires a logged-in user.
// Public pages (login.php, verify.php, forgot_password.php, reset_password.php)
// do NOT include menu.php, so they are unaffected.
ce_require_login('login.php');

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

// Map current page â†’ materiaId so the UI can adopt the subject's color
$page_materia = [
    'matematicas.php'      => 1,
    'biologia.php'         => 2,
    'quimica.php'          => 3,
    'fisica.php'           => 4,
    'historia.php'         => 5,
    'geografia.php'        => 6,
    'literatura.php'       => 7,
    'idiomas.php'          => 8,
    'arte.php'             => 9,
    'tecnologia.php'       => 10,
    'educacion_fisica.php' => 11,
];
$materiaPagina = $page_materia[$currentPage] ?? 0;

// If the current page receives ?materia=N (e.g. contenido.php), adopt that subject's color too
if ($materiaPagina === 0 && isset($_GET['materia'])) {
    $m = (int)$_GET['materia'];
    if ($m >= 1 && $m <= 11) $materiaPagina = $m;
}

// Non-subject pages (buscar, perfil, personas, sala, etc.) adopt the color of
// the last subject the user opened, so the whole UI is not stuck on the default green.
if ($materiaPagina === 0 && isset($_SESSION['usuarioId'])) {
    $lastMateria = dbOne(
        "SELECT ultimaMateria FROM usuarios WHERE usuarioId = :u",
        ['u' => $_SESSION['usuarioId']]
    );
    $m = (int)($lastMateria['ultimaMateria'] ?? 0);
    if ($m >= 1 && $m <= 11) $materiaPagina = $m;
}

// Remember the last subject opened so it can be resumed next session
if ($materiaPagina > 0 && isset($_SESSION['usuarioId'])) {
    dbExec(
        "UPDATE usuarios SET ultimaMateria = :m WHERE usuarioId = :u",
        ['m' => $materiaPagina, 'u' => $_SESSION['usuarioId']]
    );
}

if (($_SESSION['rol'] === 'estudiante' || $_SESSION['rol'] === 'student') && $currentPage !== 'pago.php') {    $pending = dbOne(
        "SELECT sesionId FROM sesiones_clase
         WHERE estudianteId = :u AND pagado = 0 AND fin IS NOT NULL
         ORDER BY fin ASC LIMIT 1",
        ['u' => $_SESSION['usuarioId']]
    );
    if ($pending) {
        header('Location: pago.php?sesion=' . $pending['sesionId']);
        exit;
    }
}

$resultados = [
    "ultimoContenido" => "",
    "ultimaClase"     => "",
    "ultimaSala"      => "",
    "esVisibleContenidos" => "d-none",
    "esVisibleClases"     => "d-none",
    "esVisibleSala"       => "d-none",
];

// Map materiaId â†’ subject page filename (same as materias.php)
$page_map = [
    1  => 'matematicas.php',
    2  => 'biologia.php',
    3  => 'quimica.php',
    4  => 'fisica.php',
    5  => 'historia.php',
    6  => 'geografia.php',
    7  => 'literatura.php',
    8  => 'idiomas.php',
    9  => 'arte.php',
    10 => 'tecnologia.php',
    11 => 'educacion_fisica.php',
];

// Fetch user's last-visited items + credit balance from DB
if (isset($_SESSION['ultimoContenido']) || isset($_SESSION['ultimaClase']) || isset($_SESSION['ultimaSala'])) {
    $resultados["ultimoContenido"] = $_SESSION['ultimoContenido'] ?? '';
    $resultados["ultimaClase"]     = $_SESSION['ultimaClase'] ?? '';
    $resultados["ultimaSala"]      = $_SESSION['ultimaSala'] ?? '';
    $resultados["esVisibleContenidos"] = ($resultados["ultimoContenido"] != "") ? "visible" : "hidden";
    $resultados["esVisibleClases"]     = ($resultados["ultimaClase"]     != "") ? "visible" : "hidden";
    $resultados["esVisibleSala"]       = ($resultados["ultimaSala"]      != "") ? "visible" : "hidden";
} else {
    $row = null;
    if (function_exists('dbOne')) {
        $row = dbOne(
            "SELECT ultimoContenido, ultimaClase, ultimaSala, creditos
               FROM usuarios WHERE usuarioId = :uid",
            ['uid' => $_SESSION['usuarioId']]
        );
    } else {
        try {
            $_pdo = new PDO(
                "mysql:host=" . (getenv('DB_HOST') ?: 'localhost') .
                ";port="      . (getenv('DB_PORT') ?: '3306') .
                ";dbname="    . (getenv('DB_NAME') ?: 'classexpress') . ";charset=utf8mb4",
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASS') ?: '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $stmt = $_pdo->prepare(
                "SELECT ultimoContenido, ultimaClase, ultimaSala, creditos
                   FROM usuarios WHERE usuarioId = :uid"
            );
            $stmt->execute(['uid' => $_SESSION['usuarioId']]);
            $row = $stmt->fetch() ?: null;
        } catch (PDOException $e) {}
    }
    if ($row) {
        $resultados["ultimoContenido"] = $row["ultimocontenido"] ?? '';
        $resultados["ultimaClase"]     = $row["ultimaclase"]     ?? '';
        $resultados["ultimaSala"]      = $row["ultimasala"]      ?? '';
        $resultados["esVisibleContenidos"] = ($resultados["ultimoContenido"] != "") ? "visible" : "hidden";
        $resultados["esVisibleClases"]     = ($resultados["ultimaClase"]     != "") ? "visible" : "hidden";
        $resultados["esVisibleSala"]       = ($resultados["ultimaSala"]      != "") ? "visible" : "hidden";
        $_SESSION['ultimoContenido'] = $resultados["ultimoContenido"];
        $_SESSION['ultimaClase']     = $resultados["ultimaClase"];
        $_SESSION['ultimaSala']      = $resultados["ultimaSala"];
        // Keep credit balance fresh in session
        if (isset($row['creditos'])) {
            $_SESSION['creditos'] = (int)$row['creditos'];
        }
    }
}

// Convenience variables for navbar
$_navNombre   = htmlspecialchars(explode(' ', trim($_SESSION['nombre'] ?? 'Usuario'))[0]);
$_navCreditos = (int)($_SESSION['creditos'] ?? 0);
$_dbRol       = $_SESSION['rol'] ?? 'estudiante';
$_navRol      = $_dbRol;
// ── Cookie role selector: only for 'both' users switching views ──────────────
if (isset($_COOKIE['ce_app_modo'])) {
    $cookieRol = $_COOKIE['ce_app_modo'];
    // Only allow cookie override if user's DB role is 'both' (instructor+student)
    if (in_array($_dbRol, ['both', 'instructor', 'estudiante'])) {
        if ($cookieRol === 'teacher' && in_array($_dbRol, ['instructor', 'both'])) {
            $_navRol = 'instructor';
        } else if ($cookieRol === 'student') {
            $_navRol = 'estudiante';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= detectLang() ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <meta name="theme-color" content="#eef1f6">
  <title>ClassExpress — Clases Particulares en Línea por Videoconferencia</title>
  <meta name="description" content="ClassExpress: plataforma de clases particulares en tiempo real. Conecta profesores y estudiantes por videoconferencia. Matemáticas, ciencias, idiomas y más. Aprende desde cualquier lugar.">
  <meta name="keywords" content="clases particulares, clases online, tutorías, videoconferencia, profesor particular, aprender en línea, matemáticas, ciencias, idiomas, educación, e-learning, clases en vivo">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://classexpress.online/">
  <meta property="og:type" content="website">
  <meta property="og:title" content="ClassExpress — Clases Particulares en Línea por Videoconferencia">
  <meta property="og:description" content="Plataforma de clases particulares en tiempo real. Conecta profesores y estudiantes por videoconferencia. Aprende desde cualquier lugar.">
  <meta property="og:url" content="https://classexpress.online/">
  <meta property="og:site_name" content="ClassExpress">
  <meta property="og:locale" content="es_CL">
  <meta property="og:image" content="https://classexpress.online/favico.svg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="ClassExpress — Clases Particulares en Línea">
  <meta name="twitter:description" content="Plataforma de clases particulares en tiempo real. Conecta profesores y estudiantes por videoconferencia.">
  <meta name="twitter:image" content="https://classexpress.online/favico.svg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="./styles.css?v=20260805">
  <link rel="icon" href="favico.svg?v=4" type="image/svg+xml">
  <link rel="manifest" href="manifest.json">
  <link rel="apple-touch-icon" href="apple-touch-icon.png">
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <style>
  :root{color-scheme:light;--p:#66ddbd;--pb:#4CBFA3;--bg:#eef1f6;--sf:#ffffff;--fg:#1e293b;--sub:#64748b;--bd:#dbe2ee;--s:#16a34a;--d:#dc2626;--tb:#ffffff;--tbi:#64748b;--r:14px}
  *{box-sizing:border-box}
  body{background:var(--bg);color:var(--fg);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;margin:0;padding:0}
  .ml-wrap{max-width:100%;margin:0 auto;padding:32px 0 100px;min-height:100vh}
  .ml-wrap-inner{max-width:100%;margin:0 auto;padding:0 32px}
  @media(min-width:1800px){.ml-wrap-inner{padding:0 48px}}
  .ml-head{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:24px;padding:0 4px}
  .ml-head-title{font-size:36px;font-weight:700;color:var(--fg);margin-bottom:4px}
  .ml-sub{font-size:18px;color:var(--sub);margin:0 4px 20px}
  .ml-card{border-radius:18px;padding:28px}
  .ml-btn{display:inline-flex;align-items:center;gap:10px;padding:18px 36px;border-radius:16px;border:0;font-weight:700;font-size:18px;cursor:pointer;text-decoration:none;color:inherit}
  .ml-btn-p{background:var(--p);color:#fff}
  .ml-btn-s{background:var(--s);color:#fff}
  .ml-btn-l{background:#fff;color:var(--p);border:1px solid var(--p)}
  .ml-chip{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:24px;background:var(--sf);border:1px solid var(--bd);font-size:15px;font-weight:500;color:var(--sub);cursor:pointer;white-space:nowrap}
  .ml-chip.active{background:var(--p);color:#fff}
  .ml-chip.active-l{background:var(--s);color:#fff}
  .ml-search{display:flex;align-items:center;gap:12px;border-radius:16px;padding:14px 20px;margin:0 4px 16px;background:var(--sf)}
  .ml-search input{flex:1;background:none;border:0;outline:0;color:var(--fg);font-size:17px;font-family:inherit}
  .ml-search input::placeholder{color:var(--tbi)}
  .ml-empty{padding:80px 32px;text-align:center}
  .ml-empty-txt{font-size:18px;color:var(--sub);margin:16px 0 28px;text-align:center}
  .ml-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;padding:0 4px 24px}
  @media(min-width:1000px){.ml-grid{grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px}}
  @media(min-width:1400px){.ml-grid{grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px}}
  .tb{position:fixed;bottom:0;left:0;right:0;z-index:999;background:var(--tb);border-top:1px solid var(--bd);display:flex;justify-content:center;padding:8px 0 env(safe-area-inset-bottom)}
  .tb-inner{display:flex;max-width:100%;width:100%}
  .tb a{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:8px 0;text-decoration:none;gap:3px;color:var(--tbi)!important;font-size:12px;font-weight:500}
  .tb a.active{color:#66ddbd!important}
  .tb a i{width:26px;height:26px}
  .tb a svg{width:26px;height:26px;stroke:var(--tbi)!important}
  .tb a.active svg{stroke:#66ddbd!important}

  </style>
</head>
<body>
  <script>window.CE_ROLE = '<?= htmlspecialchars($_navRol) ?>';</script>
  <?php
  $page = basename($_SERVER['PHP_SELF']);
  $isTeacher = $_navRol !== 'estudiante' && $_navRol !== 'student';
  $tabs = [
    'materias.php' => ['home', t('nav.materias')],
    'buscar.php' => ['search', t('nav.buscar')],
    'mi_sala.php' => ['camera', t('nav.sala')],
    'personas.php' => ['users', t('nav.personas')],
    'creditos.php' => ['credit-card', t('nav.creditos')],
    'retiro.php' => ['dollar-sign', t('retiro.withdraw')],
    'perfil.php' => ['user', t('nav.perfil')],
  ];
  $materiaPages = ['contenido.php','matematicas.php','biologia.php','quimica.php','fisica.php','historia.php','geografia.php','literatura.php','idiomas.php','arte.php','tecnologia.php','educacion_fisica.php'];
  if (in_array($page, $materiaPages)) $page = 'materias.php';
  ?>

<div class="tb"><div class="tb-inner">
  <?php foreach ($tabs as $f => $d):
    if ($f === 'buscar.php') { if ($isTeacher) continue; }
    if ($f === 'retiro.php' && !$isTeacher) continue;
    if ($f === 'creditos.php' && $isTeacher) continue;
  ?>
  <a href="<?= $f ?>" class="<?= $page === $f ? 'active' : '' ?>">
    <i data-feather="<?= $d[0] ?>"></i><span><?= $d[1] ?></span>
  </a>
  <?php endforeach; ?>


</div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){feather.replace()});
window.CE_switchLang=function(code){
  document.cookie='ce_lang='+code+';path=/;max-age=2592000';
  fetch('lang_api.php?lang='+code+'&save=1').then(function(){location.reload()}).catch(function(){location.reload()});
};
</script>

