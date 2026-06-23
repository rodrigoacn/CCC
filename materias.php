<?php
require 'menu.php';
require 'db.php';

// Map materiaId → subject page filename
$pageMap = [
    1  => 'matematicas.php',
    2  => 'biologia.php',
    3  => 'quimica.php',
    4  => 'fisica.php',
    5  => 'historia.php',
    6  => 'geografia.php',
    7  => 'literatura.php',
    8  => 'idiomas.php',
    9  => 'arte.php',
    10 => 'tecnologia.php',
    11 => 'educacion_fisica.php',
];

// Pull subjects from DB ordered by orden; fall back to static list if DB is down
$subjects = dbAll("SELECT materiaId, nombre, imagen FROM materias ORDER BY orden ASC");

if (empty($subjects)) {
    $subjects = [
        ['materiaId'=>1,  'nombre'=>'Mathematics',       'imagen'=>'mathematics.png'],
        ['materiaId'=>2,  'nombre'=>'Biology',            'imagen'=>'biology.png'],
        ['materiaId'=>3,  'nombre'=>'Chemistry',          'imagen'=>'chemistry.png'],
        ['materiaId'=>4,  'nombre'=>'Physics',            'imagen'=>'physics.png'],
        ['materiaId'=>5,  'nombre'=>'History',            'imagen'=>'history.png'],
        ['materiaId'=>6,  'nombre'=>'Geography',          'imagen'=>'geography.png'],
        ['materiaId'=>7,  'nombre'=>'Literature',         'imagen'=>'literature.png'],
        ['materiaId'=>8,  'nombre'=>'Foreign Languages',  'imagen'=>'foreign_languages.png'],
        ['materiaId'=>9,  'nombre'=>'Art and Music',      'imagen'=>'art.png'],
        ['materiaId'=>10, 'nombre'=>'Technology',         'imagen'=>'technology.png'],
        ['materiaId'=>11, 'nombre'=>'Physical Education', 'imagen'=>'physical_education.png'],
    ];
}

$rows = array_chunk($subjects, 3);
?>

  <div class="container mt-10">

    <?php foreach ($rows as $row): ?>
    <div class="row mt-5">
      <?php foreach ($row as $s):
        $id   = (int)$s['materiaid'];
        $href = $pageMap[$id] ?? 'materias.php';
        $img  = htmlspecialchars($s['imagen']);
        $name = htmlspecialchars($s['nombre']);
      ?>
      <div class="col-sm-4">
        <div class="card">
          <div class="card-body">
            <img src="<?= $img ?>" class="card-img" alt="<?= $name ?>">
            <div class="card-img-overlay d-flex flex-column justify-content-center align-items-center">
              <h5 class="card-title fs-1 title-white text-white"><?= $name ?></h5>
              <a href="<?= $href ?>" class="btn btn-light btn-lg fs-1 border-dark">Go</a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <footer class="mastfoot mt-auto">
      <div class="inner float-end">
        <p>ClassExpress done <a href="https://getbootstrap.com/">Bootstrap</a>, by <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>.</p>
      </div>
    </footer>
    <div id="modales"></div>
    <div id="contenido"></div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <script type="text/javascript" src="./presentacion/odp_ajax.js"></script>
  <script type="text/javascript" src="./presentacion/js/scripts.js"></script>
</body>
</html>
