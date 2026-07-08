<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php';

// ── Auth guard ────────────────────────────────────────────────────────────────
// Every page that includes menu.php requires a logged-in user.
// Public pages (login.php, verify.php, forgot_password.php, reset_password.php)
// do NOT include menu.php, so they are unaffected.
if (!isset($_SESSION['usuarioId'])) {
    header('Location: login.php');
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
if (($_SESSION['rol'] === 'estudiante' || $_SESSION['rol'] === 'student') && $currentPage !== 'pago.php') {
    $pending = dbOne(
        "SELECT sesionId FROM sesiones_clase
         WHERE estudianteId = :u AND pagado = 0 AND fin IS NOT NULL
         ORDER BY fin ASC LIMIT 1",
        ['u' => $_SESSION['usuarioId']]
    );
    if ($pending) {
        header('Location: pago.php?sesion=' . $pending['sesionid']);
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

// Map materiaId → subject page filename (same as materias.php)
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

// Fetch the latest chat notification data
$latestMessages = [];
$notificationCount = 0;
if (function_exists('dbAll') && function_exists('dbOne')) {
    $latestMessages = dbAll(
        "SELECT m.mensaje, m.enviado_at, u.nombre AS usuario
         FROM mensajes_chat m
         LEFT JOIN usuarios u ON u.usuarioid = m.usuarioid
         ORDER BY m.enviado_at DESC
         LIMIT 3"
    );
    $countRow = dbOne(
        "SELECT COUNT(*) AS cnt FROM mensajes_chat WHERE enviado_at >= NOW() - INTERVAL 1 DAY",
        []
    );
    $notificationCount = $countRow ? (int)($countRow['cnt'] ?? 0) : 0;
}

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
$_navRol      = $_SESSION['rol'] ?? 'estudiante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="ClassExpress — clases online en tiempo real">
  <title>ClassExpress</title>
  <link rel="stylesheet" href="./styles.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="icon" href="favico.svg" type="image/svg+xml">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
  <script>window.CE_ROLE = '<?= htmlspecialchars($_navRol) ?>';</script>
  <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
      <a class="navbar-brand fw-bold" href="materias.php">ClassExpress</a>

      <button class="navbar-toggler" type="button"
              data-bs-toggle="collapse" data-bs-target="#mainNav"
              aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav me-auto mb-2 mb-md-0">
          <li class="nav-item">
            <a class="nav-link" href="materias.php"><i class="bi bi-grid me-1"></i>Materias</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?= htmlspecialchars(($page_map[$resultados['ultimoContenido']] ?? 'arte') . '.php') ?>">
              <i class="bi bi-bookmark me-1"></i>Contenidos
            </a>
          </li>
          <li class="nav-item <?= $resultados['esVisibleSala'] ?>">
            <a class="nav-link" href="sala.php?<?= htmlspecialchars($resultados['ultimaSala']) ?>">
              <i class="bi bi-camera-video me-1"></i>Sala
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="buscar.php"><i class="bi bi-search me-1"></i>Buscar</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="profesores.php"><i class="bi bi-people me-1"></i>Profesores</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="amigos.php"><i class="bi bi-person-heart me-1"></i>Amigos</a>
          </li>
        </ul>

        <!-- Right side: credits + user name + logout -->
        <ul class="navbar-nav align-items-md-center gap-2">
          <li class="nav-item">
            <a class="nav-link" href="creditos.php">
              <span class="badge bg-warning text-dark fs-6 fw-semibold">
                <i class="bi bi-coin me-1"></i><?= $_navCreditos ?> cr.
              </span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="comprar_tokens.php">
              <span class="badge bg-primary fs-6 fw-semibold">
                <i class="bi bi-cart-plus me-1"></i>Comprar MonedasCE
              </span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="balance.php">
              <span class="badge bg-success fs-6 fw-semibold">
                <i class="bi bi-wallet2 me-1"></i>Balance
              </span>
            </a>
          </li>
          <?php if ($_navRol !== 'estudiante' && $_navRol !== 'student'): ?>
          <li class="nav-item d-none d-md-inline">
            <a class="nav-link btn btn-outline-secondary btn-sm px-3 text-white" href="dashboard_profesor.php">
              <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
          </li>
          <?php endif; ?>
          <li class="nav-item dropdown">
            <a class="nav-link position-relative" href="#" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-bell"></i>
              <?php if ($notificationCount > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                  <?= $notificationCount > 9 ? '9+' : $notificationCount ?>
                </span>
              <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="notificationDropdown" style="min-width: 280px;">
              <li><h6 class="dropdown-header">Últimos mensajes</h6></li>
              <?php if (empty($latestMessages)): ?>
                <li><span class="dropdown-item-text text-muted">No hay mensajes recientes.</span></li>
              <?php else: ?>
                <?php foreach ($latestMessages as $msg): ?>
                  <li>
                    <a class="dropdown-item small" href="amigos.php">
                      <strong><?= htmlspecialchars($msg['usuario'] ?? 'Sistema') ?></strong>
                      <div class="text-truncate" style="max-width: 240px;"><?= htmlspecialchars($msg['mensaje']) ?></div>
                      <small class="text-muted"><?= date('d/m H:i', strtotime($msg['enviado_at'])) ?></small>
                    </a>
                  </li>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-center" href="amigos.php">Ver toda la actividad</a></li>
              <?php endif; ?>
            </ul>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="perfil.php">
              <i class="bi bi-person-circle me-1"></i><?= $_navNombre ?>
            </a>
          </li>
          <li class="nav-item">
            <button class="nav-link btn btn-link" id="theme-toggle" title="Cambiar tema">
              <i class="bi bi-moon me-1" id="theme-icon"></i>
            </button>
          </li>
          <li class="nav-item">
            <a class="nav-link text-danger" href="logout.php">
              <i class="bi bi-box-arrow-right me-1"></i>Salir
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

<script>
// ── Theme Toggle Logic ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  const themeToggle = document.getElementById('theme-toggle');
  const themeIcon = document.getElementById('theme-icon');
  
  // Check for saved theme preference or default to light
  const savedTheme = localStorage.getItem('theme') || 'light';
  
  // Apply saved theme
  if (savedTheme === 'dark') {
    document.documentElement.setAttribute('data-theme', 'dark');
    if (themeIcon) {
      themeIcon.classList.remove('bi-moon');
      themeIcon.classList.add('bi-sun');
    }
  } else {
    document.documentElement.setAttribute('data-theme', 'light');
    if (themeIcon) {
      themeIcon.classList.remove('bi-sun');
      themeIcon.classList.add('bi-moon');
    }
  }
  
  // Toggle theme on click
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      
      // Apply new theme
      document.documentElement.setAttribute('data-theme', newTheme);
      
      // Update icon
      if (newTheme === 'dark') {
        themeIcon.classList.remove('bi-moon');
        themeIcon.classList.add('bi-sun');
      } else {
        themeIcon.classList.remove('bi-sun');
        themeIcon.classList.add('bi-moon');
      }
      
      // Save preference
      localStorage.setItem('theme', newTheme);
    });
  }
});
</script>
