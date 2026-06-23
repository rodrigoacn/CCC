<?php
// ─────────────────────────────────────────────────────────────────────────────
//  api_sala.php — JSON API for classroom actions
//  Actions: join | leave | chat | pay
// ─────────────────────────────────────────────────────────────────────────────
session_start();
require 'db.php';

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['usuarioId'])) {
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

$uid    = (int)$_SESSION['usuarioId'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── JOIN ─────────────────────────────────────────────────────────────────────
if ($action === 'join') {
    $claseId = (int)($_POST['claseId'] ?? 0);
    if (!$claseId) { echo json_encode(['ok'=>false,'error'=>'Missing claseId']); exit; }

    // Load class + teacher currency
    $clase = dbOne(
        "SELECT cp.*, u.nombre AS profesor_nombre, u.pais_id AS profesor_pais_id,
                p.simbolo AS prof_simbolo, p.codigo_moneda AS prof_moneda, p.tasa_usd AS prof_tasa,
                m.nombre AS materia_nombre
         FROM clases_programadas cp
         JOIN usuarios u  ON u.usuarioId   = cp.instructorId
         LEFT JOIN paises p ON p.paisId    = u.pais_id
         LEFT JOIN materias m ON m.materiaId = cp.materiaId
         WHERE cp.claseId = :id AND cp.activa = 1",
        ['id' => $claseId]
    );
    if (!$clase) { echo json_encode(['ok'=>false,'error'=>'Class not found or inactive']); exit; }

    // Check student count
    $joined = dbOne(
        "SELECT COUNT(*) AS cnt FROM sesiones_clase WHERE claseId = :id AND fin IS NULL",
        ['id' => $claseId]
    )['cnt'] ?? 0;
    if ($joined >= $clase['alumnos_max']) {
        echo json_encode(['ok'=>false,'error'=>'Class is full']);
        exit;
    }

    // Load student currency
    $student = dbOne(
        "SELECT u.pais_id, p.simbolo, p.codigo_moneda, p.tasa_usd
         FROM usuarios u LEFT JOIN paises p ON p.paisId = u.pais_id
         WHERE u.usuarioId = :id",
        ['id' => $uid]
    );

    $precio_usd   = (float)$clase['precio_base'];
    $tasa         = (float)($student['tasa_usd'] ?? 1);
    $monto_local  = round($precio_usd * $tasa, 2);
    $moneda_local = $student['codigo_moneda'] ?? 'USD';
    $simbolo      = $student['simbolo'] ?? '$';

    // Open or reuse session
    $existing = dbOne(
        "SELECT sesionId FROM sesiones_clase WHERE claseId=:c AND estudianteId=:u AND fin IS NULL",
        ['c'=>$claseId,'u'=>$uid]
    );

    if ($existing) {
        $sesionId = $existing['sesionid'];
    } else {
        $sesionId = dbExec(
            "INSERT INTO sesiones_clase
                (claseId, estudianteId, salaId, inicio, precio_usd, monto_local, moneda_local, simbolo_local)
             VALUES (:c, :u, :s, NOW(), :pu, :ml, :mon, :sim)",
            [
                'c'  => $claseId,
                'u'  => $uid,
                's'  => $clase['salaid'] ?? null,
                'pu' => $precio_usd,
                'ml' => $monto_local,
                'mon'=> $moneda_local,
                'sim'=> $simbolo,
            ]
        );
    }

    // Record participant state
    $db = getDB();
    if ($db) {
        $db->prepare(
            "INSERT INTO participantes_sala (salaId, usuarioId, camara_activa, microfono_activo)
             VALUES (:s, :u, 0, 0)
             ON DUPLICATE KEY UPDATE camara_activa=0, microfono_activo=0"
        )->execute(['s' => $clase['salaid'] ?? 0, 'u' => $uid]);
    }

    echo json_encode([
        'ok'           => true,
        'sesionId'     => $sesionId,
        'precio_usd'   => $precio_usd,
        'monto_local'  => $monto_local,
        'moneda_local' => $moneda_local,
        'simbolo'      => $simbolo,
        'clase_titulo' => $clase['titulo'],
    ]);
    exit;
}

// ── LEAVE ────────────────────────────────────────────────────────────────────
if ($action === 'leave') {
    $sesionId = (int)($_POST['sesionId'] ?? 0);
    if (!$sesionId) { echo json_encode(['ok'=>false,'error'=>'Missing sesionId']); exit; }

    $sesion = dbOne(
        "SELECT s.*, cp.instructorId, cp.precio_base,
                est_p.codigo_moneda AS mon_local, est_p.simbolo AS sim_local, est_p.tasa_usd,
                prof.nombre AS prof_nombre
         FROM sesiones_clase s
         JOIN clases_programadas cp ON cp.claseId = s.claseId
         JOIN usuarios est  ON est.usuarioId  = s.estudianteId
         LEFT JOIN paises est_p ON est_p.paisId = est.pais_id
         JOIN usuarios prof ON prof.usuarioId  = cp.instructorId
         WHERE s.sesionId = :id AND s.estudianteId = :u",
        ['id'=>$sesionId,'u'=>$uid]
    );
    if (!$sesion) { echo json_encode(['ok'=>false,'error'=>'Session not found']); exit; }
    if ($sesion['pagado']) { echo json_encode(['ok'=>false,'error'=>'Already paid']); exit; }

    // Calculate duration
    $inicio       = new DateTime($sesion['inicio']);
    $fin          = new DateTime();
    $duracion_min = max(1, (int)round(($fin->getTimestamp() - $inicio->getTimestamp()) / 60));

    // Price: flat per-session (not per-minute) from clase precio_base
    $precio_usd  = (float)$sesion['precio_base'];
    $tasa        = (float)($sesion['tasa_usd'] ?? 1);
    $monto_local = round($precio_usd * $tasa, 2);
    $mon_local   = $sesion['mon_local'] ?? 'USD';
    $sim_local   = $sesion['sim_local'] ?? '$';

    // Close the session
    dbExec(
        "UPDATE sesiones_clase
         SET fin=NOW(), duracion_min=:d, precio_usd=:pu, monto_local=:ml, moneda_local=:mon, simbolo_local=:sim
         WHERE sesionId=:id",
        ['d'=>$duracion_min,'pu'=>$precio_usd,'ml'=>$monto_local,'mon'=>$mon_local,'sim'=>$sim_local,'id'=>$sesionId]
    );

    echo json_encode([
        'ok'           => true,
        'sesionId'     => $sesionId,
        'duracion_min' => $duracion_min,
        'precio_usd'   => $precio_usd,
        'monto_local'  => $monto_local,
        'moneda_local' => $mon_local,
        'simbolo'      => $sim_local,
        'prof_nombre'  => $sesion['prof_nombre'],
        'redirect'     => 'pago.php?sesion=' . $sesionId,
    ]);
    exit;
}

// ── PAY ──────────────────────────────────────────────────────────────────────
if ($action === 'pay') {
    $sesionId = (int)($_POST['sesionId'] ?? 0);
    $metodo   = in_array($_POST['metodo'] ?? '', ['tarjeta','transferencia','efectivo'])
                ? $_POST['metodo'] : 'tarjeta';

    $sesion = dbOne(
        "SELECT s.*, cp.instructorId
         FROM sesiones_clase s
         JOIN clases_programadas cp ON cp.claseId = s.claseId
         WHERE s.sesionId=:id AND s.estudianteId=:u AND s.pagado=0",
        ['id'=>$sesionId,'u'=>$uid]
    );
    if (!$sesion) { echo json_encode(['ok'=>false,'error'=>'Session not found or already paid']); exit; }

    $db = getDB();
    if (!$db) { echo json_encode(['ok'=>false,'error'=>'DB unavailable']); exit; }

    $db->prepare(
        "INSERT INTO pagos (sesionId, estudianteId, profesorId, monto_usd, monto_local, moneda_local, simbolo_local, metodo, estado)
         VALUES (:sid,:est,:prof,:usd,:loc,:mon,:sim,:met,'completado')"
    )->execute([
        'sid'  => $sesionId,
        'est'  => $uid,
        'prof' => $sesion['instructorid'],
        'usd'  => $sesion['precio_usd'],
        'loc'  => $sesion['monto_local'],
        'mon'  => $sesion['moneda_local'],
        'sim'  => $sesion['simbolo_local'] ?? '$',
        'met'  => $metodo,
    ]);

    dbExec("UPDATE sesiones_clase SET pagado=1 WHERE sesionId=:id", ['id'=>$sesionId]);

    echo json_encode(['ok'=>true,'message'=>'Payment confirmed']);
    exit;
}

// ── CHAT ─────────────────────────────────────────────────────────────────────
if ($action === 'chat') {
    $salaId  = (int)($_POST['salaId'] ?? 0);
    $mensaje = trim($_POST['mensaje'] ?? '');
    if (!$salaId || $mensaje === '') { echo json_encode(['ok'=>false,'error'=>'Missing data']); exit; }

    $user = dbOne("SELECT nombre FROM usuarios WHERE usuarioId=:id", ['id'=>$uid]);
    $alias = $user['nombre'] ?? 'Unknown';

    $msgId = dbExec(
        "INSERT INTO mensajes_chat (salaId, usuarioId, alias, mensaje) VALUES (:s,:u,:a,:m)",
        ['s'=>$salaId,'u'=>$uid,'a'=>$alias,'m'=>htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8')]
    );

    echo json_encode(['ok'=>true,'alias'=>$alias,'mensaje'=>htmlspecialchars($mensaje),'mensajeId'=>$msgId]);
    exit;
}

// ── MESSAGES POLL ────────────────────────────────────────────────────────────
if ($action === 'messages') {
    $salaId  = (int)($_GET['salaId'] ?? 0);
    $afterId = (int)($_GET['afterId'] ?? 0);
    $msgs = dbAll(
        "SELECT mensajeId, alias, mensaje, enviado_at FROM mensajes_chat
         WHERE salaId=:s AND mensajeId > :a ORDER BY mensajeId ASC LIMIT 30",
        ['s'=>$salaId,'a'=>$afterId]
    );
    echo json_encode(['ok'=>true,'messages'=>$msgs]);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Unknown action']);
