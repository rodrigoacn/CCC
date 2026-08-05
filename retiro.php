<?php
ob_start();
require 'menu.php';
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/security_headers.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['usuarioId'];

$user = dbOne("SELECT rol, creditos FROM usuarios WHERE usuarioId = ?", [$uid]);
if (!$user || $user['rol'] === 'estudiante' || $user['rol'] === 'student') {
    header('Location: materias.php'); exit;
}

$tokens = (int)($user['creditos'] ?? 0);
$exchangeRate = 950;
$comisionPct = 15;
$minWithdraw = 10;

$msg = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $cantidad = (int)($_POST['cantidad'] ?? 0);
    $cuenta = trim($_POST['cuenta_bancaria'] ?? '');
    $banco = trim($_POST['nombre_banco'] ?? '');
    $tipoCuenta = trim($_POST['tipo_cuenta'] ?? 'corriente');
    $paypalEmail = trim($_POST['paypal_email'] ?? '');
    $metodoRetiro = trim($_POST['metodo_retiro'] ?? 'banco');

    if ($cantidad < $minWithdraw) {
        $error = "Minimum withdrawal is {$minWithdraw} CoinsCE.";
    } elseif ($cantidad > $tokens) {
        $error = "Insufficient balance.";
    } elseif ($metodoRetiro === 'paypal' && empty($paypalEmail)) {
        $error = "PayPal email is required.";
    } elseif ($metodoRetiro === 'banco' && (empty($cuenta) || empty($banco))) {
        $error = "Bank account and bank name are required.";
    } else {
        $pending = dbOne("SELECT COUNT(*) AS cnt FROM retiros_tokens WHERE usuario_id = ? AND estado = 'pendiente'", [$uid]);
        if ($pending && (int)$pending['cnt'] > 0) {
            $error = "You already have a pending withdrawal request.";
        } else {
            $pdo = getDB();
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ? AND creditos >= ?");
                $stmt->execute([$cantidad, $uid, $cantidad]);
                if ($stmt->rowCount() === 0) {
                    $pdo->rollBack();
                    $error = "Insufficient balance.";
                } else {
                    $montoUsd = (float)$cantidad;
                    $comision = round($montoUsd * ($comisionPct / 100), 2);
                    $neto = round($montoUsd - $comision, 2);
                    $montoClp = (int)round($neto * $exchangeRate);

                    $ins = $pdo->prepare(
                        "INSERT INTO retiros_tokens (usuario_id, cantidad, monto_usd, monto_clp, comision, neto_pagar, cuenta_bancaria, nombre_banco, tipo_cuenta, paypal_email, estado)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')"
                    );
                    $ins->execute([$uid, $cantidad, $montoUsd, $montoClp, $comision, $neto, $metodoRetiro === 'banco' ? $cuenta : '', $metodoRetiro === 'banco' ? $banco : 'PayPal', $metodoRetiro === 'banco' ? $tipoCuenta : 'paypal', $paypalEmail]);
                    $pdo->commit();
                    $msg = "Withdrawal request created successfully.";
                    $tokens -= $cantidad;
                }
            } catch (\Exception $e) {
                $pdo->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

$history = dbAll(
    "SELECT * FROM retiros_tokens WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 20",
    [$uid]
);
?>
<style>
.retiro-container { max-width: 700px; margin: 0 auto; padding: 24px; padding-top: 80px; }
.retiro-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; margin-bottom: 24px; }
.retiro-card h2 { font-size: 20px; font-weight: 800; margin-bottom: 6px; }
.retiro-card .subtitle { color: var(--tbi); font-size: 14px; margin-bottom: 20px; }
.token-balance { display: flex; align-items: center; gap: 12px; padding: 16px; background: rgba(32,201,151,0.08); border-radius: 12px; margin-bottom: 20px; }
.token-balance .big { font-size: 28px; font-weight: 900; color: var(--p); }
.token-balance .label { font-size: 13px; color: var(--tbi); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
.form-row.full { grid-template-columns: 1fr; }
.form-row label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: var(--sub); }
.form-row input, .form-row select { width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--bd); background: var(--sf); color: var(--fg); font-size: 15px; font-family: inherit; }
.calc-box { background: rgba(88,166,255,0.06); border: 1px solid rgba(88,166,255,0.15); border-radius: 12px; padding: 16px; margin-bottom: 16px; font-size: 14px; }
.calc-row { display: flex; justify-content: space-between; padding: 4px 0; }
.calc-row.total { border-top: 1px solid var(--bd); margin-top: 8px; padding-top: 8px; font-weight: 700; }
.btn-retiro { width: 100%; padding: 14px; border: none; border-radius: 12px; background: var(--p); color: #fff; font-size: 16px; font-weight: 700; cursor: pointer; font-family: inherit; }
.btn-retiro:disabled { opacity: 0.5; cursor: not-allowed; }
.alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
.alert-success { background: rgba(63,185,80,0.12); color: #3fb950; border: 1px solid rgba(63,185,80,0.25); }
.alert-error { background: rgba(248,81,73,0.12); color: #f85149; border: 1px solid rgba(248,81,73,0.25); }
.history-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.history-table th { text-align: left; padding: 10px 8px; border-bottom: 1px solid var(--bd); color: var(--tbi); font-weight: 600; }
.history-table td { padding: 10px 8px; border-bottom: 1px solid var(--bd); }
.badge-pending { background: rgba(240,136,62,0.15); color: #f0883e; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-approved { background: rgba(63,185,80,0.15); color: #3fb950; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-rejected { background: rgba(248,81,73,0.15); color: #f85149; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
</style>

<div class="retiro-container">
    <div class="retiro-card">
        <h2>Withdraw CoinsCE</h2>
        <p class="subtitle">Convert your earnings to real money in your bank account</p>
        
        <div class="token-balance">
            <div>
                <div class="big"><?= $tokens ?></div>
                <div class="label">Available CoinsCE</div>
            </div>
            <div style="margin-left:auto; text-align:right;">
                <div style="font-size:16px; font-weight:700;">≈ $<?= number_format($tokens * $exchangeRate) ?> CLP</div>
                <div class="label">1 CoinsCE = <?= number_format($exchangeRate) ?> CLP</div>
            </div>
        </div>

        <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <?php if ($tokens >= $minWithdraw): ?>
        <form method="POST" id="retiroForm">
            <?= csrf_field() ?>
            <div class="form-row">
                <div>
                    <label>CoinsCE to withdraw</label>
                    <input type="number" name="cantidad" id="retiroAmount" min="<?= $minWithdraw ?>" max="<?= $tokens ?>" value="<?= $minWithdraw ?>" oninput="updateCalc()">
                </div>
                <div>
                    <label>Withdrawal method</label>
                    <select name="metodo_retiro" id="metodoRetiro" onchange="toggleMethod()">
                        <option value="banco">Bank transfer</option>
                        <option value="paypal">PayPal</option>
                    </select>
                </div>
            </div>
            <div id="bankFields">
                <div class="form-row">
                    <div>
                        <label>Bank name</label>
                        <select name="nombre_banco">
                            <option value="">Select bank...</option>
                            <option value="Banco Estado">BancoEstado</option>
                            <option value="Banco de Chile">Banco de Chile</option>
                            <option value="Banco Santander">Banco Santander</option>
                            <option value="Banco BCI">Banco BCI</option>
                            <option value="Banco Scotiabank">Banco Scotiabank</option>
                            <option value="Banco Itaú">Banco Itaú</option>
                            <option value="Banco Falabella">Banco Falabella</option>
                            <option value="Banco Ripley">Banco Ripley</option>
                            <option value="Banco Consorcio">Banco Consorcio</option>
                            <option value="Transbank">Transbank</option>
                            <option value="MACH">MACH</option>
                            <option value="Cuenta RUT">Cuenta RUT</option>
                            <option value="Otro">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div>
                        <label>Account number (CBU / CVU / RUT)</label>
                        <input type="text" name="cuenta_bancaria" placeholder="12345678">
                    </div>
                    <div>
                        <label>Account type</label>
                        <select name="tipo_cuenta">
                            <option value="corriente">Corriente</option>
                            <option value="ahorro">Ahorro</option>
                            <option value="rut">RUT</option>
                            <option value="cvu">CVU</option>
                        </select>
                    </div>
                </div>
            </div>
            <div id="paypalFields" style="display:none;">
                <div class="form-row">
                    <div>
                        <label>PayPal email</label>
                        <input type="email" name="paypal_email" placeholder="your@email.com">
                    </div>
                </div>
            </div>

            <div class="calc-box" id="calcBox">
                <div class="calc-row"><span>Gross amount</span><span id="calcGross">-</span></div>
                <div class="calc-row"><span>Commission (<?= $comisionPct ?>%)</span><span id="calcFee" style="color:#f85149;">-</span></div>
                <div class="calc-row"><span>Exchange rate</span><span>1 CoinsCE = <?= number_format($exchangeRate) ?> CLP</span></div>
                <div class="calc-row total"><span>Net to receive (CLP)</span><span id="calcNet" style="color:#3fb950;">-</span></div>
            </div>

            <button type="submit" class="btn-retiro">Request Withdrawal</button>
        </form>
        <?php else: ?>
        <div class="alert alert-error">Minimum withdrawal is <?= $minWithdraw ?> CoinsCE. You currently have <?= $tokens ?> CoinsCE.</div>
        <?php endif; ?>
    </div>

    <div class="retiro-card">
        <h2 style="margin-bottom:16px;">Withdrawal History</h2>
        <?php if (empty($history)): ?>
            <p style="color:var(--tbi); text-align:center; padding:20px;">No withdrawals yet.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Tokens</th>
                        <th>Net (CLP)</th>
                        <th>Bank</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($h['created_at'])) ?></td>
                        <td><?= $h['cantidad'] ?></td>
                        <td>$<?= number_format($h['monto_clp']) ?></td>
                        <td><?= htmlspecialchars($h['nombre_banco'] ?: 'PayPal') ?></td>
                        <td>
                            <?php if ($h['estado'] === 'pendiente'): ?>
                                <span class="badge-pending">Pending</span>
                            <?php elseif ($h['estado'] === 'completado' || $h['estado'] === 'aprobado'): ?>
                                <span class="badge-approved">Completed</span>
                            <?php elseif ($h['estado'] === 'procesando'): ?>
                                <span class="badge-pending">Processing</span>
                            <?php else: ?>
                                <span class="badge-rejected">Rejected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleMethod() {
    const m = document.getElementById('metodoRetiro').value;
    document.getElementById('bankFields').style.display = m === 'banco' ? '' : 'none';
    document.getElementById('paypalFields').style.display = m === 'paypal' ? '' : 'none';
}
function updateCalc() {
    const amt = parseInt(document.getElementById('retiroAmount').value) || 0;
    const rate = <?= $exchangeRate ?>;
    const fee = <?= $comisionPct ?>;
    const gross = amt;
    const commission = Math.round(gross * fee / 100 * 100) / 100;
    const net = Math.round((gross - commission) * 100) / 100;
    const clp = Math.round(net * rate);
    document.getElementById('calcGross').textContent = amt + ' CoinsCE';
    document.getElementById('calcFee').textContent = '-' + commission + ' CoinsCE';
    document.getElementById('calcNet').textContent = '$' + clp.toLocaleString() + ' CLP';
}
updateCalc();
</script>
