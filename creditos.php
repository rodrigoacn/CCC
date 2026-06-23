<?php
ob_start();
require 'menu.php';
require 'db.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['usuarioId'];

$msg   = '';
$error = '';

// Handle top-up POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $valid  = [10, 25, 50, 100, 200];
    if (!in_array((int)$amount, $valid)) {
        $error = 'Please select a valid top-up amount.';
    } else {
        dbExec(
            "UPDATE usuarios SET creditos = creditos + :amt WHERE usuarioId = :id",
            ['amt' => $amount, 'id' => $uid]
        );
        $msg = "✅ {$amount} credits added to your account!";
    }
}

$user = dbOne(
    "SELECT u.nombre, u.creditos, pa.simbolo, pa.codigo_moneda, pa.tasa_usd
     FROM usuarios u LEFT JOIN paises pa ON pa.paisId = u.pais_id
     WHERE u.usuarioId = :id",
    ['id' => $uid]
);

$creditos    = number_format((float)($user['creditos'] ?? 0), 2);
$tasa        = (float)($user['tasa_usd'] ?? 1);
$simbolo     = $user['simbolo'] ?? '$';
$moneda      = $user['codigo_moneda'] ?? 'USD';

// History: last 20 credit movements (join payments + load from log)
$pagos = dbAll(
    "SELECT p.created_at, p.monto_local, p.simbolo_local, p.moneda_local,
            cp.titulo AS clase, 'payment' AS tipo
     FROM pagos p
     JOIN sesiones_clase sc ON sc.sesionId = p.sesionId
     JOIN clases_programadas cp ON cp.claseId = sc.claseId
     WHERE p.estudianteId = :id
     ORDER BY p.created_at DESC LIMIT 20",
    ['id' => $uid]
);
?>

  <div class="container mt-10 pb-5" style="max-width:720px">

    <h2 class="text-white mb-1">My Credits</h2>
    <p class="text-secondary mb-4">Credits are used to join classes. 1 credit = $1 USD.</p>

    <?php if ($msg): ?>
      <div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Balance card -->
    <div class="card bg-dark border-secondary mb-4">
      <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3 p-4">
        <div>
          <div class="text-secondary small mb-1">Current Balance</div>
          <div class="display-5 fw-bold text-white"><?= $creditos ?> <span class="fs-5 text-secondary">credits</span></div>
          <div class="text-secondary small mt-1">
            ≈ <?= $simbolo ?><?= number_format((float)($user['creditos'] ?? 0) * $tasa, 2) ?> <?= $moneda ?>
          </div>
        </div>
        <div>
          <span class="badge bg-secondary fs-6 px-3 py-2">
            <i class="bi bi-coin me-1"></i> 1 credit = $1 USD
          </span>
        </div>
      </div>
    </div>

    <!-- Top-up form -->
    <div class="card bg-dark border-secondary mb-4">
      <div class="card-body p-4">
        <h5 class="text-white mb-3">Add Credits</h5>
        <form method="POST" action="creditos.php">
          <div class="row g-2 mb-3">
            <?php foreach ([10, 25, 50, 100, 200] as $opt): ?>
              <div class="col-6 col-sm-4 col-md-auto">
                <input type="radio" class="btn-check" name="amount" id="amt-<?= $opt ?>"
                       value="<?= $opt ?>" <?= $opt === 25 ? 'checked' : '' ?>>
                <label class="btn btn-outline-secondary w-100" for="amt-<?= $opt ?>">
                  <strong><?= $opt ?></strong> <span class="text-secondary small">cr</span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
          <button type="submit" class="btn btn-secondary px-4 fw-semibold">
            <i class="bi bi-plus-circle me-1"></i> Add Credits (demo)
          </button>
          <p class="text-secondary small mt-2 mb-0">
            Demo mode — no real money charged. Connect Stripe to enable real payments.
          </p>
        </form>
      </div>
    </div>

    <!-- Payment history -->
    <div class="card bg-dark border-secondary">
      <div class="card-header border-secondary text-secondary small fw-bold py-2 px-4">
        RECENT PAYMENTS
      </div>
      <?php if (empty($pagos)): ?>
        <div class="card-body text-secondary text-center py-4">No payments yet.</div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($pagos as $p): ?>
            <li class="list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center px-4 py-3">
              <div>
                <div class="text-white small fw-semibold"><?= htmlspecialchars($p['clase']) ?></div>
                <div class="text-secondary" style="font-size:12px"><?= date('M j, Y g:i A', strtotime($p['created_at'])) ?></div>
              </div>
              <span class="text-danger fw-bold">
                −<?= htmlspecialchars($p['simbolo_local']) ?><?= number_format((float)$p['monto_local'], 2) ?>
                <span class="text-secondary small"><?= htmlspecialchars($p['moneda_local']) ?></span>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

  </div>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
