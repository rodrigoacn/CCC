<?php
require 'menu.php';
require 'db.php';

$sesionId = (int)($_GET['sesion'] ?? 0);

// If POST, save rating then redirect to payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 0);
    $sesion = dbOne(
        "SELECT s.sesionId, cp.instructorId
         FROM sesiones_clase s
         JOIN clases_programadas cp ON cp.claseId = s.claseId
         WHERE s.sesionId = :id",
        ['id' => $sesionId]
    );

    if ($rating >= 1 && $rating <= 5 && $sesion) {
        $profId = (int)$sesion['instructorid'];
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
          <h2 class="card-title">Califica la clase</h2>
          <h3 class="card-subtitle mb-2 text-secondary">¿Cómo fue la experiencia con el profesor?</h3>
          <form method="POST" action="calificar.php?sesion=<?= $sesionId ?>">
            <div class="rating mb-3">
              <input type="radio" name="rating" value="5" id="r5"><label for="r5">☆</label>
              <input type="radio" name="rating" value="4" id="r4"><label for="r4">☆</label>
              <input type="radio" name="rating" value="3" id="r3"><label for="r3">☆</label>
              <input type="radio" name="rating" value="2" id="r2"><label for="r2">☆</label>
              <input type="radio" name="rating" value="1" id="r1"><label for="r1">☆</label>
            </div>
            <div class="d-flex justify-content-between">
              <button type="submit" class="btn btn-dark btn-lg">Enviar</button>
              <a href="materias.php" class="btn btn-secondary btn-lg">Omitir</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

  <footer class="mastfoot mt-auto">
    <div class="inner float-end">
      <p>ClassExpress done <a href="https://getbootstrap.com/">Bootstrap</a>, by <a href="https://www.facebook.com/rodrigo.alejandro.1848816?locale=es_LA">@RodrigoConejeros</a>.</p>
    </div>
  </footer>


	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script
  	src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
	<script type="text/javascript" src="./presentacion/odp_ajax.js"></script>
	<script type="text/javascript" src="./presentacion/js/scripts.js"></script>
  <script type="text/javascript" src="./script.js"></script>
</body>
</html>