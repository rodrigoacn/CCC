<?php
require 'menu.php';
require 'db.php';

require_once __DIR__ . '/lib/security_headers.php';

$uid = (int)$_SESSION['usuarioId'];
$userRow = dbOne("SELECT nombre, rol, ultimaMateria FROM usuarios WHERE usuarioId = :id", ['id' => $uid]);
$first   = htmlspecialchars(explode(' ', $userRow['nombre'] ?? 'Usuario')[0]);
$rol     = $_navRol ?? ($userRow['rol'] ?? 'estudiante');
$isTeacher = ($rol !== 'estudiante' && $rol !== 'student');
$ultimaMateria = (int)($userRow['ultimaMateria'] ?? 0);

$colors = [
    1=>'#2563EB',2=>'#059669',3=>'#7C3AED',4=>'#0284C7',5=>'#D97706',
    6=>'#0D9488',7=>'#DC2626',8=>'#DB2777',9=>'#EA580C',10=>'#0891B2',11=>'#E11D48',
];
$icons = [
    1=>'hash',2=>'activity',3=>'zap',4=>'cpu',5=>'book-open',
    6=>'map',7=>'feather',8=>'globe',9=>'pen-tool',10=>'monitor',11=>'heart',
];

$subjects = dbAll(
    "SELECT m.materiaId AS id, m.nombre,
            (SELECT COUNT(*) FROM clases_programadas cp WHERE cp.materiaId = m.materiaId AND cp.activa = true) AS clases_activas
     FROM materias m ORDER BY m.nombre"
);
if (empty($subjects)) {
    $names = ['Mathematics','Biology','Chemistry','Physics','History','Geography','Literature','Foreign Languages','Art and Music','Technology','Physical Education'];
    foreach ($names as $i => $n) $subjects[] = ['id' => $i + 1, 'nombre' => $n, 'clases_activas' => 0];
}
?>
<div class="ml-wrap">
  <div class="ml-wrap-inner">
  <div class="ml-head" style="align-items:flex-end">
    <div>
      <div style="font-size:13px;color:var(--sub);font-weight:400;margin-bottom:2px">¡Hola, <?= $first ?>!</div>
      <div class="ml-head-title" style="margin:0">¿Qué estudias hoy?</div>
    </div>
    <?php if ($isTeacher): ?>
    <button class="ml-btn" style="width:44px;height:44px;padding:0;justify-content:center;border-radius:14px;background:var(--pb);color:var(--p)" onclick="location.href='dashboard_profesor.php'">
      <i data-feather="bar-chart-2" style="width:20px;height:20px"></i>
    </button>
    <?php endif; ?>
  </div>

  <?php
  $continuar = null;
  if ($ultimaMateria >= 1 && $ultimaMateria <= 11) {
      foreach ($subjects as $s) {
          if ((int)$s['id'] === $ultimaMateria) {
              $continuar = $s;
              break;
          }
      }
  }
  ?>

  <div class="ml-grid">
    <?php if ($continuar): $cc = $colors[$ultimaMateria]; $ico = $icons[$ultimaMateria]; ?>
    <a href="contenido.php?materia=<?= $ultimaMateria ?>&nombre=<?= urlencode($continuar['nombre']) ?>" style="text-decoration:none;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:18px;padding:20px 16px;background:<?= $cc ?>;min-height:150px;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.12);border:2px solid #fff;position:relative">
      <div style="position:absolute;top:12px;left:12px;padding:4px 12px;border-radius:20px;background:rgba(0,0,0,.25);color:#fff;font-size:11px;font-weight:600;letter-spacing:.5px">CONTINUAR</div>
      <div style="width:64px;height:64px;border-radius:18px;background:rgba(255,255,255,.22);display:flex;align-items:center;justify-content:center;margin-bottom:12px">
        <i data-feather="corner-left-up" style="width:30px;height:30px;color:#fff"></i>
      </div>
      <div style="font-weight:600;font-size:14px;line-height:1.3;text-align:center;color:#fff"><?= htmlspecialchars($continuar['nombre']) ?></div>
    </a>
    <?php endif; ?>
    <?php foreach ($subjects as $s):
      $id = (int)$s['id'];
      $c = $colors[$id] ?? '#888';
      $ico = $icons[$id] ?? 'book';
      $active = (int)($s['clases_activas'] ?? 0);
    ?>
    <a href="contenido.php?materia=<?= $id ?>&nombre=<?= urlencode($s['nombre']) ?>" style="text-decoration:none;display:flex;flex-direction:column;align-items:center;border-radius:18px;padding:20px 16px;background:<?= $c ?>;min-height:150px;color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.12)">
      <div style="width:64px;height:64px;border-radius:18px;background:rgba(255,255,255,.22);display:flex;align-items:center;justify-content:center;margin-bottom:12px">
        <i data-feather="<?= $ico ?>" style="width:34px;height:34px;color:#fff"></i>
      </div>
      <div style="font-weight:600;font-size:14px;line-height:1.3;text-align:center;color:#fff"><?= htmlspecialchars($s['nombre']) ?></div>
      <?php if ($active > 0): ?>
      <div style="margin-top:8px;padding:3px 10px;border-radius:20px;background:rgba(255,255,255,.25);color:#fff;font-size:11px;font-weight:500;align-self:center"><?= $active ?> en vivo</div>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
  </div>
</div>
<?php require 'footer.php'; ?>
