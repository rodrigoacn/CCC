<?php
ob_start();
require 'menu.php';
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';

require_once __DIR__ . '/lib/security_headers.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['usuarioId'];

// Verify teacher role
$me = dbOne(
    "SELECT u.nombre, u.rol, u.calificacion, u.num_resenas, u.avatar,
            pa.nombre AS pais, pa.simbolo, pa.codigo_moneda
     FROM usuarios u
     LEFT JOIN paises pa ON pa.paisId = u.pais_id
     WHERE u.usuarioId = :id",
    ['id' => $uid]
);
if (!$me || ($_navRol ?? $me['rol']) === 'student' || ($_navRol ?? $me['rol']) === 'estudiante') {
    header('Location: materias.php'); exit;
}

// â”€â”€ ACTIONS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action  = $_POST['action']  ?? '';
    $claseId = (int)($_POST['claseId'] ?? 0);

    if ($action === 'deactivate' && $claseId) {
        dbExec("UPDATE clases_programadas SET activa=0 WHERE claseId=:c AND instructorId=:i",
               ['c' => $claseId, 'i' => $uid]);
    } elseif ($action === 'activate' && $claseId) {
        dbExec("UPDATE clases_programadas SET activa=1 WHERE claseId=:c AND instructorId=:i",
               ['c' => $claseId, 'i' => $uid]);
    } elseif ($action === 'delete' && $claseId) {
        dbExec("DELETE FROM clases_programadas WHERE claseId=:c AND instructorId=:i",
               ['c' => $claseId, 'i' => $uid]);
    }
    header('Location: dashboard_profesor.php'); exit;
}

// â”€â”€ STATS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$stats = dbOne(
    "SELECT
        COUNT(DISTINCT cp.claseId)                                  AS total_clases,
        SUM(cp.activa)                                              AS clases_activas,
        COUNT(DISTINCT sc.sesionId)                                 AS total_sesiones,
        COUNT(DISTINCT CASE WHEN sc.pagado=1 THEN sc.sesionId END)  AS sesiones_pagadas,
        COALESCE(SUM(CASE WHEN p.estado='completado' THEN p.monto_usd END), 0) AS ganancias_usd
     FROM clases_programadas cp
     LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId
     LEFT JOIN pagos           p  ON p.sesionId = sc.sesionId
     WHERE cp.instructorId = :id",
    ['id' => $uid]
);

// Live students right now (students currently in a room linked to this instructor)
$live = dbOne(
    "SELECT COUNT(*) AS n
     FROM participantes_sala ps
     JOIN salas s ON s.salaId = ps.salaId
     JOIN clases_programadas cp ON cp.salaId = s.salaId
     WHERE cp.instructorId = :id",
    ['id' => $uid]
) ?? ['n' => 0];

// Earnings breakdown by currency
$earningsByCurrency = dbAll(
    "SELECT p.moneda_local, p.simbolo_local,
            SUM(p.monto_local) AS total, COUNT(*) AS num_pagos
     FROM pagos p
     WHERE p.profesorId = :id AND p.estado = 'completado'
     GROUP BY p.moneda_local, p.simbolo_local
     ORDER BY total DESC",
    ['id' => $uid]
);

// â”€â”€ ACTIVE CLASSES â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$clases = dbAll(
    "SELECT cp.claseId, cp.titulo, cp.activa, cp.precio_min, cp.precio_max,
            cp.precio_base, cp.codigo_moneda, cp.alumnos_min, cp.alumnos_max,
            cp.created_at, m.nombre AS materia,
            COUNT(sc.sesionId) AS num_sesiones,
            SUM(CASE WHEN sc.pagado=1 THEN 1 ELSE 0 END) AS num_pagados
     FROM clases_programadas cp
     LEFT JOIN materias m ON m.materiaId = cp.materiaId
     LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId
     WHERE cp.instructorId = :id
     GROUP BY cp.claseId, cp.titulo, cp.activa, cp.precio_min, cp.precio_max,
              cp.precio_base, cp.codigo_moneda, cp.alumnos_min, cp.alumnos_max,
              cp.created_at, m.nombre
     ORDER BY cp.activa DESC, cp.created_at DESC",
    ['id' => $uid]
);

// â”€â”€ RECENT SESSIONS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$sesiones = dbAll(
    "SELECT sc.sesionId, sc.inicio, sc.fin, sc.duracion_min,
            sc.monto_local, sc.moneda_local, sc.simbolo_local, sc.pagado,
            u.nombre AS estudiante, cp.titulo AS clase,
            m.nombre AS materia
     FROM sesiones_clase sc
     JOIN clases_programadas cp ON cp.claseId = sc.claseId
     JOIN usuarios u             ON u.usuarioId = sc.estudianteId
     LEFT JOIN materias m        ON m.materiaId = cp.materiaId
     WHERE cp.instructorId = :id
     ORDER BY sc.inicio DESC
     LIMIT 15",
    ['id' => $uid]
);

// â”€â”€ HELPERS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function fmtMoney($sym, $amount) {
    return $sym . number_format((float)$amount, 2);
}
function fmtDur($min) {
    if (!$min) return '”';
    if ($min < 60) return $min . ' min';
    return floor($min/60) . 'h ' . ($min % 60) . 'm';
}
?>

<div class="container-fluid mt-10 pb-5 px-4">

  <!-- â”€â”€ Page header â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h1 class="text-white mb-1 fs-3 fw-bold"><?= t('dashboard.title') ?></h1>
      <p class="text-secondary mb-0">
        <?= t('dashboard.welcome', ['name' => '<span class="text-white fw-semibold">' . htmlspecialchars($me['nombre']) . '</span>']) ?>
        · <span class="badge bg-secondary text-capitalize"><?= htmlspecialchars($me['rol']) ?></span>
        <?php if ($me['calificacion'] > 0): ?>
          · â­ <?= number_format((float)$me['calificacion'], 1) ?> (<?= (int)$me['num_resenas'] ?> <?= t('general.reviews') ?>)
        <?php endif; ?>
        <?php if ($me['pais']): ?>
          · 📍 <?= htmlspecialchars($me['pais']) ?>
        <?php endif; ?>
      </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="crear_clase.php"  class="btn btn-primary fw-semibold"><?= t('dashboard.post_class') ?></a>
      <a href="oferta_clase.php" class="btn btn-outline-secondary"><?= t('dashboard.quick_offer_btn') ?></a>
      <a href="buscar.php"       class="btn btn-outline-secondary"><?= t('dashboard.find_students') ?></a>
      <a href="perfil.php"       class="btn btn-outline-secondary"><?= t('dashboard.my_profile') ?></a>
    </div>
  </div>

  <!-- â”€â”€ Stat cards â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="row g-3 mb-4">

    <div class="col-6 col-md-3">
      <div class="card bg-dark border-secondary h-100">
        <div class="card-body">
          <div class="text-secondary small text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.07em;"><?= t('dashboard.active_classes') ?></div>
          <div class="fs-2 fw-bold text-white"><?= (int)($stats['clases_activas'] ?? 0) ?></div>
          <div class="text-secondary small"><?= t('dashboard.total_posted', ['count' => (int)($stats['total_clases'] ?? 0)]) ?></div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card bg-dark border-secondary h-100">
        <div class="card-body">
          <div class="text-secondary small text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.07em;"><?= t('dashboard.live_students') ?></div>
          <div class="fs-2 fw-bold <?= $live['n'] > 0 ? 'text-success' : 'text-white' ?>">
            <?= (int)$live['n'] ?>
          </div>
          <div class="text-secondary small"><?= t('dashboard.sessions_total', ['count' => (int)($stats['total_sesiones'] ?? 0)]) ?></div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card bg-dark border-secondary h-100">
        <div class="card-body">
          <div class="text-secondary small text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.07em;"><?= t('dashboard.paid_sessions') ?></div>
          <div class="fs-2 fw-bold text-white"><?= (int)($stats['sesiones_pagadas'] ?? 0) ?></div>
          <div class="text-secondary small"><?= t('dashboard.paid_out_of', ['count' => (int)($stats['total_sesiones'] ?? 0)]) ?></div>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card bg-dark border-secondary h-100">
        <div class="card-body">
          <div class="text-secondary small text-uppercase mb-1" style="font-size:.7rem;letter-spacing:.07em;"><?= t('dashboard.total_earnings') ?></div>
          <div class="fs-2 fw-bold text-success">$<?= number_format((float)($stats['ganancias_usd'] ?? 0), 2) ?></div>
          <div class="text-secondary small"><?= t('dashboard.usd_across') ?></div>
        </div>
      </div>
    </div>

  </div>

  <!-- â”€â”€ Earnings by currency + quick links â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <?php if (!empty($earningsByCurrency)): ?>
  <div class="row g-3 mb-4">
    <div class="col-12">
      <div class="card bg-dark border-secondary">
        <div class="card-header border-secondary d-flex align-items-center gap-2">
          <span class="text-white fw-semibold"><?= t('dashboard.earnings_by_currency') ?></span>
          <span class="text-secondary small ms-auto"><?= t('dashboard.currency_hint') ?></span>
        </div>
        <div class="card-body py-2">
          <div class="d-flex flex-wrap gap-3">
            <?php foreach ($earningsByCurrency as $e): ?>
            <div class="border border-secondary rounded px-3 py-2 text-center" style="min-width:130px;">
              <div class="text-secondary small"><?= htmlspecialchars($e['moneda_local']) ?></div>
              <div class="fs-5 fw-bold text-white">
                <?= fmtMoney($e['simbolo_local'], $e['total']) ?>
              </div>
              <div class="text-secondary" style="font-size:.72rem;"><?= (int)$e['num_pagos'] ?> <?= $e['num_pagos'] != 1 ? t('dashboard.payments_count_plural', ['count' => (int)$e['num_pagos']]) : t('dashboard.payments_count', ['count' => (int)$e['num_pagos']]) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- â”€â”€ My Classes â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-header border-secondary d-flex align-items-center justify-content-between">
      <span class="text-white fw-semibold"><?= t('dashboard.my_classes') ?></span>
      <a href="crear_clase.php" class="btn btn-sm btn-primary"><?= t('dashboard.new_class') ?></a>
    </div>
    <?php if (empty($clases)): ?>
    <div class="card-body text-center py-5">
      <p class="text-secondary mb-3"><?= t('dashboard.no_classes') ?></p>
      <a href="crear_clase.php" class="btn btn-primary"><?= t('dashboard.first_class') ?></a>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-light table-bordered table-hover mb-0">
        <thead>
          <tr>
            <th class="text-secondary ps-3" style="width:2rem;"><?= t('dashboard.status') ?></th>
            <th class="text-secondary"><?= t('dashboard.title_header') ?></th>
            <th class="text-secondary d-none d-md-table-cell"><?= t('dashboard.subject') ?></th>
            <th class="text-secondary d-none d-lg-table-cell"><?= t('dashboard.price') ?></th>
            <th class="text-secondary d-none d-lg-table-cell"><?= t('dashboard.students') ?></th>
            <th class="text-secondary d-none d-md-table-cell"><?= t('dashboard.sessions') ?></th>
            <th class="text-secondary d-none d-lg-table-cell"><?= t('dashboard.posted') ?></th>
            <th class="text-secondary text-end pe-3"><?= t('dashboard.actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($clases as $c): ?>
          <tr>
            <td class="ps-3">
              <?php if ($c['activa']): ?>
                <span class="badge bg-success"><?= t('dashboard.live_badge') ?></span>
              <?php else: ?>
                <span class="badge bg-secondary"><?= t('dashboard.off_badge') ?></span>
              <?php endif; ?>
            </td>
            <td class="ps-3 fw-semibold"><?= htmlspecialchars($c['titulo'] ?: t('dashboard.quick_offer')) ?></td>
            <td class="text-secondary d-none d-md-table-cell"><?= htmlspecialchars($c['materia'] ?? '”') ?></td>
            <td class="text-secondary d-none d-lg-table-cell">
              <?php
                $sym = htmlspecialchars($me['simbolo'] ?? '$');
                if ($c['precio_min'] > 0 && $c['precio_max'] > 0)
                    echo $sym . number_format($c['precio_min'],2) . ' “ ' . $sym . number_format($c['precio_max'],2);
                elseif ($c['precio_base'] > 0)
                    echo $sym . number_format($c['precio_base'],2);
                else echo '”';
              ?>
              <span class="text-secondary small"> <?= htmlspecialchars($c['codigo_moneda']) ?></span>
            </td>
            <td class="text-secondary d-none d-lg-table-cell">
              <?= (int)$c['alumnos_min'] ?>“<?= (int)$c['alumnos_max'] ?>
            </td>
            <td class="d-none d-md-table-cell">
              <span class="fw-semibold"><?= (int)$c['num_sesiones'] ?></span>
              <?php if ($c['num_pagados'] > 0): ?>
                <span class="text-secondary small">(<?= (int)$c['num_pagados'] ?> <?= t('dashboard.paid') ?>)</span>
              <?php endif; ?>
            </td>
            <td class="text-secondary small d-none d-lg-table-cell">
              <?= date('d M Y', strtotime($c['created_at'])) ?>
            </td>
            <td class="text-end pe-3">
              <div class="d-flex gap-1 justify-content-end">
                <a href="pre_sala.php?clase=<?= $c['claseId'] ?>&from=dashboard" class="btn btn-sm btn-outline-primary"><?= t('dashboard.join_btn') ?></a>
                <form method="POST" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="claseId" value="<?= $c['claseId'] ?>">
                  <?php if ($c['activa']): ?>
                    <input type="hidden" name="action" value="deactivate">
                    <button type="submit" class="btn btn-sm btn-outline-warning"><?= t('dashboard.pause_btn') ?></button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="activate">
                    <button type="submit" class="btn btn-sm btn-outline-success"><?= t('dashboard.activate_btn') ?></button>
                  <?php endif; ?>
                </form>
                <form method="POST" class="d-inline"
                      onsubmit="return confirm('<?= t('dashboard.delete_confirm') ?>')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="claseId" value="<?= $c['claseId'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i data-feather="trash-2" style="width:14px;height:14px"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- â”€â”€ Recent Sessions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div class="card bg-dark border-secondary mb-4">
    <div class="card-header border-secondary">
      <span class="text-white fw-semibold"><?= t('dashboard.recent_sessions') ?></span>
      <span class="text-secondary small ms-2"><?= t('dashboard.last_15') ?></span>
    </div>
    <?php if (empty($sesiones)): ?>
    <div class="card-body text-center py-4">
      <p class="text-secondary mb-0"><?= t('dashboard.no_sessions') ?></p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-light table-bordered table-hover mb-0">
        <thead>
          <tr>
            <th class="text-secondary ps-3"><?= t('dashboard.student') ?></th>
            <th class="text-secondary d-none d-md-table-cell"><?= t('dashboard.class') ?></th>
            <th class="text-secondary d-none d-md-table-cell"><?= t('dashboard.subject') ?></th>
            <th class="text-secondary"><?= t('dashboard.duration') ?></th>
            <th class="text-secondary"><?= t('dashboard.amount') ?></th>
            <th class="text-secondary"><?= t('general.status') ?></th>
            <th class="text-secondary d-none d-lg-table-cell"><?= t('dashboard.date') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sesiones as $s): ?>
          <tr>
            <td class="ps-3 fw-semibold"><?= htmlspecialchars($s['estudiante']) ?></td>
            <td class="text-secondary small d-none d-md-table-cell"><?= htmlspecialchars($s['clase'] ?: t('dashboard.quick_offer')) ?></td>
            <td class="text-secondary small d-none d-md-table-cell"><?= htmlspecialchars($s['materia'] ?? '”') ?></td>
            <td class="text-secondary"><?= fmtDur($s['duracion_min']) ?></td>
            <td>
              <?php if ($s['monto_local']): ?>
                <span class="fw-semibold"><?= fmtMoney($s['simbolo_local'] ?? '$', $s['monto_local']) ?></span>
                <span class="text-secondary small"> <?= htmlspecialchars($s['moneda_local']) ?></span>
              <?php else: ?>
                <span class="text-secondary">”</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($s['pagado']): ?>
                <span class="badge bg-success"><?= t('dashboard.paid_badge') ?></span>
              <?php elseif ($s['fin']): ?>
                <span class="badge bg-warning text-dark"><?= t('dashboard.unpaid_badge') ?></span>
              <?php else: ?>
                <span class="badge bg-primary"><?= t('dashboard.live_badge_session') ?></span>
              <?php endif; ?>
            </td>
            <td class="text-secondary small d-none d-lg-table-cell">
              <?= date('d M Y H:i', strtotime($s['inicio'])) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

<footer class="mastfoot" style="margin-bottom:2rem;">
  <div class="inner float-end px-4">
    <p><?= t('general.footer_text', ['bootstrap' => '<a href="https://getbootstrap.com/">Bootstrap</a>', 'author' => '<a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>']) ?></p>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

</body>
</html>
