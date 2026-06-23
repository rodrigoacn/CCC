<?php
require 'menu.php';
require 'db.php';

if (!isset($_SESSION['usuarioId'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['usuarioId'];

$error   = '';
$success = '';

$materias = dbAll("SELECT materiaId, nombre FROM materias ORDER BY orden ASC");
$teacher  = dbOne(
    "SELECT u.rol, pa.nombre AS pais, pa.simbolo, pa.codigo_moneda, pa.tasa_usd
     FROM usuarios u LEFT JOIN paises pa ON pa.paisId = u.pais_id
     WHERE u.usuarioId = :id",
    ['id' => $uid]
);

// ── SAVE ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo      = trim($_POST['titulo']      ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $precio_min  = max(0, (float)($_POST['precio_min']  ?? 0));
    $precio_max  = max(0, (float)($_POST['precio_max']  ?? 0));
    $alumnos_min = max(1, (int)($_POST['alumnos_min']   ?? 1));
    $alumnos_max = max(1, (int)($_POST['alumnos_max']   ?? 1));
    $materiaId   = (int)($_POST['materiaId'] ?? 0) ?: null;
    $moneda      = $teacher['codigo_moneda'] ?? 'USD';
    $precio_base = $precio_min > 0 ? $precio_min : max(1, $precio_max);

    if (!$titulo) {
        $error = 'Class title is required.';
    } elseif ($precio_max > 0 && $precio_max < $precio_min) {
        $error = 'Maximum price cannot be less than minimum.';
    } elseif ($alumnos_max < $alumnos_min) {
        $error = 'Maximum students cannot be less than minimum.';
    } else {
        $claseId = dbExec(
            "INSERT INTO clases_programadas
                 (instructorId, materiaId, titulo, descripcion, precio_min, precio_max,
                  precio_base, codigo_moneda, alumnos_min, alumnos_max, activa)
             VALUES (:i,:m,:t,:desc,:pmin,:pmax,:pbase,:mon,:amin,:amax,1)",
            [
                'i'     => $uid,
                'm'     => $materiaId,
                't'     => $titulo,
                'desc'  => $descripcion ?: null,
                'pmin'  => $precio_min,
                'pmax'  => $precio_max,
                'pbase' => $precio_base,
                'mon'   => $moneda,
                'amin'  => $alumnos_min,
                'amax'  => $alumnos_max,
            ]
        );
        if ($claseId) {
            $success = 'Class <strong>' . htmlspecialchars($titulo) . '</strong> is now live! '
                     . '<a href="buscar.php" class="alert-link">View it in Find a Class →</a>';
        } else {
            $error = 'Database unavailable. Please try again.';
        }
    }
}
?>

  <div class="container mt-10">
    <div class="row justify-content-center">
      <div class="col-sm-10 col-md-8 col-lg-6">

        <h3 class="text-white mb-1">Create a Class</h3>
        <p class="text-secondary mb-4">
          Fill in all details. Students will see your class in
          <a href="buscar.php" class="text-secondary">Find a Class</a> and join instantly.
        </p>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($teacher): ?>
          <div class="alert alert-dark border border-secondary mb-4 small">
            💰 Prices will be posted in
            <strong class="text-white"><?= htmlspecialchars($teacher['simbolo'] . ' ' . $teacher['codigo_moneda']) ?></strong>
            · Your country: <strong class="text-white"><?= htmlspecialchars($teacher['pais'] ?? '—') ?></strong>
            <br><span class="text-secondary">Students pay in their own local currency, auto-converted.</span>
          </div>
        <?php endif; ?>

        <form method="POST" action="crear_clase.php" class="card bg-dark border-secondary p-4">

          <!-- Title -->
          <div class="mb-3">
            <label class="form-label text-secondary">Class title <span class="text-danger">*</span></label>
            <input type="text" name="titulo" class="form-control bg-dark text-white border-secondary"
                   placeholder="e.g. Introduction to Algebra — Grade 9"
                   value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>" required>
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label class="form-label text-secondary">Short description <span class="text-secondary">(optional)</span></label>
            <textarea name="descripcion" class="form-control bg-dark text-white border-secondary"
                      rows="2" placeholder="What will students learn in this session?"><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
          </div>

          <!-- Subject -->
          <div class="mb-3">
            <label class="form-label text-secondary">Subject</label>
            <select name="materiaId" class="form-select bg-dark text-white border-secondary">
              <option value="">— Any subject —</option>
              <?php foreach ($materias as $m): ?>
                <option value="<?= $m['materiaid'] ?>"
                        <?= (int)($_POST['materiaId'] ?? 0) === (int)$m['materiaid'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($m['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Price -->
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label text-secondary">
                Min price <span class="text-white">(<?= htmlspecialchars($teacher['codigo_moneda'] ?? 'USD') ?>)</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-secondary">
                  <?= htmlspecialchars($teacher['simbolo'] ?? '$') ?>
                </span>
                <input type="number" name="precio_min"
                       class="form-control bg-dark text-white border-secondary"
                       placeholder="0.00" min="0" step="0.01"
                       value="<?= htmlspecialchars($_POST['precio_min'] ?? '') ?>">
              </div>
            </div>
            <div class="col-6">
              <label class="form-label text-secondary">
                Max price <span class="text-white">(<?= htmlspecialchars($teacher['codigo_moneda'] ?? 'USD') ?>)</span>
              </label>
              <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-secondary">
                  <?= htmlspecialchars($teacher['simbolo'] ?? '$') ?>
                </span>
                <input type="number" name="precio_max"
                       class="form-control bg-dark text-white border-secondary"
                       placeholder="0.00" min="0" step="0.01"
                       value="<?= htmlspecialchars($_POST['precio_max'] ?? '') ?>">
              </div>
            </div>
          </div>

          <!-- Student count -->
          <div class="row g-3 mb-4">
            <div class="col-6">
              <label class="form-label text-secondary">Min students</label>
              <input type="number" name="alumnos_min"
                     class="form-control bg-dark text-white border-secondary"
                     placeholder="1" min="1" step="1"
                     value="<?= htmlspecialchars($_POST['alumnos_min'] ?? '1') ?>">
            </div>
            <div class="col-6">
              <label class="form-label text-secondary">Max students</label>
              <input type="number" name="alumnos_max"
                     class="form-control bg-dark text-white border-secondary"
                     placeholder="10" min="1" step="1"
                     value="<?= htmlspecialchars($_POST['alumnos_max'] ?? '10') ?>">
            </div>
          </div>

          <button type="submit" class="btn btn-secondary w-100 fw-semibold py-2">
            🚀 Publish Class
          </button>
          <p class="text-secondary text-center small mt-2 mb-0">
            Students in any LATAM country will see the price converted to their local currency.
          </p>
        </form>

      </div>
    </div>
  </div>

  <footer class="mastfoot mt-auto mt-5">
    <div class="inner float-end">
      <p>ClassExpress done <a href="https://getbootstrap.com/">Bootstrap</a>, by <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script type="text/javascript" src="./presentacion/odp_ajax.js"></script>
  <script type="text/javascript" src="./presentacion/js/scripts.js"></script>
  <script type="text/javascript" src="./script.js"></script>
</body>
</html>
