<?php
session_start();
require_once 'db.php';

$status  = 'error';
$message = 'Enlace de verificación inválido o expirado.';

$token = trim($_GET['token'] ?? '');

if ($token !== '') {
    $row = dbOne(
        "SELECT usuarioid, nombre, verificado FROM usuarios WHERE token_verificacion = :token LIMIT 1",
        ['token' => $token]
    );

    if (!$row) {
        $message = 'No se encontró el enlace de verificación. Puede que ya haya sido usado o sea inválido.';
    } elseif ($row['verificado']) {
        $status  = 'already';
        $message = 'Tu correo ya está verificado. Puedes iniciar sesión.';
    } else {
        dbExec(
            "UPDATE usuarios SET verificado = 1, token_verificacion = '' WHERE usuarioid = :id",
            ['id' => $row['usuarioid']]
        );
        $status  = 'success';
        $message = '¡Correo verificado con éxito! Ahora puedes iniciar sesión.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassExpress – Verificación de correo</title>
    <link rel="stylesheet" href="./styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="icon" href="favico.svg" type="image/svg+xml">
    <style>
        body { background-color: #1a1a1a; }
        .verify-card {
            background-color: #2b2b2b;
            border: 1px solid #444;
            border-radius: 0.75rem;
        }
    </style>
</head>
<body>
  <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
      <a class="navbar-brand ms-2" href="login.php">ClassExpress</a>
  </nav>

  <div class="container mt-5 pt-4">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-6 col-lg-5">

        <div class="text-center mb-4">
          <h2 class="text-light fw-bold">Verificación de correo</h2>
        </div>

        <div class="verify-card p-4 text-center">
          <?php if ($status === 'success'): ?>
            <div class="mb-3">
              <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
            </div>
            <h5 class="text-light"><?= htmlspecialchars($message) ?></h5>
            <a href="login.php" class="btn btn-secondary mt-3">Ir a iniciar sesión</a>

          <?php elseif ($status === 'already'): ?>
            <div class="mb-3">
              <i class="bi bi-info-circle-fill text-secondary" style="font-size:3.5rem"></i>
            </div>
            <h5 class="text-light"><?= htmlspecialchars($message) ?></h5>
            <a href="login.php" class="btn btn-secondary mt-3">Ir a iniciar sesión</a>

          <?php else: ?>
            <div class="mb-3">
              <i class="bi bi-x-circle-fill text-danger" style="font-size:3.5rem"></i>
            </div>
            <h5 class="text-light"><?= htmlspecialchars($message) ?></h5>
            <a href="login.php" class="btn btn-dark border-secondary mt-3">Volver al login</a>
          <?php endif; ?>
        </div>

        <footer class="mastfoot mt-4">
          <div class="inner float-end">
            <p class="text-secondary small">ClassExpress hecho con <a href="https://getbootstrap.com/" class="text-secondary">Bootstrap</a>, por <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA" class="text-secondary">@RodrigoConejeros</a>.</p>
          </div>
        </footer>

      </div>
    </div>
  </div>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
