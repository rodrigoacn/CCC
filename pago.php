<?php
ob_start();
require 'menu.php';
require 'db.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }
$uid      = (int)$_SESSION['usuarioId'];
$sesionId = (int)($_GET['sesion'] ?? 0);

if (!$sesionId) { header('Location: buscar.php'); exit; }

$sesion = dbOne(
    "SELECT s.*,
            cp.titulo AS clase_titulo, cp.instructorId,
            prof.nombre AS prof_nombre, prof.avatar AS prof_avatar,
            est_p.nombre  AS pais_estudiante,
            est_p.simbolo AS simbolo_est,
            est_p.codigo_moneda AS mon_est,
            est_p.tasa_usd AS tasa_est,
            prof_p.nombre  AS pais_prof,
            prof_p.simbolo AS simbolo_prof,
            prof_p.codigo_moneda AS mon_prof,
            m.nombre AS materia
     FROM sesiones_clase s
     JOIN clases_programadas cp ON cp.claseId = s.claseId
     JOIN usuarios prof ON prof.usuarioId  = cp.instructorId
     LEFT JOIN paises est_p  ON est_p.paisId  = (SELECT pais_id FROM usuarios WHERE usuarioId = s.estudianteId)
     LEFT JOIN paises prof_p ON prof_p.paisId = prof.pais_id
     LEFT JOIN materias m    ON m.materiaId   = cp.materiaId
     WHERE s.sesionId = :id AND s.estudianteId = :u",
    ['id' => $sesionId, 'u' => $uid]
);

if (!$sesion) { header('Location: buscar.php'); exit; }

$already_paid = (bool)$sesion['pagado'];
$precio_usd   = (float)$sesion['precio_usd'];
$monto_local  = (float)$sesion['monto_local'];
$simbolo      = $sesion['simbolo_local'] ?? $sesion['simbolo_est'] ?? '$';
$mon_local    = $sesion['moneda_local']  ?? $sesion['mon_est']     ?? 'USD';
$duracion     = (int)$sesion['duracion_min'];

// Format local amount with thousands separator
$fmt_local = number_format($monto_local, 2, '.', ',');

// Handle payment POST
$success = false;
$pay_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_paid) {
    $metodo = in_array($_POST['metodo'] ?? '', ['tarjeta','transferencia','efectivo'])
              ? $_POST['metodo'] : 'tarjeta';

    try {
        dbExec(
            "INSERT INTO pagos
                 (sesionId, estudianteId, profesorId, monto_usd, monto_local,
                  moneda_local, simbolo_local, metodo, estado)
             VALUES (:sid,:est,:prof,:usd,:loc,:mon,:sim,:met,'completado')",
            [
                'sid'  => $sesionId,
                'est'  => $uid,
                'prof' => $sesion['instructorid'],
                'usd'  => $precio_usd,
                'loc'  => $monto_local,
                'mon'  => $mon_local,
                'sim'  => $simbolo,
                'met'  => $metodo,
            ]
        );
        dbExec("UPDATE sesiones_clase SET pagado=1 WHERE sesionId=:id", ['id'=>$sesionId]);

        // Deduct credits from student
        dbExec("UPDATE usuarios SET creditos = creditos - :amt WHERE usuarioId = :id",
               ['amt' => $precio_usd, 'id' => $uid]);

        // Send HTML receipt email
        $student_info = dbOne("SELECT email, nombre FROM usuarios WHERE usuarioId=:id", ['id'=>$uid]);
        if ($student_info) {
            require_once 'email_helper.php';
            ceSendSessionReceipt($student_info['email'], $student_info['nombre'], [
                'simbolo'      => $simbolo,
                'monto_local'  => $monto_local,
                'moneda_local' => $mon_local,
                'monto_usd'    => $precio_usd,
                'profesor'     => $sesion['prof_nombre'],
                'clase'        => $sesion['clase_titulo'],
                'duracion_min' => $sesion['duracion_min'] ?? 0,
            ]);
        }

        $success      = true;
        $already_paid = true;
    } catch (PDOException $e) {
        $pay_error = 'Payment could not be recorded. Please try again.';
    }
}
?>

  <div class="container mt-10">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-8 col-lg-6">

        <?php if ($success): ?>
        <!-- ── SUCCESS ─────────────────────────────────────────────────── -->
        <div class="card bg-dark border-secondary text-center p-4">
          <div class="mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="#6ea86e" viewBox="0 0 16 16">
              <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
            </svg>
          </div>
          <h3 class="text-light">Payment Confirmed!</h3>
          <p class="text-secondary">
            Your session with <strong class="text-white"><?= htmlspecialchars($sesion['prof_nombre']) ?></strong>
            has been finalised.
          </p>
          <div class="alert alert-dark border border-secondary mt-3">
            <span class="fs-4 fw-bold text-white"><?= $simbolo . $fmt_local ?> <?= $mon_local ?></span><br>
            <small class="text-secondary">≈ $<?= number_format($precio_usd, 2) ?> USD</small>
          </div>
          <a href="buscar.php" class="btn btn-secondary mt-3">Find Another Class</a>
          <a href="materias.php" class="btn btn-dark border-secondary mt-3 ms-2">Back to Subjects</a>
        </div>

        <?php elseif ($already_paid): ?>
        <!-- ── ALREADY PAID ────────────────────────────────────────────── -->
        <div class="card bg-dark border-secondary text-center p-4">
          <h4 class="text-light">Session Already Paid</h4>
          <p class="text-secondary">This session was already settled.</p>
          <a href="buscar.php" class="btn btn-secondary mt-2">Find Another Class</a>
        </div>

        <?php else: ?>
        <!-- ── PAYMENT FORM ────────────────────────────────────────────── -->
        <div class="card bg-dark border-secondary">
          <div class="card-body p-4">

            <h3 class="text-light mb-1">Finalise Your Session</h3>
            <p class="text-secondary mb-4">Your class has ended. Complete payment to unlock your progress.</p>

            <?php if ($pay_error): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($pay_error) ?></div>
            <?php endif; ?>

            <!-- Session summary -->
            <div class="rounded border border-secondary p-3 mb-4 bg-black">
              <div class="row g-2 small text-secondary">
                <div class="col-6">
                  <div>📚 Class</div>
                  <div class="text-white fw-semibold"><?= htmlspecialchars($sesion['clase_titulo']) ?></div>
                </div>
                <div class="col-6">
                  <div>👨‍🏫 Teacher</div>
                  <div class="text-white fw-semibold"><?= htmlspecialchars($sesion['prof_nombre']) ?></div>
                </div>
                <div class="col-6 mt-2">
                  <div>📍 Teacher's country</div>
                  <div class="text-white"><?= htmlspecialchars($sesion['pais_prof'] ?? '—') ?></div>
                </div>
                <div class="col-6 mt-2">
                  <div>⏱ Duration</div>
                  <div class="text-white"><?= $duracion ?> min<?= $duracion !== 1 ? 's' : '' ?></div>
                </div>
                <?php if ($sesion['materia']): ?>
                <div class="col-12 mt-2">
                  <div>📖 Subject</div>
                  <div class="text-white"><?= htmlspecialchars($sesion['materia']) ?></div>
                </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Amount breakdown -->
            <div class="rounded border border-secondary p-3 mb-4">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-secondary small">Base price (USD)</span>
                <span class="text-white">$<?= number_format($precio_usd, 2) ?></span>
              </div>
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-secondary small">Your currency</span>
                <span class="text-white"><?= htmlspecialchars($sesion['pais_estudiante'] ?? $mon_local) ?></span>
              </div>
              <hr class="border-secondary">
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-white fw-bold">Total to pay</span>
                <span class="text-white fw-bold fs-4">
                  <?= $simbolo . $fmt_local ?>
                  <small class="text-secondary fs-6"><?= $mon_local ?></small>
                </span>
              </div>
            </div>

            <!-- Payment method -->
            <form method="POST" action="pago.php?sesion=<?= $sesionId ?>">
              <p class="text-secondary small mb-2">Payment method:</p>
              <div class="d-flex gap-3 mb-4 flex-wrap">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="metodo" id="m-tarjeta" value="tarjeta" checked>
                  <label class="form-check-label text-white" for="m-tarjeta">💳 Card</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="metodo" id="m-transfer" value="transferencia">
                  <label class="form-check-label text-white" for="m-transfer">🏦 Bank Transfer</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="metodo" id="m-efectivo" value="efectivo">
                  <label class="form-check-label text-white" for="m-efectivo">💵 Cash</label>
                </div>
              </div>

              <button type="submit" class="btn btn-secondary w-100 fw-bold py-2 fs-5">
                Pay <?= $simbolo . $fmt_local ?> <?= $mon_local ?>
              </button>
              <p class="text-secondary text-center small mt-2 mb-0">
                Secure · No data stored · LATAM payment
              </p>
            </form>

          </div>
        </div>
        <?php endif; ?>

        <footer class="mastfoot mt-4">
          <div class="inner float-end">
            <p class="text-secondary small">ClassExpress done <a href="https://getbootstrap.com/" class="text-secondary">Bootstrap</a>, by <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA" class="text-secondary">@RodrigoConejeros</a>.</p>
          </div>
        </footer>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script type="text/javascript" src="./presentacion/odp_ajax.js"></script>
  <script type="text/javascript" src="./presentacion/js/scripts.js"></script>
</body>
</html>
