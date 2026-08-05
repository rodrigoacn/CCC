<?php
ob_start();
require 'menu.php';
require 'db.php';
require 'lib/BusinessLogic.php';
require_once __DIR__ . '/lib/csrf.php';

require_once __DIR__ . '/lib/security_headers.php';
if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['usuarioId'];

$msg = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $type   = $_POST['type'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);

    require_once __DIR__ . '/lib/MercadoPagoGateway.php';
    try {
        if ($type === 'credits') {
            $valid = [10, 25, 50, 100, 200];
            $qty = (int)$amount;
            if (!in_array($qty, $valid) && ($qty < 1 || $qty > 1000)) {
                $error = t('creditos.invalid_amount');
            } else {
                $checkout = MercadoPagoGateway::createPreference($uid, 'credits', $qty, $amount);
                header('Location: ' . $checkout['checkout_url']);
                exit;
            }
        } elseif ($type === 'tokens') {
            $packages = [10 => 10, 25 => 25, 50 => 50, 100 => 100, 200 => 200];
            $cant = (int)$amount;
            if (!isset($packages[$cant])) {
                $error = t('creditos.invalid_package');
            } else {
                $checkout = MercadoPagoGateway::createPreference($uid, 'tokens', $cant, (float)$packages[$cant]);
                header('Location: ' . $checkout['checkout_url']);
                exit;
            }
        }
    } catch (\Exception $e) {
        $error = t('creditos.checkout_error') . $e->getMessage();
    }
}

$user = dbOne(
    "SELECT u.creditos, u.tokens FROM usuarios u WHERE u.usuarioId = :id",
    ['id' => $uid]
);
$balance = (float)($user['creditos'] ?? 0);
$tokens  = (float)($user['tokens'] ?? 0);

$history = dbAll(
    "SELECT p.pagoId AS pid, -p.monto_local AS monto, 'class' AS tipo, cp.titulo AS ref, p.created_at
     FROM pagos p JOIN sesiones_clase sc ON sc.sesionId = p.sesionId JOIN clases_programadas cp ON cp.claseId = sc.claseId
     WHERE p.estudianteId = :id
     UNION ALL
     SELECT ct.id AS pid, ct.monto_usd AS monto, 'tokens' AS tipo, ct.cantidad AS ref, ct.created_at
     FROM compras_tokens ct WHERE ct.usuario_id = :id2
     ORDER BY created_at DESC LIMIT 20",
    ['id' => $uid, 'id2' => $uid]
);

$creditPacks = [10, 25, 50, 100, 200];
?>
<div class="ml-wrap">
  <div style="padding:0;background:var(--p);margin:0 0 0;border-radius:0 0 28px 28px;padding:20px 24px 28px">
    <div style="color:rgba(255,255,255,.8);font-size:14px;margin-bottom:8px"><?= t('creditos.wallet') ?></div>
    <div style="font-size:56px;font-weight:700;color:#fff;line-height:64px"><?= number_format($balance, 0) ?></div>
    <div style="color:rgba(255,255,255,.7);font-size:14px;margin-bottom:20px"><?= t('credits.current_balance') ?></div>
    <div style="display:flex;gap:10px">
      <button class="ml-btn ml-btn-l" onclick="openModal('credModal')"><i data-feather="plus" style="width:18px;height:18px"></i> <?= t('creditos.recharge') ?></button>
    </div>
  </div>

  <div class="ml-wrap-inner">
  <?php if ($msg): ?><div style="margin:16px 20px 0;padding:10px 14px;border-radius:12px;background:var(--s)22;color:var(--s);font-size:13px;text-align:center"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($error): ?><div style="margin:16px 20px 0;padding:10px 14px;border-radius:12px;background:var(--d)22;color:var(--d);font-size:13px;text-align:center"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <div style="margin:20px 20px 0;padding:16px;border-radius:16px;background:var(--sf);display:flex;justify-content:space-between;align-items:center">
    <div>
      <div style="font-size:12px;color:var(--sub)"><?= t('general.tokens') ?></div>
      <div style="font-size:24px;font-weight:700;color:var(--fg)"><?= number_format($tokens) ?></div>
    </div>
    <i data-feather="dollar-sign" style="width:24px;height:24px;color:var(--s)"></i>
  </div>

  <div style="font-size:18px;font-weight:700;color:var(--fg);padding:20px 20px 4px"><?= t('credits.recent_purchases') ?></div>

  <?php if (empty($history)): ?>
  <div style="text-align:center;padding-top:40px">
    <i data-feather="inbox" style="width:36px;height:36px;color:var(--tbi)"></i>
    <div style="color:var(--sub);margin-top:10px"><?= t('credits.no_purchases') ?></div>
  </div>
  <?php else: ?>
    <?php foreach ($history as $h): 
      $monto = (float)$h['monto'];
      $isPos = $monto > 0;
      $desc = $h['tipo'] === 'class'
          ? t('creditos.history_class', ['title' => $h['ref']])
          : t('creditos.history_tokens', ['qty' => $h['ref']]);
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--bd)">
      <div style="width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:<?= $isPos ? 'var(--s)' : 'var(--d)' ?>22">
        <i data-feather="<?= $isPos ? 'arrow-down-left' : 'arrow-up-right' ?>" style="width:18px;height:18px;color:<?= $isPos ? 'var(--s)' : 'var(--d)' ?>"></i>
      </div>
      <div style="flex:1">
        <div style="font-size:14px;font-weight:500;color:var(--fg)" class="truncate"><?= htmlspecialchars($desc) ?></div>
        <div style="font-size:12px;color:var(--sub)"><?= date('d/m/Y', strtotime($h['created_at'])) ?></div>
      </div>
      <div style="font-size:16px;font-weight:700;color:<?= $isPos ? 'var(--s)' : 'var(--d)' ?>">
        <?= $isPos ? '+' : '' ?><?= number_format($monto, 0) ?> <?= t('tokens.unit_label') ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
</div>

<div class="modal-overlay" id="credModal">
  <div class="modal-card">
    <div style="font-size:20px;font-weight:700;color:var(--fg);margin-bottom:4px"><?= t('creditos.recharge_credits') ?></div>
    <div style="font-size:13px;color:var(--sub);margin-bottom:20px"><?= t('creditos.credit_rate') ?></div>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px">
      <?php foreach ($creditPacks as $p): ?>
      <form method="POST" style="margin:0">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="credits">
        <button type="submit" name="amount" value="<?= $p ?>" style="width:68px;height:68px;border-radius:16px;border:0;background:var(--pb);display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer">
          <span style="font-size:20px;font-weight:700;color:var(--p)"><?= $p ?></span>
          <span style="font-size:12px;color:var(--sub)">cr.</span>
        </button>
      </form>
      <?php endforeach; ?>
    </div>
    <form method="POST" style="display:flex;align-items:center;border-radius:14px;overflow:hidden;background:var(--sf)">
      <?= csrf_field() ?>
      <input type="hidden" name="type" value="credits">
      <input type="number" name="amount" placeholder="<?= t('creditos.custom_amount') ?>" style="flex:1;padding:12px 16px;border:0;background:none;color:var(--fg);font-size:15px;outline:0;font-family:inherit">
      <button type="submit" style="padding:12px 20px;border:0;background:var(--p);cursor:pointer"><i data-feather="check" style="width:20px;height:20px;color:#fff"></i></button>
    </form>
    <button style="display:block;width:100%;text-align:center;background:none;border:0;color:var(--sub);padding:16px 0 0;cursor:pointer;font-size:14px" onclick="closeModal('credModal')"><?= t('general.cancelar') ?></button>
  </div>
</div>

<style>
.modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;display:none;align-items:flex-end;justify-content:center}
.modal-overlay.show{display:flex}
.modal-card{background:var(--sf);border-radius:28px 28px 0 0;padding:28px;width:100%;max-width:480px;max-height:75vh;overflow-y:auto}
.truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px}
</style>
<script>
function openModal(id){document.getElementById(id).classList.add('show')}
function closeModal(id){document.getElementById(id).classList.remove('show')}
document.addEventListener('click',function(e){if(e.target.classList.contains('modal-overlay'))e.target.classList.remove('show')});
</script>
<?php require 'footer.php'; ?>
