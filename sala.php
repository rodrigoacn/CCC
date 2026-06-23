<?php
require 'menu.php';
require 'db.php';

// Must be logged in
if (!isset($_SESSION['usuarioId'])) {
    header('Location: login.php');
    exit;
}

$uid     = (int)$_SESSION['usuarioId'];
$claseId = (int)($_GET['clase'] ?? 0);

if (!$claseId) {
    header('Location: buscar.php');
    exit;
}

// Load class details
$clase = dbOne(
    "SELECT cp.*, u.nombre AS profesor, u.avatar AS prof_avatar,
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

if (!$clase) {
    header('Location: buscar.php');
    exit;
}

// Get student's currency
$student = dbOne(
    "SELECT u.nombre, pa.nombre AS pais, pa.simbolo, pa.codigo_moneda, pa.tasa_usd
     FROM usuarios u LEFT JOIN paises pa ON pa.paisId = u.pais_id
     WHERE u.usuarioId = :id",
    ['id' => $uid]
);

$precio_usd   = (float)$clase['precio_base'];
$tasa         = (float)($student['tasa_usd'] ?? 1);
$monto_local  = round($precio_usd * $tasa, 2);
$moneda_local = $student['codigo_moneda'] ?? 'USD';
$simbolo      = $student['simbolo'] ?? '$';

// Get sala ID (create one if class doesn't have one yet)
$salaId = $clase['salaid'] ?? 0;
if (!$salaId) {
    $salaId = dbExec(
        "INSERT INTO salas (titulo, curso, instructorId) VALUES (:t, :c, :i)",
        ['t' => $clase['titulo'], 'c' => $clase['materia'] ?? '', 'i' => $clase['instructorid']]
    );
    dbExec("UPDATE clases_programadas SET salaId=:s WHERE claseId=:c", ['s'=>$salaId,'c'=>$claseId]);
}

// Load recent chat
$chat = dbAll(
    "SELECT alias, mensaje, enviado_at FROM mensajes_chat
     WHERE salaId=:s ORDER BY mensajeId DESC LIMIT 30",
    ['s' => $salaId]
);
$chat = array_reverse($chat);

// Count active participants
$activos = dbOne(
    "SELECT COUNT(*) AS cnt FROM sesiones_clase WHERE claseId=:c AND fin IS NULL",
    ['c' => $claseId]
)['cnt'] ?? 0;

$spots_left = max(0, $clase['alumnos_max'] - $activos);
?>

  <div class="container-fluid px-0" style="margin-top: 4em; height: calc(100vh - 4em);">
    <div class="row g-0 h-100">

      <!-- LEFT: classroom video + controls -->
      <main class="col-lg-9 h-100 d-flex flex-column p-3 bg-black">

        <!-- Video Container -->
        <div id="video-wrapper"
             class="flex-grow-1 d-flex align-items-center justify-content-center rounded
                    position-relative shadow border border-secondary mb-3 bg-black"
             style="min-height:0;">

          <video id="local-video" class="video-element d-none w-100 h-100 rounded"
                 style="object-fit:cover;" autoplay playsinline muted></video>

          <div id="video-placeholder" class="text-center p-4">
            <i class="bi bi-camera-video display-1 text-secondary mb-3"></i>
            <p class="text-secondary">Camera will start when you join</p>
          </div>

          <!-- Student thumbnail -->
          <div class="position-absolute bottom-0 end-0 m-3 bg-dark rounded border border-secondary p-1 d-none d-sm-block"
               style="width:160px;height:95px;">
            <div class="w-100 h-100 bg-black d-flex align-items-center justify-content-center rounded">
              <i class="bi bi-person-circle text-secondary fs-3"></i>
            </div>
          </div>

          <!-- Live spot counter -->
          <div class="position-absolute top-0 start-0 m-2">
            <span class="badge bg-dark border border-secondary text-secondary">
              <i class="bi bi-people-fill me-1"></i>
              <span id="spots-count"><?= $activos ?></span>/<?= $clase['alumnos_max'] ?> students
            </span>
          </div>
        </div>

        <!-- Controls Bar -->
        <div class="bg-dark p-3 rounded d-flex flex-wrap gap-2 justify-content-between
                    align-items-center border border-secondary flex-shrink-0">
          <div>
            <h6 class="mb-0 text-white fw-bold text-truncate"><?= htmlspecialchars($clase['titulo']) ?></h6>
            <small class="text-secondary">
              <?= htmlspecialchars($clase['materia'] ?? 'Course') ?> ·
              Teacher: <?= htmlspecialchars($clase['profesor']) ?> ·
              <?= $simbolo . number_format($monto_local, 2) ?> <?= $moneda_local ?> / session
            </small>
          </div>
          <div class="d-flex gap-2" id="controls">
            <button id="btn-join" class="btn btn-success px-4"
                    onclick="joinClass()"
                    <?= ($spots_left <= 0) ? 'disabled' : '' ?>>
              <?= ($spots_left <= 0) ? 'Class Full' : 'Join Class' ?>
            </button>
            <button id="btn-mic"  class="btn btn-outline-secondary rounded-circle p-2 text-white d-none" title="Toggle Mic">
              <i class="bi bi-mic-fill fs-5 px-1"></i>
            </button>
            <button id="btn-cam"  class="btn btn-outline-secondary rounded-circle p-2 text-white d-none" title="Toggle Camera">
              <i class="bi bi-camera-video-fill fs-5 px-1"></i>
            </button>
            <button id="btn-hand" class="btn btn-outline-secondary rounded-circle p-2 text-white d-none" title="Raise Hand">
              <i class="bi bi-hand-index-thumb-fill fs-5 px-1"></i>
            </button>
            <button id="btn-leave" class="btn btn-danger rounded-circle p-2 d-none" title="Leave Class"
                    onclick="leaveClass()">
              <i class="bi bi-telephone-x-fill fs-5 px-1"></i>
            </button>
          </div>
        </div>

        <!-- Price preview banner -->
        <div id="price-banner" class="alert alert-dark border border-secondary mt-3 mb-0 text-center d-none">
          When you leave, you will be charged:
          <strong id="price-display"><?= $simbolo . number_format($monto_local, 2) . ' ' . $moneda_local ?></strong>
          <span class="text-secondary small">(≈ $<?= number_format($precio_usd, 2) ?> USD)</span>
        </div>

      </main>

      <!-- RIGHT: chat -->
      <aside class="col-lg-3 h-100 d-flex flex-column border-start border-secondary" style="min-height:0;">

        <div class="p-3 border-bottom border-secondary text-center text-uppercase fw-bold small text-secondary">
          Classroom Chat
        </div>

        <div id="chat-box" class="flex-grow-1 p-3 overflow-auto d-flex flex-column gap-2 small">
          <?php foreach ($chat as $msg): ?>
            <div>
              <strong class="text-white"><?= htmlspecialchars($msg['alias']) ?>:</strong>
              <span class="text-secondary"><?= htmlspecialchars($msg['mensaje']) ?></span>
            </div>
          <?php endforeach; ?>
          <?php if (empty($chat)): ?>
            <div class="text-secondary text-center mt-3">No messages yet. Say hello! 👋</div>
          <?php endif; ?>
        </div>

        <div class="p-3 border-top border-secondary bg-dark flex-shrink-0">
          <div class="input-group">
            <input id="chat-input" type="text" maxlength="400"
                   class="form-control bg-black border-secondary text-white small"
                   placeholder="Type a message…" disabled>
            <button id="btn-send" class="btn btn-secondary" onclick="sendChat()" disabled>Send</button>
          </div>
          <small class="text-secondary d-block mt-1">Join the class to chat.</small>
        </div>

      </aside>
    </div>
  </div>

  <footer class="mastfoot">
    <div class="inner float-end">
      <p class="small text-secondary">ClassExpress done <a href="https://getbootstrap.com/" class="text-secondary">Bootstrap</a>, by <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA" class="text-secondary">@RodrigoConejeros</a>.</p>
    </div>
  </footer>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script>
  const CLASE_ID  = <?= $claseId ?>;
  const SALA_ID   = <?= $salaId ?>;
  let sesionId    = null;
  let stream      = null;
  let chatPollId  = null;
  let lastMsgId   = <?= !empty($chat) ? end($chat)['mensajeid'] ?? 0 : 0 ?>;
  let micOn       = true;
  let camOn       = true;
  let handUp      = false;

  async function joinClass() {
    const res = await fetch('api_sala.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `action=join&claseId=${CLASE_ID}`
    });
    const data = await res.json();
    if (!data.ok) { alert(data.error); return; }

    sesionId = data.sesionId;

    // Show controls, hide join button
    document.getElementById('btn-join').classList.add('d-none');
    ['btn-mic','btn-cam','btn-hand','btn-leave'].forEach(id =>
      document.getElementById(id).classList.remove('d-none'));
    document.getElementById('price-banner').classList.remove('d-none');
    document.getElementById('price-display').textContent =
      data.simbolo + parseFloat(data.monto_local).toLocaleString('es-LA', {minimumFractionDigits:2})
      + ' ' + data.moneda_local;

    // Enable chat
    document.getElementById('chat-input').disabled = false;
    document.getElementById('btn-send').disabled = false;
    document.querySelector('#chat-box .text-center')?.remove();

    // Start camera
    try {
      stream = await navigator.mediaDevices.getUserMedia({video:true, audio:true});
      const vid = document.getElementById('local-video');
      vid.srcObject = stream;
      vid.classList.remove('d-none');
      document.getElementById('video-placeholder').classList.add('d-none');
    } catch(e) {
      console.warn('Camera/mic not available:', e.message);
    }

    // Poll chat
    chatPollId = setInterval(pollChat, 3000);
  }

  async function leaveClass() {
    if (!sesionId) return;
    if (!confirm('Leave the class? You will be redirected to complete payment.')) return;

    clearInterval(chatPollId);

    // Stop camera
    if (stream) stream.getTracks().forEach(t => t.stop());

    const res = await fetch('api_sala.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `action=leave&sesionId=${sesionId}`
    });
    const data = await res.json();
    if (data.ok) {
      window.location.href = data.redirect;
    } else {
      alert('Error: ' + data.error);
    }
  }

  // Toggle mic
  document.getElementById('btn-mic').addEventListener('click', function() {
    micOn = !micOn;
    if (stream) stream.getAudioTracks().forEach(t => t.enabled = micOn);
    this.innerHTML = micOn
      ? '<i class="bi bi-mic-fill fs-5 px-1"></i>'
      : '<i class="bi bi-mic-mute-fill fs-5 px-1"></i>';
    this.classList.toggle('btn-outline-danger', !micOn);
    this.classList.toggle('btn-outline-secondary', micOn);
  });

  // Toggle camera
  document.getElementById('btn-cam').addEventListener('click', function() {
    camOn = !camOn;
    if (stream) stream.getVideoTracks().forEach(t => t.enabled = camOn);
    const vid = document.getElementById('local-video');
    const ph  = document.getElementById('video-placeholder');
    vid.classList.toggle('d-none', !camOn);
    ph.classList.toggle('d-none', camOn);
    this.innerHTML = camOn
      ? '<i class="bi bi-camera-video-fill fs-5 px-1"></i>'
      : '<i class="bi bi-camera-video-off-fill fs-5 px-1"></i>';
    this.classList.toggle('btn-outline-danger', !camOn);
    this.classList.toggle('btn-outline-secondary', camOn);
  });

  // Raise hand
  document.getElementById('btn-hand').addEventListener('click', function() {
    handUp = !handUp;
    this.classList.toggle('btn-warning', handUp);
    this.classList.toggle('btn-outline-secondary', !handUp);
  });

  // Chat
  async function sendChat() {
    const input = document.getElementById('chat-input');
    const msg   = input.value.trim();
    if (!msg) return;
    input.value = '';

    const res = await fetch('api_sala.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `action=chat&salaId=${SALA_ID}&mensaje=${encodeURIComponent(msg)}`
    });
    const data = await res.json();
    if (data.ok) appendChat(data.alias, data.mensaje);
  }

  document.getElementById('chat-input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') sendChat();
  });

  async function pollChat() {
    const res  = await fetch(`api_sala.php?action=messages&salaId=${SALA_ID}&afterId=${lastMsgId}`);
    const data = await res.json();
    if (data.ok && data.messages.length) {
      data.messages.forEach(m => { appendChat(m.alias, m.mensaje); lastMsgId = m.mensajeId; });
    }
  }

  function appendChat(alias, msg) {
    const box = document.getElementById('chat-box');
    const div = document.createElement('div');
    div.innerHTML = `<strong class="text-white">${alias}:</strong> <span class="text-secondary">${msg}</span>`;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
  }

  // Warn before leaving with active session
  window.addEventListener('beforeunload', function(e) {
    if (sesionId) { e.preventDefault(); e.returnValue = ''; }
  });
  </script>
</body>
</html>
