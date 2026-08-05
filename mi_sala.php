<?php
require 'menu.php';
require 'db.php';

$uid = (int)($_SESSION['usuarioId'] ?? 0);
$rol = $_navRol ?? ($_SESSION['rol'] ?? 'estudiante');
$isTeacher = ($rol !== 'estudiante' && $rol !== 'student');

$room = null;
if ($isTeacher) {
    $room = dbOne(
        "SELECT s.salaId AS id, s.claseId AS claseId, cp.titulo AS clase, cp.precio_base AS precio
         FROM salas s JOIN clases_programadas cp ON cp.claseId = s.claseId
         WHERE cp.instructorId = :uid AND s.activa = true LIMIT 1",
        ['uid' => $uid]
    );
} else {
    $room = dbOne(
        "SELECT s.salaId AS id, s.claseId AS claseId, cp.titulo AS clase, cp.precio_base AS precio
         FROM participantes_sala ps JOIN salas s ON s.salaId = ps.salaId
         JOIN clases_programadas cp ON cp.claseId = s.claseId
         WHERE ps.usuarioId = :uid AND s.activa = true LIMIT 1",
        ['uid' => $uid]
    );
}
?>
<div class="ml-wrap">
  <div class="ml-wrap-inner">
    <div style="padding:0 20px">
      <div class="ml-head-title" style="margin-bottom:4px">Sala</div>
      <div class="ml-sub" style="margin:0 0 16px"><?= $room ? 'Tu sala activa' : ($isTeacher ? 'No tienes una sala abierta' : 'No estás en ninguna sala') ?></div>
    </div>

    <?php if ($room): ?>
    <div style="padding:0 20px">
      <a href="sala.php?clase=<?= (int)$room['claseId'] ?>&from=mi_sala" style="text-decoration:none;display:flex;align-items:center;gap:12px;border-radius:14px;padding:16px;background:var(--sf)">
        <div style="flex:1">
          <div style="font-weight:600;font-size:16px;color:var(--fg);margin-bottom:2px"><?= htmlspecialchars($room['clase']) ?></div>
          <div style="font-size:13px;color:var(--sub)"><?= $room['precio'] ? (int)$room['precio'].' cr.' : 'Gratis' ?></div>
        </div>
        <div style="padding:4px 10px;border-radius:8px;background:var(--s);font-size:10px;font-weight:700;color:#fff;letter-spacing:1px">EN VIVO</div>
      </a>
    </div>
    <?php else: ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px;text-align:center">
      <i data-feather="video-off" style="width:48px;height:48px;color:var(--sub)"></i>
      <div style="font-size:14px;color:var(--sub);margin-top:16px;margin-bottom:24px;max-width:260px">
        <?= $isTeacher ? 'Crea una clase y ábrela desde el Dashboard' : 'Busca una clase y únete desde la pestaña Buscar' ?>
      </div>
      <a href="<?= $isTeacher ? 'dashboard_profesor.php' : 'buscar.php' ?>" style="display:inline-flex;align-items:center;gap:10px;padding:18px 36px;border-radius:16px;font-weight:700;font-size:18px;cursor:pointer;text-decoration:none;background:var(--p, #66ddbd);color:#fff;border:0">
        <i data-feather="<?= $isTeacher ? 'plus-circle' : 'search' ?>" style="width:24px;height:24px;color:#fff"></i>
        <span style="color:#fff"><?= $isTeacher ? 'Crear una clase' : 'Buscar clases' ?></span>
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require 'footer.php'; ?>
