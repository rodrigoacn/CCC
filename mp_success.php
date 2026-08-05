<?php
// ─────────────────────────────────────────────────────────────────────────────
//  MercadoPago Payment Success Page — ClassExpress
// ─────────────────────────────────────────────────────────────────────────────

ob_start();
require 'menu.php';
require 'db.php';
require_once __DIR__ . '/lib/MercadoPagoGateway.php';

// MP returns collection_id and external_reference as query params
$collectionId = (int)($_GET['collection_id'] ?? $_GET['payment_id'] ?? 0);
$extRef       = trim($_GET['external_reference'] ?? '');
$status       = trim($_GET['status'] ?? '');

$session = null;
$user = null;
$fulfilled = false;

if ($extRef) {
    $session = dbOne("SELECT * FROM checkout_sessions WHERE external_reference = ?", [$extRef]);
    if ($session && $session['status'] === 'pending' && $status === 'approved') {
        // Process payment
        $payment = MercadoPagoGateway::processWebhook([
            'type'   => 'payment',
            'action' => 'payment.created',
            'data'   => ['id' => $collectionId],
        ]);
        if ($payment) {
            $session = dbOne("SELECT * FROM checkout_sessions WHERE external_reference = ?", [$extRef]);
            $fulfilled = true;
        }
    } elseif ($session && $session['status'] === 'approved') {
        $fulfilled = true;
    }
}

$userName = '';
$typeLabel = '';
$quantity = 0;
if ($session) {
    $user = dbOne("SELECT nombre FROM usuarios WHERE usuarioId = ?", [(int)$session['usuario_id']]);
    $userName = $user['nombre'] ?? '';
    $typeLabel = $session['type'] === 'credits' ? 'Créditos' : 'MonedasCE';
    $quantity = (int)$session['quantity'];
}
?>
<div class="ml-wrap">
  <div class="ml-wrap-inner" style="text-align:center;padding:60px 20px">
    <?php if ($status === 'approved' && $fulfilled): ?>
      <div style="width:80px;height:80px;border-radius:50%;background:var(--s)22;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
        <i data-feather="check-circle" style="width:40px;height:40px;color:var(--s)"></i>
      </div>
      <h2 style="color:var(--fg);margin-bottom:8px">¡Pago exitoso!</h2>
      <p style="color:var(--sub);font-size:14px;margin-bottom:24px">
        <?= htmlspecialchars($userName) ?>, se agregaron <strong style="color:var(--fg)"><?= $quantity ?> <?= htmlspecialchars($typeLabel) ?></strong> a tu cuenta.
      </p>
    <?php elseif ($status === 'pending'): ?>
      <div style="width:80px;height:80px;border-radius:50%;background:var(--ac)22;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
        <i data-feather="clock" style="width:40px;height:40px;color:var(--ac)"></i>
      </div>
      <h2 style="color:var(--fg);margin-bottom:8px">Pago pendiente</h2>
      <p style="color:var(--sub);font-size:14px;margin-bottom:24px">
        Tu pago está siendo procesado. Se acreditarán tus <?= htmlspecialchars($typeLabel) ?> cuando se confirme.
      </p>
    <?php else: ?>
      <div style="width:80px;height:80px;border-radius:50%;background:var(--d)22;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
        <i data-feather="alert-circle" style="width:40px;height:40px;color:var(--d)"></i>
      </div>
      <h2 style="color:var(--fg);margin-bottom:8px">No pudimos procesar tu pago</h2>
      <p style="color:var(--sub);font-size:14px;margin-bottom:24px">
        Si crees que esto es un error, contacta soporte.
      </p>
    <?php endif; ?>

    <a href="creditos.php" class="ml-btn ml-btn-l" style="text-decoration:none">
      <i data-feather="arrow-left" style="width:18px;height:18px"></i> Volver a mi billetera
    </a>
  </div>
</div>

<script>if (typeof feather !== 'undefined') feather.replace();</script>
<?php require 'footer.php'; ?>
