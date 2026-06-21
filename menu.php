<?php
$host    = 'localhost';
$db      = 'ce';
$user    = 'root';
$pass    = 'v6h470fdz0';
$charset = 'utf8mb4';

// 1. Configurar la cadena de conexión (DSN)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Opciones para manejo de errores y formato de datos
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

session_start(); // Siempre obligatorio al principio

// 3. Leer los datos guardados previamente
if (isset($_SESSION['ultimoContenido']) || isset($_SESSION['ultimaClase']) || isset($_SESSION['ultimaSala'])) {
    $resultados["ultimoContenido"] = $_SESSION['ultimoContenido'];
    $resultados["ultimaClase"] = $_SESSION['ultimaClase'];
    $resultados["ultimaSala"] = $_SESSION['ultimaSala'];
    $resultados["esVisibleContenidos"] = ($_SESSION["ultimoContenido"] != "") ? "visible" : "hidden";
    $resultados["esVisibleClases"] = ($_SESSION["ultimaClase"] != "") ? "visible" : "hidden";
    $resultados["esVisibleSala"] = ($_SESSION["ultimaSala"] != "") ? "visible" : "hidden";
} elseif (isset($_SESSION['usuarioId'])) {
    try {
      // 2. Crear la conexión
      $pdo = new PDO($dsn, $user, $pass, $options);

      // 3. Preparar la consulta SQL (Evita inyección SQL)
      $sql = "SELECT ultimoContenido, ultimaClase, ultimaSala FROM usuarios WHERE usuarioId = :usuarioId";
      $stmt = $pdo->prepare($sql);

      // 4. Ejecutar la consulta pasando los parámetros
      $stmt->execute(['usuarioId' => $_SESSION['usuarioId']]);

      // 5. Obtener los datos y procesarlos
      $resultados = $stmt->fetchAll()[0];
      $resultados["esVisibleContenidos"] = ($resultados["ultimoContenido"] != "") ? "visible" : "hidden";
      $resultados["esVisibleClases"] = ($resultados["ultimaClase"] != "") ? "visible" : "hidden";
      $resultados["esVisibleSala"] = ($resultados["ultimaSala"] != "") ? "visible" : "hidden";

      $_SESSION['ultimoContenido'] = $resultados["ultimoContenido"];
      $_SESSION['ultimaClase'] = $resultados["ultimaClase"];
      $_SESSION['ultimaSala'] = $resultados["ultimaSala"];
      $_SESSION["ultimoContenido"] = ($resultados["ultimoContenido"] != "") ? "visible" : "hidden";
      $_SESSION["ultimaClase"] = ($resultados["ultimaClase"] != "") ? "visible" : "hidden";
      $_SESSION["ultimaSala"] = ($resultados["ultimaSala"] != "") ? "visible" : "hidden";

  } catch (PDOException $e) {
      // Capturar errores de conexión o consulta
      echo "Error en la base de datos: " . $e->getMessage();
  }
} else {
    echo "No has iniciado sesión.";
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
	<link rel="icon" href="favicon.svg" type="image/svg+xml">
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
            <a class="nav-link" href="file:///C:/Users/rodrigo/Desktop/CCC/pruebas/example1.html">Materias <span class="sr-only">(current)</span></a>
          </li>
          <li class="nav-item" style="visibility: <?= $resultados["esVisibleContenidos"] ?>;">
            <a class="nav-link" href="file:///C:/Users/rodrigo/Desktop/CCC/pruebas/example<?= $resultados["ultimoContenido"] ?>.html">Contenidos</a>
          </li>
          <li class="nav-item" style="visibility: <?= $resultados["esVisibleClases"] ?>;">
            <a class="nav-link" href="file:///C:/Users/rodrigo/Desktop/CCC/pruebas/example<?= $resultados["ultimaClase"] ?>.html">Clases</a>
          </li>
          <li class="nav-item" style="visibility: <?= $resultados["esVisibleSala"] ?>;">
            <a class="nav-link" href="file:///C:/Users/rodrigo/Desktop/CCC/pruebas/example18.html?<?= $resultados["ultimaSala"] ?>">Sala</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="file:///C:/Users/rodrigo/Desktop/CCC/pruebas/example2.html">Friends</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="file:///C:/Users/rodrigo/Desktop/CCC/pruebas/example16.html">Profile</a>
          </li>
        </ul>
      </div>
    </nav>