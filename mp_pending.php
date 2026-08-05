<?php
// ─────────────────────────────────────────────────────────────────────────────
//  MercadoPago Payment Pending Page — ClassExpress
//  Shown for cash payments (e.g., OXXO, Rapipago) that take time to process
// ─────────────────────────────────────────────────────────────────────────────

ob_start();
require 'menu.php';
require 'db.php';

$extRef = trim($_GET['external_reference'] ?? '');
$session = null;
if ($extRef) {
    $session = dbOne("SELECT * FROM checkout_sessions WHERE external_reference = ?", [$extRef]);
}

$typeLabel = $session ? ($session['type'] === 'credits' ? 'Créditos' : 'MonedasCE') : 'tu compra';
?>
<div class="ml-wrap">
  <div class="ml-wrap-inner" style="text-align:center;padding:60px 20px">
    <div style="width:80px;height:80px;border-radius:50%;background:var(--ac)22;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
      <i data-feather="clock" style="width:40px;height:40px;color:var(--ac)"></i>
    </div>
    <h2 style="color:var(--fg);margin-bottom:8px">Pago pendiente</h2>
    <p style="color:var(--sub);font-size:14px;margin-bottom:12px">
      Tu pago de <?= htmlspecialchars($typeLabel) ?> está siendo procesado.
    </p>
    <p style="color:var(--sub);font-size:13px;margin-bottom:24px">
      Si elegiste pago en efectivo (OXXO, Rapipago, etc.), puede tomar hasta 48 horas en acreditarse.
      Te notificaremos por correo cuando se confirme.
    </p>

    <a href="creditos.php" class="ml-btn ml-btn-l" style="text-decoration:none">
      <i data-feather="arrow-left" style="width:18px;height:18px"></i> Volver a mi billetera
    </a>
  </div>
</div>

<script>if (typeof feather !== 'undefined') feather.replace();</script>
<?php require 'footer.php'; ?>
