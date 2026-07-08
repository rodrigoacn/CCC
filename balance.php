<?php
ob_start();
require 'menu.php';
require 'db.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }

$uid = (int)$_SESSION['usuarioId'];
$rol = $_SESSION['rol'] ?? 'estudiante';

// Get user data
$user = dbOne(
    "SELECT u.*, p.nombre AS pais_nombre, p.simbolo AS pais_simbolo, p.codigo_moneda AS pais_moneda
     FROM usuarios u
     LEFT JOIN paises p ON p.paisId = u.pais_id
     WHERE u.usuarioId = :id",
    ['id' => $uid]
);

if (!$user) { header('Location: login.php'); exit; }

$tokens = (float)($user['tokens'] ?? 0);
$creditos = (float)($user['creditos'] ?? 0);

// Get transaction history
$transacciones = dbAll(
    "SELECT ct.*, 
            CASE 
                WHEN ct.metodo_pago = 'clase_terminada' THEN 'Ganancia de clase'
                WHEN ct.metodo_pago LIKE '%stripe%' THEN 'Tarjeta de crédito'
                WHEN ct.metodo_pago LIKE '%paypal%' THEN 'PayPal'
                ELSE ct.metodo_pago
            END AS metodo_descripcion
     FROM compras_tokens ct
     WHERE ct.usuario_id = :uid
     ORDER BY ct.created_at DESC
     LIMIT 20",
    ['uid' => $uid]
);

// Get withdrawal history (for teachers)
$retiros = [];
if ($rol !== 'estudiante' && $rol !== 'student') {
    $retiros = dbAll(
        "SELECT * FROM retiros_tokens 
         WHERE usuario_id = :uid 
         ORDER BY created_at DESC 
         LIMIT 10",
        ['uid' => $uid]
    );
}

$isTeacher = ($rol !== 'estudiante' && $rol !== 'student');
?>

<div class="container mt-5">
  <div class="row">
    <div class="col-md-8 mx-auto">
      
      <!-- Balance Card -->
      <div class="card shadow mb-4">
        <div class="card-header bg-success text-white py-4">
          <h3 class="mb-0">
            <i class="bi bi-wallet2 me-2"></i>
            Balance de Tokens
          </h3>
        </div>
        <div class="card-body p-5">
          
          <div class="text-center mb-4">
            <h5 class="text-muted mb-3">Tokens Disponibles</h5>
            <div class="display-1 fw-bold text-success">
              <?= number_format($tokens, 2) ?>
            </div>
            <p class="text-muted">monedasCE</p>
          </div>

          <?php if (!$isTeacher): ?>
          <!-- Student info -->
          <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Tus tokens se usan para pagar clases. 1 token = 1 USD.
          </div>
          <?php else: ?>
          <!-- Teacher info -->
          <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Tus tokens se generan cuando terminas clases. Puedes retirarlos a tu cuenta bancaria.
          </div>
          <?php endif; ?>

        </div>
      </div>

      <?php if ($isTeacher): ?>
      <!-- Withdrawal Form -->
      <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white py-3">
          <h5 class="mb-0">
            <i class="bi bi-arrow-down-circle me-2"></i>
            Retirar Tokens
          </h5>
        </div>
        <div class="card-body p-4">
          <form id="withdrawalForm">
            <div class="mb-3">
              <label for="withdrawAmount" class="form-label">Cantidad a retirar (tokens)</label>
              <input type="number" class="form-control" id="withdrawAmount" 
                     min="1" max="<?= $tokens ?>" step="0.01" required
                     placeholder="Ej: 50.00">
              <small class="text-muted">Máximo disponible: <?= number_format($tokens, 2) ?> tokens</small>
            </div>
            <div class="mb-3">
              <label for="bankAccount" class="form-label">Cuenta bancaria</label>
              <input type="text" class="form-control" id="bankAccount" required
                     placeholder="Ej: CL123456789">
            </div>
            <div class="mb-3">
              <label for="bankName" class="form-label">Nombre del banco</label>
              <input type="text" class="form-control" id="bankName" required
                     placeholder="Ej: Banco de Chile">
            </div>
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-send me-2"></i>Solicitar Retiro
            </button>
          </form>
        </div>
      </div>

      <!-- Withdrawal History -->
      <?php if (!empty($retiros)): ?>
      <div class="card shadow mb-4">
        <div class="card-header bg-light py-3">
          <h5 class="mb-0">
            <i class="bi bi-clock-history me-2"></i>
            Historial de Retiros
          </h5>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Cantidad</th>
                  <th>Cuenta</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($retiros as $r): ?>
                <tr>
                  <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                  <td><?= number_format($r['cantidad'], 2) ?> tokens</td>
                  <td><?= htmlspecialchars($r['cuenta_bancaria']) ?></td>
                  <td>
                    <span class="badge bg-<?= $r['estado'] === 'completado' ? 'success' : ($r['estado'] === 'pendiente' ? 'warning' : 'danger') ?>">
                      <?= ucfirst($r['estado']) ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Transaction History -->
      <div class="card shadow">
        <div class="card-header bg-light py-3">
          <h5 class="mb-0">
            <i class="bi bi-clock-history me-2"></i>
            Historial de Transacciones
          </h5>
        </div>
        <div class="card-body p-0">
          <?php if (!empty($transacciones)): ?>
          <div class="table-responsive">
            <table class="table table-striped mb-0">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Tipo</th>
                  <th>Cantidad</th>
                  <th>Método</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($transacciones as $t): ?>
                <tr>
                  <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
                  <td>
                    <span class="badge bg-<?= $t['metodo_pago'] === 'clase_terminada' ? 'success' : 'primary' ?>">
                      <?= $t['metodo_descripcion'] ?>
                    </span>
                  </td>
                  <td><?= number_format($t['cantidad'], 2) ?> tokens</td>
                  <td><?= number_format($t['monto_usd'], 2) ?> USD</td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="text-center p-4 text-muted">
            No hay transacciones registradas
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
document.getElementById('withdrawalForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const cantidad = parseFloat(document.getElementById('withdrawAmount').value);
  const cuenta = document.getElementById('bankAccount').value;
  const banco = document.getElementById('bankName').value;
  
  if (cantidad <= 0) {
    alert('La cantidad debe ser mayor a 0');
    return;
  }
  
  if (cantidad > <?= $tokens ?>) {
    alert('No tienes suficientes tokens');
    return;
  }
  
  try {
    const res = await fetch('api_mobile.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        action: 'withdraw_tokens',
        cantidad: cantidad,
        cuenta_bancaria: cuenta,
        nombre_banco: banco
      })
    });
    
    const data = await res.json();
    
    if (data.ok) {
      alert('Solicitud de retiro enviada exitosamente');
      location.reload();
    } else {
      alert('Error: ' + (data.error || 'No se pudo procesar el retiro'));
    }
  } catch (error) {
    alert('Error de conexión: ' + error.message);
  }
});
</script>

<?php
require 'footer.php';
?>
