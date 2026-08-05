<?php
ob_start();
require 'menu.php';
require 'db.php';

require_once __DIR__ . '/lib/security_headers.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }

$uid     = (int)$_SESSION['usuarioId'];
$claseId = (int)($_GET['clase'] ?? 0);
$from    = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['from'] ?? '');
if (!$claseId) { header('Location: buscar.php'); exit; }

$clase = dbOne(
    "SELECT cp.*, u.nombre AS profesor, u.usuarioId AS prof_uid,
            m.nombre AS materia
     FROM clases_programadas cp
     JOIN usuarios u ON u.usuarioId = cp.instructorId
     LEFT JOIN materias m ON m.materiaId = cp.materiaId
     WHERE cp.claseId = :id",
    ['id' => $claseId]
);
if (!$clase) { header('Location: buscar.php'); exit; }

$isTeacher = ($uid === (int)$clase['instructorId']);
$creditos  = (float)(dbOne("SELECT creditos FROM usuarios WHERE usuarioId = :id", ['id' => $uid])['creditos'] ?? 0);
$precio    = (float)($clase['precio_base'] ?? 0);
?>

<!DOCTYPE html>
<html lang="<?= detectLang() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($clase['titulo']) ?> ” ClassExpress</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <style>
    body { background: #f4f6fb; color: #1e293b; font-family: system-ui, -apple-system, sans-serif; }
    .preview-box { aspect-ratio: 4/3; background: #000; border-radius: 16px; overflow: hidden; position: relative; }
    .preview-box video { width: 100%; height: 100%; object-fit: cover; }
    .btn-toggle { min-width: 120px; border-radius: 24px; padding: 10px 20px; font-weight: 600; transition: all .2s; }
    .btn-toggle.active { background: var(--p, #66ddbd); color: #fff; border-color: var(--p, #66ddbd); }
    .btn-toggle.inactive { background: #eef1f8; color: #64748b; border-color: #dbe2ee; }
    .mic-badge { position: absolute; bottom: 12px; left: 12px; background: rgba(0,0,0,0.6); padding: 6px 14px; border-radius: 20px; font-size: 13px; display: flex; align-items: center; gap: 8px; }
    .section-title { font-weight: 700; font-size: 20px; margin-bottom: 12px; color: #1e293b; }
    .meta-chip { display: inline-flex; align-items: center; gap: 6px; background: #ffffff; border: 1px solid #dbe2ee; padding: 6px 14px; border-radius: 20px; font-size: 13px; color: #1e293b; }
  </style>
</head>
<body>
  <div class="container py-4" style="margin-top: 3em;padding-bottom:90px !important;">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <!-- â”€â”€ Class info â”€â”€ -->
        <div class="d-flex align-items-start gap-3 mb-3 flex-wrap">
          <div class="flex-grow-1">
            <h2 class="fw-bold text-white mb-1"><?= htmlspecialchars($clase['titulo']) ?></h2>
            <div class="d-flex gap-2 flex-wrap align-items-center">
              <span class="meta-chip"><i class="bi bi-book text-primary"></i> <?= htmlspecialchars($clase['materia'] ?? '') ?></span>
              <span class="meta-chip"><i class="bi bi-person text-info"></i> <?= htmlspecialchars($clase['profesor']) ?></span>
              <?php if ($clase['duracion_min'] ?? 0): ?>
                <span class="meta-chip"><i class="bi bi-clock text-warning"></i> <?= (int)$clase['duracion_min'] ?> min</span>
              <?php endif; ?>
              <span class="meta-chip"><i class="bi bi-star text-warning"></i> <?= number_format((float)($clase['calificacion'] ?? 4), 1) ?></span>
            </div>
          </div>
        </div>

        <?php if ($clase['descripcion'] ?? ''): ?>
          <div class="bg-dark border border-secondary rounded-4 p-3 mb-4">
            <p class="mb-0 text-secondary"><?= nl2br(htmlspecialchars($clase['descripcion'])) ?></p>
          </div>
        <?php endif; ?>

        <!-- â”€â”€ Price box â”€â”€ -->
        <div class="bg-dark border border-secondary rounded-4 p-4 mb-4">
          <p class="text-secondary small mb-1">Precio de la clase</p>
          <p class="fw-bold text-white fs-2 mb-1"><?= number_format($precio, 2) ?> créditos</p>
          <p class="text-secondary small mb-0">
            Tu saldo: <?= number_format($creditos, 0) ?> cr.
            <?php if ($creditos >= $precio): ?>
              <span class="text-success ms-2"><i class="bi bi-check-circle"></i> Tienes suficiente</span>
            <?php else: ?>
              <span class="text-danger ms-2"><i class="bi bi-exclamation-circle"></i> Saldo insuficiente</span>
            <?php endif; ?>
          </p>
        </div>

        <!-- â”€â”€ Camera preview â”€â”€ -->
        <h3 class="section-title text-white">Vista previa</h3>
        <div class="preview-box mb-3">
          <video id="preview-video" autoplay playsinline muted></video>
          <div id="preview-placeholder" class="w-100 h-100 d-flex align-items-center justify-content-center text-secondary position-absolute top-0 start-0" style="background:#000;">
            <div class="text-center">
              <i class="bi bi-camera-video-off" style="font-size:2rem"></i>
              <p class="mt-2 mb-0">Cámara no disponible</p>
              <button id="btn-request-cam" class="btn btn-outline-primary btn-sm mt-2">Permitir cámara</button>
            </div>
          </div>
          <div id="mic-indicator" class="mic-badge d-none">
            <i id="mic-icon" class="bi bi-mic text-success"></i>
            <span id="mic-text" class="text-white">Micrófono activo</span>
          </div>
        </div>

        <!-- â”€â”€ Toggles â”€â”€ -->
        <div class="d-flex gap-3 justify-content-center mb-4">
          <button id="toggle-cam" class="btn btn-toggle active"><i class="bi bi-camera-video me-2"></i>Cámara</button>
          <button id="toggle-mic" class="btn btn-toggle active"><i class="bi bi-mic me-2"></i>Micrófono</button>
        </div>

        <!-- â”€â”€ Start button â”€â”€ -->
        <button id="btn-empezar" class="btn btn-success w-100 fw-bold py-3 fs-5 rounded-4"
          <?= (!$isTeacher && ($creditos < $precio)) ? 'disabled' : '' ?>>
          <i class="bi bi-play-circle me-2"></i>
          <?= $isTeacher ? 'Empezar' : 'Entrar a la clase' ?>
        </button>

        <!-- â”€â”€ Leave / back button â”€â”€ -->
        <button id="btn-salir" class="btn btn-outline-secondary w-100 fw-bold py-2 rounded-4 mt-2">
          <i class="bi bi-box-arrow-left me-2"></i>Salir
        </button>

      </div>
    </div>
  </div>

  <script>
    const CLASE_ID = <?= $claseId ?>;
    const FROM = <?= json_encode($from) ?>;
    const IS_TEACHER = <?= $isTeacher ? 'true' : 'false' ?>;
    let previewStream = null;
    let camEnabled = true;
    let micEnabled = true;

    // â”€â”€ Start preview â”€â”€
    async function startPreview() {
      const placeholder = document.getElementById('preview-placeholder');
      const reqBtn = document.getElementById('btn-request-cam');
      placeholder.querySelector('p').textContent = 'Solicitando permiso…';
      if (reqBtn) reqBtn.classList.add('d-none');
      try {
        previewStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
      } catch (e) {
        console.warn('audio not available, retrying video only', e);
        try {
          previewStream = await navigator.mediaDevices.getUserMedia({ video: true });
          micEnabled = false;
        } catch (e2) {
          console.warn('Camera/mic not available', e2);
          if (e2 && (e2.name === 'NotAllowedError' || e2.name === 'SecurityError')) {
            placeholder.querySelector('p').textContent = 'Permiso denegado: permite la cámara y el micrófono en la barra de direcciones del navegador.';
            if (reqBtn) {
              reqBtn.textContent = 'Reintentar';
              reqBtn.classList.remove('d-none');
            }
          } else if (e2 && e2.name === 'NotFoundError') {
            placeholder.querySelector('p').textContent = 'No se encontró ninguna cámara o micrófono en este dispositivo.';
          } else if (e2 && e2.name === 'NotReadableError') {
            placeholder.querySelector('p').textContent = 'La cámara o el micrófono está en uso por otra aplicación.';
          } else {
            placeholder.querySelector('p').textContent = 'No se pudo acceder a la cámara: ' + (e2.name || e2.message || 'error desconocido');
            if (reqBtn) reqBtn.classList.remove('d-none');
          }
          return;
        }
      }
      const vid = document.getElementById('preview-video');
      vid.srcObject = previewStream;
      placeholder.classList.add('d-none');
      document.getElementById('mic-indicator').classList.remove('d-none');
      if (!micEnabled) {
        document.getElementById('toggle-mic').classList.add('inactive');
        document.getElementById('toggle-mic').classList.remove('active');
      }
      updateMicIndicator();
    }

    function hasMic() {
      return !!(previewStream && previewStream.getAudioTracks().length);
    }

    function updateMicIndicator() {
      const icon = document.getElementById('mic-icon');
      const text = document.getElementById('mic-text');
      if (!hasMic()) {
        icon.className = 'bi bi-mic-mute text-warning';
        text.textContent = 'Sin micrófono';
      } else if (micEnabled && previewStream) {
        icon.className = 'bi bi-mic text-success';
        text.textContent = 'Micrófono activo';
      } else {
        icon.className = 'bi bi-mic-mute text-danger';
        text.textContent = 'Micrófono mute';
      }
    }

    // â”€â”€ Auto-start preview on load â”€â”€
    startPreview();

    // â”€â”€ Request camera button â”€â”€
    document.getElementById('btn-request-cam')?.addEventListener('click', startPreview);

    // â”€â”€ Toggle camera â”€â”€
    document.getElementById('toggle-cam').addEventListener('click', () => {
      camEnabled = !camEnabled;
      const btn = document.getElementById('toggle-cam');
      btn.classList.toggle('active', camEnabled);
      btn.classList.toggle('inactive', !camEnabled);
      btn.innerHTML = camEnabled
        ? '<i class="bi bi-camera-video me-2"></i>Cámara'
        : '<i class="bi bi-camera-video-off me-2"></i>Cámara';
      const vid = document.getElementById('preview-video');
      const placeholder = document.getElementById('preview-placeholder');
      if (camEnabled) {
        vid.classList.remove('d-none');
        placeholder.classList.add('d-none');
      } else {
        vid.classList.add('d-none');
        placeholder.classList.remove('d-none');
        placeholder.querySelector('p').textContent = 'Cámara apagada';
        document.getElementById('btn-request-cam')?.classList.add('d-none');
      }
      if (previewStream) {
        previewStream.getVideoTracks().forEach(t => t.enabled = camEnabled);
      } else if (camEnabled) {
        startPreview();
      }
    });

    // â”€â”€ Toggle microphone â”€â”€
    document.getElementById('toggle-mic').addEventListener('click', () => {
      if (!hasMic()) return;
      micEnabled = !micEnabled;
      const btn = document.getElementById('toggle-mic');
      btn.classList.toggle('active', micEnabled);
      btn.classList.toggle('inactive', !micEnabled);
      btn.innerHTML = micEnabled
        ? '<i class="bi bi-mic me-2"></i>Micrófono'
        : '<i class="bi bi-mic-mute me-2"></i>Micrófono';
      if (previewStream) {
        previewStream.getAudioTracks().forEach(t => t.enabled = micEnabled);
      }
      updateMicIndicator();
    });

    // â”€â”€ Empezar button â”€â”€
    document.getElementById('btn-empezar').addEventListener('click', () => {
      const btn = document.getElementById('btn-empezar');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Entrando...';
      if (previewStream) previewStream.getTracks().forEach(t => t.stop());
      window.location.href = 'sala.php?clase=' + CLASE_ID + (FROM ? '&from=' + FROM : '');
    });

    // â”€â”€ Salir / volver button â”€â”€
    document.getElementById('btn-salir').addEventListener('click', () => {
      if (previewStream) previewStream.getTracks().forEach(t => t.stop());
      if (FROM === 'crear') {
        window.location.href = 'crear_clase.php';
      } else if (FROM === 'dashboard') {
        window.location.href = 'dashboard_profesor.php';
      } else {
        window.location.href = 'buscar.php';
      }
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php ob_end_flush(); ?>
