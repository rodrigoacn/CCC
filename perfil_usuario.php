<?php
require 'menu.php';
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';

function __getBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/CCC';
}

$uid = (int)$_SESSION['usuarioId'];
$targetId = (int)($_GET['id'] ?? 0);
if (!$targetId) { header('Location: personas.php'); exit; }

$user = dbOne(
    "SELECT u.*, p.nombre AS pais, p.codigo_moneda, p.simbolo
     FROM usuarios u
     LEFT JOIN paises p ON p.paisId = u.pais_id
     WHERE u.usuarioId = :id",
    ['id' => $targetId]
);
if (!$user) { echo '<div class="ml-wrap"><div class="ml-wrap-inner"><div style="text-align:center;padding:60px 20px;color:var(--sub)">Usuario no encontrado</div></div></div>'; require 'footer.php'; exit; }

$idiomas = array_column(
    dbAll(
        "SELECT i.nombre FROM usuario_idiomas ui JOIN idiomas i ON i.idiomaId = ui.idiomaId WHERE ui.usuarioId = :id",
        ['id' => $targetId]
    ),
    'nombre'
);

$esProfesor = $user['rol'] === 'instructor' || $user['rol'] === 'both';
$esMiPerfil = $targetId === $uid;

// Follow status
$yoLoSigo = false;
if (!$esMiPerfil) {
    $rel = dbOne("SELECT id FROM relaciones WHERE seguidorId = :me AND seguidoId = :t AND estado = 'following'", ['me' => $uid, 't' => $targetId]);
    $yoLoSigo = (bool)$rel;
}

// Get reviews for teachers
$resenas = [];
if ($esProfesor) {
    $resenas = dbAll(
        "SELECT r.*, u.nombre AS estudiante_nombre, u.avatar AS estudiante_avatar
         FROM resenas r
         JOIN usuarios u ON u.usuarioId = r.estudianteId
         WHERE r.profesorId = :id
         ORDER BY r.created_at DESC LIMIT 50",
        ['id' => $targetId]
    );
    $base = __getBaseUrl();
    foreach ($resenas as &$r) {
        if (!empty($r['estudiante_avatar'])) $r['estudiante_avatar'] = $base . '/' . $r['estudiante_avatar'];
    }
}
?>
<div class="ml-wrap">
  <div class="ml-wrap-inner">
    <!-- Profile header -->
    <div style="padding:0 20px 16px;border-bottom:1px solid var(--bd)">
      <a href="personas.php" style="color:var(--fg);text-decoration:none;display:inline-flex;align-items:center;gap:6px;margin-bottom:16px;font-size:13px">
        <i data-feather="arrow-left" style="width:18px;height:18px"></i> Personas
      </a>
      <div style="display:flex;align-items:center;gap:16px">
        <div style="width:72px;height:72px;border-radius:36px;background:var(--pb);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden">
          <?php if ($user['avatar']): ?>
            <img src="<?= htmlspecialchars(__getBaseUrl() . '/' . $user['avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            <span style="font-weight:700;font-size:28px;color:var(--p)"><?= strtoupper(($user['nombre'] ?? '?')[0]) ?></span>
          <?php endif; ?>
        </div>
        <div style="flex:1">
          <div style="font-weight:700;font-size:20px;color:var(--fg)"><?= htmlspecialchars($user['nombre']) ?></div>
          <div style="font-size:13px;color:var(--sub)">@<?= htmlspecialchars($user['username'] ?? '') ?></div>
          <div style="font-size:13px;color:var(--p);margin-top:2px"><?= $esProfesor ? 'Profesor' : 'Estudiante' ?></div>
        </div>
        <?php if (!$esMiPerfil): ?>
        <div style="display:flex;flex-direction:column;gap:6px">
          <?php if ($yoLoSigo): ?>
          <button id="btn-follow" class="btn btn-sm btn-outline-secondary" onclick="toggleFollow(<?= $targetId ?>)" style="font-size:12px;padding:6px 16px;border-radius:20px">Dejar de seguir</button>
          <?php else: ?>
          <button id="btn-follow" class="btn btn-sm btn-primary" onclick="toggleFollow(<?= $targetId ?>)" style="font-size:12px;padding:6px 16px;border-radius:20px">Seguir</button>
          <?php endif; ?>
          <a href="personas.php?chat=<?= $targetId ?>" class="btn btn-sm btn-outline-primary" style="font-size:12px;padding:6px 16px;border-radius:20px;text-decoration:none">Enviar mensaje</a>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Bio -->
    <?php if ($user['biografia']): ?>
    <div style="padding:16px 20px;border-bottom:1px solid var(--bd)">
      <div style="font-size:13px;color:var(--sub);white-space:pre-wrap"><?= htmlspecialchars($user['biografia']) ?></div>
    </div>
    <?php endif; ?>

    <!-- Info -->
    <div style="padding:16px 20px;border-bottom:1px solid var(--bd)">
      <div style="font-size:13px;color:var(--fg);display:flex;flex-wrap:wrap;gap:16px">
        <?php if ($user['pais']): ?>
        <div><span style="color:var(--sub)">País:</span> <?= htmlspecialchars($user['pais']) ?></div>
        <?php endif; ?>
        <?php if (!empty($idiomas)): ?>
        <div><span style="color:var(--sub)">Idiomas:</span> <?= htmlspecialchars(implode(', ', $idiomas)) ?></div>
        <?php endif; ?>
        <?php
          $su_web_raw = trim($user['sitio_web'] ?? '');
          $su_web     = htmlspecialchars($su_web_raw);
          $su_web_url = preg_match('#^https?://#i', $su_web_raw) ? $su_web : '';
        ?>
        <?php if ($su_web): ?>
        <div><span style="color:var(--sub)">Web:</span> <?php if ($su_web_url): ?><a href="<?= $su_web_url ?>" target="_blank" style="color:var(--p)"><?= $su_web ?></a><?php else: ?><span style="color:var(--p)"><?= $su_web ?></span><?php endif; ?></div>
        <?php endif; ?>
        <div><span style="color:var(--sub)">Miembro desde:</span> <?= date('M Y', strtotime($user['created_at'])) ?></div>
      </div>
    </div>

    <!-- Rating (teachers only) -->
    <?php if ($esProfesor): ?>
    <div style="padding:16px 20px;border-bottom:1px solid var(--bd)">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="font-size:36px;font-weight:700;color:var(--p)"><?= number_format((float)$user['calificacion'], 1) ?></div>
        <div>
          <div style="font-size:14px;color:var(--p)">★ ★ ★ ★ ★</div>
          <div style="font-size:12px;color:var(--sub)"><?= (int)$user['num_resenas'] ?> reseñas</div>
        </div>
      </div>
    </div>

    <!-- Reviews -->
    <div style="padding:0">
      <div style="padding:12px 20px;font-weight:600;font-size:14px;color:var(--fg);border-bottom:1px solid var(--bd)">Reseñas (<?= count($resenas) ?>)</div>
      <?php if (empty($resenas)): ?>
      <div style="text-align:center;padding:40px 20px;color:var(--sub);font-size:13px">Aún no tiene reseñas</div>
      <?php else: ?>
        <?php foreach ($resenas as $r): ?>
        <div style="padding:14px 20px;border-bottom:1px solid var(--bd)">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
            <div style="width:32px;height:32px;border-radius:16px;background:var(--pb);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden">
              <?php if ($r['estudiante_avatar']): ?>
                <img src="<?= htmlspecialchars($r['estudiante_avatar']) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
                <span style="font-weight:700;font-size:13px;color:var(--p)"><?= strtoupper(($r['estudiante_nombre'] ?? '?')[0]) ?></span>
              <?php endif; ?>
            </div>
            <div style="flex:1">
              <div style="font-weight:600;font-size:13px;color:var(--fg)"><?= htmlspecialchars($r['estudiante_nombre']) ?></div>
              <div style="font-size:11px;color:var(--p)"><?= str_repeat('★', (int)$r['rating']) ?><?= str_repeat('☆', 5-(int)$r['rating']) ?></div>
            </div>
            <div style="font-size:10px;color:var(--sub)"><?= date('d/m/Y', strtotime($r['created_at'])) ?></div>
          </div>
          <?php if ($r['comentario']): ?>
          <div style="font-size:13px;color:var(--sub);margin-left:42px;white-space:pre-wrap"><?= htmlspecialchars($r['comentario']) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Teacher's available classes -->
    <?php if ($esProfesor):
      $clases = dbAll(
        "SELECT cp.claseId AS id, cp.titulo, cp.precio_base, cp.duracion_min AS duracion, cp.activa,
                m.nombre AS materia, m.icono, m.color,
                (SELECT COUNT(*) FROM sesiones_clase sc WHERE sc.claseId = cp.claseId AND sc.fin IS NULL) AS alumnos_activos
         FROM clases_programadas cp
         LEFT JOIN materias m ON m.materiaId = cp.materiaId
         WHERE cp.instructorId = ? AND cp.activa = 1
         ORDER BY cp.created_at DESC LIMIT 20",
        [$targetId]
      );
    ?>
    <?php if (!empty($clases)): ?>
    <div style="padding:0">
      <div style="padding:12px 20px;font-weight:600;font-size:14px;color:var(--fg);border-bottom:1px solid var(--bd)"><?= t('friends.classes_available') ?> (<?= count($clases) ?>)</div>
      <?php foreach ($clases as $c): ?>
      <div style="display:flex;align-items:center;gap:10px;padding:12px 20px;border-bottom:1px solid var(--bd)">
        <div style="width:36px;height:36px;border-radius:18px;background:<?= htmlspecialchars($c['color'] ?? 'var(--pb)') ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <span style="font-size:16px"><?= htmlspecialchars($c['icono'] ?? '📚') ?></span>
        </div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:13px;color:var(--fg)"><?= htmlspecialchars($c['titulo']) ?></div>
          <div style="font-size:12px;color:var(--sub)"><?= htmlspecialchars($c['materia'] ?? '') ?> · $<?= number_format((float)$c['precio_base'], 2) ?> · <?= (int)$c['duracion'] ?>min</div>
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <?php if ((int)$c['alumnos_activos'] > 0): ?>
          <span style="font-size:11px;color:var(--s)">🔴 <?= (int)$c['alumnos_activos'] ?> en clase</span>
          <?php endif; ?>
          <?php if (!$esMiPerfil): ?>
          <a href="pre_sala.php?clase=<?= (int)$c['id'] ?>" class="btn btn-sm btn-primary" style="font-size:12px;padding:6px 16px;border-radius:20px;text-decoration:none">Reservar</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';
function toggleFollow(targetId){
  const btn = document.getElementById('btn-follow');
  const following = btn.textContent.trim() !== 'Seguir';
  fetch('personas.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN},
    body:'action='+(following?'unfollow':'follow')+'&usuario_id='+targetId
  }).then(r=>r.json()).then(d=>{
    if(d.ok){
      if(following){
        btn.textContent = 'Seguir';
        btn.className = 'btn btn-sm btn-primary';
      } else {
        btn.textContent = 'Dejar de seguir';
        btn.className = 'btn btn-sm btn-outline-secondary';
      }
    }
  });
}
</script>
<?php require 'footer.php'; ?>
