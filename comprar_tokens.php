<?php
ob_start();
require 'menu.php';
require 'db.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }

$uid = (int)$_SESSION['usuarioId'];

$user = dbOne(
    "SELECT usuarioId, nombre, tokens, creditos, username, num_referidos, minutos_gratis
     FROM usuarios WHERE usuarioId = :id",
    ['id' => $uid]
);

if (!$user) { header('Location: login.php'); exit; }

// Token packages
$packages = [
    ['tokens' => 100, 'price_usd' => 1.99, 'name' => 'Básico'],
    ['tokens' => 500, 'price_usd' => 7.99, 'name' => 'Estándar'],
    ['tokens' => 1000, 'price_usd' => 14.99, 'name' => 'Premium'],
    ['tokens' => 2500, 'price_usd' => 29.99, 'name' => 'Profesional'],
    ['tokens' => 5000, 'price_usd' => 49.99, 'name' => 'Empresarial'],
];

// Handle purchase
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_idx = (int)($_POST['package'] ?? -1);
    if (isset($packages[$package_idx])) {
        $pkg = $packages[$package_idx];
        
        try {
            // Record purchase
            dbExec(
                "INSERT INTO compras_tokens (usuarioId, cantidad, monto_usd, metodo, estado)
                 VALUES (:uid, :cant, :monto, 'stripe', 'completado')",
                ['uid' => $uid, 'cant' => $pkg['tokens'], 'monto' => $pkg['price_usd']]
            );
            
            // Add tokens to user
            dbExec(
                "UPDATE usuarios SET tokens = tokens + :cant WHERE usuarioId = :id",
                ['cant' => $pkg['tokens'], 'id' => $uid]
            );
            
            $success = true;
            $user['tokens'] += $pkg['tokens'];
        } catch (PDOException $e) {
            $error = 'Error al procesar la compra. Intenta de nuevo.';
        }
    } else {
        $error = 'Paquete inválido.';
    }
}
?>

  <div class="container mt-10">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-8 col-lg-6">

        <div class="card bg-dark border-secondary p-4">
          <h2 class="text-light mb-3">Comprar MonedasCE</h2>
          <p class="text-secondary mb-4">
            Saldo actual: <strong class="text-white"><?= number_format($user['tokens'] ?? 0, 2) ?></strong> tokens
            <br>
            Minutos gratis: <strong class="text-success"><?= $user['minutos_gratis'] ?? 0 ?></strong>
          </p>

          <?php if ($success): ?>
            <div class="alert alert-success">
              ¡Compra exitosa! Tus tokens han sido agregados.
            </div>
          <?php endif; ?>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <h5 class="text-light mb-3">Elige un paquete:</h5>
          
          <form method="POST" action="comprar_tokens.php">
            <?php foreach ($packages as $idx => $pkg): ?>
              <div class="card bg-black border-secondary mb-3">
                <div class="card-body p-3">
                  <div class="form-check">
                    <input class="form-check-input" type="radio" name="package" 
                           id="pkg<?= $idx ?>" value="<?= $idx ?>" <?= $idx === 2 ? 'checked' : '' ?>>
                    <label class="form-check-label d-flex justify-content-between align-items-center" for="pkg<?= $idx ?>">
                      <div>
                        <span class="text-white fw-bold"><?= $pkg['name'] ?></span>
                        <div class="text-secondary small"><?= number_format($pkg['tokens']) ?> tokens</div>
                      </div>
                      <div class="text-end">
                        <span class="text-white fw-bold">$<?= number_format($pkg['price_usd'], 2) ?></span>
                        <div class="text-secondary small">USD</div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-secondary w-100 fw-bold py-2 mt-3">
              Comprar con Stripe
            </button>
          </form>

          <div class="mt-4 pt-3 border-top border-secondary">
            <h6 class="text-light mb-2">Sistema de Referidos</h6>
            <p class="text-secondary small mb-2">
              Referidos: <strong class="text-white"><?= $user['num_referidos'] ?? 0 ?>/5</strong>
            </p>
            <?php if (($user['num_referidos'] ?? 0) < 5): ?>
              <p class="text-success small">
                ¡Referir personas te da 1 minuto gratis por cada referido!
              </p>
              <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control bg-black border-secondary text-white" 
                       value="<?= htmlspecialchars($user['username'] ?? '') ?>" readonly>
                <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($user['username'] ?? '') ?>')">
                  Copiar
                </button>
              </div>
              <p class="text-secondary small">
                Comparte tu nombre de usuario para que te refieran.
              </p>
            <?php else: ?>
              <p class="text-warning small">
                Has alcanzado el máximo de 5 referidos.
              </p>
            <?php endif; ?>
          </div>
        </div>

        <footer class="mastfoot mt-4">
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
