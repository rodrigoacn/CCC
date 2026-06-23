<?php
session_start();

$resultados = [
    "ultimoContenido" => "",
    "ultimaClase" => "",
    "ultimaSala" => "",
    "esVisibleContenidos" => "hidden",
    "esVisibleClases" => "hidden",
    "esVisibleSala" => "hidden",
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

if (isset($_SESSION['ultimoContenido']) || isset($_SESSION['ultimaClase']) || isset($_SESSION['ultimaSala'])) {
    $resultados["ultimoContenido"] = $_SESSION['ultimoContenido'] ?? '';
    $resultados["ultimaClase"] = $_SESSION['ultimaClase'] ?? '';
    $resultados["ultimaSala"] = $_SESSION['ultimaSala'] ?? '';
    $resultados["esVisibleContenidos"] = ($resultados["ultimoContenido"] != "") ? "visible" : "hidden";
    $resultados["esVisibleClases"] = ($resultados["ultimaClase"] != "") ? "visible" : "hidden";
    $resultados["esVisibleSala"] = ($resultados["ultimaSala"] != "") ? "visible" : "hidden";
} elseif (isset($_SESSION['usuarioId'])) {
    // Use db.php helper if already included, otherwise connect directly
    if (function_exists('dbOne')) {
        $row = dbOne("SELECT ultimoContenido, ultimaClase, ultimaSala FROM usuarios WHERE usuarioId = :uid", ['uid' => $_SESSION['usuarioId']]);
    } else {
        $row = null;
        try {
            $_pghost = getenv('PGHOST') ?: 'localhost';
            $_pgport = getenv('PGPORT') ?: '5432';
            $_pgname = getenv('PGDATABASE') ?: 'replit_db';
            $_pguser = getenv('PGUSER') ?: 'postgres';
            $_pgpass = getenv('PGPASSWORD') ?: '';
            $_pdo = new PDO("pgsql:host=$_pghost;port=$_pgport;dbname=$_pgname", $_pguser, $_pgpass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
            $stmt = $_pdo->prepare("SELECT ultimoContenido, ultimaClase, ultimaSala FROM usuarios WHERE usuarioId = :uid");
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
    }
}
?>
<!DOCTYPE html>
<html lang=es>
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="">
        <title>Organizador de Proyectos</title>
  <link rel="stylesheet" href="./styles.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="icon" href="favico.svg" type="image/svg+xml">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
</head>
<body>
  <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
      <a class="navbar-brand ms-2" href="#">ClassExpress</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarsExampleDefault">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item active">
            <a class="nav-link" href="materias.php">Materias <span class="sr-only">(current)</span></a>
          </li>
          <li class="nav-item" style="visibility: <?= $resultados["esVisibleContenidos"] ?>;">
            <a class="nav-link" href="<?= htmlspecialchars($page_map[$resultados["ultimoContenido"]] ?? 'materias') ?>.php">Contenidos</a>
          </li>
          <li class="nav-item" style="visibility: <?= $resultados["esVisibleClases"] ?>;">
            <a class="nav-link" href="<?= htmlspecialchars($page_map[$resultados["ultimaClase"]] ?? 'materias') ?>.php">Clases</a>
          </li>
          <li class="nav-item" style="visibility: <?= $resultados["esVisibleSala"] ?>;">
            <a class="nav-link" href="aula_virtual.php?<?= htmlspecialchars($resultados["ultimaSala"]) ?>">Sala</a>
          </li>
          <?php if (isset($_SESSION['usuarioId']) && ($_SESSION['rol'] ?? 'student') !== 'student'): ?>
          <li class="nav-item">
            <a class="nav-link" href="dashboard_profesor.php">My Dashboard</a>
          </li>
          <?php endif; ?>
          <?php if (isset($_SESSION['usuarioId'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="creditos.php">
              <i class="bi bi-coin me-1"></i>Credits
            </a>
          </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link" href="amigos.php">Friends</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="perfil.php">Profile</a>
          </li>
        </ul>
      </div>
    </nav>
