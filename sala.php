<?php
ob_start();
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/security_headers.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }

$uid     = (int)$_SESSION['usuarioId'];
$claseId = (int)($_GET['clase'] ?? 0);
$from    = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['from'] ?? '');

// Adopt the class's subject color so the room UI is themed (not stuck on green)
if ($claseId) {
    $mRow = dbOne("SELECT materiaId FROM clases_programadas WHERE claseId = :id AND activa = 1", ['id' => $claseId]);
    if ($mRow && !empty($mRow['materiaId'])) {
        $_GET['materia'] = (int)$mRow['materiaId'];
    }
}

require 'menu.php';

if (!$claseId) { header('Location: buscar.php'); exit; }

$clase = dbOne(
    "SELECT cp.*, u.nombre AS profesor, u.usuarioId AS prof_uid, u.avatar AS prof_avatar,
            u.calificacion, u.num_resenas, u.pais_id AS prof_pais_id,
            pa.nombre AS pais_prof, pa.simbolo AS simbolo_prof, pa.codigo_moneda AS moneda_prof,
            m.nombre AS materia
     FROM clases_programadas cp
     JOIN usuarios u ON u.usuarioId = cp.instructorId
     LEFT JOIN paises pa ON pa.paisId = u.pais_id
     LEFT JOIN materias m ON m.materiaId = cp.materiaId
     WHERE cp.claseId = :id AND cp.activa = 1",
    ['id' => $claseId]
);

if (!$clase) { header('Location: buscar.php'); exit; }

$student = dbOne(
    "SELECT u.nombre, u.creditos, pa.nombre AS pais, pa.simbolo, pa.codigo_moneda, pa.tasa_usd
     FROM usuarios u LEFT JOIN paises pa ON pa.paisId = u.pais_id
     WHERE u.usuarioId = :id",
    ['id' => $uid]
);

$precio_usd   = (float)$clase['precio_base'];
$tasa         = (float)($student['tasa_usd'] ?? 1);
$monto_local  = round($precio_usd * $tasa, 2);
$moneda_local = $student['codigo_moneda'] ?? 'USD';
$simbolo      = $student['simbolo'] ?? '$';
$creditos     = (float)($student['creditos'] ?? 0);
$isTeacher    = ($uid === (int)$clase['instructorId']);

$salaId = $clase['salaId'] ?? 0;
if (!$salaId) {
    $salaId = dbExec(
        "INSERT INTO salas (claseId, titulo, curso, instructorId) VALUES (:cid, :t, :c, :i)",
        ['cid' => $claseId, 't' => $clase['titulo'], 'c' => $clase['materia'] ?? '', 'i' => $clase['instructorId']]
    );
    dbExec("UPDATE clases_programadas SET salaId=:s WHERE claseId=:c", ['s'=>$salaId,'c'=>$claseId]);
}

$chat = dbAll(
    "SELECT mensajeId, alias, mensaje, enviado_at FROM mensajes_chat
     WHERE salaId=:s ORDER BY mensajeId DESC LIMIT 30",
    ['s' => $salaId]
);
$chat = array_reverse($chat);

$activos    = dbOne("SELECT COUNT(*) AS cnt FROM sesiones_clase WHERE claseId=:c AND fin IS NULL", ['c'=>$claseId])['cnt'] ?? 0;
$spots_left = max(0, $clase['alumnos_max'] - $activos);
$lastMsgId  = !empty($chat) ? (int)(end($chat)['mensajeId'] ?? 0) : 0;

// Color accent = the class's subject color (menu stays fixed #66ddbd)
$matId = (int)($clase['materiaId'] ?? 0);
$matColors = [
    1=>['#2563EB','#1D4ED8'], 2=>['#059669','#047857'], 3=>['#7C3AED','#6D28D9'],
    4=>['#0284C7','#0369A1'], 5=>['#D97706','#B45309'], 6=>['#0D9488','#0F766E'],
    7=>['#DC2626','#B91C1C'], 8=>['#DB2777','#BE185D'], 9=>['#EA580C','#C2410C'],
    10=>['#0891B2','#0E7490'], 11=>['#E11D48','#BE123C'],
];
[$salaP, $salaPb] = $matColors[$matId] ?? ['#66ddbd', '#4CBFA3'];
?>
  <style>
  :root{--p:<?= $salaP ?>;--pb:<?= $salaPb ?>;--primary-color:<?= $salaP ?>;--primary-hover:<?= $salaPb ?>}
  </style>

  <div class="container-fluid px-0" style="height:calc(max(100vh - 70px, 400px));padding-top:4em;">
    <div class="row g-0 h-100">

      <!-- LEFT: video + controls -->
      <main class="col-lg-9 h-100 d-flex flex-column p-3 bg-black">

        <!-- Video grid -->
        <div id="video-wrapper" class="flex-grow-1 position-relative rounded border border-secondary mb-3 bg-black overflow-hidden" style="min-height:0;">

          <!-- Remote video (full area) -->
          <video id="remote-video" class="w-100 h-100 d-none" style="object-fit:cover;" autoplay playsinline></video>

          <!-- Placeholder when no peer connected -->
          <div id="video-placeholder" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center text-center p-4">
            <i class="bi bi-camera-video display-1 text-secondary mb-3"></i>
            <p class="text-secondary mb-1">
              <?= $isTeacher ? t('sala.waiting_student') : t('sala.camera_join') ?>
            </p>
            <?php if (!$isTeacher && $creditos < $precio_usd): ?>
              <div class="alert alert-warning mt-3 py-2 small">
                <?= t('sala.need_credits', ['needed' => number_format($precio_usd,2), 'have' => number_format($creditos,2)]) ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Local video (thumbnail, bottom-right) -->
          <div class="position-absolute bottom-0 end-0 m-3 rounded border border-secondary overflow-hidden"
               style="width:160px;height:95px;background:#000;">
            <video id="local-video" class="w-100 h-100 d-none" style="object-fit:cover;" autoplay playsinline muted></video>
            <div id="local-placeholder" class="w-100 h-100 d-flex align-items-center justify-content-center">
              <i class="bi bi-person-circle text-secondary fs-2"></i>
            </div>
          </div>

          <!-- Connection status badge -->
          <div class="position-absolute top-0 start-0 m-2 d-flex gap-2">
            <span class="badge bg-dark border border-secondary text-secondary">
              <i class="bi bi-people-fill me-1"></i>
              <span id="spots-count"><?= $activos ?></span>/<?= $clase['alumnos_max'] ?> students
            </span>
            <span id="rtc-badge" class="badge bg-dark border border-secondary text-secondary d-none">
              <span id="rtc-status">⚪ Connecting…</span>
            </span>
          </div>

          <!-- Timer badge (top-right) -->
          <div class="position-absolute top-0 end-0 m-2 d-none" id="timer-wrap">
            <span class="badge bg-dark border border-secondary text-white">
              <i class="bi bi-clock me-1"></i><span id="timer">00:00</span>
            </span>
          </div>

          <!-- Countdown badge (top-center, for students) -->
          <div class="position-absolute top-0 start-50 translate-middle-x m-2 d-none" id="countdown-wrap">
            <span class="badge bg-warning text-dark border border-warning">
              <i class="bi bi-hourglass-split me-1"></i>
              <span id="countdown">3:00</span> <?= t('sala.free') ?>
            </span>
          </div>

          <!-- Billing status badge (bottom-left) -->
          <div class="position-absolute bottom-0 start-0 m-2 d-none" id="billing-wrap">
            <span class="badge bg-dark border border-secondary">
              <i class="bi bi-cash me-1"></i>
              <span id="billing-status"><?= t('sala.billing') ?></span>
            </span>
          </div>

          <!-- Spectators list (for teachers) -->
          <?php if ($isTeacher): ?>
          <div class="position-absolute top-0 start-0 m-2 d-none" id="spectators-wrap" style="max-width: 250px;">
            <div class="card bg-dark border border-secondary p-2">
              <h6 class="text-white small mb-2">
                <i class="bi bi-people me-1"></i><?= t('sala.spectators') ?>
                <span id="spectators-count-badge" class="badge bg-secondary ms-1">0</span>
              </h6>
              <div id="spectators-list">
                <p class="text-secondary small"><?= t('sala.no_spectators') ?></p>
              </div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Controls bar -->
        <div class="bg-dark p-3 rounded d-flex flex-wrap gap-2 justify-content-between align-items-center border border-secondary flex-shrink-0">
          <div>
            <h6 class="mb-0 text-white fw-bold text-truncate"><?= htmlspecialchars($clase['titulo']) ?></h6>
            <small class="text-secondary">
              <?= htmlspecialchars($clase['materia'] ?? t('sala.course')) ?> ·
              Teacher: <?= htmlspecialchars($clase['profesor']) ?>
              <?php if (!$isTeacher): ?>
                · <?= $simbolo . number_format($monto_local, 2) ?> <?= $moneda_local ?><?= t('sala.per_session') ?>
              <?php endif; ?>
            </small>
          </div>
          <div class="d-flex gap-2 flex-wrap" id="controls">
            <?php if (!$isTeacher): ?>
              <button id="btn-join" class="btn btn-success px-4"
                      <?= ($spots_left <= 0 || $creditos < $precio_usd) ? 'disabled' : '' ?>>
                <?= $spots_left <= 0 ? t('buscar.class_full') : ($creditos < $precio_usd ? t('sala.need_credits_btn') : t('sala.join_class')) ?>
              </button>
            <?php else: ?>
              <button id="btn-host" class="btn btn-primary px-4"><?= t('sala.start_hosting') ?></button>
            <?php endif; ?>
            <button id="btn-mic"  class="btn btn-outline-secondary rounded-circle p-2 d-none" title="<?= t('sala.toggle_mic') ?>">
              <i class="bi bi-mic-fill fs-5 px-1"></i>
            </button>
            <button id="btn-cam"  class="btn btn-outline-secondary rounded-circle p-2 d-none" title="<?= t('sala.toggle_cam') ?>">
              <i class="bi bi-camera-video-fill fs-5 px-1"></i>
            </button>
            <button id="btn-leave" class="btn btn-danger rounded-circle p-2" title="<?= t('sala.leave_end') ?>">
              <i class="bi bi-telephone-x-fill fs-5 px-1"></i>
            </button>
          </div>
        </div>

        <!-- Price banner -->
        <div id="price-banner" class="alert alert-dark border border-secondary mt-3 mb-0 text-center d-none small">
          <?php if (!$isTeacher): ?>
            <?= t('sala.charge_notice') ?>
            <strong id="price-display"><?= $simbolo . number_format($monto_local,2) . ' ' . $moneda_local ?></strong>
            <span class="text-secondary">(â‰ˆ $<?= number_format($precio_usd,2) ?> USD)</span>
          <?php else: ?>
            <i class="bi bi-broadcast text-success me-1"></i>
            <?= t('sala.live_notice') ?>
          <?php endif; ?>
        </div>
        <div style="height:70px;flex-shrink:0;"></div>
      </main>

      <!-- RIGHT: chat + student list -->
      <aside class="col-lg-3 h-100 d-flex flex-column border-start border-secondary" style="min-height:0;">
        <?php if ($isTeacher): ?>
        <!-- ── STUDENT LIST (teacher only) ── -->
        <div class="border-bottom border-secondary" style="flex-shrink:0;">
          <div class="p-2 border-bottom border-secondary d-flex justify-content-between align-items-center">
            <span class="text-uppercase fw-bold small text-secondary">
              <i class="bi bi-people-fill me-1"></i><span id="student-count">0</span> <?= t('sala.students') ?>
            </span>
            <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('students-wrap').classList.toggle('d-none')" title="Toggle student list">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div id="students-wrap" class="p-2" style="max-height:200px;overflow-y:auto;">
            <div id="students-list">
              <p class="text-secondary small text-center"><?= t('sala.no_spectators') ?></p>
            </div>
          </div>
        </div>
        <?php endif; ?>
        <!-- ── CHAT ── -->
        <div class="p-3 border-bottom border-secondary text-center text-uppercase fw-bold small text-secondary">
          <?= t('sala.chat_title') ?>
        </div>
        <div id="chat-box" class="flex-grow-1 p-3 overflow-auto d-flex flex-column gap-2 small">
          <?php foreach ($chat as $msg): ?>
            <div>
              <strong class="text-white"><?= htmlspecialchars($msg['alias']) ?>:</strong>
              <span class="text-secondary"><?= htmlspecialchars($msg['mensaje']) ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (empty($chat)): ?>
            <div class="text-secondary text-center mt-3 fst-italic" id="empty-chat"><?= t('sala.chat_empty') ?></div>
          <?php endif; ?>
        </div>
        <div class="p-3 border-top border-secondary bg-dark flex-shrink-0">
          <div class="input-group">
            <input id="chat-input" type="text" maxlength="400"
                   class="form-control bg-black border-secondary text-white small"
                   placeholder="<?= t('sala.chat_placeholder') ?>" disabled>
            <button id="btn-send" class="btn btn-secondary" disabled><?= t('general.send') ?></button>
          </div>
          <small class="text-secondary d-block mt-1" id="chat-hint"><?= t('sala.chat_hint') ?></small>
        </div>
      </aside>

    </div>
  </div>


  <!-- â”€â”€â”€ KICK MODAL â”€â”€â”€ -->
  <div id="kick-modal" class="position-fixed top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center" style="z-index:9999;background:rgba(0,0,0,.7)">
    <div class="bg-dark border border-secondary rounded-4 p-4 text-center" style="max-width:400px;width:90%">
      <div class="display-5 mb-2 text-danger"><i class="bi bi-shield-exclamation"></i></div>
      <h4 class="text-white fw-bold mb-1"><?= t('sala.kick_title') ?></h4>
      <p class="text-secondary small mb-3" id="kick-student-name">—</p>
      <textarea id="kick-reason" class="form-control bg-black border-secondary text-white small mb-3" rows="3" placeholder="<?= t('sala.kick_reason') ?>" maxlength="500"></textarea>
      <div class="d-flex gap-2">
        <button class="btn btn-danger flex-fill fw-bold py-2" onclick="doKick()"><?= t('sala.kick_btn') ?></button>
        <button class="btn btn-outline-secondary flex-fill py-2" onclick="closeKickModal()"><?= t('sala.kick_cancel') ?></button>
      </div>
      <p class="text-danger small mt-2 d-none" id="kick-error">Completa el motivo</p>
    </div>
  </div>

  <!-- â”€â”€â”€ TIME-UP MODAL â”€â”€â”€ -->
  <div id="timeup-modal" class="position-fixed top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center" style="z-index:9999;background:rgba(0,0,0,.7)">
    <div class="bg-dark border border-secondary rounded-4 p-4 text-center" style="max-width:400px;width:90%">
      <div class="display-3 mb-3">â°</div>
      <h4 class="text-white fw-bold mb-2"><?= t('sala.time_up') ?></h4>
      <p class="text-secondary mb-4"><?= t('sala.time_up_continue') ?></p>
      <div class="d-flex gap-2">
        <button id="timeup-accept" class="btn btn-success flex-fill fw-bold py-2"><?= t('sala.time_up_accept') ?></button>
        <button id="timeup-cancel" class="btn btn-outline-danger flex-fill py-2"><?= t('sala.time_up_exit') ?></button>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
  // â”€â”€ Constants â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  const CLASE_ID   = <?= $claseId ?>;
  const SALA_ID    = <?= $salaId ?>;
  const CSRF_TOKEN = '<?= csrf_token() ?>';
  const MY_UID     = <?= $uid ?>;
  const IS_TEACHER = <?= $isTeacher ? 'true' : 'false' ?>;
  const FROM       = <?= json_encode($from) ?>;
  const PROF_UID   = <?= (int)$clase['instructorId'] ?>;
  const FREE_MINUTES = 3;  // First 3 minutes are free
  const SPECTATOR_MAX = 8;  // Max 8 minutes as spectator

  // â”€â”€ State â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  let sesionId      = null;
  let localStream   = null;
  let pc            = null;          // RTCPeerConnection
  let lastMsgId     = <?= $lastMsgId ?>;
  let spectatorPollId = null;
  let timerStart    = null;
  let timerInterval = null;
  let countdownInterval = null;
  let micOn         = true;
  let camOn         = true;
  let inCall        = false;
  let isSpectator   = true;  // Start as spectator
  let joinedAt      = null;
  let spectators    = [];

  // â”€â”€ ICE servers (public STUN ” works for same-LAN and most NAT) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  const RTC_CONFIG = {
    iceServers: [
      { urls: 'stun:stun.l.google.com:19302' },
      { urls: 'stun:stun1.l.google.com:19302' },
    ]
  };

  // â”€â”€ Helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  function api(params) {
    return fetch('api_sala.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN},
      body: new URLSearchParams(params).toString()
    }).then(r => r.json());
  }

  // WebSocket signaling
  const WS_URL = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + '/ws/';
  let ws = null;
  let wsReady = false;

  function wsSend(tipo, payload) {
    if (wsReady && ws && ws.readyState === WebSocket.OPEN) {
      ws.send(JSON.stringify({ type:'signal', tipo, payload }));
    }
  }

  function initWebSocket() {
    if (ws) { ws.close(); ws = null; }
    ws = new WebSocket(WS_URL);
    ws.onopen = () => {
      wsReady = true;
      ws.send(JSON.stringify({ type:'join', salaId:String(SALA_ID), userId:<?= $uid ?> }));
    };
    ws.onmessage = async (event) => {
      try {
        const msg = JSON.parse(event.data);
        if (msg.type === 'signal') {
          await handleSignal(msg.data);
        } else if (msg.type === 'chat') {
          const d = msg.data;
          appendChat(d.alias, d.mensaje);
          lastMsgId = Math.max(lastMsgId, d.mensajeId ?? 0);
        }
      } catch(e) { console.warn('WS error:', e); }
    };
    ws.onclose = () => { wsReady = false; };
    ws.onerror = () => { wsReady = false; };
  }

  function setRtcStatus(icon, text) {
    document.getElementById('rtc-badge').classList.remove('d-none');
    document.getElementById('rtc-status').textContent = icon + ' ' + text;
  }

  let segundosAcumulados = 0;  // total seconds spent across all segments

  function startTimer(acumulado) {
    segundosAcumulados = acumulado || 0;
    timerStart = Date.now();
    joinedAt = Date.now();
    document.getElementById('timer-wrap').classList.remove('d-none');
    timerInterval = setInterval(() => {
      const total = segundosAcumulados + Math.floor((Date.now() - timerStart) / 1000);
      const m = Math.floor(total / 60).toString().padStart(2,'0');
      const s = (total % 60).toString().padStart(2,'0');
      document.getElementById('timer').textContent = m + ':' + s;
    }, 1000);
  }

  function startCountdown() {
    if (IS_TEACHER) return;
    const totalNow = segundosAcumulados + Math.floor((Date.now() - timerStart) / 1000);
    let secondsLeft = Math.max(0, FREE_MINUTES * 60 - totalNow);
    document.getElementById('countdown-wrap').classList.remove('d-none');
    if (secondsLeft <= 0) document.getElementById('countdown-wrap').classList.add('d-none');
    countdownInterval = setInterval(() => {
      secondsLeft--;
      if (secondsLeft > 0) {
        const m = Math.floor(secondsLeft / 60);
        const s = secondsLeft % 60;
        document.getElementById('countdown').textContent = `${m}:${s.toString().padStart(2,'0')}`;
      } else {
        clearInterval(countdownInterval);
        document.getElementById('countdown').textContent = '0:00';
        document.getElementById('countdown-wrap').classList.add('d-none');
        if (!IS_TEACHER) {
          document.getElementById('timeup-modal').classList.remove('d-none');
          document.getElementById('timeup-modal').classList.add('d-flex');
        }
        document.getElementById('billing-wrap').classList.remove('d-none');
        isSpectator = false;
      }
    }, 1000);
  }

  function updateBillingStatus() {
    if (IS_TEACHER) return;
    const total = segundosAcumulados + (joinedAt ? Math.floor((Date.now() - joinedAt) / 1000) : 0);
    const minutosEnSesion = total / 60;
    
    if (minutosEnSesion < FREE_MINUTES) {
      document.getElementById('billing-status').textContent = '<?= t('sala.billing_free') ?>';
      document.getElementById('billing-status').className = 'text-success';
    } else {
      document.getElementById('billing-status').textContent = '<?= t('sala.billing_charging') ?>';
      document.getElementById('billing-status').className = 'text-warning';
    }
  }

  let studentsInRoom = [];
  let kickTargetId = null;

  async function pollStudents() {
    if (!IS_TEACHER) return;
    try {
      const res = await fetch(`api_sala.php?action=students&salaId=${SALA_ID}`);
      const data = await res.json();
      if (data.ok && data.students) {
        studentsInRoom = data.students;
        renderStudents();
      }
    } catch(e) {}
  }

  function renderStudents() {
    const container = document.getElementById('students-list');
    const countEl   = document.getElementById('student-count');
    if (!container) return;
    if (countEl) countEl.textContent = studentsInRoom.length;
    if (studentsInRoom.length === 0) {
      container.innerHTML = '<p class="text-secondary small text-center">No hay estudiantes</p>';
      return;
    }
    container.innerHTML = studentsInRoom.map(st => `
      <div class="d-flex align-items-center gap-2 mb-2 p-2 bg-black rounded" style="font-size:12px">
        <div style="width:32px;height:32px;border-radius:16px;background:var(--pb);display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <span style="font-weight:700;font-size:13px;color:var(--p)">${(st.nombre || '?')[0].toUpperCase()}</span>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;color:var(--fg);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(st.nombre)}</div>
          <div style="color:var(--sub);font-size:10px">${st.pais ? escHtml(st.pais) : 'Desconocido'}${st.idiomas ? ' · ' + escHtml(st.idiomas) : ''}</div>
          <div style="font-size:10px"><span style="color:${st.es_gratis ? 'var(--s)' : 'var(--p)'}">${st.es_gratis ? 'Gratis' : 'Pagando'}</span>${st.espectador ? ' · Espectador' : ''}</div>
        </div>
        <button class="btn btn-outline-danger btn-sm p-1" style="line-height:1" onclick="openKickModal(${st.estudianteId}, '${escHtml(st.nombre)}')" title="Expulsar">
          <i class="bi bi-x-circle" style="font-size:14px"></i>
        </button>
      </div>
    `).join('');
  }

  function escHtml(s) { return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;') : ''; }

  function openKickModal(estudianteId, nombre) {
    kickTargetId = estudianteId;
    document.getElementById('kick-student-name').textContent = nombre;
    document.getElementById('kick-reason').value = '';
    document.getElementById('kick-error').classList.add('d-none');
    document.getElementById('kick-modal').classList.remove('d-none');
    document.getElementById('kick-modal').classList.add('d-flex');
  }

  function closeKickModal() {
    document.getElementById('kick-modal').classList.add('d-none');
    document.getElementById('kick-modal').classList.remove('d-flex');
    kickTargetId = null;
  }

  async function doKick() {
    const reason = document.getElementById('kick-reason').value.trim();
    if (!reason || !kickTargetId) {
      document.getElementById('kick-error').classList.remove('d-none');
      return;
    }
    document.getElementById('kick-error').classList.add('d-none');
    const res = await api({
      action: 'kick_student',
      salaId: SALA_ID,
      estudianteId: kickTargetId,
      comentario: reason
    });
    if (res.ok) {
      closeKickModal();
      pollStudents();
    } else {
      alert('Error: ' + res.error);
    }
  }

  async function pollSpectators() {
    if (!IS_TEACHER) return;
    const res = await fetch(`api_sala.php?action=get_spectators&salaId=${SALA_ID}`);
    const data = await res.json();
    if (data.ok && data.spectators) {
      spectators = data.spectators;
      renderSpectators();
    }
  }

  function renderSpectators() {
    const container = document.getElementById('spectators-list');
    const badgeEl   = document.getElementById('spectators-count-badge');
    if (!container) return;
    
    if (badgeEl) badgeEl.textContent = spectators.length;
    
    if (spectators.length === 0) {
      container.innerHTML = '<p class="text-secondary small"><?= t('sala.no_spectators') ?></p>';
      return;
    }
    
    container.innerHTML = spectators.map(s => `
      <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-black rounded">
        <div>
          <span class="text-white small">${s.nombre}</span>
          <span class="text-secondary small">(@${s.username})</span>
        </div>
        <div>
          <button class="btn btn-success btn-sm me-1" onclick="approveSpectator(${s.espectadorId})">
            <i class="bi bi-check"></i>
          </button>
          <button class="btn btn-danger btn-sm" onclick="rejectSpectator(${s.espectadorId})">
            <i class="bi bi-x"></i>
          </button>
        </div>
      </div>
    `).join('');
  }

  async function approveSpectator(espectadorId) {
    const res = await api({
      action: 'approve_spectator',
      espectadorId: espectadorId,
      salaId: SALA_ID
    });
    if (res.ok) {
      pollSpectators();
    } else {
      alert('<?= t('sala.error_prefix') ?>' + res.error);
    }
  }

  async function rejectSpectator(espectadorId) {
    const res = await api({
      action: 'reject_spectator',
      espectadorId: espectadorId,
      salaId: SALA_ID
    });
    if (res.ok) {
      pollSpectators();
    } else {
      alert('<?= t('sala.error_prefix') ?>' + res.error);
    }
  }

  function escHtml(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
  function appendChat(alias, msg) {
    document.getElementById('empty-chat')?.remove();
    const box = document.getElementById('chat-box');
    const div = document.createElement('div');
    div.innerHTML = `<strong class="text-white">${escHtml(alias)}:</strong> <span class="text-secondary">${escHtml(msg)}</span>`;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
  }

  // â”€â”€ Get camera/mic â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  async function startLocalMedia() {
    try {
      localStream = await navigator.mediaDevices.getUserMedia({video:true, audio:true});
    } catch(e) {
      console.warn('Audio not available, retrying video only:', e.message);
      try {
        localStream = await navigator.mediaDevices.getUserMedia({video:true});
        micOn = false;
        updateMicBtn();
      } catch(e2) {
        console.warn('Media error:', e2.message);
        return false;
      }
    }
    const lv = document.getElementById('local-video');
    lv.srcObject = localStream;
    lv.classList.remove('d-none');
    document.getElementById('local-placeholder').classList.add('d-none');
    updateMicBtn();
    return true;
  }

  function updateMicBtn() {
    const btn = document.getElementById('btn-mic');
    if (!btn) return;
    const hasMic = !!(localStream && localStream.getAudioTracks().length);
    btn.disabled = !hasMic;
    btn.innerHTML = !hasMic
      ? '<i class="bi bi-mic-mute-fill fs-5 px-1"></i>'
      : (micOn ? '<i class="bi bi-mic-fill fs-5 px-1"></i>' : '<i class="bi bi-mic-mute-fill fs-5 px-1"></i>');
    btn.classList.toggle('btn-outline-danger', !hasMic || !micOn);
    btn.classList.toggle('btn-outline-secondary', hasMic && micOn);
    btn.title = !hasMic ? 'Sin micrófono' : 'Micrófono';
  }

  // â”€â”€ Build RTCPeerConnection â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  function buildPC() {
    if (pc) { pc.close(); pc = null; }
    pc = new RTCPeerConnection(RTC_CONFIG);

    // Add local tracks
    if (localStream) localStream.getTracks().forEach(t => pc.addTrack(t, localStream));

    // Remote stream â†’ video element
    pc.ontrack = e => {
      const rv = document.getElementById('remote-video');
      if (!rv.srcObject) rv.srcObject = new MediaStream();
      rv.srcObject.addTrack(e.track);
      rv.classList.remove('d-none');
      document.getElementById('video-placeholder').classList.add('d-none');
      setRtcStatus('🟢', '<?= t('sala.rtc_connected') ?>');
    };

    // ICE candidates â†’ send via WebSocket
    pc.onicecandidate = e => {
      if (e.candidate) {
        wsSend('candidate', JSON.stringify(e.candidate));
      }
    };

    pc.onconnectionstatechange = () => {
      const s = pc.connectionState;
      if (s === 'connected')    setRtcStatus('🟢', '<?= t('sala.rtc_connected') ?>');
      if (s === 'disconnected') setRtcStatus('🔴', '<?= t('sala.rtc_disconnected') ?>');
      if (s === 'failed')       setRtcStatus('🔴', '<?= t('sala.rtc_failed') ?>');
    };
  }

  // â”€â”€ Teacher: start hosting â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  async function doHostClass() {
    document.getElementById('btn-host').classList.add('d-none');
    document.getElementById('btn-leave').classList.remove('d-none');
    document.getElementById('btn-mic').classList.remove('d-none');
    document.getElementById('btn-cam').classList.remove('d-none');
    document.getElementById('price-banner').classList.remove('d-none');
    document.getElementById('chat-input').disabled = false;
    document.getElementById('btn-send').disabled   = false;
    document.getElementById('chat-hint').textContent = '';

    await startLocalMedia();
    setRtcStatus('🟡', '<?= t('sala.rtc_waiting') ?>');
    startTimer(0);
    document.getElementById('spectators-wrap')?.classList.remove('d-none');
    inCall = true;
    initWebSocket();
    spectatorPollId = setInterval(pollSpectators, 5000);
    setInterval(pollStudents, 5000);
    pollStudents();
  }

  document.getElementById('btn-host')?.addEventListener('click', async () => {
    document.getElementById('btn-host').disabled = true;
    document.getElementById('btn-host').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Iniciando...';
    await doHostClass();
  });

  // â”€â”€ TIME-UP MODAL handlers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  document.getElementById('timeup-accept')?.addEventListener('click', () => {
    document.getElementById('timeup-modal').classList.add('d-none');
    document.getElementById('timeup-modal').classList.remove('d-flex');
  });

  document.getElementById('timeup-cancel')?.addEventListener('click', () => {
    document.getElementById('btn-leave').click();
  });

  // â”€â”€ Student: join class (called from pre-room) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  async function doJoinClass() {
    document.getElementById('btn-join').disabled = true;
    document.getElementById('btn-join').textContent = '<?= t('sala.joining') ?>';

    const data = await api({action:'join', claseId:CLASE_ID});
    if (!data.ok) { alert(data.error); document.getElementById('btn-join').disabled=false; document.getElementById('btn-join').textContent='<?= t('sala.join_class') ?>'; return; }

    sesionId = data.sesionId;
    document.getElementById('btn-join').classList.add('d-none');
    document.getElementById('btn-leave').classList.remove('d-none');
    document.getElementById('btn-mic').classList.remove('d-none');
    document.getElementById('btn-cam').classList.remove('d-none');
    document.getElementById('price-banner').classList.remove('d-none');
    document.getElementById('chat-input').disabled = false;
    document.getElementById('btn-send').disabled   = false;
    document.getElementById('chat-hint').textContent = '';

    await startLocalMedia();
    buildPC();
    setRtcStatus('🟡', '<?= t('sala.connecting') ?>');
    startTimer(data.segundos_acumulados || 0);
    startCountdown();
    document.getElementById('billing-wrap').classList.remove('d-none');
    updateBillingStatus();
    inCall = true;

    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    wsSend('offer', JSON.stringify(offer));

    initWebSocket();
    setInterval(updateBillingStatus, 30000);
  }

  // â”€â”€ Show pre-room on student join click â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  document.getElementById('btn-join')?.addEventListener('click', async () => {
    document.getElementById('btn-join').disabled = true;
    document.getElementById('btn-join').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Entrando...';
    await doJoinClass();
  });

  // â”€â”€ Leave / End â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  document.getElementById('btn-leave').addEventListener('click', async () => {
    // Not in a call yet: just go back to origin
    if (!inCall) {
      if (FROM === 'mi_sala') {
        window.location.href = 'mi_sala.php';
      } else if (FROM === 'crear') {
        window.location.href = 'crear_clase.php';
      } else if (FROM === 'dashboard') {
        window.location.href = 'dashboard_profesor.php';
      } else if (FROM === 'explorar') {
        window.location.href = 'buscar.php';
      } else {
        window.location.href = IS_TEACHER ? 'crear_clase.php' : 'materias.php';
      }
      return;
    }

    const totalSegundos = segundosAcumulados + (joinedAt ? Math.floor((Date.now() - joinedAt) / 1000) : 0);
    let verb = IS_TEACHER ? '¿Terminar la clase?' : '<?= t('sala.leave_q') ?>';
    
    if (!IS_TEACHER) {
      if (totalSegundos <= FREE_MINUTES * 60) {
        verb = '<?= t('sala.leave_free') ?>';
      } else {
        verb = '<?= t('sala.leave_charge') ?>';
      }
    }
    
    if (!confirm(verb)) return;

    if (ws) { wsSend('bye', 'bye'); ws.close(); wsReady = false; }
    clearInterval(timerInterval);
    clearInterval(countdownInterval);

    if (pc) { pc.close(); pc = null; }
    if (localStream) localStream.getTracks().forEach(t => t.stop());

    if (IS_TEACHER) {
      if (FROM === 'mi_sala') {
        window.location.href = 'mi_sala.php';
      } else if (FROM === 'dashboard') {
        window.location.href = 'dashboard_profesor.php';
      } else {
        window.location.href = 'crear_clase.php';
      }
      return;
    }

    try {
      const res = await api({
        action:'leave',
        sesionId:sesionId,
        intentional: 1,
        elapsedSeconds: totalSegundos,
        isSpectator: isSpectator
      });
      if (res.ok) {
        // Only redirect to rating if charged (beyond free minutes)
        if (totalSegundos > FREE_MINUTES * 60) {
          window.location.href = 'calificar.php?sesion=' + sesionId;
        } else if (FROM === 'explorar') {
          window.location.href = 'buscar.php';
        } else if (FROM === 'mi_sala') {
          window.location.href = 'mi_sala.php';
        } else {
          window.location.href = 'materias.php';
        }
        return;
      }
      alert('<?= t('sala.error_prefix') ?>' + res.error);
    } catch(e) {
      alert('<?= t('sala.error_prefix') ?>' + e.message);
    }
  });

  // Signal handler via WebSocket
  async function handleSignal(sig) {
    const payload = typeof sig.payload === 'string' ? JSON.parse(sig.payload) : sig.payload;

    if (sig.tipo === 'offer' && IS_TEACHER) {
      buildPC();
      await pc.setRemoteDescription(new RTCSessionDescription(payload));
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      wsSend('answer', JSON.stringify(answer));

    } else if (sig.tipo === 'answer' && !IS_TEACHER) {
      if (pc && pc.signalingState !== 'stable') {
        await pc.setRemoteDescription(new RTCSessionDescription(payload));
      }

    } else if (sig.tipo === 'candidate') {
      if (pc && pc.remoteDescription) {
        try { await pc.addIceCandidate(new RTCIceCandidate(payload)); } catch(e) {}
      }

    } else if (sig.tipo === 'bye') {
      document.getElementById('remote-video').classList.add('d-none');
      document.getElementById('video-placeholder').classList.remove('d-none');
      setRtcStatus('🔴', '<?= t('sala.rtc_peer_left') ?>');
    }
  }

  function sendChat() {
    const input = document.getElementById('chat-input');
    const msg   = input.value.trim();
    if (!msg) return;
    input.value = '';
    if (wsReady && ws) {
      ws.send(JSON.stringify({ type:'chat_send', data:{ mensaje:msg } }));
    } else {
      api({action:'chat', salaId:SALA_ID, mensaje:msg}).then(data => {
        if (data.ok) appendChat(data.alias, data.mensaje);
      });
    }
  }

  document.getElementById('chat-input').addEventListener('keypress', e => { if (e.key === 'Enter') sendChat(); });
  document.getElementById('btn-send').addEventListener('click', sendChat);

  // â”€â”€ Mic toggle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  document.getElementById('btn-mic').addEventListener('click', function() {
    if (!(localStream && localStream.getAudioTracks().length)) return;
    micOn = !micOn;
    if (localStream) localStream.getAudioTracks().forEach(t => t.enabled = micOn);
    updateMicBtn();
  });

  // â”€â”€ Cam toggle â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  document.getElementById('btn-cam').addEventListener('click', function() {
    camOn = !camOn;
    if (localStream) localStream.getVideoTracks().forEach(t => t.enabled = camOn);
    const lv = document.getElementById('local-video');
    lv.classList.toggle('d-none', !camOn);
    document.getElementById('local-placeholder').classList.toggle('d-none', camOn);
    this.innerHTML = camOn ? '<i class="bi bi-camera-video-fill fs-5 px-1"></i>' : '<i class="bi bi-camera-video-off-fill fs-5 px-1"></i>';
    this.classList.toggle('btn-outline-danger', !camOn);
    this.classList.toggle('btn-outline-secondary', camOn);
  });

  // â”€â”€ Warn on unload / pause on unload â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  window.addEventListener('beforeunload', e => {
    if (inCall) {
      // Pause session (non-intentional leave) for 5-min grace
      if (sesionId && !IS_TEACHER) {
        navigator.sendBeacon('api_sala.php', new URLSearchParams({
          action: 'leave',
          sesionId: sesionId,
          intentional: 0
        }));
      }
      e.preventDefault();
      e.returnValue = '';
    }
  });
  </script>
</body>
</html>
