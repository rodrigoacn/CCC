<?php
require_once __DIR__ . '/lib/app/web_bootstrap.php';
require_once 'db.php';
ce_start_session();
require_once 'lang.php';
require_once __DIR__ . '/lib/csrf.php';

require_once __DIR__ . '/lib/security_headers.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('forgot.invalid_email');
    } else {
        $row = dbOne("SELECT usuarioId, nombre, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);

        if ($row && $row['verificado']) {
            $token   = bin2hex(random_bytes(32));
            $expiry  = time() + 3600; // 1 hour

            dbExec(
                "UPDATE usuarios SET reset_token = :token, reset_token_expiry = :expiry WHERE usuarioId = :id",
                ['token' => $token, 'expiry' => $expiry, 'id' => $row['usuarioId']]
            );

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $link     = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/reset_password.php?token=' . urlencode($token);

            require_once 'email_helper.php';
            ceSendReset($email, $row['nombre'], $link);
        }

        // Always show the same message (don't reveal whether the email exists)
        $success = t('forgot.success');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= detectLang() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('forgot.title_tag') ?></title>
  <link rel="icon" href="favico.svg?v=3" type="image/svg+xml">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
  <style>
    body { background: #f4f6fb; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .card { background: #ffffff; border: 1px solid #dbe2ee; border-radius: 1rem; box-shadow: 0 8px 30px rgba(30,41,59,0.08); }
  </style>
</head>
<body>
  <div style="position:fixed;top:12px;right:12px;z-index:1000"><?= renderLangSelector() ?></div>
  <script>window.CE_I18N = <?= renderTranslationsJSON() ?>;window.CE_LANG = '<?= detectLang() ?>';</script>
  <script>
  window.CE_switchLang=function(code){
    document.cookie='ce_lang='+code+';path=/;max-age=2592000';
    fetch('lang_api.php?lang='+code+'&save=1').then(function(){location.reload()}).catch(function(){location.reload()});
  };
  </script>
  <div class="container" style="max-width:440px;">
    <div class="card p-4 p-md-5 shadow-lg">

      <div class="text-center mb-4">
        <a href="login.php" class="text-decoration-none">
          <h1 class="fw-bold text-light fs-4" style="color:#1e293b!important">ClassExpress</h1>
        </a>
        <p class="text-secondary mb-0"><?= t('forgot.subtitle') ?></p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div>
        <div class="text-center mt-3">
          <a href="login.php" class="btn btn-outline-secondary btn-sm"><?= t('forgot.back_login') ?></a>
        </div>
      <?php else: ?>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="forgot_password.php" novalidate>
          <?= csrf_field() ?>
          <div class="mb-4">
            <label for="email" class="form-label"><?= t('login.email') ?></label>
            <input type="email" class="form-control" id="email" name="email"
                   placeholder="correo@ejemplo.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            <div class="form-text text-secondary"><?= t('forgot.hint') ?></div>
          </div>
          <button type="submit" class="btn btn-primary w-100 fw-semibold"><?= t('forgot.send_link') ?></button>
        </form>

        <p class="text-secondary text-center mt-4 small mb-0">
          <?= t('forgot.remembered') ?>
          <a href="login.php" class="text-primary"><?= t('forgot.login_now') ?></a>
        </p>

      <?php endif; ?>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
