<?php
ob_start();
require 'menu.php';
require 'db.php';

require_once __DIR__ . '/lib/security_headers.php';

$uid = (int)($_SESSION['usuarioId'] ?? 0);
if (!$uid) { header('Location: login.php'); exit; }

$materiaId = (int)($_GET['materia'] ?? 0);
$nombre    = htmlspecialchars($_GET['nombre'] ?? '');

if (!$materiaId) { header('Location: materias.php'); exit; }

$classes = dbAll(
    "SELECT cp.claseId AS id, cp.titulo, cp.precio_base AS precio, cp.descripcion,
            u.nombre AS profesor, u.calificacion AS rating, m.nombre AS materia,
            (SELECT s.activa FROM salas s WHERE s.claseId = cp.claseId AND s.activa = true LIMIT 1) AS sala_activa
     FROM clases_programadas cp
     JOIN usuarios u ON u.usuarioId = cp.instructorId
     JOIN materias m ON m.materiaId = cp.materiaId
     WHERE cp.materiaId = :m AND cp.activa = true
     ORDER BY cp.created_at DESC",
    ['m' => $materiaId]
);
?>
<div class="ml-wrap">
  <div class="ml-wrap-inner">
    <div style="padding:0 20px 12px">
      <div style="font-size:26px;font-weight:700;color:var(--p);margin-bottom:2px"><?= $nombre ?: 'Materia' ?></div>
      <div style="font-size:14px;color:var(--sub)"><?= count($classes) ?> clase<?= count($classes) !== 1 ? 's' : '' ?> disponible<?= count($classes) !== 1 ? 's' : '' ?></div>
    </div>

    <div id="classList" style="padding:0 20px 24px">
      <?php if (empty($classes)): ?>
      <div class="ml-empty">
        <i data-feather="book-open" style="width:40px;height:40px;color:var(--tbi)"></i>
        <div class="ml-empty-txt">No hay clases disponibles aún</div>
      </div>
      <?php else: ?>
        <?php foreach ($classes as $c): 
          $live = !empty($c['sala_activa']);
          $rating = number_format((float)($c['rating'] ?? 4), 1);
        ?>
        <a href="pre_sala.php?clase=<?= (int)$c['id'] ?>&from=explorar" style="text-decoration:none;display:flex;gap:16px;border-radius:18px;padding:16px;background:var(--sf);margin-bottom:12px">
          <div style="flex:1;display:flex;flex-direction:column;gap:4px">
            <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:2px">
              <div style="flex:1;font-weight:600;font-size:15px;color:var(--p);line-height:1.3"><?= htmlspecialchars($c['titulo']) ?></div>
              <?php if ($live): ?>
              <div style="display:flex;align-items:center;gap:4px;padding:3px 8px;border-radius:20px;background:var(--d);flex-shrink:0">
                <div style="width:6px;height:6px;border-radius:3px;background:#fff"></div>
                <span style="font-size:10px;font-weight:700;color:#fff;letter-spacing:1px">EN VIVO</span>
              </div>
              <?php endif; ?>
            </div>
            <div style="font-size:13px;color:var(--sub)"><?= htmlspecialchars($c['profesor']) ?></div>
            <?php if (!empty($c['descripcion'])): ?>
            <div style="font-size:12px;color:var(--tbi);line-height:1.3"><?= htmlspecialchars(mb_substr($c['descripcion'], 0, 120)) ?><?= mb_strlen($c['descripcion']) > 120 ? '...' : '' ?></div>
            <?php endif; ?>
            <div style="display:flex;gap:12px;margin-top:4px">
              <div style="display:flex;align-items:center;gap:4px">
                <i data-feather="star" style="width:12px;height:12px;color:#f59e0b"></i>
                <span style="font-size:12px;color:var(--sub)"><?= $rating ?></span>
              </div>
            </div>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;justify-content:center">
            <div style="font-size:22px;font-weight:700;color:var(--p)"><?= (int)$c['precio'] ?></div>
            <div style="font-size:12px;color:var(--sub)">cr.</div>
          </div>
        </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require 'footer.php'; ?>
