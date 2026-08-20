<?php
require_once __DIR__ . '/lib/app/web_bootstrap.php';
require_once 'db.php';
ce_start_session();
require_once 'lang.php';

require_once __DIR__ . '/lib/security_headers.php';
require_once __DIR__ . '/lib/RateLimiter.php';

// Only the owner's public IP may open the login page.
// When logged in as the owner, the login page also auto-redirects to the app.
// An owner that enters LOGIN_OWNER_ACCESS_EMAIL in the landing signup form gets
// the session unlocked (`ce_emergency`), bypassing the IP allowlist.
$allowedLoginIps = array_filter(array_map('trim', explode(',', getenv('LOGIN_ALLOWED_IPS') ?: '')));
$ownerEmails = array_filter(array_map('strtolower', array_map('trim', explode(',', getenv('APP_OWNER_EMAIL') ?: ''))));
if ($allowedLoginIps && empty($_SESSION['ce_emergency'])) {
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $isOwnerSession = isset($_SESSION['usuarioId']) && in_array(strtolower(trim(dbOne("SELECT email FROM usuarios WHERE usuarioId = ?", [$_SESSION['usuarioId']])['email'] ?? '')), $ownerEmails, true);
    if (!$isOwnerSession && !in_array($clientIp, $allowedLoginIps, true)) {
        header('Location: landing.php');
        exit;
    }
}

$error_login  = '';
$error_signup = '';
$success_msg  = '';
$active_tab   = 'signin';

// Compra global "sin anuncios": oculta oferta y anuncios.
$adsFreeActive = false;
if (getDB()) {
    $adsFree = dbOne("SELECT id FROM ads_free_compras WHERE estado='activo' AND valido_hasta > NOW() ORDER BY valido_hasta DESC LIMIT 1");
    $adsFreeActive = $adsFree !== null;
}

// â”€â”€â”€ SIGN IN â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signin') {
    $active_tab = 'signin';
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $loginIp  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!rateLimit('login', $loginIp, 10, 300)) {
        $error_login = t('login.rate_limit');
    } elseif (!$email || !$password) {
        $error_login = t('login.error_fields');
    } else {
        $row = dbOne("SELECT usuarioId, nombre, rol, password, verificado, creditos, idioma_preferido, eliminado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);
        if ($row === null && !getDB()) {
            $error_login = t('login.error_db');
        } elseif (!$row) {
            $error_login = t('login.error_notfound');
        } elseif ($row['eliminado']) {
            $error_login = t('login.error_deleted');
        } elseif (!password_verify($password, $row['password'])) {
            $error_login = t('login.error_wrongpass');
        } elseif (!$row['verificado']) {
            $error_login = t('login.error_unverified');
        } else {
            session_regenerate_id(true);
            $_SESSION['usuarioId'] = $row['usuarioId'];
            $_SESSION['nombre']    = $row['nombre'];
            $_SESSION['rol']       = $row['rol'];
            $_SESSION['creditos']  = (int)($row['creditos'] ?? 0);
            // Load preferred language from BD
            if (!empty($row['idioma_preferido'])) {
                $_SESSION['_lang'] = $row['idioma_preferido'];
                setcookie('ce_lang', $row['idioma_preferido'], time() + 86400 * 30, '/', '', false, false);
            }

            // â”€â”€ "Guardar sesión" â†’ cookie persistente por 30 días â”€â”€
            if (!empty($_POST['recuerdame'])) {
                $token = bin2hex(random_bytes(32));
                $hash  = hash('sha256', $token);
                dbExec("UPDATE usuarios SET remember_token = :t WHERE usuarioId = :id", ['t'=>$hash, 'id'=>$row['usuarioId']]);
                setcookie('ce_remember', $token, time() + 30*24*60*60, '/', '', !empty($_SERVER['HTTPS']), true);
                setcookie(session_name(), session_id(), time() + 30*24*60*60, '/', '', !empty($_SERVER['HTTPS']), true);
            } else {
                dbExec("UPDATE usuarios SET remember_token = NULL WHERE usuarioId = :id", ['id'=>$row['usuarioId']]);
                setcookie('ce_remember', '', time() - 3600, '/', '', !empty($_SERVER['HTTPS']), true);
            }
            $loginRol = $_POST['login_rol'] ?? 'student';
            setcookie('ce_app_modo', $loginRol === 'instructor' ? 'teacher' : 'student', time() + 365*24*60*60, '/', '', !empty($_SERVER['HTTPS']), true);
            header('Location: materias.php');
            exit;
        }
    }
}

// â”€â”€â”€ RESEND VERIFICATION â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_verify') {
    $active_tab = 'signin';
    $email = trim($_POST['email'] ?? '');
    $resendIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!rateLimit('resend_verify', $resendIp, 3, 300)) {
        $error_login = t('login.rate_limit');
    } elseif ($email && filter_var($email, FILTER_VALIDATE_EMAIL) && getDB()) {
        $row = dbOne("SELECT usuarioId, nombre, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);
        if ($row && !$row['verificado']) {
            $token = bin2hex(random_bytes(32));
            dbExec(
                "UPDATE usuarios SET token_verificacion = :token WHERE usuarioId = :id",
                ['token' => $token, 'id' => $row['usuarioId']]
            );
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $link = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/verify.php?token=' . urlencode($token);
            require_once 'email_helper.php';
            ceSendVerify($email, $row['nombre'], $link);
        }
    }
    if (!$error_login) {
        $success_msg = t('login.resend_sent');
    }
}

// â”€â”€â”€ SIGN UP â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup') {
    $active_tab = 'signup';
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = trim($_POST['email_signup'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password_signup'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $pais_id  = (int)($_POST['pais_id'] ?? 0) ?: null;
    $rol      = in_array($_POST['rol'] ?? 'student', ['student', 'instructor'], true) ? $_POST['rol'] : 'student';
    $signupIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!rateLimit('signup', $signupIp, 5, 600)) {
        $error_signup = t('login.signup_rate_limit');
    } elseif (!$nombre || !$email || !$username || !$password || !$confirm) {
        $error_signup = t('login.error_fields');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_signup = t('login.error_email');
    } elseif (strlen($username) < 3) {
        $error_signup = t('login.error_user');
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error_signup = t('login.error_userchars');
    } elseif (strlen($password) < 6) {
        $error_signup = t('login.error_passshort');
    } elseif ($password !== $confirm) {
        $error_signup = t('login.error_pass');
    } else {
        if (!getDB()) {
            $error_signup = t('login.error_db');
        } else {
            $existing = dbOne("SELECT usuarioId, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);
            $existing_username = dbOne("SELECT usuarioId FROM usuarios WHERE username = :username LIMIT 1", ['username' => $username]);

            if ($existing_username) {
                $error_signup = t('login.error_userexists');
            } elseif ($existing) {
                if ($existing['verificado']) {
                    $error_signup = t('login.error_emailexists');
                } else {
                    $token = bin2hex(random_bytes(32));
                    dbExec(
                        "UPDATE usuarios SET token_verificacion = :token WHERE usuarioId = :id",
                        ['token' => $token, 'id' => $existing['usuarioId']]
                    );
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $link = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/verify.php?token=' . urlencode($token);
                    require_once 'email_helper.php';
                    $sent = ceSendVerify($email, $nombre, $link);
                    if ($sent) {
                        $success_msg = t('login.verify_resent');
                    } else {
                        $error_signup = t('login.verify_send_error');
                    }
                    $active_tab  = 'signin';
                }
            } else {
                if (!$error_signup) {
                    $hash  = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32));

                    dbExec(
                        "INSERT INTO usuarios (nombre, email, password, rol, verificado, token_verificacion, pais_id, creditos, username, ultimoContenido, ultimaClase, ultimaSala)
                         VALUES (:nombre, :email, :password, :rol, 0, :token, :pais_id, 100, :username, '', '', '')",
                        ['nombre' => $nombre, 'email' => $email, 'password' => $hash, 'rol' => $rol, 'token' => $token, 'pais_id' => $pais_id, 'username' => $username]
                    );

                    $newUserId = getDB()->lastInsertId();
                    $idiomasReg = $_POST['idiomas'] ?? [];
                    if (!empty($idiomasReg) && is_array($idiomasReg)) {
                        $stmtId = getDB()->prepare("INSERT IGNORE INTO usuario_idiomas (usuarioId, idiomaId) VALUES (?, ?)");
                        foreach ($idiomasReg as $iid) {
                            $stmtId->execute([$newUserId, (int)$iid]);
                        }
                    }

                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $link = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/verify.php?token=' . urlencode($token);
                    require_once 'email_helper.php';
                    $sent = ceSendVerify($email, $nombre, $link);
                    
                    if ($sent) {
                        $success_msg = t('login.success_created');
                    } else {
                        $error_signup = t('login.verify_send_error');
                    }
                    $active_tab  = 'signin';
                }
            }
        }
    }
}

// â”€â”€ Remember-me auto-login â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if (!isset($_SESSION['usuarioId'])) {
    ce_remember_autologin();
}

// Redirect already-logged-in users to the right page
if (isset($_SESSION['usuarioId'])) {
    $rol = $_SESSION['rol'] ?? 'estudiante';
    if (($rol === 'estudiante' || $rol === 'student') && isset($_SESSION['usuarioId'])) {
        $pending = dbOne(
            "SELECT sesionId FROM sesiones_clase
             WHERE estudianteId = :u AND pagado = 0 AND fin IS NOT NULL
             ORDER BY fin ASC LIMIT 1",
            ['u' => $_SESSION['usuarioId']]
        );
        if ($pending) {
            header('Location: pago.php?sesion=' . $pending['sesionId']);
            exit;
        }
    }
    header('Location: materias.php');
    exit;
}

// Load all countries for signup dropdown
$paises_list = dbAll("SELECT paisId, nombre, codigo_moneda, simbolo FROM paises ORDER BY nombre ASC");
if (!$paises_list) {
    $paises_list = [];
}

$resultados = [
    "ultimoContenido"    => "",
    "ultimaClase"        => "",
    "ultimaSala"         => "",
    "esVisibleContenidos"=> "hidden",
    "esVisibleClases"    => "hidden",
    "esVisibleSala"      => "hidden",
];
?>
<!DOCTYPE html>
<html lang="<?= detectLang() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassExpress — Clases Particulares en Línea por Videoconferencia</title>
    <meta name="description" content="ClassExpress: plataforma de clases particulares en tiempo real. Conecta profesores y estudiantes por videoconferencia. Matemáticas, ciencias, idiomas y más. Aprende desde cualquier lugar.">
    <meta name="keywords" content="clases particulares, clases online, tutorías, videoconferencia, profesor particular, aprender en línea, matemáticas, ciencias, idiomas, educación, e-learning, clases en vivo">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://classexpress.online/login.php">
    <meta property="og:type" content="website">
    <meta property="og:title" content="ClassExpress — Clases Particulares en Línea por Videoconferencia">
    <meta property="og:description" content="Plataforma de clases particulares en tiempo real. Conecta profesores y estudiantes por videoconferencia. Aprende desde cualquier lugar.">
    <meta property="og:url" content="https://classexpress.online/login.php">
    <meta property="og:site_name" content="ClassExpress">
    <meta property="og:locale" content="es_CL">
    <meta property="og:image" content="https://classexpress.online/favico.svg">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="ClassExpress — Clases Particulares en Línea">
    <meta name="twitter:description" content="Plataforma de clases particulares en tiempo real. Conecta profesores y estudiantes por videoconferencia.">
    <meta name="twitter:image" content="https://classexpress.online/favico.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="./styles.css?v=20260804">
    <link rel="icon" href="favico.svg?v=4" type="image/svg+xml">
    <meta name="google-adsense-account" content="ca-pub-5524033374028556">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php if (!$adsFreeActive): ?>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5524033374028556" crossorigin="anonymous"></script>
    <?php endif; ?>
    <style>
        body {
            background: var(--bg-primary, #f4f6fb);
            transition: background-color 0.3s ease;
        }
        .login-card {
            background: var(--card-bg, #ffffff);
            border: 1px solid var(--border-color, #dbe2ee);
            border-radius: 0.75rem;
            box-shadow: 0 8px 30px rgba(30,41,59,0.08);
        }
        .nav-tabs .nav-link {
            color: var(--text-secondary, #64748b);
            border-color: transparent;
        }
        .nav-tabs .nav-link.active {
            background: var(--bg-tertiary, #eef1f8);
            border-color: var(--border-color, #dbe2ee) var(--border-color, #dbe2ee) var(--bg-tertiary, #eef1f8);
            color: var(--primary-color, #66ddbd);
        }
        .nav-tabs {
            border-bottom-color: var(--border-color, #dbe2ee);
        }
        .form-control {
            background: var(--input-bg, #ffffff);
            border-color: var(--input-border, #cbd5e1);
            color: var(--text-primary, #1e293b);
        }
        .form-control:focus {
            background: var(--bg-tertiary, #eef1f8);
            border-color: var(--primary-color, #66ddbd);
            color: var(--text-primary, #1e293b);
            box-shadow: 0 0 0 0.2rem rgba(102,221,189,.15);
        }
        .form-control::placeholder { color: var(--text-secondary, #94a3b8); }
        .form-label { color: var(--text-secondary, #64748b); }
        .tab-content { background: var(--bg-tertiary, #eef1f8); border-radius: 0 0 0.5rem 0.5rem; }
        .brand-title { letter-spacing: 0.05em; }
        #pais_search { border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
        #pais_id { border-top-left-radius: 0; border-top-right-radius: 0; }
        .country-option { padding: 2px 4px; }
    </style>
</head>
<body>
  <nav class="navbar navbar-expand-md navbar-light bg-white border-bottom fixed-top" style="border-bottom:1px solid #dbe2ee!important;">
      <a class="navbar-brand ms-2 fw-bold" style="color:#66ddbd" href="index.php">ClassExpress</a>
      <div class="ms-auto me-3"><?= renderLangSelector() ?></div>
  </nav>
  <script>
  window.CE_switchLang=function(code){
    document.cookie='ce_lang='+code+';path=/;max-age=2592000';
    fetch('lang_api.php?lang='+code+'&save=1').then(function(){location.reload()}).catch(function(){location.reload()});
  };
  </script>

  <div class="container mt-10">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-7 col-lg-5">

        <div class="text-center mb-4">
          <h2 class="fw-bold brand-title" style="color:#1e293b">ClassExpress</h2>
          <p class="text-secondary"><?= t('login.tagline') ?></p>
        </div>

        <!-- Oferta 5000 CLP: sin anuncios por 1 semana -->
        <?php if (!$adsFreeActive): ?>
        <div class="text-center mb-3">
          <button type="button" id="adsFreeBtn"
                  class="btn w-100 fw-bold"
                  style="padding:16px 20px; border-radius:16px; border:2px solid #f59e0b; background:linear-gradient(135deg,#f59e0b,#fbbf24); color:#fff; box-shadow:0 8px 20px rgba(245,158,11,.35);"
                  onclick="document.getElementById('adsFreeModal').style.display='flex';">
            <span style="display:block; font-size:13px; letter-spacing:.5px; opacity:.95;"><i class="bi bi-stars"></i> OFERTA POR TIEMPO LIMITADO</span>
            <span style="display:block; font-size:30px; font-weight:900; line-height:1.2; text-shadow:0 2px 6px rgba(0,0,0,.2);">5.000 CLP</span>
            <span style="display:block; font-size:14px; font-weight:600;">elimina anuncios por 1 semana</span>
          </button>
        </div>
        <?php endif; ?>

        <div class="login-card p-4">

          <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?= $success_msg ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?= t('login.account_deleted') ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <!-- Tabs -->
          <ul class="nav nav-tabs mb-0" id="authTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link <?= $active_tab === 'signin' ? 'active' : '' ?>"
                      id="signin-tab" data-bs-toggle="tab" data-bs-target="#signin"
                      type="button" role="tab"><?= t('login.iniciar') ?></button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link <?= $active_tab === 'signup' ? 'active' : '' ?>"
                      id="signup-tab" data-bs-toggle="tab" data-bs-target="#signup"
                      type="button" role="tab"><?= t('login.registrar') ?></button>
            </li>
          </ul>

          <div class="tab-content p-3" id="authTabContent">

            <!-- â”€â”€ SIGN IN â”€â”€ -->
            <div class="tab-pane fade <?= $active_tab === 'signin' ? 'show active' : '' ?>"
                 id="signin" role="tabpanel">
              <?php if ($error_login): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error_login) ?></div>
              <?php endif; ?>
              <form method="POST" action="login.php" novalidate>
                <input type="hidden" name="action" value="signin">
                <div class="mb-3">
                  <label for="email" class="form-label"><?= t('login.email') ?></label>
                  <input type="email" class="form-control" id="email" name="email"
                         placeholder="correo@ejemplo.com"
                         value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="mb-4">
                  <div class="d-flex justify-content-between align-items-baseline">
                    <label for="password" class="form-label"><?= t('login.password') ?></label>
                    <a href="forgot_password.php" class="text-secondary small"><?= t('login.olvide') ?></a>
                  </div>
                  <input type="password" class="form-control" id="password" name="password"
                         placeholder="••••••••" required>
                </div>
                <div class="mb-3">
                  <label class="form-label d-block"><?= t('login.role_label') ?></label>
                  <div class="d-flex gap-2">
                    <label class="form-check form-check-inline" id="rol-student-label" style="cursor:pointer;padding:8px 16px;border-radius:24px;border:1px solid var(--bd);background:<?= (($_POST['login_rol'] ?? 'student') === 'student') ? 'var(--pb)' : 'transparent' ?>">
                      <input type="radio" name="login_rol" value="student" <?= (($_POST['login_rol'] ?? 'student') === 'student') ? 'checked' : '' ?> style="display:none" data-role="student">
                       <i class="bi bi-person"></i> <?= t('login.estudiante') ?>
                    </label>
                    <label class="form-check form-check-inline" id="rol-instructor-label" style="cursor:pointer;padding:8px 16px;border-radius:24px;border:1px solid var(--bd);background:<?= (($_POST['login_rol'] ?? '') === 'instructor') ? 'var(--pb)' : 'transparent' ?>">
                      <input type="radio" name="login_rol" value="instructor" <?= (($_POST['login_rol'] ?? '') === 'instructor') ? 'checked' : '' ?> style="display:none" data-role="instructor">
                       <i class="bi bi-briefcase"></i> <?= t('login.profesor') ?>
                    </label>
                  </div>
                </div>
                <div class="form-check mb-3">
                  <input type="checkbox" class="form-check-input" id="recuerdame" name="recuerdame" value="1">
                  <label class="form-check-label text-secondary small" for="recuerdame"><?= t('login.remember') ?></label>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-semibold"><?= t('login.iniciar') ?></button>
              </form>
              <p class="text-secondary text-center mt-3 small mb-0">
                <?= t('login.no_account') ?>
                <a href="#" class="text-primary" onclick="document.getElementById('signup-tab').click(); return false;"><?= t('login.register_here') ?></a>
              </p>
              <form method="POST" action="login.php" class="mt-3 pt-3 border-top border-secondary">
                <input type="hidden" name="action" value="resend_verify">
                <p class="text-secondary small mb-2"><?= t('login.no_verify_email') ?></p>
                <div class="input-group input-group-sm">
                  <input type="email" class="form-control" name="email" placeholder="correo@ejemplo.com"
                         value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                  <button type="submit" class="btn btn-outline-secondary"><?= t('login.resend_link') ?></button>
                </div>
              </form>
            </div>

            <!-- â”€â”€ SIGN UP â”€â”€ -->
            <div class="tab-pane fade <?= $active_tab === 'signup' ? 'show active' : '' ?>"
                 id="signup" role="tabpanel">
              <?php if ($error_signup): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error_signup) ?></div>
              <?php endif; ?>
              <form method="POST" action="login.php" novalidate>
                <input type="hidden" name="action" value="signup">
                <div class="mb-3">
                  <label for="nombre" class="form-label"><?= t('login.nombre') ?></label>
                   <input type="text" class="form-control" id="nombre" name="nombre"
                          placeholder="<?= t('login.placeholder_nombre') ?>"
                         value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                  <label for="username" class="form-label"><?= t('login.username') ?> <span class="text-secondary">(<?= t('login.username_hint') ?>)</span></label>
                   <input type="text" class="form-control" id="username" name="username"
                          placeholder="<?= t('login.placeholder_username') ?>"
                         value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                <?php
                $idiomas_registro = dbAll("SELECT idiomaId, nombre FROM idiomas ORDER BY nombre ASC");
                if (!empty($idiomas_registro)):
                ?>
                <div class="mb-4">
                  <label class="form-label d-block">Idiomas que hablas</label>
                  <div class="d-flex flex-wrap gap-2" style="max-width:450px">
                    <?php foreach ($idiomas_registro as $idi): ?>
                      <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="idiomas[]" value="<?= $idi['idiomaId'] ?>"
                               id="reg_idioma_<?= $idi['idiomaId'] ?>"
                               <?= in_array($idi['idiomaId'], ($_POST['idiomas'] ?? [])) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="reg_idioma_<?= $idi['idiomaId'] ?>"><?= htmlspecialchars($idi['nombre']) ?></label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>
                <div class="mb-3">
                  <label for="email_signup" class="form-label"><?= t('login.email') ?></label>
                  <input type="text" class="form-control" id="email_signup" name="email_signup"
                         placeholder="correo@ejemplo.com"
                         value="<?= htmlspecialchars($_POST['email_signup'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                  <label for="password_signup" class="form-label"><?= t('login.password') ?> <span class="text-secondary"><?= t('login.pass_info') ?></span></label>
                  <input type="password" class="form-control" id="password_signup" name="password_signup"
                         placeholder="••••••••" required>
                </div>
                <div class="mb-3">
                  <label for="password_confirm" class="form-label"><?= t('login.confirmpass') ?></label>
                  <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                         placeholder="••••••••" required>
                </div>
                <div class="mb-4">
                  <label for="pais_id" class="form-label"><?= t('login.pais') ?> <span class="text-secondary">(para pagos)</span></label>
                  <input type="text" class="form-control mb-2" id="pais_search"
                         placeholder="Buscar país…" oninput="filterCountries()">
                  <select class="form-select" id="pais_id" name="pais_id" size="6">
                    <option value=""><?= t('login.pais_placeholder') ?></option>
                    <?php if (is_array($paises_list)): ?>
                      <?php foreach ($paises_list as $p): ?>
                        <?php if (isset($p['paisId']) && isset($p['nombre'])): ?>
                          <option value="<?= $p['paisId'] ?>"
                                  data-nombre="<?= htmlspecialchars(mb_strtolower($p['nombre'], 'UTF-8')) ?>"
                                  <?= (int)($_POST['pais_id'] ?? 0) === (int)$p['paisId'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                            (<?= htmlspecialchars($p['simbolo'] . ' ' . $p['codigo_moneda']) ?>)
                          </option>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>
                </div>
                <button type="submit" class="btn btn-primary w-100 fw-semibold"><?= t('login.crear') ?></button>
              </form>
              <p class="text-secondary text-center mt-3 small mb-0">
                <?= t('login.already_account') ?>
                <a href="#" class="text-primary" onclick="document.getElementById('signin-tab').click(); return false;"><?= t('login.login_here') ?></a>
              </p>
            </div>

          </div>
        </div>



        <footer class="mastfoot mt-auto mt-4">
          <div class="inner float-end">
            <p class="text-secondary small"><?= t('brand') ?> <?= t('general.footer_text', ['bootstrap' => '<a href="https://getbootstrap.com/" class="text-secondary">Bootstrap</a>', 'author' => '<a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA" class="text-secondary">@RodrigoConejeros</a>']) ?></p>
          </div>
        </footer>

        <?php if (!$adsFreeActive): ?>
        <div style="display:flex; justify-content:center; margin-top:24px;">
          <ins class="adsbygoogle"
               style="display:inline-block; text-align:center; width:320px; height:100px"
               data-ad-client="ca-pub-5524033374028556"
               data-ad-slot="8321266117"></ins>
          <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script>
  function filterCountries() {
    var input = document.getElementById('pais_search');
    var filter = input.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    var select = document.getElementById('pais_id');
    var first = select.options[0];
    // Hide placeholder during search
    first.hidden = filter.length > 0;
    for (var i = 1; i < select.options.length; i++) {
      var opt = select.options[i];
      var name = opt.getAttribute('data-nombre').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      opt.hidden = name.indexOf(filter) === -1;
    }
  }
  // Click on search input focuses the select when there are results
  document.addEventListener('DOMContentLoaded', function() {
    var search = document.getElementById('pais_search');
    var select = document.getElementById('pais_id');
    search.addEventListener('focus', function() {
      select.size = Math.min(select.options.length, 10);
    });
    search.addEventListener('blur', function() {
      setTimeout(function() { select.size = 6; }, 200);
    });
    var rolStudent = document.getElementById('rol-student-label');
    var rolInstructor = document.getElementById('rol-instructor-label');
    if (rolStudent && rolInstructor) {
      rolStudent.addEventListener('click', function() {
        rolStudent.style.background = 'var(--pb)';
        rolInstructor.style.background = 'transparent';
      });
      rolInstructor.addEventListener('click', function() {
        rolInstructor.style.background = 'var(--pb)';
        rolStudent.style.background = 'transparent';
      });
    }
  });
  </script>


  <!-- â”€â”€ Cookie Consent â”€â”€ -->
  <div id="cookie-blocker" class="position-fixed top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center" style="z-index:99999;background:rgba(0,0,0,0.85);">
    <div class="bg-dark border border-secondary rounded-4 p-4 text-center" style="max-width:400px;">
      <h4 class="text-white fw-bold mb-2">Cookies requeridas</h4>
      <p class="text-secondary small mb-4">Debes aceptar las cookies para usar esta plataforma.</p>
      <button id="cookie-retry" class="btn btn-success px-5">Aceptar cookies</button>
    </div>
  </div>
  <div id="cookie-banner" class="position-fixed bottom-0 start-0 w-100 p-3" style="z-index:99998;background:rgba(20,20,20,0.95);border-top:1px solid #444;display:none;">
    <div class="container d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3">
      <p class="mb-0 text-secondary small text-center text-sm-start">
        Esta página utiliza cookies para mejorar tu experiencia. Al hacer clic en "Aceptar", consientes su uso.
      </p>
      <div class="d-flex gap-2 flex-shrink-0">
        <button id="cookie-accept" class="btn btn-success btn-sm px-4">Aceptar</button>
        <button id="cookie-decline" class="btn btn-outline-secondary btn-sm px-4">Rechazar</button>
      </div>
    </div>
  </div>
  <script>
  (function() {
    var c = document.cookie;
    if (c.indexOf('ce_cookie_consent=1') !== -1) return;
    if (c.indexOf('ce_cookie_consent=0') !== -1) {
      document.getElementById('cookie-blocker').classList.remove('d-none');
      document.getElementById('cookie-retry').addEventListener('click', function() {
        document.cookie = 'ce_cookie_consent=1;path=/;max-age=31536000;SameSite=Lax';
        document.getElementById('cookie-blocker').classList.add('d-none');
      });
      return;
    }
    document.getElementById('cookie-banner').style.display = 'block';
    document.getElementById('cookie-accept').addEventListener('click', function() {
      document.cookie = 'ce_cookie_consent=1;path=/;max-age=31536000;SameSite=Lax';
      document.getElementById('cookie-banner').style.display = 'none';
    });
    document.getElementById('cookie-decline').addEventListener('click', function() {
      document.cookie = 'ce_cookie_consent=0;path=/;max-age=31536000;SameSite=Lax';
      location.reload();
    });
  })();
  </script>

  <!-- Modal Oferta 5000 CLP -->
  <?php if (!$adsFreeActive): ?>
  <div id="adsFreeModal" class="position-fixed top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center" style="z-index:100000;background:rgba(0,0,0,0.75);">
    <div class="bg-white rounded-4 p-4 text-center" style="max-width:420px;width:90%;">
      <div class="mb-2" style="font-size:42px;">💎</div>
      <h4 class="fw-bold text-dark mb-1" style="font-size:16px;">Sin anuncios por 1 semana</h4>
      <div class="fw-black mb-1" style="font-size:44px; font-weight:900; color:#f59e0b;">5.000 CLP</div>
      <p class="text-secondary small mb-3">Oferta por tiempo limitado. Paga <strong>5.000 CLP</strong> y no verás anuncios durante 1 semana a partir de hoy.</p>
      <button type="button" id="adsFreePay" class="btn w-100 fw-bold mb-2" style="padding:16px; background:linear-gradient(135deg,#f59e0b,#fbbf24); color:#fff; border:0; font-size:17px; box-shadow:0 6px 16px rgba(245,158,11,.35);">
        <i class="bi bi-credit-card-fill"></i> Pagar 5.000 CLP con MercadoPago
      </button>
      <button type="button" class="btn btn-outline-secondary w-100 btn-sm" onclick="document.getElementById('adsFreeModal').style.display='none';">
        Cancelar
      </button>
    </div>
  </div>
  <?php endif; ?>

  <script>
  var adsFreePayEl = document.getElementById('adsFreePay');
  if (adsFreePayEl) adsFreePayEl.addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creando pago...';
    fetch('landing_api.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({action: 'ads_free_checkout', monto: 5000})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.checkout_url) {
        window.location.href = data.checkout_url;
      } else {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-credit-card-fill"></i> Pagar 5.000 CLP con MercadoPago';
        alert(data.error || 'Error al crear el pago.');
      }
    })
    .catch(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-credit-card-fill"></i> Pagar 5.000 CLP con MercadoPago';
      alert('Error de conexión.');
    });
  });
  </script>

</body>
</html>
