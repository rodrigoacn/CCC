<?php

require_once __DIR__ . '/lib/security_headers.php';
/**
 * _subject_page.php — Shared subject-page renderer.
 * Called by each subject page after defining:
 *   $materiaId    int
 *   $subjectName  string  (English display name)
 *   $subjectImage string  (filename, e.g. "physics.png")
 *   $secciones    array   [ 'Section Title' => [ ['slug'=>…,'title'=>…,'desc'=>…], … ] ]
 */
require_once __DIR__ . '/lib/csrf.php';

// Load completed topic slugs for the logged-in student
$completados = [];
if (isset($_SESSION['usuarioId'])) {
    $rows = dbAll(
        "SELECT slug FROM progreso_usuario WHERE usuarioid = :u AND slug != '' AND completado = 1",
        ['u' => $_SESSION['usuarioId']]
    );
    foreach ($rows as $r) $completados[] = $r['slug'];
}

$slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $subjectName));
$translatedName = t('subject.name.' . $materiaId) ?: $subjectName;
?>

  <div class="container mt-10 pb-5">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="materias.php" class="text-secondary"><?= t('tecnologia.breadcrumb_subjects') ?></a></li>
        <li class="breadcrumb-item active" style="color:var(--p)"><?= htmlspecialchars($translatedName) ?></li>
      </ol>
    </nav>

    <div class="d-flex align-items-center gap-3 mb-2">
      <img src="<?= htmlspecialchars($subjectImage) ?>" alt="<?= htmlspecialchars($translatedName) ?>"
           style="width:56px;height:56px;object-fit:cover;border-radius:.5rem;">
      <div>
        <h1 class="mb-0" style="color:var(--p)"><?= htmlspecialchars($translatedName) ?></h1>
        <p class="text-secondary mb-0"><?= t('tecnologia.subtitle', ['max' => 5]) ?></p>
      </div>
    </div>

    <div id="selection-banner" class="alert alert-dark border border-secondary d-flex align-items-center justify-content-between mb-4" style="display:none!important;">
      <span class="text-secondary">
        <span id="sel-count" class="fw-bold text-white">0</span><?= t('tecnologia.themes_selected', ['max' => 5]) ?>
      </span>
      <div id="max-warning" class="text-warning small" style="display:none;">
        <?= t('tecnologia.max_warning', ['max' => 5]) ?>
      </div>
    </div>

    <form method="POST" id="theme-form">
      <?= csrf_field() ?>

      <?php foreach ($secciones as $caption => $temas): ?>
      <div class="card bg-dark border-secondary mb-3">
        <div class="card-header border-secondary">
          <h6 class="text-secondary mb-0 text-uppercase" style="font-size:.75rem;letter-spacing:.08em;">
            <?= htmlspecialchars($caption) ?>
          </h6>
        </div>
        <div class="card-body p-0">
          <table class="table table-dark table-hover mb-0">
            <thead>
              <tr>
                <th style="width:3rem;" class="text-secondary ps-3"><?= t('tecnologia.pick') ?></th>
                <th class="text-secondary"><?= t('tecnologia.theme_col') ?></th>
                <th class="text-secondary d-none d-md-table-cell"><?= t('tecnologia.description_col') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($temas as $t):
                $done = in_array($t['slug'], $completados);
              ?>
              <tr class="theme-row <?= $done ? 'opacity-50' : '' ?>" data-slug="<?= $t['slug'] ?>">
                <td class="ps-3">
                  <input class="form-check-input theme-cb mt-0" type="checkbox"
                         name="temas[]" value="<?= $t['slug'] ?>"
                         id="t-<?= $t['slug'] ?>"
                          <?= $done ? 'disabled title="' . t('tecnologia.already_completed') . '"' : '' ?>>
                </td>
                <td>
                  <label class="form-check-label fw-semibold" style="color:var(--p)" for="t-<?= $t['slug'] ?>">
                    <?= htmlspecialchars($t['title']) ?>
                    <?php if ($done): ?>
                      <span class="badge bg-success ms-2 small"><?= t('tecnologia.done_badge') ?></span>
                    <?php endif; ?>
                  </label>
                </td>
                <td class="text-secondary small d-none d-md-table-cell">
                  <?= htmlspecialchars($t['desc']) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>

    </form>

  </div>

  <!-- â”€â”€ Sticky bottom action bar â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
  <div id="sticky-bar"
       class="position-fixed bottom-0 start-0 end-0 bg-dark border-top border-secondary py-3 px-4"
       style="z-index:1050;transition:transform .25s;transform:translateY(100%);">
    <div class="container d-flex align-items-center justify-content-between gap-3">
      <div>
        <span class="fw-bold" id="bar-count" style="color:var(--p)">0</span>
        <span class="text-secondary"><?= t('tecnologia.themes_selected', ['max' => 5]) ?></span>
        <div id="bar-names" class="text-secondary small mt-1" style="font-size:.75rem;"></div>
      </div>
      <button type="submit" form="theme-form"
              id="find-teacher-btn"
              class="btn btn-lg px-4 fw-bold"
              style="background:var(--p);color:#fff;border-color:var(--p)"
              disabled>
        <?= t('tecnologia.find_teacher') ?>
      </button>
    </div>
  </div>

  <footer class="mastfoot" style="margin-bottom:5rem;">
    <div class="inner float-end">
      <p><?= t('general.footer_text', ['bootstrap' => '<a href="https://getbootstrap.com/">Bootstrap</a>', 'author' => '<a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>']) ?></p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <script>
  (function () {
    const MAX = 5;
    const bar   = document.getElementById('sticky-bar');
    const cntEl = document.getElementById('bar-count');
    const names = document.getElementById('bar-names');
    const btn   = document.getElementById('find-teacher-btn');
    const warn  = document.getElementById('max-warning');

    function slugToTitle(slug) {
      return slug.replace(/-/g,' ').replace(/\b\w/g,c=>c.toUpperCase());
    }

    function update() {
      const checked = [...document.querySelectorAll('.theme-cb:checked')];
      const n = checked.length;

      cntEl.textContent = n;
      document.getElementById('sel-count').textContent = n;
      names.textContent = checked.map(c => slugToTitle(c.value)).join(' · ');

      bar.style.transform = n > 0 ? 'translateY(0)' : 'translateY(100%)';
      btn.disabled = n === 0;
      if (warn) warn.style.display = n >= MAX ? '' : 'none';

      document.querySelectorAll('.theme-cb:not(:checked):not([data-done])').forEach(cb => {
        cb.disabled = n >= MAX;
      });
    }

    document.querySelectorAll('.theme-cb').forEach(cb => {
      cb.addEventListener('change', () => {
        const checked = document.querySelectorAll('.theme-cb:checked').length;
        if (cb.checked && checked > MAX) { cb.checked = false; }
        cb.closest('tr').classList.toggle('table-secondary', cb.checked);
        update();
      });
    });

    update();
  })();
  </script>
</body>
</html>
