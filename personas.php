<?php
require 'menu.php';
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';

$uid = (int)$_SESSION['usuarioId'];

$siguiendo = dbAll(
    "SELECT u.usuarioId, u.nombre, u.username, u.rol, u.avatar, u.calificacion, u.num_resenas, r.created_at AS seguido_desde
     FROM relaciones r JOIN usuarios u ON u.usuarioId = r.seguidoId
     WHERE r.seguidorId = :uid AND r.estado = 'following'
     ORDER BY r.created_at DESC",
    ['uid' => $uid]
);

$seguidores = dbAll(
    "SELECT u.usuarioId, u.nombre, u.username, u.rol, u.avatar, u.calificacion, u.num_resenas, r.created_at AS sigue_desde
     FROM relaciones r JOIN usuarios u ON u.usuarioId = r.seguidorId
     WHERE r.seguidoId = :uid AND r.estado = 'following'
     ORDER BY r.created_at DESC",
    ['uid' => $uid]
);

$siguiendoIds = array_column($siguiendo, 'usuarioid');

$tab = $_GET['tab'] ?? 'siguiendo';

// Handle AJAX follow/unfollow/search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_validate()) {
        header('Content-Type: application/json');
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'CSRF']);
        exit;
    }
    $targetId = (int)($_POST['usuario_id'] ?? 0);
    if ($_POST['action'] === 'follow') {
        $exists = dbOne("SELECT id FROM relaciones WHERE seguidorId = :me AND seguidoId = :t", ['me' => $uid, 't' => $targetId]);
        if ($exists) {
            dbExec("UPDATE relaciones SET estado = 'following' WHERE id = :id", ['id' => $exists['id']]);
        } else {
            dbExec("INSERT INTO relaciones (seguidorId, seguidoId, estado) VALUES (:me, :t, 'following')", ['me' => $uid, 't' => $targetId]);
        }
    } elseif ($_POST['action'] === 'unfollow') {
        dbExec("DELETE FROM relaciones WHERE seguidorId = :me AND seguidoId = :t", ['me' => $uid, 't' => $targetId]);
    } elseif ($_POST['action'] === 'send_dm') {
        $msg = trim($_POST['mensaje'] ?? '');
        if ($targetId && $msg) {
            dbExec(
                "INSERT INTO mensajes_directos (remitente_id, destinatario_id, mensaje) VALUES (:me, :t, :msg)",
                ['me' => $uid, 't' => $targetId, 'msg' => $msg]
            );
        }
    } elseif ($_POST['action'] === 'search') {
        $q = trim($_POST['q'] ?? '');
        if (strlen($q) >= 1) {
            $results = dbAll(
                "SELECT u.usuarioId, u.nombre, u.username, u.avatar, u.rol, u.calificacion, u.num_resenas, u.biografia,
                        p.nombre AS pais
                 FROM usuarios u
                 LEFT JOIN paises p ON p.paisId = u.pais_id
                 WHERE u.nombre LIKE :q OR u.username LIKE :q2
                 ORDER BY u.num_resenas DESC, u.calificacion DESC
                 LIMIT 20",
                ['q' => "%$q%", 'q2' => "%$q%"]
            );
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'results' => $results]);
            exit;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// Handle GET new messages (AJAX polling)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_new_dms') {
    $chatW = (int)($_GET['chat'] ?? 0);
    $lastId = (int)($_GET['last_id'] ?? 0);
    header('Content-Type: application/json');
    if (!$chatW) { echo json_encode(['ok' => false, 'error' => 'No chat']); exit; }
    $newDms = dbAll(
        "SELECT md.id, md.remitente_id, md.destinatario_id, md.mensaje, md.created_at, u.nombre AS remitente_nombre
         FROM mensajes_directos md
         JOIN usuarios u ON u.usuarioId = md.remitente_id
         WHERE ((md.remitente_id = :me AND md.destinatario_id = :them) OR (md.remitente_id = :them2 AND md.destinatario_id = :me2))
           AND md.id > :last_id
         ORDER BY md.id ASC",
        ['me' => $uid, 'them' => $chatW, 'them2' => $chatW, 'me2' => $uid, 'last_id' => $lastId]
    );
    dbExec(
        "UPDATE mensajes_directos SET leido = 1 WHERE destinatario_id = :me AND remitente_id = :them AND leido = 0",
        ['me' => $uid, 'them' => $chatW]
    );
    echo json_encode(['ok' => true, 'messages' => $newDms]);
    exit;
}

// Handle GET teacher classes for chat partner
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_teacher_classes') {
    $teacherId = (int)($_GET['teacher_id'] ?? 0);
    header('Content-Type: application/json');
    if (!$teacherId) { echo json_encode(['ok' => false]); exit; }
    $clases = dbAll(
        "SELECT cp.claseId AS id, cp.titulo, cp.precio_base, cp.duracion_min AS duracion, cp.activa,
                m.nombre AS materia, m.icono, m.color,
                (SELECT COUNT(*) FROM sesiones_clase sc WHERE sc.claseId = cp.claseId AND sc.fin IS NULL) AS alumnos_activos
         FROM clases_programadas cp
         LEFT JOIN materias m ON m.materiaId = cp.materiaId
         WHERE cp.instructorId = ? AND cp.activa = 1
         ORDER BY cp.created_at DESC LIMIT 20",
        [$teacherId]
    );
    echo json_encode(['ok' => true, 'clases' => $clases]);
    exit;
}

$chatWith = (int)($_GET['chat'] ?? 0);
$dms = [];
if ($chatWith) {
    $dms = dbAll(
        "SELECT md.*, u.nombre AS remitente_nombre
         FROM mensajes_directos md
         JOIN usuarios u ON u.usuarioId = md.remitente_id
         WHERE (md.remitente_id = :me AND md.destinatario_id = :them)
            OR (md.remitente_id = :them2 AND md.destinatario_id = :me2)
         ORDER BY md.id ASC LIMIT 100",
        ['me' => $uid, 'them' => $chatWith, 'them2' => $chatWith, 'me2' => $uid]
    );
    dbExec(
        "UPDATE mensajes_directos SET leido = 1 WHERE destinatario_id = :me AND remitente_id = :them AND leido = 0",
        ['me' => $uid, 'them' => $chatWith]
    );
    $chatUser = dbOne("SELECT usuarioId, nombre, username, rol, avatar, biografia FROM usuarios WHERE usuarioId = :id", ['id' => $chatWith]);
    $chatEsProfesor = $chatUser && ($chatUser['rol'] === 'instructor' || $chatUser['rol'] === 'both');
}
?>
<div class="ml-wrap">
  <div class="ml-wrap-inner">
  <?php if ($chatWith && $chatUser): ?>
  <!-- ─── CHAT VIEW ─── -->
  <div style="padding:0 20px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--bd)">
    <a href="personas.php" style="color:var(--fg);text-decoration:none"><i data-feather="arrow-left" style="width:22px;height:22px"></i></a>
    <div style="width:40px;height:40px;border-radius:20px;background:var(--pb);display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <span style="font-weight:700;font-size:16px;color:var(--p)"><?= strtoupper(($chatUser['nombre'] ?? '?')[0]) ?></span>
    </div>
    <div style="flex:1">
      <div style="font-weight:600;font-size:15px;color:var(--fg)"><?= htmlspecialchars($chatUser['nombre']) ?></div>
      <div style="font-size:12px;color:var(--sub)">@<?= htmlspecialchars($chatUser['username']) ?> · <?= ($chatUser['rol'] === 'instructor' || $chatUser['rol'] === 'both') ? t('people.teacher') : t('people.student') ?></div>
    </div>
    <a href="perfil_usuario.php?id=<?= (int)$chatUser['usuarioId'] ?>" style="color:var(--p);text-decoration:none" title="<?= t('people.view_profile') ?>">
      <i data-feather="external-link" style="width:18px;height:18px"></i>
    </a>
  </div>

  <div id="dm-box" style="flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column;gap:8px;min-height:300px;max-height:calc(100vh - 280px)">
    <?php if (empty($dms)): ?>
      <div id="dm-empty" style="text-align:center;padding:40px 20px;color:var(--sub);font-size:13px"><?= t('people.dm_empty') ?></div>
    <?php else: ?>
      <?php foreach ($dms as $dm):
        $mine = (int)$dm['remitente_id'] === $uid;
      ?>
      <div class="dm-msg" data-id="<?= (int)$dm['id'] ?>" style="max-width:80%;align-self:<?= $mine ? 'flex-end' : 'flex-start' ?>;background:<?= $mine ? 'var(--p)' : 'var(--sf)' ?>;border-radius:18px;padding:10px 14px;<?= $mine ? 'border-bottom-right-radius:4px' : 'border-bottom-left-radius:4px' ?>">
        <div style="font-size:14px;color:<?= $mine ? '#fff' : 'var(--fg)' ?>"><?= htmlspecialchars($dm['mensaje']) ?></div>
        <div style="font-size:10px;color:<?= $mine ? 'rgba(255,255,255,0.6)' : 'var(--sub)' ?>;margin-top:2px;text-align:right"><?= date('H:i', strtotime($dm['created_at'])) ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Teacher's classes section (inline in chat) -->
  <?php if ($chatEsProfesor): ?>
  <div id="teacher-classes-section" style="display:none;padding:10px 20px;border-top:1px solid var(--bd)">
    <div style="font-size:12px;font-weight:600;color:var(--fg);margin-bottom:6px"><?= t('friends.classes_available') ?></div>
    <div id="teacher-classes-list" style="display:flex;flex-direction:column;gap:4px"></div>
  </div>
  <?php endif; ?>

  <div style="display:flex;gap:6px;overflow-x:auto;padding:8px 20px">
    <?php $quickMsgs = [t('people.quick_1'), t('people.quick_2'), t('people.quick_3'), t('people.quick_4'), t('people.quick_5'), t('people.quick_6')];
    foreach ($quickMsgs as $qm): ?>
    <button style="white-space:nowrap;padding:6px 14px;border-radius:20px;border:0;background:var(--sf);color:var(--p);font-size:12px;cursor:pointer" onclick="sendQuickMsg(<?= $chatWith ?>, '<?= htmlspecialchars($qm, ENT_QUOTES) ?>')"><?= htmlspecialchars($qm) ?></button>
    <?php endforeach; ?>
  </div>

  <div style="display:flex;gap:10px;padding:12px 20px;border-top:1px solid var(--bd);background:var(--sf)">
    <input type="text" id="dm-input" placeholder="<?= t('people.dm_placeholder') ?>" style="flex:1;border-radius:24px;border:1px solid var(--bd);padding:10px 16px;font-size:14px;background:var(--bg);color:var(--fg);outline:none">
    <button style="width:44px;height:44px;border-radius:22px;border:0;background:var(--p);color:#fff;cursor:pointer" onclick="sendDm(<?= $chatWith ?>)"><i data-feather="send" style="width:18px;height:18px"></i></button>
  </div>

  <script>
  const CSRF_TOKEN = '<?= csrf_token() ?>';
  let lastDmId = <?= !empty($dms) ? max(array_column($dms, 'id')) : 0 ?>;
  let dmPollId = null;

  function sendDm(to){
    const inp = document.getElementById('dm-input');
    if(!inp.value.trim()) return;
    const msg = inp.value.trim();
    inp.value = '';
    fetch('personas.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN},
      body:'action=send_dm&usuario_id='+to+'&mensaje='+encodeURIComponent(msg)
    }).then(r=>r.json()).then(d=>{
      if(d.ok) fetchNewDms(to); // poll to get the just-sent message with its server ID
    });
  }
  function sendQuickMsg(to, msg){
    fetch('personas.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN},
      body:'action=send_dm&usuario_id='+to+'&mensaje='+encodeURIComponent(msg)
    }).then(r=>r.json()).then(d=>{
      if(d.ok) fetchNewDms(to);
    });
  }

  function appendDm(msg){
    const box = document.getElementById('dm-box');
    if(!box) return;
    const empty = document.getElementById('dm-empty');
    if(empty) empty.remove();
    const mine = parseInt(msg.remitente_id) === <?= $uid ?>;
    const div = document.createElement('div');
    div.className = 'dm-msg';
    div.setAttribute('data-id', msg.id);
    div.style.cssText = 'max-width:80%;align-self:'+(mine?'flex-end':'flex-start')+';background:'+(mine?'var(--p)':'var(--sf)')+';border-radius:18px;padding:10px 14px;'+(mine?'border-bottom-right-radius:4px':'border-bottom-left-radius:4px');
    div.innerHTML = '<div style="font-size:14px;color:'+(mine?'#fff':'var(--fg)')+'">'+escHtml(msg.mensaje)+'</div><div style="font-size:10px;color:'+(mine?'rgba(255,255,255,0.6)':'var(--sub)')+';margin-top:2px;text-align:right">'+formatTime(msg.created_at)+'</div>';
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
    if(parseInt(msg.id) > lastDmId) lastDmId = parseInt(msg.id);
  }

  function fetchNewDms(to){
    fetch('personas.php?action=get_new_dms&chat='+to+'&last_id='+lastDmId)
      .then(r=>r.json()).then(d=>{
        if(d.ok && d.messages && d.messages.length){
          d.messages.forEach(m => appendDm(m));
        }
      });
  }

  function formatTime(dateStr){
    if(!dateStr) return '';
    const d = new Date(dateStr.replace(' ','T')+'Z');
    return d.toLocaleTimeString('<?= detectLang() ?>',{hour:'2-digit',minute:'2-digit'});
  }

  const dmBox = document.getElementById('dm-box');
  if(dmBox) dmBox.scrollTop = dmBox.scrollHeight;
  if(window.location.search.includes('chat=')){
    const chatId = parseInt(new URLSearchParams(window.location.search).get('chat'));
    dmPollId = setInterval(() => fetchNewDms(chatId), 5000);
    // Load teacher classes if chat partner is a teacher
    <?php if ($chatEsProfesor): ?>
    fetch('personas.php?action=get_teacher_classes&teacher_id=<?= (int)$chatUser['usuarioId'] ?>')
      .then(r=>r.json()).then(d=>{
        if(d.ok && d.clases && d.clases.length){
          const section = document.getElementById('teacher-classes-section');
          const list = document.getElementById('teacher-classes-list');
          if(section && list){
            list.innerHTML = d.clases.map(c =>
              '<a href="pre_sala.php?clase='+c.id+'" style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:12px;background:var(--sf);text-decoration:none;color:var(--fg);font-size:13px">'+
                '<span>'+(c.icono||'📚')+'</span>'+
                '<span style="flex:1">'+escHtml(c.titulo)+'</span>'+
                '<span style="color:var(--p);font-weight:600">$'+parseFloat(c.precio_base).toFixed(2)+'</span>'+
                (c.alumnos_activos>0?'<span style="color:var(--s);font-size:11px">🔴 '+c.alumnos_activos+' <?= t('people.in_class') ?></span>':'')+
              '</a>'
            ).join('');
            section.style.display = 'block';
          }
        }
      });
    <?php endif; ?>
  }
  </script>

  <?php else: ?>
  <!-- ─── MAIN PERSONAS VIEW ─── -->
  <div style="padding:0 20px 16px">
    <div class="ml-head-title" style="margin-bottom:4px"><?= t('people.title') ?></div>
    <div style="font-size:13px;color:var(--sub)"><?= t('people.subtitle') ?></div>
  </div>

  <!-- ─── SEARCH BAR ─── -->
  <div style="padding:0 20px;margin-bottom:12px">
    <div style="display:flex;gap:8px">
      <input type="text" id="search-input" placeholder="<?= t('people.search_placeholder') ?>"
             style="flex:1;border-radius:24px;border:1px solid var(--bd);padding:10px 16px;font-size:14px;background:var(--bg);color:var(--fg);outline:none"
             onkeyup="if(event.key==='Enter') searchPeople()">
      <button style="width:44px;height:44px;border-radius:22px;border:0;background:var(--p);color:#fff;cursor:pointer" onclick="searchPeople()">
        <i data-feather="search" style="width:18px;height:18px"></i>
      </button>
    </div>
  </div>

  <!-- ─── SEARCH RESULTS ─── -->
  <div id="search-results" style="display:none">
    <div style="padding:0 20px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:13px;font-weight:600;color:var(--fg)"><?= t('people.search_results') ?></span>
      <button style="background:none;border:0;color:var(--p);font-size:12px;cursor:pointer" onclick="clearSearch()"><?= t('people.clear') ?></button>
    </div>
    <div id="results-list"></div>
  </div>

  <div id="friendList">
    <div style="display:flex;gap:6px;padding:0 20px;margin-bottom:12px">
      <button class="ml-chip <?= $tab === 'siguiendo' ? 'active' : '' ?>" onclick="location.href='personas.php?tab=siguiendo'" style="flex:1;text-align:center"><?= t('people.following') ?> (<?= count($siguiendo) ?>)</button>
      <button class="ml-chip <?= $tab === 'seguidores' ? 'active' : '' ?>" onclick="location.href='personas.php?tab=seguidores'" style="flex:1;text-align:center"><?= t('people.followers') ?> (<?= count($seguidores) ?>)</button>
    </div>

    <?php
    $list = $tab === 'siguiendo' ? $siguiendo : $seguidores;
    if (empty($list)): ?>
    <div style="text-align:center;padding-top:40px">
      <i data-feather="users" style="width:40px;height:40px;color:var(--tbi)"></i>
      <div style="color:var(--sub);margin-top:12px;padding:0 40px">
        <?php if ($tab === 'siguiendo'): ?>
          <?= t('people.no_following') ?>
        <?php elseif ($tab === 'seguidores'): ?>
          <?= t('people.no_followers') ?>
        <?php endif; ?>
      </div>
    </div>
    <?php else:
      foreach ($list as $ref):
        $isMe = (int)$ref['usuarioId'] === $uid;
        $yoLoSigo = in_array((int)$ref['usuarioId'], $siguiendoIds);
        $esProfesor = ($ref['rol'] ?? '') === 'instructor' || ($ref['rol'] ?? '') === 'both';
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--bd)">
      <a href="perfil_usuario.php?id=<?= (int)$ref['usuarioId'] ?>" style="text-decoration:none;display:flex;align-items:center;gap:12px;flex:1">
        <div style="width:44px;height:44px;border-radius:22px;background:var(--pb);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <span style="font-weight:700;font-size:18px;color:var(--p)"><?= strtoupper(($ref['nombre'] ?? '?')[0]) ?></span>
        </div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:15px;color:var(--fg)"><?= htmlspecialchars($ref['nombre']) ?></div>
          <div style="font-size:12px;color:var(--sub)">@<?= htmlspecialchars($ref['username']) ?> · <?= $esProfesor ? t('people.teacher') : t('people.student') ?></div>
          <?php if (isset($ref['calificacion']) && (float)$ref['calificacion'] > 0): ?>
          <div style="font-size:11px;color:var(--p)">★ <?= number_format((float)$ref['calificacion'], 1) ?> (<?= (int)($ref['num_resenas'] ?? 0) ?>)</div>
          <?php endif; ?>
        </div>
      </a>
      <?php if (!$isMe): ?>
      <div style="display:flex;gap:6px">
        <button style="width:36px;height:36px;border-radius:18px;border:0;background:var(--p);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center" onclick="location.href='personas.php?chat=<?= (int)$ref['usuarioId'] ?>'" title="<?= t('people.chat') ?>">
          <i data-feather="message-circle" style="width:16px;height:16px"></i>
        </button>
        <?php if (!$yoLoSigo): ?>
          <button style="width:36px;height:36px;border-radius:18px;border:0;background:var(--s);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center" onclick="followUser(<?= (int)$ref['usuarioId'] ?>)" title="<?= t('people.follow') ?>" data-target="<?= (int)$ref['usuarioId'] ?>">
            <i data-feather="user-plus" style="width:16px;height:16px"></i>
          </button>
          <?php else: ?>
          <button style="width:36px;height:36px;border-radius:18px;border:0;background:var(--tbi);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center" onclick="unfollowUser(<?= (int)$ref['usuarioId'] ?>,'<?= htmlspecialchars($ref['nombre'], ENT_QUOTES) ?>')" title="<?= t('people.unfollow') ?>" data-target="<?= (int)$ref['usuarioId'] ?>">
            <i data-feather="user-minus" style="width:16px;height:16px"></i>
          </button>
          <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach;
    endif; ?>
  </div>
  <?php endif; ?>
  </div>
</div>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';
function followUser(id){
  fetch('personas.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN}, body:'action=follow&usuario_id='+id})
    .then(r=>r.json()).then(d=>{
      if(d.ok) updateFollowBtn(id, true);
    });
}
function unfollowUser(id, name){
  if(!confirm('<?= t('people.unfollow_confirm', ['name' => '__NAME__']) ?>'.replace('__NAME__', name))) return;
  fetch('personas.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN}, body:'action=unfollow&usuario_id='+id})
    .then(r=>r.json()).then(d=>{
      if(d.ok) updateFollowBtn(id, false);
    });
}
function updateFollowBtn(id, following){
  const btn = document.querySelector(`button[data-target="${id}"]`);
  if(!btn) return;
  if(following){
    btn.innerHTML = '<i data-feather="user-minus" style="width:16px;height:16px"></i>';
    btn.style.background = 'var(--tbi)';
    btn.setAttribute('onclick', 'unfollowUser('+id+',"")');
    btn.title = '<?= t('people.unfollow') ?>';
  } else {
    btn.innerHTML = '<i data-feather="user-plus" style="width:16px;height:16px"></i>';
    btn.style.background = 'var(--s)';
    btn.setAttribute('onclick', 'followUser('+id+')');
    btn.title = '<?= t('people.follow') ?>';
  }
  if(typeof feather !== 'undefined') feather.replace();
}
function searchPeople(){
  const q = document.getElementById('search-input').value.trim();
  if(!q) return;
  fetch('personas.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN}, body:'action=search&q='+encodeURIComponent(q)})
    .then(r=>r.json()).then(d=>{
      if(d.ok && d.results){
        showResults(d.results);
      }
    });
}
function showResults(results){
  const container = document.getElementById('results-list');
  const section = document.getElementById('search-results');
  section.style.display = 'block';
  if(results.length === 0){
    container.innerHTML = '<div style="text-align:center;padding:40px 20px;color:var(--sub);font-size:13px"><?= t('people.no_results') ?></div>';
    return;
  }
  container.innerHTML = results.map(u => {
    const esProf = u.rol === 'instructor' || u.rol === 'both';
    const ratingHtml = u.calificacion > 0 ? `<div style="font-size:11px;color:var(--p)">★ ${parseFloat(u.calificacion).toFixed(1)} (${u.num_resenas})</div>` : '';
    return `<div style="display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid var(--bd)">
      <a href="perfil_usuario.php?id=${u.usuarioId}" style="text-decoration:none;display:flex;align-items:center;gap:12px;flex:1">
        <div style="width:44px;height:44px;border-radius:22px;background:var(--pb);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <span style="font-weight:700;font-size:18px;color:var(--p)">${(u.nombre||'?')[0].toUpperCase()}</span>
        </div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:15px;color:var(--fg)">${escHtml(u.nombre)}</div>
          <div style="font-size:12px;color:var(--sub)">@${escHtml(u.username)} · ${esProf ? '<?= t('people.teacher') ?>' : '<?= t('people.student') ?>'}${u.pais ? ' · '+escHtml(u.pais) : ''}</div>
          ${ratingHtml}
        </div>
      </a>
    </div>`;
  }).join('');
  if(typeof feather !== 'undefined') feather.replace();
}
function escHtml(s){return s?String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'):''}
function clearSearch(){
  document.getElementById('search-input').value = '';
  document.getElementById('search-results').style.display = 'none';
  document.getElementById('friendList').style.display = 'block';
}
</script>
<?php require 'footer.php'; ?>
