<?php
session_start();

$host    = 'localhost';
$db      = 'ce';
$user    = 'root';
$pass    = 'v6h470fdz0';
$charset = 'utf8mb4';
$dsn     = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$status  = 'error';
$message = 'Invalid or expired verification link.';

$token = trim($_GET['token'] ?? '');

if ($token !== '') {
    try {
        $pdo  = new PDO($dsn, $user, $pass, $options);
        $stmt = $pdo->prepare("SELECT usuarioId, nombre, verificado FROM usuarios WHERE token_verificacion = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            $message = 'Verification link not found. It may have already been used or is invalid.';
        } elseif ($row['verificado']) {
            $status  = 'already';
            $message = 'Your email is already verified. You can sign in.';
        } else {
            $upd = $pdo->prepare("UPDATE usuarios SET verificado = 1, token_verificacion = '' WHERE usuarioId = :id");
            $upd->execute(['id' => $row['usuarioid']]);
            $status  = 'success';
            $message = 'Email verified successfully! You can now sign in.';
        }
    } catch (PDOException $e) {
        $message = 'Database unavailable. Please try again later.';
    }
}

$resultados = [
    "ultimoContenido"     => "",
    "ultimaClase"         => "",
    "ultimaSala"          => "",
    "esVisibleContenidos" => "hidden",
    "esVisibleClases"     => "hidden",
    "esVisibleSala"       => "hidden",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassExpress – Email Verification</title>
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
      <a class="navbar-brand ms-2" href="materias.php">ClassExpress</a>
  </nav>

  <div class="container mt-10">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-6 col-lg-5">

        <div class="text-center mb-4">
          <h2 class="text-light fw-bold">Email Verification</h2>
        </div>

        <div class="verify-card p-4 text-center">
          <?php if ($status === 'success'): ?>
            <div class="mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" fill="#6ea86e" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
              </svg>
            </div>
            <h5 class="text-light"><?= htmlspecialchars($message) ?></h5>
            <a href="login.php" class="btn btn-secondary mt-3">Go to Sign In</a>

          <?php elseif ($status === 'already'): ?>
            <div class="mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" fill="#adb5bd" class="bi bi-info-circle-fill" viewBox="0 0 16 16">
                <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
              </svg>
            </div>
            <h5 class="text-light"><?= htmlspecialchars($message) ?></h5>
            <a href="login.php" class="btn btn-secondary mt-3">Go to Sign In</a>

          <?php else: ?>
            <div class="mb-3">
              <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" fill="#c0392b" class="bi bi-x-circle-fill" viewBox="0 0 16 16">
                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/>
              </svg>
            </div>
            <h5 class="text-light"><?= htmlspecialchars($message) ?></h5>
            <a href="login.php" class="btn btn-dark border-secondary mt-3">Back to Login</a>
          <?php endif; ?>
        </div>

        <footer class="mastfoot mt-4">
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
