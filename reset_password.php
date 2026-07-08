<?php
session_start();
require_once 'db.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';

// Validate token immediately
$row = null;
if ($token !== '') {
    $row = dbOne(
        "SELECT usuarioId, nombre, reset_token_expiry FROM usuarios
         WHERE reset_token = :token
         LIMIT 1",
        ['token' => $token]
    );

    if ($row && (int)$row['reset_token_expiry'] < time()) {
        $row = null; // Expired
        $error = 'Este enlace de restablecimiento expiró. Solicita uno nuevo.';
    }
}

if ($token === '' || ($row === null && $error === '')) {
    $error = 'Token inválido o faltante. Solicita un nuevo enlace de restablecimiento.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        dbExec(
            "UPDATE usuarios SET password = :pwd, reset_token = '', reset_token_expiry = 0 WHERE usuarioId = :id",
            ['pwd' => $hash, 'id' => $row['usuarioid']]
        );
        $success = 'Tu contraseña ha sido actualizada. Ahora puedes iniciar sesión.';
        $row = null; // Hide the form
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ClassExpress – Restablecer contraseña</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
  <style>
    body { background: #111; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .card { background: #1e1e1e; border: 1px solid #333; border-radius: 1rem; }
  </style>
</head>
<body>
  <div class="container" style="max-width:440px;">
    <div class="card p-4 p-md-5 shadow-lg">

      <div class="text-center mb-4">
        <a href="login.php" class="text-decoration-none">
          <h1 class="fw-bold text-light fs-4">ClassExpress</h1>
        </a>
        <p class="text-secondary mb-0">Establece una nueva contraseña</p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div>
        <div class="text-center mt-3">
          <a href="login.php" class="btn btn-secondary">Iniciar sesión</a>
        </div>

      <?php elseif ($error && !$row): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <div class="text-center mt-3">
          <a href="forgot_password.php" class="btn btn-outline-secondary btn-sm">Solicitar un nuevo enlace</a>
        </div>

      <?php else: ?>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <p class="text-secondary small">
          Hola <strong class="text-light"><?= htmlspecialchars($row['nombre']) ?></strong>, elige una nueva contraseña a continuación.

        <form method="POST" action="reset_password.php?token=<?= urlencode($token) ?>" novalidate>
          <div class="mb-3">
            <label for="password" class="form-label text-light">Nueva contraseña</label>
            <input type="password" class="form-control" id="password" name="password"
                   placeholder="Mínimo 6 caracteres" required autofocus>
          </div>
          <div class="mb-4">
            <label for="confirm" class="form-label text-light">Confirmar nueva contraseña</label>
            <input type="password" class="form-control" id="confirm" name="confirm"
                   placeholder="••••••••" required>
          </div>
          <button type="submit" class="btn btn-secondary w-100 fw-semibold">Actualizar contraseña</button>
        </form>

      <?php endif; ?>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
