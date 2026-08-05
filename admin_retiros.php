<?php
ob_start();
require 'menu.php';
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/security_headers.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['usuarioId'];

$user = dbOne("SELECT rol FROM usuarios WHERE usuarioId = ?", [$uid]);
if (!$user || $user['rol'] !== 'admin') {
    header('Location: materias.php'); exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $retiroId = (int)($_POST['retiro_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if ($retiroId && in_array($action, ['approve', 'reject'])) {
        $retiro = dbOne("SELECT * FROM retiros_tokens WHERE id = ?", [$retiroId]);
        if ($retiro && $retiro['estado'] === 'pendiente') {
            $pdo = getDB();
            $pdo->beginTransaction();
            try {
                $newState = $action === 'approve' ? 'completado' : 'rechazado';
                dbExec(
                    "UPDATE retiros_tokens SET estado = ?, admin_note = ?, procesado_por = ?, procesado_at = NOW() WHERE id = ?",
                    [$newState, $note, $uid, $retiroId]
                );
                if ($action === 'reject') {
                    dbExec("UPDATE usuarios SET creditos = creditos + ? WHERE usuarioId = ?",
                        [$retiro['cantidad'], $retiro['usuario_id']]);
                }
                $pdo->commit();
                $msg = "Withdrawal #{$retiroId} {$newState}.";
            } catch (\Exception $e) {
                $pdo->rollBack();
                $msg = "Error: " . $e->getMessage();
            }
        }
    }
    header('Location: admin_retiros.php?msg=' . urlencode($msg));
    exit;
}

$filter = $_GET['filter'] ?? '';
$sql = "SELECT r.*, u.nombre, u.email FROM retiros_tokens r JOIN usuarios u ON u.usuarioId = r.usuario_id";
$params = [];
if ($filter) {
    $sql .= " WHERE r.estado = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY r.created_at DESC LIMIT 100";
$withdrawals = dbAll($sql, $params);

$stats = dbOne("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN estado='rechazado' THEN 1 ELSE 0 END) AS rejected,
    SUM(CASE WHEN estado='pendiente' THEN monto_usd ELSE 0 END) AS pending_usd
    FROM retiros_tokens", []);
?>
<style>
.admin-retiro { max-width: 900px; margin: 0 auto; padding: 24px; padding-top: 80px; }
.admin-retiro h1 { font-size: 24px; font-weight: 900; margin-bottom: 20px; }
.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
.stat-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 16px; text-align: center; }
.stat-box .num { font-size: 24px; font-weight: 900; }
.stat-box .lbl { font-size: 12px; color: var(--tbi); margin-top: 4px; }
.filter-bar { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
.filter-bar a { padding: 6px 16px; border-radius: 20px; background: var(--sf); color: var(--sub); text-decoration: none; font-size: 13px; font-weight: 600; border: 1px solid var(--bd); }
.filter-bar a.active { background: var(--p); color: #fff; border-color: var(--p); }
.w-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.w-table th { text-align: left; padding: 10px 8px; border-bottom: 2px solid var(--bd); color: var(--tbi); font-weight: 600; }
.w-table td { padding: 10px 8px; border-bottom: 1px solid var(--bd); }
.btn-approve { background: #238636; color: #fff; border: none; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; }
.btn-reject { background: #da3633; color: #fff; border: none; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; }
.badge-pending { background: rgba(240,136,62,0.15); color: #f0883e; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-approved { background: rgba(63,185,80,0.15); color: #3fb950; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-rejected { background: rgba(248,81,73,0.15); color: #f85149; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; background: rgba(63,185,80,0.12); color: #3fb950; border: 1px solid rgba(63,185,80,0.25); }
</style>

<div class="admin-retiro">
    <h1>Withdrawal Management</h1>
    <?php if ($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>
    
    <div class="stats-row">
        <div class="stat-box"><div class="num"><?= $stats['total'] ?? 0 ?></div><div class="lbl">Total</div></div>
        <div class="stat-box"><div class="num" style="color:#f0883e;"><?= $stats['pending'] ?? 0 ?></div><div class="lbl">Pending</div></div>
        <div class="stat-box"><div class="num" style="color:#3fb950;"><?= $stats['approved'] ?? 0 ?></div><div class="lbl">Approved</div></div>
        <div class="stat-box"><div class="num" style="color:#f85149;"><?= $stats['rejected'] ?? 0 ?></div><div class="lbl">Rejected</div></div>
        <div class="stat-box"><div class="num" style="color:#58a6ff;"><?= $stats['pending_usd'] ?? 0 ?></div><div class="lbl">Pending (tokens)</div></div>
    </div>

    <div class="filter-bar">
        <a href="?filter=" class="<?= !$filter ? 'active' : '' ?>">All</a>
        <a href="?filter=pendiente" class="<?= $filter === 'pendiente' ? 'active' : '' ?>">Pending</a>
        <a href="?filter=completado" class="<?= $filter === 'completado' ? 'active' : '' ?>">Approved</a>
        <a href="?filter=rechazado" class="<?= $filter === 'rechazado' ? 'active' : '' ?>">Rejected</a>
    </div>

    <?php if (empty($withdrawals)): ?>
        <p style="color:var(--tbi); text-align:center; padding:40px;">No withdrawal requests.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table class="w-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Teacher</th>
                    <th>Tokens</th>
                    <th>Net (CLP)</th>
                    <th>Bank</th>
                    <th>Account</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($withdrawals as $w): ?>
                <tr>
                    <td><?= $w['id'] ?></td>
                    <td><?= htmlspecialchars($w['nombre']) ?><br><span style="font-size:11px;color:var(--tbi);"><?= htmlspecialchars($w['email']) ?></span></td>
                    <td><?= $w['cantidad'] ?></td>
                    <td>$<?= number_format($w['monto_clp']) ?></td>
                    <td><?= htmlspecialchars($w['nombre_banco'] ?: 'PayPal') ?></td>
                    <td><?= htmlspecialchars($w['cuenta_bancaria'] ?: $w['paypal_email'] ?? '') ?></td>
                    <td><?= date('M d, H:i', strtotime($w['created_at'])) ?></td>
                    <td>
                        <?php if ($w['estado'] === 'pendiente'): ?>
                            <span class="badge-pending">Pending</span>
                        <?php elseif ($w['estado'] === 'completado' || $w['estado'] === 'aprobado'): ?>
                            <span class="badge-approved">Completed</span>
                        <?php elseif ($w['estado'] === 'procesando'): ?>
                            <span class="badge-pending">Processing</span>
                        <?php else: ?>
                            <span class="badge-rejected">Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($w['estado'] === 'pendiente'): ?>
                        <form method="POST" style="display:inline; gap:4px; align-items:center;" onsubmit="return confirm('Are you sure?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="retiro_id" value="<?= $w['id'] ?>">
                            <input type="text" name="note" placeholder="Note..." style="width:80px;padding:4px 6px;border:1px solid var(--bd);border-radius:6px;background:var(--sf);color:var(--fg);font-size:11px;">
                            <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                        </form>
                        <?php else: ?>
                            <span style="font-size:11px;color:var(--tbi);"><?= htmlspecialchars($w['admin_note'] ?? '') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
