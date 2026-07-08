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

    error_log("LOGIN DEBUG - Email recibido: '" . $email . "'");
    error_log("LOGIN DEBUG - Password recibido: '" . $password . "'");

    if (!$email || !$password) {
        $error_login = 'Por favor completa todos los campos.';
    } else {
        $row = dbOne("SELECT usuarioId, nombre, rol, password, verificado, creditos FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);
        error_log("LOGIN DEBUG - Usuario encontrado: " . ($row ? 'Sí (ID: ' . $row['usuarioId'] . ')' : 'No'));
        if ($row === null && !getDB()) {
            $error_login = 'Base de datos no disponible. Intenta de nuevo más tarde.';
        } elseif (!$row) {
            $error_login = 'No se encontró una cuenta con ese correo. Regístrate.';
        } elseif (!password_verify($password, $row['password'])) {
            $error_login = 'Contraseña incorrecta.';
        } elseif (!$row['verificado']) {
            $error_login = 'Por favor verifica tu correo antes de iniciar sesión. Revisa tu bandeja de entrada o solicita un nuevo enlace abajo.';
        } else {
            $_SESSION['usuarioId'] = $row['usuarioId'];
            $_SESSION['nombre']    = $row['nombre'];
            $_SESSION['rol']       = $row['rol'];
            $_SESSION['creditos']  = (int)($row['creditos'] ?? 0);
            $pending = dbOne(
                "SELECT sesionId FROM sesiones_clase
                 WHERE estudianteId = :u AND pagado = 0 AND fin IS NOT NULL
                 ORDER BY fin ASC LIMIT 1",
                ['u' => $row['usuarioId']]
            );
            if ($pending) {
                header('Location: pago.php?sesion=' . $pending['sesionid']);
                exit;
            }
            $rol  = $row['rol'];
            $dest = ($rol !== 'estudiante' && $rol !== 'student') ? 'dashboard_profesor.php' : 'materias.php';
            header('Location: ' . $dest);
            exit;
        }
    }
}

// ─── RESEND VERIFICATION ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_verify') {
    $active_tab = 'signin';
    $email = trim($_POST['email'] ?? '');
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL) && getDB()) {
        $row = dbOne("SELECT usuarioId, nombre, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);
        if ($row && !$row['verificado']) {
            $token = bin2hex(random_bytes(32));
            dbExec(
                "UPDATE usuarios SET token_verificacion = :token WHERE usuarioId = :id",
                ['token' => $token, 'id' => $row['usuarioid']]
            );
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $link = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/verify.php?token=' . urlencode($token);
            require_once 'email_helper.php';
            ceSendVerify($email, $row['nombre'], $link);
        }
    }
    $success_msg = 'Si ese correo está pendiente de verificación, se envió un nuevo enlace. Revisa tu bandeja de entrada y spam.';
}

// ─── SIGN UP ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'signup') {
    $active_tab = 'signup';
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = trim($_POST['email_signup'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password_signup'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';
    $referido = trim($_POST['referido_por'] ?? '');
    $pais_id  = (int)($_POST['pais_id'] ?? 0) ?: null;
    $rol      = in_array($_POST['rol'] ?? 'student', ['student', 'instructor'], true) ? $_POST['rol'] : 'student';

    if (!$nombre || !$email || !$username || !$password || !$confirm) {
        $error_signup = 'Por favor completa todos los campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_signup = 'Por favor ingresa una dirección de correo válida.';
    } elseif (strlen($username) < 3) {
        $error_signup = 'El nombre de usuario debe tener al menos 3 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error_signup = 'El nombre de usuario solo puede contener letras, números y guiones bajos.';
    } elseif (strlen($password) < 6) {
        $error_signup = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($password !== $confirm) {
        $error_signup = 'Las contraseñas no coinciden.';
    } else {
        if (!getDB()) {
            $error_signup = 'Base de datos no disponible. Intenta de nuevo más tarde.';
        } else {
            $existing = dbOne("SELECT usuarioId, verificado FROM usuarios WHERE email = :email LIMIT 1", ['email' => $email]);
            $existing_username = dbOne("SELECT usuarioId FROM usuarios WHERE username = :username LIMIT 1", ['username' => $username]);

            if ($existing_username) {
                $error_signup = 'Ese nombre de usuario ya está en uso. Elige otro.';
            } elseif ($existing) {
                if ($existing['verificado']) {
                    $error_signup = 'Ese correo ya está registrado. Inicia sesión.';
                } else {
                    $token = bin2hex(random_bytes(32));
                    dbExec(
                        "UPDATE usuarios SET token_verificacion = :token WHERE usuarioId = :id",
                        ['token' => $token, 'id' => $existing['usuarioid']]
                    );
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $link = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/verify.php?token=' . urlencode($token);
                    require_once 'email_helper.php';
                    $sent = ceSendVerify($email, $nombre, $link);
                    if ($sent) {
                        $success_msg = 'Ese correo está pendiente de verificación. Enviamos un nuevo enlace a tu bandeja de entrada.';
                    } else {
                        $error_signup = 'No se pudo enviar el correo de verificación. Contacta al soporte.';
                    }
                    $active_tab  = 'signin';
                }
            } else {
                // Validar referido si se proporcionó
                $referido_id = null;
                if ($referido) {
                    $ref_user = dbOne("SELECT usuarioId, num_referidos FROM usuarios WHERE username = :username LIMIT 1", ['username' => $referido]);
                    if (!$ref_user) {
                        $error_signup = 'El nombre de usuario del referido no existe.';
                    } elseif (($ref_user['num_referidos'] ?? 0) >= 5) {
                        $error_signup = 'Ese usuario ya alcanzó el máximo de 5 referidos.';
                    } else {
                        $referido_id = $ref_user['usuarioid'];
                    }
                }

                if (!$error_signup) {
                    $hash  = password_hash($password, PASSWORD_DEFAULT);
                    $token = bin2hex(random_bytes(32));

                    dbExec(
                        "INSERT INTO usuarios (nombre, email, password, rol, verificado, token_verificacion, pais_id, creditos, username, referido_por, ultimoContenido, ultimaClase, ultimaSala)
                         VALUES (:nombre, :email, :password, :rol, 0, :token, :pais_id, 100, :username, :referido, '', '', '')",
                        ['nombre' => $nombre, 'email' => $email, 'password' => $hash, 'rol' => $rol, 'token' => $token, 'pais_id' => $pais_id, 'username' => $username, 'referido' => $referido]
                    );

                    // Si hay referido, actualizar contador y agregar a tabla referidos
                    if ($referido_id) {
                        $new_user_id = getDB()->lastInsertId();
                        dbExec("UPDATE usuarios SET num_referidos = num_referidos + 1 WHERE usuarioId = :id", ['id' => $referido_id]);
                        dbExec("INSERT INTO referidos (referidor_username, referido_usuarioId) VALUES (:username, :uid)", ['username' => $referido, 'uid' => $new_user_id]);
                        
                        // Dar 1 minuto espectador gratis al referido por cada referido nuevo
                        dbExec("UPDATE usuarios SET minutos_espectador_gratis = minutos_espectador_gratis + 1 WHERE usuarioId = :id", ['id' => $referido_id]);
                    }

                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $link = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/verify.php?token=' . urlencode($token);
                    require_once 'email_helper.php';
                    $sent = ceSendVerify($email, $nombre, $link);
                    
                    if ($sent) {
                        $success_msg = '¡Cuenta creada! Revisa tu correo y haz clic en el enlace de verificación antes de iniciar sesión.';
                    } else {
                        $error_signup = 'No se pudo enviar el correo de verificación. Contacta al soporte.';
                    }
                    $active_tab  = 'signin';
                }
            }
        }
    }
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
            header('Location: pago.php?sesion=' . $pending['sesionid']);
            exit;
        }
    }
    header('Location: ' . ($rol !== 'estudiante' && $rol !== 'student' ? 'dashboard_profesor.php' : 'materias.php'));
    exit;
}

// Load LATAM countries for signup dropdown
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
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassExpress – Iniciar sesión</title>
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
          <p class="text-secondary">Tu plataforma de aprendizaje</p>
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
                      type="button" role="tab">Iniciar sesión</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link <?= $active_tab === 'signup' ? 'active' : '' ?>"
                      id="signup-tab" data-bs-toggle="tab" data-bs-target="#signup"
                      type="button" role="tab">Registrarse</button>
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
                  <label for="email" class="form-label">Correo electrónico</label>
                  <input type="email" class="form-control" id="email" name="email"
                         placeholder="correo@ejemplo.com"
                         value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="mb-4">
                  <div class="d-flex justify-content-between align-items-baseline">
                    <label for="password" class="form-label">Contraseña</label>
                    <a href="forgot_password.php" class="text-secondary small">¿Olvidaste tu contraseña?</a>
                  </div>
                  <input type="password" class="form-control" id="password" name="password"
                         placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-secondary w-100 fw-semibold">Iniciar sesión</button>
              </form>
              <p class="text-secondary text-center mt-3 small mb-0">
                ¿No tienes una cuenta?
                <a href="#" class="text-light" onclick="document.getElementById('signup-tab').click(); return false;">Regístrate aquí</a>
              </p>
              <form method="POST" action="login.php" class="mt-3 pt-3 border-top border-secondary">
                <input type="hidden" name="action" value="resend_verify">
                <p class="text-secondary small mb-2">¿No recibiste el correo de verificación?</p>
                <div class="input-group input-group-sm">
                  <input type="email" class="form-control" name="email" placeholder="correo@ejemplo.com"
                         value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                  <button type="submit" class="btn btn-outline-secondary">Reenviar enlace</button>
                </div>
              </form>
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
                  <label for="nombre" class="form-label">Nombre completo</label>
                  <input type="text" class="form-control" id="nombre" name="nombre"
                         placeholder="Tu nombre"
                         value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                  <label for="username" class="form-label">Nombre de usuario <span class="text-secondary">(único, letras/números/_)</span></label>
                  <input type="text" class="form-control" id="username" name="username"
                         placeholder="tu_usuario"
                         value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                  <label for="referido_por" class="form-label">Referido por <span class="text-secondary">(opcional)</span></label>
                  <input type="text" class="form-control" id="referido_por" name="referido_por"
                         placeholder="nombre_usuario_quien_te_refirió"
                         value="<?= htmlspecialchars($_POST['referido_por'] ?? '') ?>">
                </div>
                <div class="mb-3">
                  <label class="form-label">Registrarme como</label>
                  <select class="form-select" name="rol" id="rol">
                    <option value="student" <?= (($_POST['rol'] ?? 'student') === 'student') ? 'selected' : '' ?>>Estudiante</option>
                    <option value="instructor" <?= (($_POST['rol'] ?? '') === 'instructor') ? 'selected' : '' ?>>Profesor</option>
                  </select>
                </div>
                <div class="mb-3">
                  <label for="email_signup" class="form-label">Correo electrónico</label>
                  <input type="email" class="form-control" id="email_signup" name="email_signup"
                         placeholder="correo@ejemplo.com"
                         value="<?= htmlspecialchars($_POST['email_signup'] ?? '') ?>" required>
                </div>
                <div class="mb-3">
                  <label for="password_signup" class="form-label">Contraseña <span class="text-secondary">(mín. 6 caracteres)</span></label>
                  <input type="password" class="form-control" id="password_signup" name="password_signup"
                         placeholder="••••••••" required>
                </div>
                <div class="mb-3">
                  <label for="password_confirm" class="form-label">Confirmar contraseña</label>
                  <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                         placeholder="••••••••" required>
                </div>
                <div class="mb-4">
                  <label for="pais_id" class="form-label">País <span class="text-secondary">(para pagos LATAM)</span></label>
                  <select class="form-select" id="pais_id" name="pais_id">
                    <option value="">— Selecciona tu país —</option>
                    <?php if (is_array($paises_list)): ?>
                      <?php foreach ($paises_list as $p): ?>
                        <?php if (isset($p['paisId']) && isset($p['nombre'])): ?>
                          <option value="<?= $p['paisId'] ?>"
                                  <?= (int)($_POST['pais_id'] ?? 0) === (int)$p['paisId'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                            (<?= htmlspecialchars($p['simbolo'] . ' ' . $p['codigo_moneda']) ?>)
                          </option>
                        <?php endif; ?>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>
                </div>
                <button type="submit" class="btn btn-dark border-secondary w-100 fw-semibold">Crear cuenta</button>
              </form>
              <p class="text-secondary text-center mt-3 small mb-0">
                ¿Ya tienes una cuenta?
                <a href="#" class="text-light" onclick="document.getElementById('signin-tab').click(); return false;">Inicia sesión aquí</a>
              </p>
            </div>

          </div>
        </div>

        <footer class="mastfoot mt-auto mt-4">
          <div class="inner float-end">
            <p class="text-secondary small">ClassExpress hecho con <a href="https://getbootstrap.com/" class="text-secondary">Bootstrap</a>, por <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA" class="text-secondary">@RodrigoConejeros</a>.</p>
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
