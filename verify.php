<?php
require_once __DIR__ . '/lib/app/web_bootstrap.php';
require_once 'db.php';
ce_start_session();
require_once 'lang.php';

$status  = 'error';
$message = 'Enlace de verificación inválido o expirado.';

$token = trim($_GET['token'] ?? '');

if ($token !== '') {
    $row = dbOne(
        "SELECT usuarioId, nombre, verificado FROM usuarios WHERE token_verificacion = :token LIMIT 1",
        ['token' => $token]
    );

    if (!$row) {
        $message = 'No se encontró el enlace de verificación. Puede que ya haya sido usado o sea inválido.';
    } elseif ($row['verificado']) {
        $status  = 'already';
        $message = 'Tu correo ya está verificado. Puedes iniciar sesión.';
    } else {
        dbExec(
            "UPDATE usuarios SET verificado = 1, token_verificacion = '' WHERE usuarioId = :id",
            ['id' => $row['usuarioId']]
        );
        $status  = 'success';
        $message = '¡Correo verificado con éxito! Ahora puedes iniciar sesión.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= detectLang() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassExpress – Verificación de correo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="./styles.css?v=20260804">
    <link rel="icon" href="favico.svg?v=3" type="image/svg+xml">
    <style>
        body { background-color: #f4f6fb; }
        .verify-card {
            background-color: #ffffff;
            border: 1px solid #dbe2ee;
            border-radius: 0.75rem;
            box-shadow: 0 8px 30px rgba(30,41,59,0.08);
        }
    </style>
</head>
<body>
  <nav class="navbar navbar-expand-md navbar-light bg-white border-bottom fixed-top" style="border-bottom:1px solid #dbe2ee!important;">
      <a class="navbar-brand ms-2 fw-bold" style="color:#66ddbd" href="login.php">ClassExpress</a>
  </nav>

  <div class="container mt-5 pt-4">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-6 col-lg-5">

        <div class="text-center mb-4">
          <h2 class="fw-bold" style="color:#1e293b">Verificación de correo</h2>
        </div>

        <div class="verify-card p-4 text-center">
          <?php if ($status === 'success'): ?>
            <div class="mb-3">
              <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
            </div>
            <h5 style="color:#1e293b"><?= htmlspecialchars($message) ?></h5>
            <a href="login.php" class="btn btn-primary mt-3">Ir a iniciar sesión</a>

          <?php elseif ($status === 'already'): ?>
            <div class="mb-3">
              <i class="bi bi-info-circle-fill text-secondary" style="font-size:3.5rem"></i>
            </div>
            <h5 style="color:#1e293b"><?= htmlspecialchars($message) ?></h5>
            <a href="login.php" class="btn btn-primary mt-3">Ir a iniciar sesión</a>

          <?php else: ?>
            <div class="mb-3">
              <i class="bi bi-x-circle-fill text-danger" style="font-size:3.5rem"></i>
            </div>
            <h5 style="color:#1e293b"><?= htmlspecialchars($message) ?></h5>
            <a href="login.php" class="btn btn-dark border-secondary mt-3">Volver al login</a>
          <?php endif; ?>
        </div>

        <footer class="mastfoot mt-4">
          <div class="inner float-end">
            <p class="text-secondary small"><?= t('general.footer_text', ['bootstrap' => '<a href="https://getbootstrap.com/" class="text-secondary">Bootstrap</a>', 'author' => '<a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA" class="text-secondary">@RodrigoConejeros</a>']) ?></p>
          </div>
        </footer>

      </div>
    </div>
  </div>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
