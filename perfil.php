<?php
require 'menu.php';
require 'db.php';
require_once __DIR__ . '/lib/csrf.php';

require_once __DIR__ . '/lib/security_headers.php';
$uid = (int)$_SESSION['usuarioId'];
$user = dbOne(
    "SELECT u.*, p.nombre AS pais
     FROM usuarios u
     LEFT JOIN paises p ON p.paisId = u.pais_id
     WHERE u.usuarioId = :id",
    ['id' => $uid]
);
if (!$user) { header('Location: login.php'); exit; }
$nombre   = htmlspecialchars($user['nombre'] ?? 'Usuario');
$email    = htmlspecialchars($user['email'] ?? '');
$avatar   = htmlspecialchars($user['avatar'] ?? '');
$rol      = $_navRol ?? ($user['rol'] ?? 'estudiante');
$creditos = (float)($user['creditos'] ?? 0);
$initial  = strtoupper(($nombre[0] ?? '?'));
$isTeacher = ($rol !== 'estudiante' && $rol !== 'student');
$calificacion = (float)($user['calificacion'] ?? 0);
$num_resenas  = (int)($user['num_resenas'] ?? 0);
$biografia    = htmlspecialchars($user['biografia'] ?? '');
$pais         = htmlspecialchars($user['pais'] ?? '');
$sitio_web_raw = trim($user['sitio_web'] ?? '');
$sitio_web     = htmlspecialchars($sitio_web_raw);
$sitio_web_url = preg_match('#^https?://#i', $sitio_web_raw) ? $sitio_web : '';
$idiomas = array_column(
    dbAll("SELECT i.nombre FROM usuario_idiomas ui JOIN idiomas i ON i.idiomaId = ui.idiomaId WHERE ui.usuarioId = :id", ['id' => $uid]),
    'nombre'
);

// Role switch lock logic
$switchErrorMsg = $_SESSION['error_switch'] ?? ''; unset($_SESSION['error_switch']);
$switchSuccessMsg = $_SESSION['switch_success'] ?? ''; unset($_SESSION['switch_success']);
$canSwitchRole = in_array($user['rol'], ['both', 'instructor', 'instructor_pendiente']);$switchLockedDays = 0;
if ($user['last_role_switch'] && $canSwitchRole) {
    $lastSwitch = strtotime($user['last_role_switch']);
    $hoursSince = floor((time() - $lastSwitch) / 3600);
    if ($hoursSince < 24) {
        $switchLockedDays = 1;
    }
}
$isSwitchLocked = $switchLockedDays > 0;
$_getBaseUrl = function(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/CCC';
};
$curLang = isset($_SESSION['_lang']) ? $_SESSION['_lang'] : (isset($_COOKIE['ce_lang']) ? $_COOKIE['ce_lang'] : 'en');
$langs = [['code' => 'es', 'label' => 'Español'], ['code' => 'en', 'label' => 'English'], ['code' => 'fr', 'label' => 'Français'], ['code' => 'de', 'label' => 'Deutsch'], ['code' => 'pt', 'label' => 'Português'], ['code' => 'it', 'label' => 'Italiano'], ['code' => 'zh', 'label' => '中文'], ['code' => 'ja', 'label' => '日本語'], ['code' => 'ru', 'label' => 'Русский']];
$avatarMsg = $_SESSION['avatar_msg'] ?? ''; unset($_SESSION['avatar_msg']);
$bioMsg = $_SESSION['bio_msg'] ?? ''; unset($_SESSION['bio_msg']);
$deleteErrorMsg = $_SESSION['error_delete'] ?? ''; unset($_SESSION['error_delete']);
?>
<div class="ml-wrap" style="padding-top:0;padding-bottom:32px">
  <div class="ml-wrap-inner">
  <div style="padding:0 24px;align-items:center;padding-top:20px">
    <?php if ($switchSuccessMsg): ?>
    <div style="text-align:center;font-size:13px;color:var(--s);background:var(--s)15;border:1px solid var(--s);border-radius:10px;padding:10px 14px;margin-bottom:12px"><?= htmlspecialchars($switchSuccessMsg) ?></div>
    <?php endif; ?>
    <?php if ($switchErrorMsg): ?>
    <div style="text-align:center;font-size:13px;color:var(--d);background:var(--d)15;border:1px solid var(--d);border-radius:10px;padding:10px 14px;margin-bottom:12px"><?= htmlspecialchars($switchErrorMsg) ?></div>
    <?php endif; ?>
    <?php if ($avatarMsg): ?>
    <div style="text-align:center;font-size:13px;color:var(--s);margin-bottom:12px"><?= htmlspecialchars($avatarMsg) ?></div>
    <?php endif; ?>
    <?php if ($bioMsg): ?>
    <div style="text-align:center;font-size:13px;color:var(--s);background:var(--s)15;border:1px solid var(--s);border-radius:10px;padding:10px 14px;margin-bottom:12px"><?= htmlspecialchars($bioMsg) ?></div>
    <?php endif; ?>
    <?php if ($deleteErrorMsg): ?>
    <div style="text-align:center;font-size:13px;color:var(--d);background:var(--d)15;border:1px solid var(--d);border-radius:10px;padding:10px 14px;margin-bottom:12px"><?= htmlspecialchars($deleteErrorMsg) ?></div>
    <?php endif; ?>
    <div style="position:relative;width:80px;height:80px;margin:0 auto 12px">
      <?php if ($avatar): ?>
        <img src="<?= $avatar ?>" style="width:80px;height:80px;border-radius:40px;object-fit:cover;border:2px solid var(--p);display:block">
      <?php else: ?>
        <div style="width:80px;height:80px;border-radius:40px;background:var(--p);display:flex;align-items:center;justify-content:center">
          <span style="font-size:32px;font-weight:700;color:#fff"><?= $initial ?></span>
        </div>
      <?php endif; ?>
      <label for="avatarInput" style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:14px;background:var(--p);display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--bg)">
        <i data-feather="camera" style="width:14px;height:14px;color:#fff"></i>
      </label>
    </div>
    <form method="POST" action="upload_avatar.php" enctype="multipart/form-data" id="avatarForm" style="display:none">
      <?= csrf_field() ?>
      <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" onchange="document.getElementById('avatarForm').submit()">
    </form>
    <div style="font-size:22px;font-weight:700;color:var(--fg);text-align:center;margin-bottom:2px"><?= $nombre ?></div>
    <div style="font-size:14px;color:var(--sub);text-align:center;margin-bottom:4px"><?= $email ?></div>
    <div style="font-size:13px;color:var(--p);text-align:center;margin-bottom:10px">@<?= htmlspecialchars($user['username'] ?? '') ?> · <?= $isTeacher ? t('people.teacher') : t('people.student') ?></div>

    <?php if ($calificacion > 0): ?>
    <div style="text-align:center;margin:0 0 12px">
      <span style="font-size:20px;font-weight:700;color:var(--p)"><?= number_format($calificacion, 1) ?></span>
      <span style="font-size:13px;color:var(--sub)"> ★ (<?= $num_resenas ?> <?= t('general.reviews') ?>)</span>
    </div>
    <?php endif; ?>

    <div style="margin:0 0 16px;padding:12px 16px;border-radius:12px;background:var(--sf);font-size:13px;color:var(--sub);line-height:20px">
      <?php if ($biografia): ?><div style="margin-bottom:6px;white-space:pre-wrap"><?= $biografia ?></div>
      <?php else: ?><div style="margin-bottom:6px;color:var(--tbi);font-style:italic"><?= t('profile.bio_empty') ?></div>
      <?php endif; ?>
      <button onclick="openBioModal()" style="border:0;background:none;cursor:pointer;color:var(--p);font-size:12px;font-weight:600;font-family:inherit;padding:0"><?= t('perfil.edit') ?></button>
      <?php if ($pais): ?><div><span style="color:var(--fg)"><?= t('profile.country') ?></span> <?= $pais ?></div><?php endif; ?>
      <?php if (!empty($idiomas)): ?><div><span style="color:var(--fg)"><?= t('profile.languages') ?></span> <?= htmlspecialchars(implode(', ', $idiomas)) ?></div><?php endif; ?>
      <?php if ($sitio_web): ?><div><span style="color:var(--fg)"><?= t('profile.web') ?></span> <?php if ($sitio_web_url): ?><a href="<?= $sitio_web_url ?>" target="_blank" style="color:var(--p)"><?= $sitio_web ?></a><?php else: ?><span style="color:var(--p)"><?= $sitio_web ?></span><?php endif; ?></div><?php endif; ?>
    </div>

    <div style="display:flex;margin:0 0 16px;border-radius:16px;border:1px solid var(--bd);overflow:hidden">
      <div style="flex:1;padding:16px;text-align:center">
        <div style="font-size:22px;font-weight:700;color:var(--p)"><?= number_format($creditos, 0) ?></div>
        <div style="font-size:12px;color:var(--sub);margin-top:2px"><?= t('profile.credits_label') ?></div>
      </div>
      <div style="width:1px;background:var(--bd)"></div>
      <div style="flex:1;padding:16px;text-align:center">
        <div style="font-size:22px;font-weight:700;color:var(--p)">1 USD</div>
        <div style="font-size:12px;color:var(--sub);margin-top:2px"><?= t('profile.per_credit') ?></div>
      </div>
    </div>
  </div>

  <div style="margin:0 20px 12px;border-radius:16px;overflow:hidden;background:var(--sf)">
    <div style="font-size:11px;font-weight:700;color:var(--sub);letter-spacing:1px;padding:12px 16px 4px"><?= t('profile.account_section') ?></div>
    <?php if ($canSwitchRole): ?>
    <button onclick="openSwitchModal()" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:0;width:100%;background:none;cursor:pointer;font-family:inherit;border-bottom:1px solid var(--bd)">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pb);display:flex;align-items:center;justify-content:center"><i data-feather="refresh-cw" style="width:18px;height:18px;color:var(--p)"></i></div>
      <span style="flex:1;font-size:15px;color:var(--fg);text-align:left"><?= $isTeacher ? t('profile.switch_to_student') : t('profile.switch_to_teacher') ?></span>
      <?php if ($isSwitchLocked): ?>
      <span style="font-size:11px;color:var(--sub);margin-right:4px"><i data-feather="lock" style="width:11px;height:11px;vertical-align:-1px"></i> <?= $switchLockedDays ?>d</span>
      <?php endif; ?>
      <i data-feather="chevron-right" style="width:18px;height:18px;color:var(--tbi)"></i>
    </button>
    <?php endif; ?>
    <?php if ($isTeacher): ?>
    <a href="dashboard_profesor.php" style="display:flex;align-items:center;gap:12px;padding:14px 16px;text-decoration:none;border-bottom:1px solid var(--bd)">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pb);display:flex;align-items:center;justify-content:center"><i data-feather="bar-chart-2" style="width:18px;height:18px;color:var(--p)"></i></div>
      <span style="flex:1;font-size:15px;color:var(--fg)"><?= t('profile.teacher_panel') ?></span>
      <i data-feather="chevron-right" style="width:18px;height:18px;color:var(--tbi)"></i>
    </a>
    <a href="crear_clase.php" style="display:flex;align-items:center;gap:12px;padding:14px 16px;text-decoration:none;border-bottom:1px solid var(--bd)">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pb);display:flex;align-items:center;justify-content:center"><i data-feather="plus-circle" style="width:18px;height:18px;color:var(--p)"></i></div>
      <span style="flex:1;font-size:15px;color:var(--fg)"><?= t('profile.create_class') ?></span>
      <i data-feather="chevron-right" style="width:18px;height:18px;color:var(--tbi)"></i>
    </a>
    <?php endif; ?>
    <a href="creditos.php" style="display:flex;align-items:center;gap:12px;padding:14px 16px;text-decoration:none;border-bottom:1px solid var(--bd)">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pb);display:flex;align-items:center;justify-content:center"><i data-feather="credit-card" style="width:18px;height:18px;color:var(--p)"></i></div>
      <span style="flex:1;font-size:15px;color:var(--fg)"><?= t('profile.my_credits') ?></span>
      <i data-feather="chevron-right" style="width:18px;height:18px;color:var(--tbi)"></i>
    </a>
    <a href="buscar.php" style="display:flex;align-items:center;gap:12px;padding:14px 16px;text-decoration:none">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pb);display:flex;align-items:center;justify-content:center"><i data-feather="search" style="width:18px;height:18px;color:var(--p)"></i></div>
      <span style="flex:1;font-size:15px;color:var(--fg)"><?= t('profile.search_classes') ?></span>
      <i data-feather="chevron-right" style="width:18px;height:18px;color:var(--tbi)"></i>
    </a>
    <a href="perfil_usuario.php?id=<?= $uid ?>" style="display:flex;align-items:center;gap:12px;padding:14px 16px;text-decoration:none">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pb);display:flex;align-items:center;justify-content:center"><i data-feather="external-link" style="width:18px;height:18px;color:var(--p)"></i></div>
      <span style="flex:1;font-size:15px;color:var(--fg)"><?= t('profile.view_public_profile') ?></span>
      <i data-feather="chevron-right" style="width:18px;height:18px;color:var(--tbi)"></i>
    </a>
  </div>

  <div style="margin:0 20px 12px;border-radius:16px;overflow:hidden;background:var(--sf)">
    <div style="font-size:11px;font-weight:700;color:var(--sub);letter-spacing:1px;padding:12px 16px 4px"><?= t('profile.app_language') ?></div>
    <button style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:0;width:100%;background:none;cursor:pointer;font-family:inherit" onclick="openLangModal()">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pb);display:flex;align-items:center;justify-content:center"><i data-feather="globe" style="width:18px;height:18px;color:var(--p)"></i></div>
      <span style="flex:1;font-size:15px;color:var(--fg);text-align:left"><?= htmlspecialchars($langs[array_search($curLang, array_column($langs, 'code'))]['label'] ?? 'Español') ?></span>
      <i data-feather="chevron-right" style="width:18px;height:18px;color:var(--tbi)"></i>
    </button>
  </div>

  <div style="margin:0 20px 12px;border-radius:16px;overflow:hidden;background:var(--sf)">
    <div style="font-size:11px;font-weight:700;color:var(--sub);letter-spacing:1px;padding:12px 16px 4px"><?= t('profile.languages_spoken') ?></div>
    <button onclick="openLangEditModal()" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:0;width:100%;background:none;cursor:pointer;font-family:inherit">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pb);display:flex;align-items:center;justify-content:center"><i data-feather="globe" style="width:18px;height:18px;color:var(--p)"></i></div>
      <span style="flex:1;font-size:15px;color:var(--fg);text-align:left"><?= !empty($idiomas) ? htmlspecialchars(implode(', ', $idiomas)) : t('profile.select_languages') ?></span>
      <i data-feather="chevron-right" style="width:18px;height:18px;color:var(--tbi)"></i>
    </button>
  </div>

  <div style="margin:0 20px 12px;border-radius:16px;overflow:hidden;background:var(--sf)">
    <div style="font-size:11px;font-weight:700;color:var(--sub);letter-spacing:1px;padding:12px 16px 4px"><?= t('general.session') ?></div>
    <a href="logout.php" style="display:flex;align-items:center;gap:12px;padding:14px 16px;text-decoration:none;border-bottom:1px solid var(--bd)">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--d)22;display:flex;align-items:center;justify-content:center"><i data-feather="log-out" style="width:18px;height:18px;color:var(--d)"></i></div>
      <span style="flex:1;font-size:15px;color:var(--d)"><?= t('profile.logout') ?></span>
    </a>
    <button style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:0;width:100%;background:none;cursor:pointer;font-family:inherit" onclick="openDelModal()">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--d)22;display:flex;align-items:center;justify-content:center"><i data-feather="trash-2" style="width:18px;height:18px;color:var(--d)"></i></div>
      <span style="flex:1;font-size:15px;color:var(--d);text-align:left"><?= t('profile.delete_title') ?></span>
    </button>
  </div>

  <p style="text-align:center;font-size:12px;color:var(--tbi);margin-top:32px"><?= t('general.footer_text') ?></p>
  </div>
</div>

<div class="modal-overlay" id="langEditModal">
  <div class="modal-card">
    <div style="font-size:18px;font-weight:700;color:var(--fg);margin-bottom:12px"><?= t('profile.languages_modal_title') ?></div>
    <form method="POST" action="update_languages.php">
      <div class="d-flex flex-wrap gap-2 mb-3">
        <?php
        $todosIdiomas = dbAll("SELECT idiomaId, nombre FROM idiomas ORDER BY nombre ASC");
        $userLangIds = array_column(dbAll("SELECT idiomaId FROM usuario_idiomas WHERE usuarioId = :uid", ['uid' => $uid]), 'idiomaId');
        foreach ($todosIdiomas as $idi):
          $checked = in_array($idi['idiomaId'], $userLangIds);
        ?>
        <label style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:20px;border:1px solid <?= $checked ? 'var(--p)' : 'var(--bd)' ?>;background:<?= $checked ? 'var(--pb)' : 'transparent' ?>;cursor:pointer;font-size:13px;font-weight:500;color:<?= $checked ? 'var(--p)' : 'var(--fg)' ?>">
          <input type="checkbox" name="idiomas[]" value="<?= $idi['idiomaId'] ?>" <?= $checked ? 'checked' : '' ?> style="display:none" onchange="this.parentElement.style.borderColor=this.checked?'var(--p)':'var(--bd)';this.parentElement.style.background=this.checked?'var(--pb)':'transparent';this.parentElement.style.color=this.checked?'var(--p)':'var(--fg)'">
          <?= htmlspecialchars($idi['nombre']) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:10px">
        <button type="button" style="flex:1;padding:12px;border-radius:10px;border:0;background:var(--sf);color:var(--fg);font-weight:600;cursor:pointer;font-size:14px" onclick="closeModal('langEditModal')"><?= t('general.cancelar') ?></button>
        <button type="submit" style="flex:1;padding:12px;border-radius:10px;border:0;background:var(--p);color:#fff;font-weight:600;cursor:pointer;font-size:14px"><?= t('general.update') ?></button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="bioModal">
  <div class="modal-card">
    <div style="font-size:18px;font-weight:700;color:var(--fg);margin-bottom:12px"><?= t('profile.bio_modal_title') ?></div>
    <form method="POST" action="update_bio.php" id="bioForm">
      <?= csrf_field() ?>
      <textarea name="biografia" maxlength="1000" placeholder="<?= t('profile.bio_placeholder') ?>" style="width:100%;padding:10px 12px;border:1px solid var(--bd);border-radius:10px;background:var(--sf);color:var(--fg);font-size:14px;outline:0;margin-bottom:16px;box-sizing:border-box;font-family:inherit;resize:vertical;min-height:120px"><?= $biografia ?></textarea>
      <div style="display:flex;gap:10px">
        <button type="button" style="flex:1;padding:12px;border-radius:10px;border:0;background:var(--sf);color:var(--fg);font-weight:600;cursor:pointer;font-size:14px" onclick="closeModal('bioModal')"><?= t('general.cancelar') ?></button>
        <button type="submit" style="flex:1;padding:12px;border-radius:10px;border:0;background:var(--p);color:#fff;font-weight:600;cursor:pointer;font-size:14px"><?= t('general.update') ?></button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="langModal">
  <div class="modal-card">
    <div style="font-size:18px;font-weight:700;color:var(--fg);margin-bottom:12px"><?= t('profile.select_language_modal') ?></div>
    <?php foreach ($langs as $l): 
      $sel = $l['code'] === $curLang;
    ?>
    <a href="?lang=<?= $l['code'] ?>" style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-radius:10px;margin-bottom:4px;text-decoration:none;background:<?= $sel ? 'var(--pb)' : 'transparent' ?>">
      <span style="font-size:15px;font-weight:<?= $sel ? '700' : '500' ?>;color:var(--fg)"><?= htmlspecialchars($l['label']) ?></span>
      <?php if ($sel): ?><i data-feather="check" style="width:18px;height:18px;color:var(--p)"></i><?php endif; ?>
    </a>
    <?php endforeach; ?>
    <button style="display:block;width:100%;text-align:center;background:none;border:0;color:var(--sub);padding:12px 0 0;cursor:pointer;font-size:14px" onclick="closeModal('langModal')"><?= t('general.cancelar') ?></button>
  </div>
</div>

<div class="modal-overlay" id="delModal">
  <div class="modal-card">
    <div style="font-size:18px;font-weight:700;color:var(--d);margin-bottom:12px"><?= t('profile.delete_title') ?></div>
    <div style="font-size:13px;color:var(--sub);line-height:18px;margin-bottom:16px"><?= t('profile.delete_warning') ?></div>
    <div style="font-size:13px;font-weight:500;color:var(--fg);margin-bottom:8px"><?= t('profile.delete_confirm_field') ?></div>
    <form method="POST" action="delete_account.php" id="delForm">
      <?= csrf_field() ?>
      <input type="password" name="password" autocomplete="off" placeholder="<?= t('profile.password_placeholder') ?>" style="width:100%;padding:10px 12px;border:1px solid var(--bd);border-radius:10px;background:var(--sf);color:var(--fg);font-size:14px;outline:0;margin-bottom:16px;box-sizing:border-box;font-family:inherit">
      <div style="display:flex;gap:10px">
        <button type="button" style="flex:1;padding:12px;border-radius:10px;border:0;background:var(--sf);color:var(--fg);font-weight:600;cursor:pointer;font-size:14px" onclick="closeModal('delModal')"><?= t('general.cancelar') ?></button>
        <button type="submit" style="flex:1;padding:12px;border-radius:10px;border:0;background:var(--d);color:#fff;font-weight:600;cursor:pointer;font-size:14px"><?= t('profile.delete_permanent') ?></button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="switchModal">
  <div class="modal-card">
    <div style="font-size:18px;font-weight:700;color:var(--p);margin-bottom:12px"><?= t('profile.switch_confirm_title') ?></div>
    <div style="font-size:13px;color:var(--sub);line-height:18px;margin-bottom:16px"><?= t('profile.switch_confirm_msg') ?></div>
    <form method="POST" action="switch_role.php" id="switchForm">
      <?= csrf_field() ?>
      <input type="hidden" name="target_role" id="switchTargetRole" value="">
      <div style="font-size:13px;font-weight:500;color:var(--fg);margin-bottom:8px"><?= t('profile.switch_confirm_field') ?></div>
      <input type="password" name="password" autocomplete="off" placeholder="<?= t('profile.switch_confirm_field') ?>" style="width:100%;padding:10px 12px;border:1px solid var(--bd);border-radius:10px;background:var(--sf);color:var(--fg);font-size:14px;outline:0;margin-bottom:16px;box-sizing:border-box;font-family:inherit">
      <div style="display:flex;gap:10px">
        <button type="button" style="flex:1;padding:12px;border-radius:10px;border:0;background:var(--sf);color:var(--fg);font-weight:600;cursor:pointer;font-size:14px" onclick="closeModal('switchModal')"><?= t('general.cancelar') ?></button>
        <button type="submit" style="flex:1;padding:12px;border-radius:10px;border:0;background:var(--p);color:#fff;font-weight:600;cursor:pointer;font-size:14px"><?= t('profile.switch_confirm_btn') ?></button>
      </div>
    </form>
  </div>
</div>

<style>
.modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.7);z-index:1000;display:none;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal-card{background:var(--sf);border-radius:16px;padding:20px;width:85%;max-width:400px}
</style>
<script>
function openLangModal(){document.getElementById('langModal').classList.add('show')}
function openDelModal(){document.getElementById('delModal').classList.add('show')}
function openSwitchModal(){var m=(document.cookie.match(/ce_app_modo=(\w+)/)||[])[1]||'student';document.getElementById('switchTargetRole').value=m==='teacher'?'student':'teacher';document.getElementById('switchModal').classList.add('show')}
function openLangEditModal(){document.getElementById('langEditModal').classList.add('show')}
function openBioModal(){document.getElementById('bioModal').classList.add('show')}
function closeModal(id){document.getElementById(id).classList.remove('show')}
document.addEventListener('click',function(e){if(e.target.classList.contains('modal-overlay'))e.target.classList.remove('show')});
</script>
<?php require 'footer.php'; ?>
