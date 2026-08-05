<?php
ob_start();
require 'menu.php';
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';

require_once __DIR__ . '/lib/security_headers.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['usuarioId'];

$error   = '';
$success = '';

// Load subjects and teacher's currency
$materias = dbAll("SELECT materiaId, nombre FROM materias ORDER BY orden ASC");
$teacher  = dbOne(
    "SELECT u.rol, pa.nombre AS pais, pa.simbolo, pa.codigo_moneda, pa.tasa_usd
     FROM usuarios u LEFT JOIN paises pa ON pa.paisId = u.pais_id
     WHERE u.usuarioId = :id",
    ['id' => $uid]
);

// â”€â”€ SAVE FILTER / CLASS OFFER â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $precio_min  = max(0, (float)($_POST['precio_min']  ?? 0));
    $precio_max  = max(0, (float)($_POST['precio_max']  ?? 0));
    $alumnos_min = max(1, (int)($_POST['alumnos_min']   ?? 1));
    $alumnos_max = max(1, (int)($_POST['alumnos_max']   ?? 1));
    $materiaId   = (int)($_POST['materiaId'] ?? 0) ?: null;
    $solo_yo     = isset($_POST['solo_yo']) ? 1 : 0;
    $moneda      = $teacher['codigo_moneda'] ?? 'USD';

    // Use midpoint as base price
    $precio_base = $precio_min > 0 ? $precio_min : max(1, $precio_max);

    if ($precio_max > 0 && $precio_max < $precio_min) {
        $error = t('oferta.error_price');
    } elseif ($alumnos_max < $alumnos_min) {
        $error = t('oferta.error_students');
    } else {
        $claseId = dbExec(
            "INSERT INTO clases_programadas
                 (instructorId, materiaId, precio_min, precio_max, precio_base,
                  codigo_moneda, alumnos_min, alumnos_max, solo_yo, activa)
             VALUES (:i, :m, :pmin, :pmax, :pbase, :mon, :amin, :amax, :solo, 1)",
            [
                'i'     => $uid,
                'm'     => $materiaId,
                'pmin'  => $precio_min,
                'pmax'  => $precio_max,
                'pbase' => $precio_base,
                'mon'   => $moneda,
                'amin'  => $alumnos_min,
                'amax'  => $alumnos_max,
                'solo'  => $solo_yo,
            ]
        );
        if ($claseId) {
            $success = t('oferta.success');
        } else {
            $error = t('oferta.error_db');
        }
    }
}
?>

  <div class="container mt-10">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-7 col-lg-6">

        <h3 class="text-white mb-1"><?= t('oferta.title') ?></h3>
        <p class="text-secondary mb-4"><?= t('oferta.subtitle') ?></p>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($teacher): ?>
          <div class="alert alert-dark border border-secondary mb-4 small">
            💰 <?= t('oferta.your_currency') ?>
            <strong class="text-white"><?= htmlspecialchars($teacher['simbolo'] . ' ' . $teacher['codigo_moneda']) ?></strong>
            · <?= t('oferta.country') ?> <strong class="text-white"><?= htmlspecialchars($teacher['pais'] ?? '”') ?></strong>
          </div>
        <?php endif; ?>

        <form method="POST" action="oferta_clase.php" class="card bg-dark border-secondary p-4">
<?= csrf_field() ?>

          <!-- Subject -->
          <div class="mb-3">
            <label class="form-label text-secondary"><?= t('oferta.subject') ?> <span class="text-secondary"><?= t('oferta.optional') ?></span></label>
            <select name="materiaId" class="form-select bg-dark text-white border-secondary">
              <option value=""><?= t('oferta.any_subject') ?></option>
              <?php foreach ($materias as $m): ?>
                <option value="<?= $m['materiaid'] ?>"
                        <?= (int)($_POST['materiaId'] ?? 0) === (int)$m['materiaid'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($m['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Price range -->
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label text-secondary"><?= t('oferta.min_price') ?> (<?= htmlspecialchars($teacher['codigo_moneda'] ?? 'USD') ?>)</label>
              <input type="number" name="precio_min" class="form-control bg-dark text-white border-secondary"
                     placeholder="0" min="0" step="0.01"
                     value="<?= htmlspecialchars($_POST['precio_min'] ?? '') ?>">
            </div>
            <div class="col-6">
              <label class="form-label text-secondary"><?= t('oferta.max_price') ?> (<?= htmlspecialchars($teacher['codigo_moneda'] ?? 'USD') ?>)</label>
              <input type="number" name="precio_max" class="form-control bg-dark text-white border-secondary"
                     placeholder="0" min="0" step="0.01"
                     value="<?= htmlspecialchars($_POST['precio_max'] ?? '') ?>">
            </div>
          </div>

          <!-- Student count -->
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label text-secondary"><?= t('oferta.min_students') ?></label>
              <input type="number" name="alumnos_min" class="form-control bg-dark text-white border-secondary"
                     placeholder="1" min="1" step="1"
                     value="<?= htmlspecialchars($_POST['alumnos_min'] ?? '1') ?>">
            </div>
            <div class="col-6">
              <label class="form-label text-secondary"><?= t('oferta.max_students') ?></label>
              <input type="number" name="alumnos_max" class="form-control bg-dark text-white border-secondary"
                     placeholder="10" min="1" step="1"
                     value="<?= htmlspecialchars($_POST['alumnos_max'] ?? '10') ?>">
            </div>
          </div>

          <!-- Solo -->
          <div class="mb-4 form-check">
            <input class="form-check-input" type="checkbox" name="solo_yo" id="solo_yo"
                   <?= isset($_POST['solo_yo']) ? 'checked' : '' ?>>
            <label class="form-check-label text-secondary" for="solo_yo">
              <?= t('oferta.one_on_one') ?>
            </label>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-secondary flex-grow-1"><?= t('oferta.save') ?></button>
            <a href="crear_clase.php" class="btn btn-dark border-secondary"><?= t('oferta.add_title') ?></a>
          </div>
        </form>

      </div>
    </div>
  </div>

  <footer class="mastfoot mt-auto mt-5">
    <div class="inner float-end">
      <p><?= t('general.footer_text', ['bootstrap' => '<a href="https://getbootstrap.com/">Bootstrap</a>', 'author' => '<a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>']) ?></p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <script type="text/javascript" src="./script.js"></script>
</body>
</html>
