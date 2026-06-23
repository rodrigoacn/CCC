<?php
require 'menu.php';
require 'db.php';

// Save theme selections to session when the Find-Teacher form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['temas'])) {
    $temas = array_slice((array)$_POST['temas'], 0, 5);
    $_SESSION['temas_tecnologia'] = $temas;
    $qs = http_build_query(['materia' => 10, 'temas' => implode(',', $temas)]);
    header("Location: profesores.php?$qs");
    exit;
}

// Load DB progress for this subject (if logged in)
$completados = [];
if (isset($_SESSION['usuarioId'])) {
    $rows = dbAll(
        "SELECT slug FROM progreso_usuario WHERE usuarioid = :u AND slug != '' AND completado = 1",
        ['u' => $_SESSION['usuarioId']]
    );
    foreach ($rows as $r) $completados[] = $r['slug'];
}

// All themes grouped by section
$secciones = [
    'Digital Literacy, Hardware and Software Systems' => [
        ['slug' => 'hardware-architecture',    'title' => 'Hardware Architecture',       'desc' => 'Core components: CPU, RAM, storage units (SSD/HDD), motherboard, and input/output peripherals.'],
        ['slug' => 'software-os',              'title' => 'Software and Operating Systems','desc' => 'System software versus application software; how the OS manages resources.'],
        ['slug' => 'file-management',          'title' => 'File Management',              'desc' => 'Directory structures, file extensions, and cloud storage systems.'],
        ['slug' => 'data-representation',      'title' => 'Data Representation',          'desc' => 'Binary code, bits, bytes, and data compression basics.'],
    ],
    'Networks, Internet and Cybersecurity' => [
        ['slug' => 'network-fundamentals',     'title' => 'Network Fundamentals',         'desc' => 'LAN/WAN networks, IP addresses, routers, and the Client-Server model.'],
        ['slug' => 'internet-protocols',       'title' => 'Internet Protocols',           'desc' => 'HTTP, HTTPS, FTP, and DNS — how data travels across the web.'],
        ['slug' => 'information-security',     'title' => 'Information Security',         'desc' => 'Threats: Malware, phishing, ransomware, and social engineering.'],
        ['slug' => 'cybersecurity-prevention', 'title' => 'Cybersecurity Prevention',     'desc' => 'Firewalls, multi-factor authentication, encryption, and strong password policies.'],
        ['slug' => 'digital-footprint',        'title' => 'Digital Footprint & Privacy',  'desc' => 'Managing personal data online, cookies, terms of service, and digital identity.'],
    ],
    'Algorithmic Thinking, Programming and Automation' => [
        ['slug' => 'algorithms-logic',         'title' => 'Algorithms and Logic',         'desc' => 'Flowcharts, pseudocode, and decomposition of complex problems.'],
        ['slug' => 'programming-variables',    'title' => 'Programming Core — Variables', 'desc' => 'Variables, constants, and data types (strings, integers, booleans).'],
        ['slug' => 'programming-control',      'title' => 'Programming Core — Control',   'desc' => 'Conditionals (If-Else) and loops (For, While).'],
        ['slug' => 'emerging-tech',            'title' => 'Emerging Technologies',        'desc' => 'Intro to AI / Machine Learning, automation, and the Internet of Things (IoT).'],
    ],
    'Technological Design, Projects and Social Impact' => [
        ['slug' => 'design-process',           'title' => 'The Design Process',           'desc' => 'Problem identification, wireframing, iterative testing, and user-centred design (UX/UI).'],
        ['slug' => 'tech-ethics',              'title' => 'Technological Ethics',         'desc' => 'E-waste, environmental impact of data centres, digital divide, and intellectual property.'],
        ['slug' => 'digital-divide',           'title' => 'Accessibility & Open Source',  'desc' => 'Digital accessibility standards, Creative Commons, and Open Source licensing.'],
    ],
];
?>

  <div class="container mt-10 pb-5">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="materias.php" class="text-secondary">Subjects</a></li>
        <li class="breadcrumb-item active text-white">Technology</li>
      </ol>
    </nav>

    <div class="d-flex align-items-center gap-3 mb-2">
      <img src="technology.png" alt="Technology" style="width:56px;height:56px;object-fit:cover;border-radius:.5rem;">
      <div>
        <h1 class="text-white mb-0">Technology</h1>
        <p class="text-secondary mb-0">Select up to <strong class="text-white">5 themes</strong> you want help with, then find a teacher.</p>
      </div>
    </div>

    <div id="selection-banner" class="alert alert-dark border border-secondary d-flex align-items-center justify-content-between mb-4" style="display:none!important;">
      <span class="text-secondary">
        <span id="sel-count" class="fw-bold text-white">0</span>/5 themes selected
      </span>
      <div id="max-warning" class="text-warning small" style="display:none;">
        ⚠ Maximum 5 themes reached
      </div>
    </div>

    <form method="POST" action="tecnologia.php" id="theme-form">

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
                <th style="width:3rem;" class="text-secondary ps-3">Pick</th>
                <th class="text-secondary">Theme</th>
                <th class="text-secondary d-none d-md-table-cell">Description</th>
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
                         <?= $done ? 'disabled title="Already completed"' : '' ?>>
                </td>
                <td>
                  <label class="form-check-label text-white fw-semibold" for="t-<?= $t['slug'] ?>">
                    <?= htmlspecialchars($t['title']) ?>
                    <?php if ($done): ?>
                      <span class="badge bg-success ms-2 small">✓ Done</span>
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

  <!-- ── Sticky bottom action bar ──────────────────────────────────────────── -->
  <div id="sticky-bar"
       class="position-fixed bottom-0 start-0 end-0 bg-dark border-top border-secondary py-3 px-4"
       style="z-index:1050;transition:transform .25s;transform:translateY(100%);">
    <div class="container d-flex align-items-center justify-content-between gap-3">
      <div>
        <span class="text-white fw-bold" id="bar-count">0</span>
        <span class="text-secondary"> / 5 themes selected</span>
        <div id="bar-names" class="text-secondary small mt-1" style="font-size:.75rem;"></div>
      </div>
      <button type="submit" form="theme-form"
              id="find-teacher-btn"
              class="btn btn-secondary btn-lg px-4 fw-bold"
              disabled>
        Find a Teacher →
      </button>
    </div>
  </div>

  <footer class="mastfoot" style="margin-bottom:5rem;">
    <div class="inner float-end">
      <p>ClassExpress done <a href="https://getbootstrap.com/">Bootstrap</a>, by <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>.</p>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script type="text/javascript" src="./presentacion/odp_ajax.js"></script>
  <script type="text/javascript" src="./presentacion/js/scripts.js"></script>
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

      // Update names list in bar
      names.textContent = checked.map(c => slugToTitle(c.value)).join(' · ');

      // Show/hide sticky bar
      bar.style.transform = n > 0 ? 'translateY(0)' : 'translateY(100%)';

      // Enable/disable button
      btn.disabled = n === 0;

      // Max warning
      if (warn) warn.style.display = n >= MAX ? '' : 'none';

      // Block extras beyond MAX
      document.querySelectorAll('.theme-cb:not(:checked)').forEach(cb => {
        cb.disabled = n >= MAX;
      });
      // Never disable already-done ones (they stay disabled)
    }

    document.querySelectorAll('.theme-cb').forEach(cb => {
      cb.addEventListener('change', () => {
        const checked = document.querySelectorAll('.theme-cb:checked').length;
        if (cb.checked && checked > MAX) { cb.checked = false; }
        update();
      });
    });

    // Highlight row on check
    document.querySelectorAll('.theme-cb').forEach(cb => {
      cb.addEventListener('change', () => {
        cb.closest('tr').classList.toggle('table-secondary', cb.checked);
      });
    });

    update();
  })();
  </script>
</body>
</html>
