<?php
ob_start();
require 'menu.php';
require 'db.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }

$uid     = (int)$_SESSION['usuarioId'];
$claseId = (int)($_GET['clase'] ?? 0);

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
$isTeacher    = ($uid === (int)$clase['instructorid']);

$salaId = $clase['salaid'] ?? 0;
if (!$salaId) {
    $salaId = dbExec(
        "INSERT INTO salas (titulo, curso, instructorId) VALUES (:t, :c, :i)",
        ['t' => $clase['titulo'], 'c' => $clase['materia'] ?? '', 'i' => $clase['instructorid']]
    );
    dbExec("UPDATE clases_programadas SET salaId=:s WHERE claseId=:c", ['s'=>$salaId,'c'=>$claseId]);
}

$chat = dbAll(
    "SELECT mensajeid, alias, mensaje, enviado_at FROM mensajes_chat
     WHERE salaId=:s ORDER BY mensajeid DESC LIMIT 30",
    ['s' => $salaId]
);
$chat = array_reverse($chat);

$activos    = dbOne("SELECT COUNT(*) AS cnt FROM sesiones_clase WHERE claseId=:c AND fin IS NULL", ['c'=>$claseId])['cnt'] ?? 0;
$spots_left = max(0, $clase['alumnos_max'] - $activos);
$lastMsgId  = !empty($chat) ? (int)(end($chat)['mensajeid'] ?? 0) : 0;
?>

  <div class="container-fluid px-0" style="margin-top:4em;height:calc(100vh - 4em);">
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
              <?= $isTeacher ? 'Waiting for a student to join…' : 'Camera will start when you join' ?>
            </p>
            <?php if (!$isTeacher && $creditos < $precio_usd): ?>
              <div class="alert alert-warning mt-3 py-2 small">
                You need <strong><?= number_format($precio_usd,2) ?> credits</strong> to join.
                You have <strong><?= number_format($creditos,2) ?></strong>.
                <a href="creditos.php" class="alert-link">Top up here</a>.
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
        </div>

        <!-- Controls bar -->
        <div class="bg-dark p-3 rounded d-flex flex-wrap gap-2 justify-content-between align-items-center border border-secondary flex-shrink-0">
          <div>
            <h6 class="mb-0 text-white fw-bold text-truncate"><?= htmlspecialchars($clase['titulo']) ?></h6>
            <small class="text-secondary">
              <?= htmlspecialchars($clase['materia'] ?? 'Course') ?> ·
              Teacher: <?= htmlspecialchars($clase['profesor']) ?>
              <?php if (!$isTeacher): ?>
                · <?= $simbolo . number_format($monto_local, 2) ?> <?= $moneda_local ?>/session
              <?php endif; ?>
            </small>
          </div>
          <div class="d-flex gap-2 flex-wrap" id="controls">
            <?php if (!$isTeacher): ?>
              <button id="btn-join" class="btn btn-success px-4"
                      <?= ($spots_left <= 0 || $creditos < $precio_usd) ? 'disabled' : '' ?>>
                <?= $spots_left <= 0 ? 'Class Full' : ($creditos < $precio_usd ? 'Need Credits' : 'Join Class') ?>
              </button>
            <?php else: ?>
              <button id="btn-host" class="btn btn-primary px-4">Start Hosting</button>
            <?php endif; ?>
            <button id="btn-mic"  class="btn btn-outline-secondary rounded-circle p-2 d-none" title="Toggle Mic">
              <i class="bi bi-mic-fill fs-5 px-1"></i>
            </button>
            <button id="btn-cam"  class="btn btn-outline-secondary rounded-circle p-2 d-none" title="Toggle Camera">
              <i class="bi bi-camera-video-fill fs-5 px-1"></i>
            </button>
            <button id="btn-leave" class="btn btn-danger rounded-circle p-2 d-none" title="Leave / End Class">
              <i class="bi bi-telephone-x-fill fs-5 px-1"></i>
            </button>
          </div>
        </div>

        <!-- Price banner -->
        <div id="price-banner" class="alert alert-dark border border-secondary mt-3 mb-0 text-center d-none small">
          <?php if (!$isTeacher): ?>
            When you leave, you will be charged:
            <strong id="price-display"><?= $simbolo . number_format($monto_local,2) . ' ' . $moneda_local ?></strong>
            <span class="text-secondary">(≈ $<?= number_format($precio_usd,2) ?> USD)</span>
          <?php else: ?>
            <i class="bi bi-broadcast text-success me-1"></i>
            You are live — students can join now.
          <?php endif; ?>
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
            <div class="text-secondary text-center mt-3 fst-italic" id="empty-chat">No messages yet. Say hello! 👋</div>
          <?php endif; ?>
        </div>
        <div class="p-3 border-top border-secondary bg-dark flex-shrink-0">
          <div class="input-group">
            <input id="chat-input" type="text" maxlength="400"
                   class="form-control bg-black border-secondary text-white small"
                   placeholder="Type a message…" disabled>
            <button id="btn-send" class="btn btn-secondary" disabled>Send</button>
          </div>
          <small class="text-secondary d-block mt-1" id="chat-hint">Join the class to chat.</small>
        </div>
      </aside>

    </div>
  </div>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
  // ── Constants ─────────────────────────────────────────────────────────────
  const CLASE_ID   = <?= $claseId ?>;
  const SALA_ID    = <?= $salaId ?>;
  const MY_UID     = <?= $uid ?>;
  const IS_TEACHER = <?= $isTeacher ? 'true' : 'false' ?>;
  const PROF_UID   = <?= (int)$clase['instructorid'] ?>;

  // ── State ─────────────────────────────────────────────────────────────────
  let sesionId      = null;
  let localStream   = null;
  let pc            = null;          // RTCPeerConnection
  let lastMsgId     = <?= $lastMsgId ?>;
  let lastSigId     = 0;
  let chatPollId    = null;
  let sigPollId     = null;
  let timerStart    = null;
  let timerInterval = null;
  let micOn         = true;
  let camOn         = true;
  let inCall        = false;

  // ── ICE servers (public STUN — works for same-LAN and most NAT) ───────────
  const RTC_CONFIG = {
    iceServers: [
      { urls: 'stun:stun.l.google.com:19302' },
      { urls: 'stun:stun1.l.google.com:19302' },
    ]
  };

  // ── Helpers ───────────────────────────────────────────────────────────────
  function api(params) {
    return fetch('api_sala.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams(params).toString()
    }).then(r => r.json());
  }

  function setRtcStatus(icon, text) {
    document.getElementById('rtc-badge').classList.remove('d-none');
    document.getElementById('rtc-status').textContent = icon + ' ' + text;
  }

  function startTimer() {
    timerStart = Date.now();
    document.getElementById('timer-wrap').classList.remove('d-none');
    timerInterval = setInterval(() => {
      const s = Math.floor((Date.now() - timerStart) / 1000);
      const m = Math.floor(s / 60).toString().padStart(2,'0');
      const sc = (s % 60).toString().padStart(2,'0');
      document.getElementById('timer').textContent = m + ':' + sc;
    }, 1000);
  }

  function appendChat(alias, msg) {
    document.getElementById('empty-chat')?.remove();
    const box = document.getElementById('chat-box');
    const div = document.createElement('div');
    div.innerHTML = `<strong class="text-white">${$('<span>').text(alias).html()}:</strong> <span class="text-secondary">${$('<span>').text(msg).html()}</span>`;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
  }

  // ── Get camera/mic ────────────────────────────────────────────────────────
  async function startLocalMedia() {
    try {
      localStream = await navigator.mediaDevices.getUserMedia({video:true, audio:true});
      const lv = document.getElementById('local-video');
      lv.srcObject = localStream;
      lv.classList.remove('d-none');
      document.getElementById('local-placeholder').classList.add('d-none');
      return true;
    } catch(e) {
      console.warn('Media error:', e.message);
      return false;
    }
  }

  // ── Build RTCPeerConnection ───────────────────────────────────────────────
  function buildPC() {
    if (pc) { pc.close(); pc = null; }
    pc = new RTCPeerConnection(RTC_CONFIG);

    // Add local tracks
    if (localStream) localStream.getTracks().forEach(t => pc.addTrack(t, localStream));

    // Remote stream → video element
    pc.ontrack = e => {
      const rv = document.getElementById('remote-video');
      if (!rv.srcObject) rv.srcObject = new MediaStream();
      rv.srcObject.addTrack(e.track);
      rv.classList.remove('d-none');
      document.getElementById('video-placeholder').classList.add('d-none');
      setRtcStatus('🟢', 'Connected');
    };

    // ICE candidates → send via API
    pc.onicecandidate = e => {
      if (e.candidate) {
        api({action:'signal', salaId:SALA_ID,
             tipo:'candidate', payload:JSON.stringify(e.candidate)});
      }
    };

    pc.onconnectionstatechange = () => {
      const s = pc.connectionState;
      if (s === 'connected')    setRtcStatus('🟢', 'Connected');
      if (s === 'disconnected') setRtcStatus('🔴', 'Disconnected');
      if (s === 'failed')       setRtcStatus('🔴', 'Connection failed');
    };
  }

  // ── Teacher: start hosting ────────────────────────────────────────────────
  document.getElementById('btn-host')?.addEventListener('click', async () => {
    document.getElementById('btn-host').classList.add('d-none');
    document.getElementById('btn-leave').classList.remove('d-none');
    document.getElementById('btn-mic').classList.remove('d-none');
    document.getElementById('btn-cam').classList.remove('d-none');
    document.getElementById('price-banner').classList.remove('d-none');
    document.getElementById('chat-input').disabled = false;
    document.getElementById('btn-send').disabled   = false;
    document.getElementById('chat-hint').textContent = '';

    await startLocalMedia();
    setRtcStatus('🟡', 'Waiting for student');
    startTimer();
    inCall = true;
    chatPollId = setInterval(pollChat, 3000);
    sigPollId  = setInterval(pollSignals, 1500);
  });

  // ── Student: join class ───────────────────────────────────────────────────
  document.getElementById('btn-join')?.addEventListener('click', async () => {
    document.getElementById('btn-join').disabled = true;
    document.getElementById('btn-join').textContent = 'Joining…';

    const data = await api({action:'join', claseId:CLASE_ID});
    if (!data.ok) { alert(data.error); document.getElementById('btn-join').disabled=false; document.getElementById('btn-join').textContent='Join Class'; return; }

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
    setRtcStatus('🟡', 'Connecting…');
    startTimer();
    inCall = true;

    // Student creates offer
    const offer = await pc.createOffer();
    await pc.setLocalDescription(offer);
    await api({action:'signal', salaId:SALA_ID, tipo:'offer', payload:JSON.stringify(offer)});

    chatPollId = setInterval(pollChat, 3000);
    sigPollId  = setInterval(pollSignals, 1500);
  });

  // ── Leave / End ───────────────────────────────────────────────────────────
  document.getElementById('btn-leave').addEventListener('click', async () => {
    const verb = IS_TEACHER ? 'End the class?' : 'Leave the class? You will be redirected to payment.';
    if (!confirm(verb)) return;

    clearInterval(chatPollId);
    clearInterval(sigPollId);
    clearInterval(timerInterval);

    if (pc) { pc.close(); pc = null; }
    if (localStream) localStream.getTracks().forEach(t => t.stop());

    await api({action:'signal', salaId:SALA_ID, tipo:'bye', payload:'bye'});

    if (IS_TEACHER) {
      window.location.href = 'dashboard_profesor.php';
    } else {
      const res = await api({action:'leave', sesionId:sesionId});
      if (res.ok) window.location.href = res.redirect;
      else alert('Error: ' + res.error);
    }
  });

  // ── Signal polling (WebRTC signaling) ─────────────────────────────────────
  async function pollSignals() {
    const res = await fetch(`api_sala.php?action=poll_signals&salaId=${SALA_ID}&afterId=${lastSigId}`);
    const data = await res.json();
    if (!data.ok || !data.signals.length) return;

    for (const sig of data.signals) {
      lastSigId = Math.max(lastSigId, sig.signalid ?? sig.signalId ?? 0);
      const payload = JSON.parse(sig.payload);

      if (sig.tipo === 'offer' && IS_TEACHER) {
        // Teacher receives offer → build PC, answer
        buildPC();
        await pc.setRemoteDescription(new RTCSessionDescription(payload));
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        await api({action:'signal', salaId:SALA_ID, tipo:'answer', payload:JSON.stringify(answer)});

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
        setRtcStatus('🔴', 'Peer left');
      }
    }
  }

  // ── Chat polling ──────────────────────────────────────────────────────────
  async function pollChat() {
    const res  = await fetch(`api_sala.php?action=messages&salaId=${SALA_ID}&afterId=${lastMsgId}`);
    const data = await res.json();
    if (data.ok && data.messages.length) {
      data.messages.forEach(m => {
        appendChat(m.alias, m.mensaje);
        lastMsgId = Math.max(lastMsgId, m.mensajeid ?? m.mensajeId ?? 0);
      });
    }
  }

  async function sendChat() {
    const input = document.getElementById('chat-input');
    const msg   = input.value.trim();
    if (!msg) return;
    input.value = '';
    const data = await api({action:'chat', salaId:SALA_ID, mensaje:msg});
    if (data.ok) appendChat(data.alias, data.mensaje);
  }

  document.getElementById('chat-input').addEventListener('keypress', e => { if (e.key === 'Enter') sendChat(); });
  document.getElementById('btn-send').addEventListener('click', sendChat);

  // ── Mic toggle ────────────────────────────────────────────────────────────
  document.getElementById('btn-mic').addEventListener('click', function() {
    micOn = !micOn;
    if (localStream) localStream.getAudioTracks().forEach(t => t.enabled = micOn);
    this.innerHTML = micOn ? '<i class="bi bi-mic-fill fs-5 px-1"></i>' : '<i class="bi bi-mic-mute-fill fs-5 px-1"></i>';
    this.classList.toggle('btn-outline-danger', !micOn);
    this.classList.toggle('btn-outline-secondary', micOn);
  });

  // ── Cam toggle ────────────────────────────────────────────────────────────
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

  // ── Warn on unload ────────────────────────────────────────────────────────
  window.addEventListener('beforeunload', e => { if (inCall) { e.preventDefault(); e.returnValue = ''; } });
  </script>
</body>
</html>
