<?php
require 'menu.php';
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';

require_once __DIR__ . '/lib/security_headers.php';

$sesionId = (int)($_GET['sesion'] ?? 0);

// If POST, save rating then redirect to payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $rating = (int)($_POST['rating'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');
    $sesion = dbOne(
        "SELECT s.sesionId, cp.instructorId
         FROM sesiones_clase s
         JOIN clases_programadas cp ON cp.claseId = s.claseId
         WHERE s.sesionId = :id",
        ['id' => $sesionId]
    );

    if ($rating >= 1 && $rating <= 5 && $sesion) {
        $profId = (int)$sesion['instructorId'];

        // Save review with comment
        $existing = dbOne("SELECT resenaId FROM resenas WHERE sesionId = :id", ['id'=>$sesionId]);
        if ($existing) {
            dbExec("UPDATE resenas SET rating = :r, comentario = :c WHERE sesionId = :id", ['r'=>$rating, 'c'=>$comentario, 'id'=>$sesionId]);
        } else {
            dbExec(
                "INSERT INTO resenas (sesionId, estudianteId, profesorId, rating, comentario) VALUES (:sid, :est, :prof, :r, :c)",
                ['sid'=>$sesionId, 'est'=>(int)$_SESSION['usuarioId'], 'prof'=>$profId, 'r'=>$rating, 'c'=>$comentario]
            );
        }

        $prof = dbOne("SELECT calificacion, num_resenas FROM usuarios WHERE usuarioId = :id", ['id'=>$profId]);
        $curAvg = (float)($prof['calificacion'] ?? 0);
        $curCount = (int)($prof['num_resenas'] ?? 0);
        $newCount = $curCount + 1;
        $newAvg = ($curAvg * $curCount + $rating) / max(1, $newCount);
        dbExec("UPDATE usuarios SET calificacion = :avg, num_resenas = :count WHERE usuarioId = :id", ['avg'=>round($newAvg,2),'count'=>$newCount,'id'=>$profId]);
    }

    // After saving rating, go to subjects page
    header('Location: materias.php');
    exit;
}

?>

  <div class="container mt-10">
    <div class="d-flex justify-content-center">
     <div class="card text-center mb-5">
      <div class="card" style="width: 36rem;">
        <div class="card-body">
          <h2 class="card-title"><?= t('calificar.title') ?></h2>
          <h3 class="card-subtitle mb-2 text-secondary"><?= t('calificar.subtitle') ?></h3>
          <form method="POST" action="calificar.php?sesion=<?= $sesionId ?>">
            <?= csrf_field() ?>
            <div class="rating mb-3">
              <input type="radio" name="rating" value="5" id="r5"><label for="r5">★</label>
              <input type="radio" name="rating" value="4" id="r4"><label for="r4">★</label>
              <input type="radio" name="rating" value="3" id="r3"><label for="r3">★</label>
              <input type="radio" name="rating" value="2" id="r2"><label for="r2">★</label>
              <input type="radio" name="rating" value="1" id="r1"><label for="r1">★</label>
            </div>
            <div class="mb-3">
              <textarea name="comentario" class="form-control bg-black border-secondary text-white" rows="3" placeholder="<?= t('calificar.comment_placeholder') ?>" maxlength="500"></textarea>
            </div>
            <div class="d-flex justify-content-between">
              <button type="submit" class="btn btn-dark btn-lg"><?= t('calificar.send') ?></button>
              <a href="materias.php" class="btn btn-secondary btn-lg"><?= t('calificar.skip') ?></a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

  <footer class="mastfoot mt-auto">
    <div class="inner float-end">
      <p><?= t('general.footer_text', ['bootstrap' => '<a href="https://getbootstrap.com/">Bootstrap</a>', 'author' => '<a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>']) ?></p>
    </div>
  </footer>


	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script
  	src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

  <script type="text/javascript" src="./script.js"></script>
</body>
</html>
