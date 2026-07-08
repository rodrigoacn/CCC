<?php require 'menu.php'; ?>

<?php
$userId = $_SESSION['usuarioId'];

$friends = dbAll(
    "SELECT u.usuarioid, u.nombre, u.avatar, u.rol
     FROM usuarios u
     WHERE u.usuarioid IN (
         SELECT seguidoid FROM relaciones WHERE seguidorid = :u
         UNION
         SELECT seguidorid FROM relaciones WHERE seguidoid = :u
     )
     ORDER BY u.nombre ASC",
    ['u' => $userId]
);

$friendIds = array_map(fn($f) => (int)$f['usuarioid'], $friends);

foreach ($friends as &$friend) {
    $last = dbOne(
        "SELECT mensaje, enviado_at FROM mensajes_chat WHERE usuarioid = ? ORDER BY enviado_at DESC LIMIT 1",
        [$friend['usuarioid']]
    );
    $friend['last_mensaje'] = $last['mensaje'] ?? '';
    $friend['last_at']      = $last['enviado_at'] ?? '';
}
unset($friend);

usort($friends, function ($a, $b) {
    return strtotime($b['last_at'] ?? '1970-01-01') <=> strtotime($a['last_at'] ?? '1970-01-01');
});

$recentMessages = [];
if (!empty($friendIds)) {
    $placeholders = implode(',', array_fill(0, count($friendIds), '?'));
    $recentMessages = dbAll(
        "SELECT m.mensaje, m.enviado_at, u.nombre AS usuario
         FROM mensajes_chat m
         LEFT JOIN usuarios u ON u.usuarioid = m.usuarioid
         WHERE m.usuarioid IN ($placeholders)
         ORDER BY m.enviado_at DESC
         LIMIT 5",
        $friendIds
    );
}
?>

<div class="container mt-10">
  <div class="d-flex align-items-center p-3 my-3 vh-10 text-white-50 bg-secondary rounded box-shadow mt-5">
    <div>
      <h6 class="mb-0 text-white lh-100">Amigos</h6>
      <small>Conecta con tus contactos y revisa los mensajes recientes</small>
    </div>
  </div>

  <div class="my-3 p-3 bg-white rounded box-shadow">
    <h6 class="border-bottom border-gray pb-2 mb-3">Mis amigos</h6>
    <?php if (empty($friends)): ?>
      <div class="text-center text-secondary py-4">Aún no tienes amigos agregados. Busca nuevos contactos en Buscar o Profesores.</div>
    <?php else: ?>
      <?php foreach ($friends as $friend): ?>
        <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
          <div class="d-flex align-items-center">
            <img src="<?= htmlspecialchars($friend['avatar'] ?: './rostro_femenino_1.png') ?>" class="rounded-circle me-3" alt="Avatar" style="width:46px;height:46px;object-fit:cover;">
            <div>
              <strong class="d-block"><?= htmlspecialchars($friend['nombre']) ?></strong>
              <small class="text-secondary"><?= $friend['last_mensaje'] ? htmlspecialchars($friend['last_mensaje']) : 'Sin mensajes recientes' ?></small>
            </div>
          </div>
          <div class="text-end">
            <?php if ($friend['last_at']): ?>
              <small class="text-muted d-block"><?= date('d/m H:i', strtotime($friend['last_at'])) ?></small>
            <?php endif; ?>
            <a class="link-secondary small" href="amigos.php">Ver</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="my-3 p-3 bg-white rounded box-shadow">
    <h6 class="border-bottom border-gray pb-2 mb-3">Últimos mensajes</h6>
    <?php if (empty($recentMessages)): ?>
      <div class="text-center text-secondary py-4">No hay mensajes recientes.</div>
    <?php else: ?>
      <?php foreach ($recentMessages as $msg): ?>
        <div class="pb-3 mb-3 border-bottom">
          <strong><?= htmlspecialchars($msg['usuario'] ?? 'Usuario desconocido') ?></strong>
          <div class="text-truncate" style="max-width: 100%;"><?= htmlspecialchars($msg['mensaje']) ?></div>
          <small class="text-muted"><?= date('d/m H:i', strtotime($msg['enviado_at'])) ?></small>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<footer class="mastfoot mt-auto ml-auto">
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
