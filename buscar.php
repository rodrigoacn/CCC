<?php
require 'menu.php';
require 'db.php';

// ── Available classes (posted by teachers) ───────────────────────────────────
$clases = dbAll(
    "SELECT cp.claseId, cp.titulo, cp.descripcion, cp.precio_base, cp.codigo_moneda,
            cp.alumnos_min, cp.alumnos_max, cp.solo_yo,
            u.nombre AS profesor, u.calificacion, u.num_resenas, u.avatar,
            pa.nombre AS pais_profesor, pa.simbolo AS simbolo_prof,
            m.nombre AS materia, m.imagen AS materia_img,
            (SELECT COUNT(*) FROM sesiones_clase sc
             WHERE sc.claseId = cp.claseId AND sc.fin IS NULL) AS alumnos_activos
     FROM clases_programadas cp
     JOIN usuarios u ON u.usuarioId = cp.instructorId
     LEFT JOIN paises pa ON pa.paisId = u.pais_id
     LEFT JOIN materias m ON m.materiaId = cp.materiaId
     WHERE cp.activa = 1
     ORDER BY u.calificacion DESC, cp.claseId DESC"
);

// ── Students looking for a teacher (no active session) ───────────────────────
$estudiantes = dbAll(
    "SELECT u.usuarioId, u.nombre, u.calificacion, u.avatar, u.biografia,
            pa.nombre AS pais, pa.codigo_moneda, pa.simbolo
     FROM usuarios u
     LEFT JOIN paises pa ON pa.paisId = u.pais_id
     WHERE u.rol = 'student'
       AND u.verificado = 1
       AND u.usuarioId NOT IN (
           SELECT DISTINCT estudianteId FROM sesiones_clase WHERE fin IS NULL
       )
     ORDER BY u.nombre ASC"
);

// ── Materias for filter ───────────────────────────────────────────────────────
$materias = dbAll("SELECT materiaId, nombre FROM materias ORDER BY orden ASC");
?>

  <div class="container mt-10">

    <h2 class="text-white mb-1">Find a Class</h2>
    <p class="text-secondary mb-4">Connect with teachers or recruit students for your next session.</p>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-0" id="buscarTabs" role="tablist">
      <li class="nav-item">
        <button class="nav-link active text-white" id="tab-student-btn"
                data-bs-toggle="tab" data-bs-target="#tab-student" type="button">
          🎓 I'm a Student — Find a Teacher
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link text-secondary" id="tab-teacher-btn"
                data-bs-toggle="tab" data-bs-target="#tab-teacher" type="button">
          📋 I'm a Teacher — Find Students
        </button>
      </li>
    </ul>

    <div class="tab-content bg-dark border border-top-0 border-secondary rounded-bottom p-4" id="buscarTabContent">

      <!-- ── STUDENT VIEW ──────────────────────────────────────────────────── -->
      <div class="tab-pane fade show active" id="tab-student" role="tabpanel">

        <!-- Filters -->
        <div class="row g-2 mb-4">
          <div class="col-sm-4">
            <input type="text" id="filter-title" class="form-control bg-dark text-white border-secondary"
                   placeholder="🔍 Search class title or teacher…">
          </div>
          <div class="col-sm-3">
            <select id="filter-materia" class="form-select bg-dark text-white border-secondary">
              <option value="">All subjects</option>
              <?php foreach ($materias as $m): ?>
                <option value="<?= htmlspecialchars($m['nombre']) ?>">
                  <?= htmlspecialchars($m['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-sm-3">
            <input type="number" id="filter-max-price" class="form-control bg-dark text-white border-secondary"
                   placeholder="Max price (USD)" min="0">
          </div>
          <div class="col-sm-2">
            <button class="btn btn-secondary w-100" onclick="filterClases()">Filter</button>
          </div>
        </div>

        <!-- Class Cards -->
        <?php if (empty($clases)): ?>
          <div class="alert alert-secondary text-center">
            No classes available right now. Check back soon!
          </div>
        <?php else: ?>
          <div class="row g-3" id="clases-grid">
            <?php foreach ($clases as $c):
              $spots_left = $c['alumnos_max'] - $c['alumnos_activos'];
              $full       = $spots_left <= 0;
              $stars      = str_repeat('★', min(5, round($c['calificacion'])))
                          . str_repeat('☆', max(0, 5 - round($c['calificacion'])));
            ?>
            <div class="col-sm-6 col-lg-4 clase-card"
                 data-titulo="<?= strtolower(htmlspecialchars($c['titulo'])) ?>"
                 data-materia="<?= htmlspecialchars($c['materia'] ?? '') ?>"
                 data-precio="<?= $c['precio_base'] ?>">
              <div class="card bg-dark border-secondary h-100">
                <div class="card-body">
                  <!-- Subject badge -->
                  <?php if ($c['materia']): ?>
                    <span class="badge bg-secondary mb-2"><?= htmlspecialchars($c['materia']) ?></span>
                  <?php endif; ?>

                  <h5 class="card-title text-white"><?= htmlspecialchars($c['titulo']) ?></h5>
                  <?php if ($c['descripcion']): ?>
                    <p class="text-secondary small"><?= htmlspecialchars($c['descripcion']) ?></p>
                  <?php endif; ?>

                  <!-- Teacher info -->
                  <div class="d-flex align-items-center gap-2 mt-3 mb-2">
                    <?php if ($c['avatar']): ?>
                      <img src="<?= htmlspecialchars($c['avatar']) ?>"
                           class="rounded-circle" width="36" height="36" style="object-fit:cover;" alt="">
                    <?php else: ?>
                      <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center"
                           style="width:36px;height:36px;font-size:1rem;">👤</div>
                    <?php endif; ?>
                    <div>
                      <div class="text-white fw-semibold small"><?= htmlspecialchars($c['profesor']) ?></div>
                      <div class="text-warning" style="font-size:.75rem;"><?= $stars ?> <span class="text-secondary">(<?= $c['num_resenas'] ?>)</span></div>
                    </div>
                    <?php if ($c['pais_profesor']): ?>
                      <span class="ms-auto badge bg-dark border border-secondary text-secondary small">
                        📍 <?= htmlspecialchars($c['pais_profesor']) ?>
                      </span>
                    <?php endif; ?>
                  </div>

                  <hr class="border-secondary">

                  <!-- Price and spots -->
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <span class="text-white fw-bold fs-5">
                        <?= $c['simbolo_prof'] ?? '$' ?><?= number_format($c['precio_base'], 2) ?>
                      </span>
                      <span class="text-secondary small ms-1"><?= htmlspecialchars($c['codigo_moneda']) ?> / session</span>
                    </div>
                    <span class="badge <?= $full ? 'bg-danger' : 'bg-success' ?>">
                      <?= $full ? 'Full' : $spots_left . ' spot' . ($spots_left !== 1 ? 's' : '') . ' left' ?>
                    </span>
                  </div>
                </div>

                <div class="card-footer bg-dark border-secondary">
                  <?php if ($full): ?>
                    <button class="btn btn-secondary w-100" disabled>Class Full</button>
                  <?php else: ?>
                    <a href="sala.php?clase=<?= $c['claseId'] ?>"
                       class="btn btn-dark border-secondary w-100 text-white">
                      Join Class →
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ── TEACHER VIEW ──────────────────────────────────────────────────── -->
      <div class="tab-pane fade" id="tab-teacher" role="tabpanel">

        <div class="d-flex justify-content-between align-items-center mb-4">
          <p class="text-secondary mb-0">Students currently looking for a class to join:</p>
          <a href="example20.php" class="btn btn-dark border-secondary text-white">+ Post a New Class</a>
        </div>

        <?php if (empty($estudiantes)): ?>
          <div class="alert alert-secondary text-center">
            All students are currently in a session. Check back soon!
          </div>
        <?php else: ?>
          <div class="row g-3">
            <?php foreach ($estudiantes as $e):
              $stars = $e['calificacion'] > 0
                ? str_repeat('★', round($e['calificacion'])) . str_repeat('☆', 5 - round($e['calificacion']))
                : '—';
            ?>
            <div class="col-sm-6 col-lg-4">
              <div class="card bg-dark border-secondary h-100">
                <div class="card-body d-flex gap-3 align-items-start">
                  <?php if ($e['avatar']): ?>
                    <img src="<?= htmlspecialchars($e['avatar']) ?>"
                         class="rounded-circle" width="48" height="48" style="object-fit:cover;" alt="">
                  <?php else: ?>
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:48px;height:48px;font-size:1.3rem;">👤</div>
                  <?php endif; ?>
                  <div>
                    <h6 class="text-white mb-0"><?= htmlspecialchars($e['nombre']) ?></h6>
                    <?php if ($e['pais']): ?>
                      <div class="text-secondary small">📍 <?= htmlspecialchars($e['pais']) ?>
                        (<?= htmlspecialchars($e['simbolo'] . ' ' . $e['codigo_moneda']) ?>)
                      </div>
                    <?php endif; ?>
                    <?php if ($e['biografia']): ?>
                      <p class="text-secondary small mt-1 mb-0"><?= htmlspecialchars(mb_substr($e['biografia'], 0, 80)) ?>…</p>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="card-footer bg-dark border-secondary">
                  <a href="example20.php?invite=<?= $e['usuarioId'] ?>"
                     class="btn btn-dark border-secondary w-100 text-white small">
                    Invite to a Class →
                  </a>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div><!-- /tab-content -->

  </div><!-- /container -->

  <footer class="mastfoot mt-auto mt-5">
    <div class="inner float-end">
      <p>ClassExpress done <a href="https://getbootstrap.com/">Bootstrap</a>, by <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script type="text/javascript" src="./presentacion/odp_ajax.js"></script>
  <script type="text/javascript" src="./presentacion/js/scripts.js"></script>
  <script>
  function filterClases() {
    const title    = document.getElementById('filter-title').value.toLowerCase();
    const materia  = document.getElementById('filter-materia').value.toLowerCase();
    const maxPrice = parseFloat(document.getElementById('filter-max-price').value) || Infinity;

    document.querySelectorAll('.clase-card').forEach(card => {
      const tOk = !title   || card.dataset.titulo.includes(title);
      const mOk = !materia || card.dataset.materia.toLowerCase() === materia;
      const pOk = parseFloat(card.dataset.precio) <= maxPrice;
      card.style.display = (tOk && mOk && pOk) ? '' : 'none';
    });
  }
  document.getElementById('filter-title').addEventListener('keyup', filterClases);
  document.getElementById('filter-materia').addEventListener('change', filterClases);
  document.getElementById('filter-max-price').addEventListener('input', filterClases);
  </script>
</body>
</html>
