<?php
ob_start();
require 'menu.php';
require 'db.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }

$uid = (int)$_SESSION['usuarioId'];
$claseId = (int)($_GET['clase'] ?? 0);
$tokensGanados = (float)($_GET['tokens'] ?? 0);

if (!$claseId) { header('Location: dashboard_profesor.php'); exit; }

$clase = dbOne(
    "SELECT cp.*, u.nombre AS profesor, m.nombre AS materia
     FROM clases_programadas cp
     JOIN usuarios u ON u.usuarioId = cp.instructorId
     LEFT JOIN materias m ON m.materiaId = cp.materiaId
     WHERE cp.claseId = :id",
    ['id' => $claseId]
);

if (!$clase) { header('Location: dashboard_profesor.php'); exit; }

// Get session details
$sessions = dbAll(
    "SELECT sc.*, u.nombre AS estudiante_nombre
     FROM sesiones_clase sc
     JOIN usuarios u ON u.usuarioId = sc.estudianteId
     WHERE sc.claseId = :c AND sc.instructorId = :i",
    ['c' => $claseId, 'i' => $uid]
);

$totalEstudiantes = count($sessions);
$tiempoTotal = 0;
foreach ($sessions as $s) {
    if ($s['fin'] && $s['inicio']) {
        $tiempoTotal += (strtotime($s['fin']) - strtotime($s['inicio'])) / 60;
    }
}
?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card shadow">
        <div class="card-header bg-success text-white text-center py-4">
          <h3 class="mb-0">
            <i class="bi bi-check-circle-fill me-2"></i>
            ¡Clase Terminada!
          </h3>
        </div>
        <div class="card-body p-5">
          
          <!-- Tokens ganados -->
          <div class="text-center mb-5">
            <h5 class="text-muted mb-3">Tokens Ganados</h5>
            <div class="display-1 fw-bold text-success">
              <?= number_format($tokensGanados, 2) ?>
            </div>
            <p class="text-muted">monedasCE</p>
          </div>

          <!-- Detalles de la clase -->
          <div class="card bg-light mb-4">
            <div class="card-body">
              <h6 class="card-title fw-bold mb-3">Detalles de la Clase</h6>
              <div class="row">
                <div class="col-6 mb-2">
                  <small class="text-muted">Materia:</small>
                  <div class="fw-bold"><?= htmlspecialchars($clase['materia'] ?? 'N/A') ?></div>
                </div>
                <div class="col-6 mb-2">
                  <small class="text-muted">Título:</small>
                  <div class="fw-bold"><?= htmlspecialchars($clase['titulo']) ?></div>
                </div>
                <div class="col-6 mb-2">
                  <small class="text-muted">Estudiantes:</small>
                  <div class="fw-bold"><?= $totalEstudiantes ?></div>
                </div>
                <div class="col-6 mb-2">
                  <small class="text-muted">Tiempo Total:</small>
                  <div class="fw-bold"><?= number_format($tiempoTotal, 1) ?> min</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Lista de estudiantes -->
          <?php if (!empty($sessions)): ?>
          <div class="card bg-light mb-4">
            <div class="card-body">
              <h6 class="card-title fw-bold mb-3">Estudiantes Participantes</h6>
              <div class="list-group list-group-flush">
                <?php foreach ($sessions as $s): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <span class="fw-bold"><?= htmlspecialchars($s['estudiante_nombre']) ?></span>
                    <?php if ($s['espectador']): ?>
                      <span class="badge bg-info ms-2">Espectador</span>
                    <?php endif; ?>
                  </div>
                  <div>
                    <?php if ($s['pagado']): ?>
                      <span class="text-success">
                        <i class="bi bi-check-circle-fill"></i>
                        $<?= number_format($s['precio_usd'], 2) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-muted">No pagado</span>
                    <?php endif; ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <!-- Botón para volver -->
          <div class="text-center">
            <a href="dashboard_profesor.php" class="btn btn-primary btn-lg px-5">
              <i class="bi bi-arrow-left me-2"></i>
              Volver al Dashboard
            </a>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Update teacher's token balance
  async function updateTokenBalance() {
    try {
      const res = await fetch('api_mobile.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          action: 'add_tokens',
          tokens: <?= $tokensGanados ?>
        })
      });
      const data = await res.json();
      if (data.ok) {
        console.log('Tokens agregados exitosamente');
      }
    } catch (e) {
      console.error('Error al actualizar tokens:', e);
    }
  }
  
  // Update balance on page load
  updateTokenBalance();
</script>

<?php
require 'footer.php';
?>
