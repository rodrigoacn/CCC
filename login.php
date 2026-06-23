<?php
session_start();
require_once 'db.php';


$error_login  = '';
$error_signup = '';
$success_msg  = '';
$active_tab   = 'signin';

// ─── SIGN IN ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signin') {
    $active_tab = 'signin';
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error_login = 'Please fill in all fields.';
    } else {
        $row = dbOne("SELECT usuarioId, nombre, rol, password, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);
        if ($row === null && !getDB()) {
            $error_login = 'Database unavailable. Please try again later.';
        } elseif (!$row) {
            $error_login = 'No account found with that email. Please sign up.';
        } elseif (!password_verify($password, $row['password'])) {
            $error_login = 'Incorrect password.';
        } elseif (!$row['verificado']) {
            $error_login = 'Please verify your email before logging in. Check your inbox.';
        } else {
            $_SESSION['usuarioId'] = $row['usuarioid'];
            $_SESSION['nombre']    = $row['nombre'];
            $_SESSION['rol']       = $row['rol'];
            $dest = ($row['rol'] !== 'student') ? 'dashboard_profesor.php' : 'materias.php';
            header('Location: ' . $dest);
            exit;
        }
    }
}

// ─── SIGN UP ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup') {
    $active_tab = 'signup';
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = trim($_POST['email_signup'] ?? '');
    $password = $_POST['password_signup'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $pais_id  = (int)($_POST['pais_id'] ?? 0) ?: null;

    if (!$nombre || !$email || !$password || !$confirm) {
        $error_signup = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_signup = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error_signup = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error_signup = 'Passwords do not match.';
    } else {
        if (!getDB()) {
            $error_signup = 'Database unavailable. Please try again later.';
        } else {
            $existing = dbOne("SELECT usuarioId, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);

            if ($existing) {
                if ($existing['verificado']) {
                    $error_signup = 'That email is already registered. Please sign in.';
                } else {
                    $error_signup = 'That email is registered but not yet verified. Check your inbox.';
                }
            } else {
                $hash  = password_hash($password, PASSWORD_DEFAULT);
                $token = bin2hex(random_bytes(32));

                dbExec(
                    "INSERT INTO usuarios (nombre, email, password, verificado, token_verificacion, pais_id, ultimoContenido, ultimaClase, ultimaSala)
                     VALUES (:nombre, :email, :password, 0, :token, :pais_id, '', '', '')",
                    ['nombre' => $nombre, 'email' => $email, 'password' => $hash, 'token' => $token, 'pais_id' => $pais_id]
                );

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
                $link     = $host_url . '/verify.php?token=' . urlencode($token);

                $subject = 'ClassExpress – Verify your email';
                $body    = "Hello {$nombre},\n\nThank you for signing up to ClassExpress!\n\nPlease click the link below to verify your email address:\n\n{$link}\n\nIf you did not create an account, you can ignore this email.\n\nClassExpress Team";
                $headers = "From: noreply@classexpress.app\r\nX-Mailer: PHP/" . phpversion();

                mail($email, $subject, $body, $headers);

                $success_msg = "Account created! A verification link has been sent to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox (and spam folder) to activate your account.";
                $active_tab  = 'signup';
            }
        }
    }
}

// Redirect already-logged-in users
if (isset($_SESSION['usuarioId'])) {
    header('Location: materias.php');
    exit;
}

// Load LATAM countries for signup dropdown
$paises_list = dbAll("SELECT paisId, nombre, codigo_moneda, simbolo FROM paises ORDER BY nombre ASC");

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
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassExpress – Login</title>
    <link rel="stylesheet" href="./styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="icon" href="favico.svg" type="image/svg+xml">
    <style>
        body { background-color: #1a1a1a; }
        .login-card {
            background-color: #2b2b2b;
            border: 1px solid #444;
            border-radius: 0.75rem;
        }
        .nav-tabs .nav-link {
            color: #adb5bd;
            border-color: transparent;
        }
        .nav-tabs .nav-link.active {
            background-color: #3a3a3a;
            border-color: #555 #555 #3a3a3a;
            color: #f8f9fa;
        }
        .nav-tabs {
            border-bottom-color: #555;
        }
        .form-control {
            background-color: #3a3a3a;
            border-color: #555;
            color: #f8f9fa;
        }
        .form-control:focus {
            background-color: #444;
            border-color: #888;
            color: #f8f9fa;
            box-shadow: 0 0 0 0.2rem rgba(180,180,180,.15);
        }
        .form-control::placeholder { color: #888; }
        .form-label { color: #ccc; }
        .tab-content { background-color: #3a3a3a; border-radius: 0 0 0.5rem 0.5rem; }
        .brand-title { letter-spacing: 0.05em; }
    </style>
</head>
<body>
  <nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
      <a class="navbar-brand ms-2" href="materias.php">ClassExpress</a>
  </nav>

  <div class="container mt-10">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-7 col-lg-5">

        <div class="text-center mb-4">
          <h2 class="text-light brand-title fw-bold">ClassExpress</h2>
          <p class="text-secondary">Your academic progress tracker</p>
        </div>

        <div class="login-card p-4">

          <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?= $success_msg ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <!-- Tabs -->
          <ul class="nav nav-tabs mb-0" id="authTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link <?= $active_tab === 'signin' ? 'active' : '' ?>"
                      id="signin-tab" data-bs-toggle="tab" data-bs-target="#signin"
                      type="button" role="tab">Sign In</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link <?= $active_tab === 'signup' ? 'active' : '' ?>"
                      id="signup-tab" data-bs-toggle="tab" data-bs-target="#signup"
                      type="button" role="tab">Sign Up</button>
            </li>
          </ul>

          <div class="tab-content p-3" id="authTabContent">

            <!-- ── SIGN IN ── -->
            <div class="tab-pane fade <?= $active_tab === 'signin' ? 'show active' : '' ?>"
                 id="signin" role="tabpanel">
              <?php if ($error_login): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error_login) ?></div>
              <?php endif; ?>
              <form method="POST" action="login.php" novalidate>
                <input type="hidden" name="action" value="signin">
                <div class="mb-3">
                  <label for="email" class="form-label">Email address</label>
                  <input type="email" class="form-control" id="email" name="email"
                         placeholder="you@example.com"
                         value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="mb-4">
                  <div class="d-flex justify-content-between align-items-baseline">
                    <label for="password" class="form-label">Password</label>
                    <a href="forgot_password.php" class="text-secondary small">Forgot your password?</a>
                  </div>
                  <input type="password" class="form-control" id="password" name="password"
                         placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-secondary w-100 fw-semibold">Sign In</button>
              </form>
              <p class="text-secondary text-center mt-3 small mb-0">
                Don't have an account?
                <a href="#" class="text-light" onclick="document.getElementById('signup-tab').click(); return false;">Sign up here</a>
              </p>
            </div>

            <!-- ── SIGN UP ── -->
            <div class="tab-pane fade <?= $active_tab === 'signup' ? 'show active' : '' ?>"
                 id="signup" role="tabpanel">
              <?php if ($error_signup): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error_signup) ?></div>
              <?php endif; ?>
              <form method="POST" action="login.php" novalidate>
                <input type="hidden" name="action" value="signup">
                <div class="mb-3">
                  <label for="nombre" class="form-label">Full name</label>
                  <input type="text" class="form-control" id="nombre" name="nombre"
                         placeholder="Jane Doe"
                         value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                  <label for="email_signup" class="form-label">Email address</label>
                  <input type="email" class="form-control" id="email_signup" name="email_signup"
                         placeholder="you@example.com"
                         value="<?= htmlspecialchars($_POST['email_signup'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                  <label for="password_signup" class="form-label">Password <span class="text-secondary">(min. 6 chars)</span></label>
                  <input type="password" class="form-control" id="password_signup" name="password_signup"
                         placeholder="••••••••" required>
                </div>
                <div class="mb-3">
                  <label for="password_confirm" class="form-label">Confirm password</label>
                  <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                         placeholder="••••••••" required>
                </div>
                <div class="mb-4">
                  <label for="pais_id" class="form-label">Country <span class="text-secondary">(for LATAM payments)</span></label>
                  <select class="form-select" id="pais_id" name="pais_id">
                    <option value="">— Select your country —</option>
                    <?php foreach ($paises_list as $p): ?>
                      <option value="<?= $p['paisid'] ?>"
                              <?= (int)($_POST['pais_id'] ?? 0) === (int)$p['paisid'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nombre']) ?>
                        (<?= htmlspecialchars($p['simbolo'] . ' ' . $p['codigo_moneda']) ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button type="submit" class="btn btn-dark border-secondary w-100 fw-semibold">Create Account</button>
              </form>
              <p class="text-secondary text-center mt-3 small mb-0">
                Already have an account?
                <a href="#" class="text-light" onclick="document.getElementById('signin-tab').click(); return false;">Sign in here</a>
              </p>
            </div>

          </div>
        </div>

        <footer class="mastfoot mt-auto mt-4">
          <div class="inner float-end">
            <p class="text-secondary small">ClassExpress done <a href="https://getbootstrap.com/" class="text-secondary">Bootstrap</a>, by <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA" class="text-secondary">@RodrigoConejeros</a>.</p>
          </div>
        </footer>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script type="text/javascript" src="./presentacion/odp_ajax.js"></script>
  <script type="text/javascript" src="./presentacion/js/scripts.js"></script>
</body>
</html>
