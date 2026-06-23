<?php
require 'menu.php';
require 'db.php';

// ── Inputs from tecnologia.php (or any subject page) ─────────────────────────
$materiaId  = (int)($_GET['materia'] ?? 0) ?: null;
$temas_raw  = trim($_GET['temas'] ?? '');
$temas      = $temas_raw
    ? array_map('trim', explode(',', $temas_raw))
    : ($_SESSION['temas_tecnologia'] ?? []);
$temas = array_slice($temas, 0, 5);

// ── Load materia info ─────────────────────────────────────────────────────────
$materia_info = $materiaId
    ? dbOne("SELECT materiaId, nombre, imagen FROM materias WHERE materiaId = :id", ['id' => $materiaId])
    : null;

// ── Query: teachers with active classes, soonest/most active first ────────────
// Sort: classes with active students first, then newest class
$whereMateria = $materiaId ? 'AND cp.materiaId = :mid' : '';
$params = $materiaId ? ['mid' => $materiaId] : [];

$clases = dbAll(
    "SELECT cp.claseId, cp.titulo, cp.descripcion, cp.precio_base, cp.codigo_moneda,
            cp.alumnos_min, cp.alumnos_max, cp.solo_yo, cp.created_at,
            u.usuarioId AS profId, u.nombre AS prof_nombre, u.avatar,
            u.calificacion, u.num_resenas, u.biografia,
            pa.nombre AS pais, pa.simbolo, pa.codigo_moneda AS mon_prof,
            m.nombre AS materia_nombre, m.imagen AS materia_img,
            COUNT(sc.sesionId) AS alumnos_activos
     FROM clases_programadas cp
     JOIN usuarios u          ON u.usuarioId  = cp.instructorId
     LEFT JOIN paises pa       ON pa.paisId    = u.pais_id
     LEFT JOIN materias m      ON m.materiaId  = cp.materiaId
     LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId AND sc.fin IS NULL
     WHERE cp.activa = 1
     $whereMateria
     GROUP BY cp.claseId, cp.titulo, cp.descripcion, cp.precio_base, cp.codigo_moneda,
              cp.alumnos_min, cp.alumnos_max, cp.solo_yo, cp.created_at,
              u.usuarioId, u.nombre, u.avatar, u.calificacion, u.num_resenas, u.biografia,
              pa.nombre, pa.simbolo, pa.codigo_moneda, m.nombre, m.imagen
     ORDER BY alumnos_activos DESC, cp.created_at DESC",
    $params
);

// ── Convert slug to readable title ───────────────────────────────────────────
function slugToTitle(string $slug): string {
    return ucwords(str_replace('-', ' ', $slug));
}

// ── Human-readable "started X ago" ───────────────────────────────────────────
function timeAgo(string $ts): string {
    $diff = time() - strtotime($ts);
    if ($diff < 60)             return 'just now';
    if ($diff < 3600)           return intval($diff/60) . ' min ago';
    if ($diff < 86400)          return intval($diff/3600) . ' hr ago';
    return intval($diff/86400) . ' days ago';
}
?>

  <div class="container mt-10 pb-5">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="materias.php" class="text-secondary">Subjects</a></li>
        <?php if ($materia_info): ?>
          <li class="breadcrumb-item">
            <a href="tecnologia.php" class="text-secondary"><?= htmlspecialchars($materia_info['nombre']) ?></a>
          </li>
        <?php endif; ?>
        <li class="breadcrumb-item active text-white">Find a Teacher</li>
      </ol>
    </nav>

    <!-- Context banner ──────────────────────────────────────────────────────── -->
    <?php if ($materia_info || !empty($temas)): ?>
    <div class="card bg-dark border-secondary mb-4">
      <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <?php if ($materia_info && $materia_info['imagen']): ?>
            <img src="<?= htmlspecialchars($materia_info['imagen']) ?>"
                 style="width:48px;height:48px;object-fit:cover;border-radius:.4rem;" alt="">
          <?php endif; ?>
          <div>
            <?php if ($materia_info): ?>
              <div class="text-white fw-bold">
                Looking for a <?= htmlspecialchars($materia_info['nombre']) ?> teacher
              </div>
            <?php endif; ?>
            <?php if (!empty($temas)): ?>
              <div class="text-secondary small mt-1">
                <span class="text-white">Selected themes:</span>
                <?php foreach ($temas as $t): ?>
                  <span class="badge bg-secondary ms-1"><?= htmlspecialchars(slugToTitle($t)) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <a href="<?= $materia_info ? 'tecnologia.php' : 'materias.php' ?>"
             class="btn btn-sm btn-dark border-secondary ms-auto">← Change themes</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h2 class="text-white mb-0">Available Teachers</h2>
        <p class="text-secondary mb-0 small">
          Sorted by activity — join any class that's running now.
        </p>
      </div>
      <a href="buscar.php<?= $materiaId ? '?materia='.$materiaId : '' ?>"
         class="btn btn-dark border-secondary">All Classes →</a>
    </div>

    <!-- ── Teacher / Class cards ─────────────────────────────────────────────── -->
    <?php if (empty($clases)): ?>
      <div class="alert alert-dark border border-secondary text-center py-4">
        <p class="text-secondary mb-2">No teachers are running a live class right now.</p>
        <p class="text-secondary small mb-3">Be the first to post one, or come back in a few minutes.</p>
        <a href="crear_clase.php<?= $materiaId ? '?materia='.$materiaId : '' ?>"
           class="btn btn-secondary">Post a Class →</a>
      </div>

      <!-- ── Demo / seed teachers (static fallback so the page is never empty) ── -->
      <p class="text-secondary small mb-3 mt-4">Teachers registered on ClassExpress:</p>
      <div class="row g-3">
        <?php
        $demo = [
          ['nombre'=>'Alexander V.', 'rol'=>'Director',   'rating'=>4.8, 'reseñas'=>123, 'time'=>'00:05', 'pais'=>'Chile'],
          ['nombre'=>'Liam S.',      'rol'=>'Instructor', 'rating'=>4.2, 'reseñas'=>98,  'time'=>'10:00', 'pais'=>'Mexico'],
          ['nombre'=>'Elena R.',     'rol'=>'Assistant',  'rating'=>3.5, 'reseñas'=>47,  'time'=>'15:00', 'pais'=>'Argentina'],
          ['nombre'=>'Marcus T.',    'rol'=>'Researcher', 'rating'=>4.9, 'reseñas'=>210, 'time'=>'05:00', 'pais'=>'Colombia'],
          ['nombre'=>'Sophia L.',    'rol'=>'Adviser',    'rating'=>3.8, 'reseñas'=>61,  'time'=>'08:00', 'pais'=>'Peru'],
          ['nombre'=>'Diana P.',     'rol'=>'Director',   'rating'=>4.5, 'reseñas'=>175, 'time'=>'12:00', 'pais'=>'Brazil'],
        ];
        foreach ($demo as $d):
          $stars = str_repeat('★', round($d['rating'])) . str_repeat('☆', 5-round($d['rating']));
        ?>
        <div class="col-sm-6 col-lg-4">
          <div class="card bg-dark border-secondary h-100">
            <div class="card-body">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:52px;height:52px;font-size:1.4rem;">👤</div>
                <div>
                  <div class="text-white fw-bold"><?= $d['nombre'] ?></div>
                  <div class="text-secondary small"><?= $d['rol'] ?> · 📍 <?= $d['pais'] ?></div>
                </div>
              </div>
              <div class="text-warning mb-2" style="font-size:.85rem;">
                <?= $stars ?> <span class="text-secondary">(<?= $d['reseñas'] ?> reviews)</span>
              </div>
              <div class="d-flex align-items-center gap-2 text-secondary small">
                <span class="badge bg-warning text-dark">⏱ Starts in <?= $d['time'] ?></span>
                <?php if ($materia_info): ?>
                  <span class="badge bg-secondary"><?= htmlspecialchars($materia_info['nombre']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="card-footer bg-dark border-secondary">
              <a href="crear_clase.php" class="btn btn-dark border-secondary w-100 text-secondary" disabled>
                No live class yet
              </a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

    <?php else: ?>

      <div class="row g-3" id="teachers-grid">
        <?php foreach ($clases as $c):
          $spots   = $c['alumnos_max'] - $c['alumnos_activos'];
          $full    = $spots <= 0;
          $active  = $c['alumnos_activos'] > 0;
          $stars   = str_repeat('★', min(5, (int)round($c['calificacion'])))
                   . str_repeat('☆', max(0, 5 - (int)round($c['calificacion'])));
        ?>
        <div class="col-sm-6 col-lg-4">
          <div class="card bg-dark border-secondary h-100 <?= $active ? 'border-success' : '' ?>"
               style="<?= $active ? 'border-color:#6ea86e!important;' : '' ?>">

            <?php if ($active): ?>
              <div class="card-header border-0 py-2 px-3"
                   style="background:rgba(110,168,110,.15);">
                <span class="text-success small fw-bold">🟢 Live now — <?= $c['alumnos_activos'] ?> student<?= $c['alumnos_activos']!==1?'s':'' ?> in class</span>
              </div>
            <?php else: ?>
              <div class="card-header border-0 py-2 px-3 bg-dark">
                <span class="text-secondary small">🕐 Posted <?= timeAgo($c['created_at']) ?></span>
              </div>
            <?php endif; ?>

            <div class="card-body">
              <!-- Subject badge -->
              <?php if ($c['materia_nombre']): ?>
                <span class="badge bg-secondary mb-2"><?= htmlspecialchars($c['materia_nombre']) ?></span>
              <?php endif; ?>

              <h5 class="card-title text-white mb-1"><?= htmlspecialchars($c['titulo'] ?: 'Open Class') ?></h5>
              <?php if ($c['descripcion']): ?>
                <p class="text-secondary small mb-3"><?= htmlspecialchars(mb_substr($c['descripcion'], 0, 100)) ?>…</p>
              <?php endif; ?>

              <!-- Teacher row -->
              <div class="d-flex align-items-center gap-2 mb-3">
                <?php if ($c['avatar']): ?>
                  <img src="<?= htmlspecialchars($c['avatar']) ?>"
                       class="rounded-circle flex-shrink-0"
                       width="44" height="44" style="object-fit:cover;" alt="">
                <?php else: ?>
                  <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center flex-shrink-0"
                       style="width:44px;height:44px;font-size:1.2rem;">👤</div>
                <?php endif; ?>
                <div class="min-w-0">
                  <div class="text-white fw-semibold text-truncate"><?= htmlspecialchars($c['prof_nombre']) ?></div>
                  <div class="text-warning" style="font-size:.75rem;">
                    <?= $stars ?>
                    <span class="text-secondary">(<?= (int)$c['num_resenas'] ?>)</span>
                  </div>
                  <?php if ($c['pais']): ?>
                    <div class="text-secondary" style="font-size:.72rem;">📍 <?= htmlspecialchars($c['pais']) ?></div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Price + spots -->
              <div class="d-flex justify-content-between align-items-end">
                <div>
                  <span class="text-white fw-bold fs-5">
                    <?= htmlspecialchars($c['simbolo'] ?? '$') ?><?= number_format($c['precio_base'], 2) ?>
                  </span>
                  <span class="text-secondary small ms-1"><?= htmlspecialchars($c['codigo_moneda']) ?>/session</span>
                  <div class="text-secondary" style="font-size:.72rem;">Billed in your local currency</div>
                </div>
                <span class="badge <?= $full ? 'bg-danger' : ($active ? 'bg-success' : 'bg-secondary') ?>">
                  <?= $full ? 'Full' : ($active ? $spots.' spot'.($spots!==1?'s':'').' left' : 'Open') ?>
                </span>
              </div>
            </div>

            <div class="card-footer bg-dark border-secondary">
              <?php if ($full): ?>
                <button class="btn btn-secondary w-100" disabled>Class Full</button>
              <?php else: ?>
                <a href="sala.php?clase=<?= $c['claseid'] ?>"
                   class="btn <?= $active ? 'btn-success' : 'btn-dark border-secondary' ?> w-100 fw-semibold">
                  <?= $active ? '🟢 Join Live Class →' : 'Start Session →' ?>
                </a>
              <?php endif; ?>
            </div>

          </div>
        </div>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>

  </div>

  <footer class="mastfoot mt-5">
    <div class="inner float-end">
      <p>ClassExpress done <a href="https://getbootstrap.com/">Bootstrap</a>, by <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script type="text/javascript" src="./presentacion/odp_ajax.js"></script>
  <script type="text/javascript" src="./presentacion/js/scripts.js"></script>
</body>
</html>
