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

$host    = 'localhost';
$db      = 'ce';
$user    = 'root';
$pass    = 'v6h470fdz0';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

if (isset($_SESSION['ultimoContenido']) || isset($_SESSION['ultimaClase']) || isset($_SESSION['ultimaSala'])) {
    $resultados["ultimoContenido"] = $_SESSION['ultimoContenido'] ?? '';
    $resultados["ultimaClase"] = $_SESSION['ultimaClase'] ?? '';
    $resultados["ultimaSala"] = $_SESSION['ultimaSala'] ?? '';
    $resultados["esVisibleContenidos"] = ($resultados["ultimoContenido"] != "") ? "visible" : "hidden";
    $resultados["esVisibleClases"] = ($resultados["ultimaClase"] != "") ? "visible" : "hidden";
    $resultados["esVisibleSala"] = ($resultados["ultimaSala"] != "") ? "visible" : "hidden";
} elseif (isset($_SESSION['usuarioId'])) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        $sql = "SELECT ultimoContenido, ultimaClase, ultimaSala FROM usuarios WHERE usuarioId = :usuarioId";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['usuarioId' => $_SESSION['usuarioId']]);
        $row = $stmt->fetchAll();
        if (!empty($row)) {
            $resultados = $row[0];
            $resultados["esVisibleContenidos"] = ($resultados["ultimoContenido"] != "") ? "visible" : "hidden";
            $resultados["esVisibleClases"] = ($resultados["ultimaClase"] != "") ? "visible" : "hidden";
            $resultados["esVisibleSala"] = ($resultados["ultimaSala"] != "") ? "visible" : "hidden";
            $_SESSION['ultimoContenido'] = $resultados["ultimoContenido"];
            $_SESSION['ultimaClase'] = $resultados["ultimaClase"];
            $_SESSION['ultimaSala'] = $resultados["ultimaSala"];
        }
    } catch (PDOException $e) {
        // DB not available - continue with defaults
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
</head>
<body>
  <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
      <a class="navbar-brand ms-2" href="#">ClassExpress</a>
      <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarsExampleDefault" aria-controls="navbarsExampleDefault" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarsExampleDefault">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item active">
            <a class="nav-link" href="example1.php">Materias <span class="sr-only">(current)</span></a>
          </li>
          <li class="nav-item" style="visibility: <?= $resultados["esVisibleContenidos"] ?>;">
            <a class="nav-link" href="example<?= htmlspecialchars($resultados["ultimoContenido"]) ?>.php">Contenidos</a>
          </li>
          <li class="nav-item" style="visibility: <?= $resultados["esVisibleClases"] ?>;">
            <a class="nav-link" href="example<?= htmlspecialchars($resultados["ultimaClase"]) ?>.php">Clases</a>
          </li>
          <li class="nav-item" style="visibility: <?= $resultados["esVisibleSala"] ?>;">
            <a class="nav-link" href="example18.php?<?= htmlspecialchars($resultados["ultimaSala"]) ?>">Sala</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="example2.php">Friends</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="example16.php">Profile</a>
          </li>
        </ul>
      </div>
    </nav>
