<?php
$subjectId  = (int)($_GET['s'] ?? 0);
if ($subjectId >= 1 && $subjectId <= 11) $_GET['materia'] = $subjectId;

require 'menu.php';
require 'db.php';

$search     = $_GET['q'] ?? '';
$activeOnly = ($_GET['live'] ?? '') === '1';
$sort       = $_GET['sort'] ?? 'relevance';
$uid        = (int)$_SESSION['usuarioId'];

$subjects = dbAll("SELECT materiaId AS id, nombre FROM materias ORDER BY nombre");
if (empty($subjects)) {
    $names = ['Mathematics','Biology','Chemistry','Physics','History','Geography','Literature','Foreign Languages','Art and Music','Technology','Physical Education'];
    foreach ($names as $i => $n) $subjects[] = ['id' => $i + 1, 'nombre' => $n];
}

$colors = [
    1=>'#2563EB',2=>'#059669',3=>'#7C3AED',4=>'#0284C7',5=>'#D97706',
    6=>'#0D9488',7=>'#DC2626',8=>'#DB2777',9=>'#EA580C',10=>'#0891B2',11=>'#E11D48',
];

$sql = "SELECT cp.claseId AS claseId, cp.titulo, cp.descripcion, cp.precio_base, cp.duracion_min, cp.calificacion, cp.alumnos_max, cp.alumnos_activos,
               m.nombre AS materia,
               u.nombre AS profesor,
               (SELECT s.activa FROM salas s WHERE s.claseId = cp.claseId AND s.activa = true LIMIT 1) AS sala_activa,
               (SELECT COALESCE(SUM(sc.segundos_acumulados), 0) FROM sesiones_clase sc WHERE sc.claseId = cp.claseId) AS total_visto,
               (SELECT COUNT(*) FROM relaciones r WHERE r.seguidoId = cp.instructorId AND r.seguidorId = :uid AND r.estado = 'following') AS es_amigo
        FROM clases_programadas cp
        JOIN materias m ON m.materiaId = cp.materiaId
        JOIN usuarios u ON u.usuarioId = cp.instructorId
        WHERE cp.activa = true";
$params = ['uid' => $uid];
if ($search) {
    $sql .= " AND (cp.titulo LIKE :q OR u.nombre LIKE :q2 OR m.nombre LIKE :q3 OR cp.descripcion LIKE :q4)";
    $params['q'] = "%$search%"; $params['q2'] = "%$search%"; $params['q3'] = "%$search%"; $params['q4'] = "%$search%";
}
if ($subjectId) { $sql .= " AND cp.materiaId = :s"; $params['s'] = $subjectId; }
if ($activeOnly) { $sql .= " AND EXISTS (SELECT 1 FROM salas s WHERE s.claseId = cp.claseId AND s.activa = true)"; }

switch ($sort) {
    case 'price_asc':   $orderBy = "cp.precio_base ASC"; break;
    case 'price_desc':  $orderBy = "cp.precio_base DESC"; break;
    case 'rating':      $orderBy = "cp.calificacion DESC, total_visto DESC"; break;
    case 'popular':     $orderBy = "total_visto DESC"; break;
    case 'newest':      $orderBy = "cp.created_at DESC"; break;
    case 'relevance':
    default:
        $orderBy = "es_amigo DESC, sala_activa IS NULL, sala_activa DESC, total_visto DESC, cp.precio_base ASC";
        break;
}
$sql .= " ORDER BY $orderBy LIMIT 50";
$classes = dbAll($sql, $params);

$sortOpts = [
    'relevance' => t('buscar.sort_relevance'),
    'popular'   => t('buscar.sort_popular'),
    'rating'    => t('buscar.sort_rating'),
    'price_asc' => t('buscar.sort_price_low'),
    'price_desc'=> t('buscar.sort_price_high'),
    'newest'    => t('buscar.sort_newest'),
];
?>
<style>
.bc-card{display:block;border-radius:16px;padding:16px;background:var(--sf);margin-bottom:10px;border:1px solid var(--bd);position:relative;transition:box-shadow .15s}
.bc-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
.bc-card.live{border-color:var(--s)}
.bc-live{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:20px;background:var(--s);color:#fff;font-size:10px;font-weight:700;letter-spacing:.5px;margin-bottom:8px}
.bc-title{font-weight:600;font-size:15px;color:var(--fg);margin-bottom:4px;line-height:1.3}
.bc-prof{font-size:12px;color:var(--sub);margin-bottom:10px}
.bc-meta{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.bc-chip{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:10px;background:var(--bg-card);font-size:11px;font-weight:500;color:var(--sub)}
.bc-bottom{display:flex;justify-content:space-between;align-items:center}
.bc-price{font-weight:700;font-size:16px;color:var(--p)}
.bc-stars{display:inline-flex;gap:1px;margin-right:4px}
.bc-rating{font-size:11px;font-weight:600;color:var(--sub)}
.bc-amigo{font-size:10px;color:var(--p);margin-left:6px}
.bc-section{font-size:13px;font-weight:600;color:var(--p);margin-bottom:8px;margin-top:4px;display:flex;align-items:center;gap:4px}
.bc-section-more{font-size:13px;font-weight:600;color:var(--sub);margin-bottom:8px;margin-top:16px;display:flex;align-items:center;gap:4px}
.bc-count{font-size:11px;font-weight:500;color:var(--sub);margin-bottom:8px}
.sort-bar{display:flex;align-items:center;gap:8px;margin-top:8px}
.sort-btn{display:inline-flex;align-items:center;gap:4px;padding:6px 14px;border-radius:20px;background:var(--sf);border:1px solid var(--bd);color:var(--sub);font-size:12px;font-weight:500;cursor:pointer;font-family:inherit}
.sort-btn.active{background:var(--p);color:#fff;border-color:var(--p)}
</style>

<div class="ml-wrap">
  <div class="ml-wrap-inner">
  <div style="padding:0 20px 12px">
    <div class="ml-head-title" style="margin-bottom:14px"><?= t('buscar.title') ?></div>

    <div class="ml-search">
      <i data-feather="search" style="width:18px;height:18px;color:var(--sub);flex-shrink:0"></i>
      <input type="text" id="searchInput" placeholder="<?= t('buscar.search_placeholder') ?>" value="<?= htmlspecialchars($search) ?>" oninput="debounceSearch()">
      <?php if ($search): ?>
      <button style="background:none;border:0;color:var(--sub);cursor:pointer;padding:0" onclick="clearSearch()"><i data-feather="x" style="width:18px;height:18px"></i></button>
      <?php endif; ?>
    </div>

    <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;align-items:center">
      <button class="ml-chip <?= $activeOnly ? 'active-l' : '' ?>" onclick="toggleLive()">
        <i data-feather="radio" style="width:13px;height:13px"></i> <?= t('buscar.live') ?>
      </button>
      <?php foreach ($subjects as $s):
        $sel = (int)$s['id'] === $subjectId;
        $cc = $colors[(int)$s['id']] ?? '#66ddbd';
      ?>
      <button class="ml-chip <?= $sel ? 'active' : '' ?>" style="border-color:<?= $cc ?>;color:<?= $sel ? '#fff' : $cc ?>;background:<?= $sel ? $cc : $cc . '22' ?>" onclick="filterSubject(<?= (int)$s['id'] ?>)"><?= htmlspecialchars($s['nombre']) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="sort-bar">
      <span style="font-size:11px;font-weight:600;color:var(--sub)"><i data-feather="arrow-up-down" style="width:12px;height:12px"></i> <?= t('buscar.sort') ?>:</span>
      <?php foreach ($sortOpts as $k => $label): ?>
        <button class="sort-btn <?= $sort === $k ? 'active' : '' ?>" onclick="setSort('<?= $k ?>')"><?= $label ?></button>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($classes)): ?>
    <div class="bc-count"><?= count($classes) ?> <?= t('buscar.results') ?></div>
    <?php endif; ?>
  </div>

  <div id="classList" style="padding:0 20px 24px">
    <?php if (empty($classes)): ?>
    <div class="ml-empty">
      <i data-feather="search" style="width:40px;height:40px;color:var(--tbi)"></i>
      <div class="ml-empty-txt"><?= t('buscar.no_classes') ?></div>
    </div>
    <?php else: ?>
      <?php
      $shownFriend = false;
      foreach ($classes as $c):
        $live  = !empty($c['sala_activa']);
        $amigo = !empty($c['es_amigo']);
        $rating = (float)($c['calificacion'] ?? 0);
        $mins = (int)($c['duracion_min'] ?? 0);
        $capacity = (int)($c['alumnos_max'] ?? 0);
        $enrolled = (int)($c['alumnos_activos'] ?? 0);
        $precio = (int)($c['precio_base'] ?? 0);

        if ($amigo && !$shownFriend) {
          echo '<div class="bc-section"><i data-feather="heart" style="width:14px;height:14px"></i> ' . t('buscar.friend_classes') . '</div>';
          $shownFriend = true;
        } elseif (!$amigo && $shownFriend === true) {
          echo '<div class="bc-section-more"><i data-feather="trending-up" style="width:14px;height:14px"></i> ' . t('buscar.more_classes') . '</div>';
          $shownFriend = false;
        }
      ?>
      <a href="pre_sala.php?clase=<?= (int)$c['claseId'] ?>&from=explorar" class="bc-card<?= $live ? ' live' : '' ?>" style="text-decoration:none<?= $amigo ? ';border:1px solid var(--p)' : '' ?>">
        <?php if ($live): ?>
        <div class="bc-live"><i data-feather="radio" style="width:10px;height:10px"></i> <?= t('buscar.live_badge') ?></div>
        <?php endif; ?>
        <div class="bc-title">
          <?= htmlspecialchars($c['titulo']) ?>
          <?php if ($amigo): ?><span class="bc-amigo">· <?= t('buscar.friend') ?></span><?php endif; ?>
        </div>
        <div class="bc-prof"><?= htmlspecialchars($c['profesor']) ?></div>
        <div class="bc-meta">
          <span class="bc-chip"><i data-feather="book-open" style="width:11px;height:11px"></i> <?= htmlspecialchars($c['materia']) ?></span>
          <?php if ($mins > 0): ?>
          <span class="bc-chip"><i data-feather="clock" style="width:11px;height:11px"></i> <?= $mins ?>min</span>
          <?php endif; ?>
          <?php if ($capacity > 0): ?>
          <span class="bc-chip"><i data-feather="users" style="width:11px;height:11px"></i> <?= $enrolled ?>/<?= $capacity ?></span>
          <?php endif; ?>
        </div>
        <div class="bc-bottom">
          <div>
            <?php if ($rating > 0): ?>
            <span class="bc-stars" id="stars-<?= (int)$c['claseId'] ?>"></span>
            <span class="bc-rating"><?= number_format($rating, 1) ?></span>
            <?php endif; ?>
          </div>
          <div class="bc-price"><?= $precio ?> cr.</div>
        </div>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  </div>
</div>

<script>
var _searchTimer;
function debounceSearch(){
  clearTimeout(_searchTimer);
  _searchTimer = setTimeout(function(){ filterClasses(); }, 300);
}
function featherReplace(){if(typeof feather !== 'undefined') feather.replace()}
featherReplace();

function buildUrl(extra){
  var params = new URLSearchParams(location.search);
  if(extra) for(var k in extra){
    if(extra[k]===''||extra[k]===null||extra[k]===undefined) params.delete(k);
    else params.set(k, extra[k]);
  }
  var qs = params.toString();
  return 'buscar.php' + (qs ? '?' + qs : '');
}
function filterClasses(){
  var q = document.getElementById('searchInput').value;
  location.href = buildUrl({q: q || null});
}
function filterSubject(id){
  var cur = new URLSearchParams(location.search).get('s')||'';
  location.href = buildUrl({s: cur == id ? null : id});
}
function toggleLive(){
  var l = new URLSearchParams(location.search).get('live');
  location.href = buildUrl({live: l === '1' ? null : '1'});
}
function setSort(s){
  location.href = buildUrl({sort: s === 'relevance' ? null : s});
}
function clearSearch(){
  location.href = buildUrl({q: null});
}

document.querySelectorAll('.bc-stars').forEach(function(el){
  var id = el.id.replace('stars-','');
  var card = el.closest('.bc-card');
  if(!card) return;
  var rating = parseFloat(card.querySelector('.bc-rating')?.textContent||'0');
  var full = Math.floor(rating);
  var half = (rating - full) >= 0.5;
  var html = '';
  for(var i=0;i<5;i++){
    if(i < full) html += '<svg width="11" height="11" viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
    else if(i === full && half) html += '<svg width="11" height="11" viewBox="0 0 24 24" fill="#F59E0B" stroke="#F59E0B" stroke-width="2" opacity="0.6"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
    else html += '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
  }
  el.innerHTML = html;
});
</script>
<?php require 'footer.php'; ?>
