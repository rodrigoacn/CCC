<?php
session_start();
require_once 'db.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $row = dbOne("SELECT usuarioId, nombre, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);

        if ($row && $row['verificado']) {
            $token   = bin2hex(random_bytes(32));
            $expiry  = time() + 3600; // 1 hour

            dbExec(
                "UPDATE usuarios SET reset_token = :token, reset_token_expiry = :expiry WHERE usuarioId = :id",
                ['token' => $token, 'expiry' => $expiry, 'id' => $row['usuarioid']]
            );

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $link     = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/reset_password.php?token=' . urlencode($token);

            require_once 'email_helper.php';
            ceSendReset($email, $row['nombre'], $link);
        }

        // Always show the same message (don't reveal whether the email exists)
        $success = "If that email is registered and verified, a reset link has been sent. Check your inbox and spam folder.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ClassExpress – Forgot Password</title>
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
        <p class="text-secondary mb-0">Reset your password</p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div>
        <div class="text-center mt-3">
          <a href="login.php" class="btn btn-outline-secondary btn-sm">Back to Sign In</a>
        </div>
      <?php else: ?>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php" novalidate>
          <div class="mb-4">
            <label for="email" class="form-label text-light">Email address</label>
            <input type="email" class="form-control" id="email" name="email"
                   placeholder="you@example.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            <div class="form-text text-secondary">We'll send you a link to reset your password.</div>
          </div>
          <button type="submit" class="btn btn-secondary w-100 fw-semibold">Send Reset Link</button>
        </form>

        <p class="text-secondary text-center mt-4 small mb-0">
          Remembered it?
          <a href="login.php" class="text-light">Sign in</a>
        </p>

      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
