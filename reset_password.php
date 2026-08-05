<?php
require_once __DIR__ . '/lib/app/web_bootstrap.php';
require_once 'db.php';
ce_start_session();
require_once 'lang.php';
require_once __DIR__ . '/lib/csrf.php';

require_once __DIR__ . '/lib/security_headers.php';

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
        $error = t('reset.expired');
    }
}

if ($token === '' || ($row === null && $error === '')) {
    $error = t('reset.invalid_token');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
    csrf_require();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (strlen($password) < 6) {
        $error = t('reset.password_short');
    } elseif ($password !== $confirm) {
        $error = t('reset.password_mismatch');
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        dbExec(
            "UPDATE usuarios SET password = :pwd, reset_token = '', reset_token_expiry = 0 WHERE usuarioId = :id",
            ['pwd' => $hash, 'id' => $row['usuarioId']]
        );
        $success = t('reset.success');
        $row = null; // Hide the form
    }
}
?>
<!DOCTYPE html>
<html lang="<?= detectLang() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= t('reset.title_tag') ?></title>
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
        <p class="text-secondary mb-0"><?= t('reset.subtitle') ?></p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div>
        <div class="text-center mt-3">
          <a href="login.php" class="btn btn-primary"><?= t('login.iniciar') ?></a>
        </div>

      <?php elseif ($error && !$row): ?>
        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <div class="text-center mt-3">
          <a href="forgot_password.php" class="btn btn-outline-secondary btn-sm"><?= t('reset.request_new') ?></a>
        </div>

      <?php else: ?>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <p class="text-secondary small">
          <?= t('reset.greeting', ['name' => '<strong class="text-primary">' . htmlspecialchars($row['nombre']) . '</strong>']) ?>

        <form method="POST" action="reset_password.php?token=<?= urlencode($token) ?>" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label for="password" class="form-label"><?= t('reset.new_password') ?></label>
            <input type="password" class="form-control" id="password" name="password"
                   placeholder="<?= t('reset.min_chars') ?>" required autofocus>
          </div>
          <div class="mb-4">
            <label for="confirm" class="form-label"><?= t('reset.confirm_password') ?></label>
            <input type="password" class="form-control" id="confirm" name="confirm"
                   placeholder="��������" required>
          </div>
          <button type="submit" class="btn btn-primary w-100 fw-semibold"><?= t('reset.update_button') ?></button>
        </form>

      <?php endif; ?>

    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
