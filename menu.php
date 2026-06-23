<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Auth guard ────────────────────────────────────────────────────────────────
// Every page that includes menu.php requires a logged-in user.
// Public pages (login.php, verify.php, forgot_password.php, reset_password.php)
// do NOT include menu.php, so they are unaffected.
if (!isset($_SESSION['usuarioId'])) {
    header('Location: login.php');
    exit;
}

$resultados = [
    "ultimoContenido" => "",
    "ultimaClase"     => "",
    "ultimaSala"      => "",
    "esVisibleContenidos" => "hidden",
    "esVisibleClases"     => "hidden",
    "esVisibleSala"       => "hidden",
];

// Map legacy numeric page IDs → new filenames (stored in usuarios.ultimoContenido / ultimaClase)
$page_map = [
    '1'  => 'materias',        '2'  => 'amigos',          '3'  => 'calificar',
    '4'  => 'matematicas',     '5'  => 'historia',         '6'  => 'literatura',
    '7'  => 'quimica',         '8'  => 'biologia',         '9'  => 'fisica',
    '10' => 'geografia',       '11' => 'arte',             '12' => 'educacion_fisica',
    '13' => 'idiomas',         '14' => 'tecnologia',       '15' => 'profesores',
    '16' => 'perfil',          '17' => 'checkout',         '18' => 'aula_virtual',
    '19' => 'oferta_clase',    '20' => 'crear_clase',
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
                "pgsql:host=" . (getenv('PGHOST') ?: 'localhost') .
                ";port="      . (getenv('PGPORT') ?: '5432') .
                ";dbname="    . (getenv('PGDATABASE') ?: 'replit_db'),
                getenv('PGUSER') ?: 'postgres',
                getenv('PGPASSWORD') ?: '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $stmt = $_pdo->prepare(
                "SELECT ultimoContenido, ultimaClase, ultimaSala, creditos
                   FROM usuarios WHERE usuarioId = :uid"
            );
            $stmt->execute(['uid' => $_SESSION['usuarioId']]);
            $row = $stmt->fetch() ?: null;
            if ($row) $row = array_change_key_case($row, CASE_LOWER);
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
          <li class="nav-item" style="visibility:<?= $resultados['esVisibleContenidos'] ?>;">
            <a class="nav-link" href="<?= htmlspecialchars($page_map[$resultados['ultimoContenido']] ?? 'materias') ?>.php">
              <i class="bi bi-bookmark me-1"></i>Contenidos
            </a>
          </li>
          <li class="nav-item" style="visibility:<?= $resultados['esVisibleClases'] ?>;">
            <a class="nav-link" href="<?= htmlspecialchars($page_map[$resultados['ultimaClase']] ?? 'materias') ?>.php">
              <i class="bi bi-journal-bookmark me-1"></i>Clases
            </a>
          </li>
          <li class="nav-item" style="visibility:<?= $resultados['esVisibleSala'] ?>;">
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
          <?php if ($_navRol !== 'estudiante' && $_navRol !== 'student'): ?>
          <li class="nav-item">
            <a class="nav-link" href="dashboard_profesor.php"><i class="bi bi-bar-chart-line me-1"></i>Mi Panel</a>
          </li>
          <?php endif; ?>
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
            <a class="nav-link" href="perfil.php">
              <i class="bi bi-person-circle me-1"></i><?= $_navNombre ?>
            </a>
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
