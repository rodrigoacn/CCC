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

    // Check if already in spectator queue
    $psalaId = (int)($clase['salaid'] ?? 0);
    if ($psalaId) {
        $existing_spectator = dbOne(
            "SELECT espectadorId, estado FROM espectadores WHERE salaId = :s AND usuarioId = :u AND estado = 'pendiente'",
            ['s' => $psalaId, 'u' => $uid]
        );
        if ($existing_spectator) {
            echo json_encode(['ok'=>false,'error'=>'Already in spectator queue, waiting for teacher approval']);
            exit;
        }
    }

    // Check student count (only for approved participants)
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
                (claseId, estudianteId, instructorId, salaId, inicio, precio_usd, monto_local, moneda_local, simbolo_local, espectador)
             VALUES (:c, :u, :i, :s, NOW(), :pu, :ml, :mon, :sim, 1)",
            [
                'c'  => $claseId,
                'u'  => $uid,
                'i'  => $clase['instructorid'],
                's'  => $clase['salaid'] ?? null,
                'pu' => $precio_usd,
                'ml' => $monto_local,
                'mon'=> $moneda_local,
                'sim'=> $simbolo,
            ]
        );
    }

    // Add to spectator queue
    if ($psalaId) {
        dbExec(
            "INSERT INTO espectadores (salaId, usuarioId, estado) VALUES (:s, :u, 'pendiente')",
            ['s' => $psalaId, 'u' => $uid]
        );
    }

    // Record participant state
    if ($psalaId) {
        dbExec(
            "INSERT INTO participantes_sala (salaId, usuarioId, camara_activa, microfono_activo)
             VALUES (:s, :u, 0, 0)
             ON CONFLICT (salaId, usuarioId) DO UPDATE SET camara_activa=0, microfono_activo=0",
            ['s' => $psalaId, 'u' => $uid]
        );
    }

    echo json_encode([
        'ok'           => true,
        'sesionId'     => $sesionId,
        'precio_usd'   => $precio_usd,
        'monto_local'  => $monto_local,
        'moneda_local' => $moneda_local,
        'simbolo'      => $simbolo,
        'clase_titulo' => $clase['titulo'],
        'espectador'   => true,
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

    // Price calculation: divide among active students after spectator period
    // Get count of active (non-spectator) students in this class
    $active_students = dbOne(
        "SELECT COUNT(*) AS cnt FROM sesiones_clase 
         WHERE claseId = :c AND fin IS NULL AND espectador = 0",
        ['c' => $sesion['claseid']]
    )['cnt'] ?? 1;
    
    // Calculate price per student based on class price divided by active students
    $precio_base_usd = (float)$sesion['precio_base'];
    $precio_usd = round($precio_base_usd / max(1, $active_students), 2);
    
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
        // First show rating screen, then payment flow will continue from there
        'redirect'     => 'calificar.php?sesion=' . $sesionId,
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

    // Get professor info to check referrals
    $profesor = dbOne(
        "SELECT usuarioId, num_referidos FROM usuarios WHERE usuarioId = :id",
        ['id' => $sesion['instructorid']]
    );

    // Calculate commission: 15% base for Rodrigo, reduced by 1% per referral (max 5% reduction)
    $comision_rodrigo = 0;
    $num_referidos = (int)($profesor['num_referidos'] ?? 0);
    
    // Rodrigo's user ID is 1 (from seed data)
    if ($sesion['instructorid'] != 1) {
        $comision_base = 0.15; // 15% base commission
        $reduccion = min($num_referidos, 5) * 0.01; // 1% reduction per referral, max 5%
        $comision_rodrigo = round($sesion['precio_usd'] * ($comision_base - $reduccion), 2);
    }

    $db = getDB();
    if (!$db) { echo json_encode(['ok'=>false,'error'=>'DB unavailable']); exit; }

    $db->prepare(
        "INSERT INTO pagos (sesionId, estudianteId, profesorId, monto_usd, monto_local, moneda_local, simbolo_local, metodo, estado, comision_rodrigo)
         VALUES (:sid,:est,:prof,:usd,:loc,:mon,:sim,:met,'completado',:com)"
    )->execute([
        'sid'  => $sesionId,
        'est'  => $uid,
        'prof' => $sesion['instructorid'],
        'usd'  => $sesion['precio_usd'],
        'loc'  => $sesion['monto_local'],
        'mon'  => $sesion['moneda_local'],
        'sim'  => $sesion['simbolo_local'] ?? '$',
        'met'  => $metodo,
        'com'  => $comision_rodrigo,
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

// ── WEBRTC SIGNAL SEND ────────────────────────────────────────────────────────
if ($action === 'signal') {
    $salaId  = (int)($_POST['salaId'] ?? 0);
    $toUid   = (int)($_POST['toUid'] ?? 0) ?: null;
    $tipo    = in_array($_POST['tipo'] ?? '', ['offer','answer','candidate','bye'])
               ? $_POST['tipo'] : '';
    $payload = $_POST['payload'] ?? '';
    if (!$salaId || !$tipo || $payload === '') {
        echo json_encode(['ok'=>false,'error'=>'Missing signal data']); exit;
    }
    $sigId = dbExec(
        "INSERT INTO webrtc_signals (sala_id, from_uid, to_uid, tipo, payload)
         VALUES (:s, :f, :t, :tp, :p)",
        ['s'=>$salaId,'f'=>$uid,'t'=>$toUid,'tp'=>$tipo,'p'=>$payload]
    );
    echo json_encode(['ok'=>true,'signalId'=>$sigId]);
    exit;
}

// ── WEBRTC SIGNAL POLL ────────────────────────────────────────────────────────
if ($action === 'signals') {
    $salaId  = (int)($_GET['salaId'] ?? 0);
    $afterId = (int)($_GET['afterId'] ?? 0);
    $rows = dbAll(
        "SELECT signalId, from_uid, tipo, payload FROM webrtc_signals
         WHERE sala_id=:s AND signal_id > :a
           AND (to_uid IS NULL OR to_uid=:u)
           AND from_uid != :u2
         ORDER BY signalId ASC LIMIT 20",
        ['s'=>$salaId,'a'=>$afterId,'u'=>$uid,'u2'=>$uid]
    );
    // fallback: column name may be signalid (lowercase)
    if ($rows === false || $rows === null) $rows = [];
    echo json_encode(['ok'=>true,'signals'=>$rows]);
    exit;
}

// ── WEBRTC SIGNAL POLL (lowercase col) ───────────────────────────────────────
if ($action === 'poll_signals') {
    $salaId  = (int)($_GET['salaId'] ?? 0);
    $afterId = (int)($_GET['afterId'] ?? 0);
    $rows = dbAll(
        "SELECT signalid AS signalId, from_uid, tipo, payload FROM webrtc_signals
         WHERE sala_id=:s AND signalid > :a
           AND (to_uid IS NULL OR to_uid=:u)
           AND from_uid != :u2
         ORDER BY signalid ASC LIMIT 20",
        ['s'=>$salaId,'a'=>$afterId,'u'=>$uid,'u2'=>$uid]
    );
    if (!is_array($rows)) $rows = [];
    echo json_encode(['ok'=>true,'signals'=>$rows]);
    exit;
}

// ── APPROVE SPECTATOR ─────────────────────────────────────────────────────────
if ($action === 'approve_spectator') {
    $espectadorId = (int)($_POST['espectadorId'] ?? 0);
    $salaId = (int)($_POST['salaId'] ?? 0);
    
    if (!$espectadorId || !$salaId) {
        echo json_encode(['ok'=>false,'error'=>'Missing espectadorId or salaId']);
        exit;
    }
    
    // Verify user is the teacher of this classroom
    $clase = dbOne(
        "SELECT cp.instructorId FROM clases_programadas cp JOIN salas s ON s.salaId = :s WHERE s.salaId = :s",
        ['s' => $salaId]
    );
    
    if (!$clase || $clase['instructorid'] != $uid) {
        echo json_encode(['ok'=>false,'error'=>'Not authorized']);
        exit;
    }
    
    // Update spectator status
    dbExec(
        "UPDATE espectadores SET estado = 'aprobado', profesor_aprobo = :prof WHERE espectadorId = :id",
        ['prof' => $uid, 'id' => $espectadorId]
    );
    
    // Update session to no longer be spectator
    $espectador = dbOne(
        "SELECT usuarioId FROM espectadores WHERE espectadorId = :id",
        ['id' => $espectadorId]
    );
    
    if ($espectador) {
        dbExec(
            "UPDATE sesiones_clase SET espectador = 0 WHERE estudianteId = :u AND fin IS NULL",
            ['u' => $espectador['usuarioid']]
        );
    }
    
    echo json_encode(['ok'=>true,'message'=>'Spectator approved']);
    exit;
}

// ── END CLASS ───────────────────────────────────────────────────────────────
if ($action === 'end_class') {
    $claseId = (int)($_POST['claseId'] ?? 0);
    $salaId = (int)($_POST['salaId'] ?? 0);
    
    if (!$claseId || !$salaId) {
        echo json_encode(['ok'=>false,'error'=>'Missing claseId or salaId']);
        exit;
    }
    
    // Verify user is the teacher
    $clase = dbOne(
        "SELECT instructorId FROM clases_programadas WHERE claseId = :id",
        ['id' => $claseId]
    );
    
    if (!$clase || $clase['instructorid'] != $uid) {
        echo json_encode(['ok'=>false,'error'=>'Not authorized']);
        exit;
    }
    
    // Calculate total tokens earned from all completed sessions
    $sessions = dbAll(
        "SELECT sc.precio_usd, sc.pagado 
         FROM sesiones_clase sc 
         WHERE sc.claseId = :c AND sc.instructorId = :i AND sc.fin IS NOT NULL AND sc.pagado = 1",
        ['c' => $claseId, 'i' => $uid]
    );
    
    $tokens_ganados = 0;
    foreach ($sessions as $session) {
        // 1 USD = 1 token (adjust conversion rate as needed)
        $tokens_ganados += (float)$session['precio_usd'];
    }
    
    // Mark class as inactive
    dbExec("UPDATE clases_programadas SET activa = 0 WHERE claseId = :id", ['id' => $claseId]);
    
    // End all active sessions
    dbExec(
        "UPDATE sesiones_clase SET fin = NOW() WHERE claseId = :c AND fin IS NULL",
        ['c' => $claseId]
    );
    
    echo json_encode(['ok'=>true,'tokens_ganados'=>$tokens_ganados]);
    exit;
}

// ── REJECT SPECTATOR ─────────────────────────────────────────────────────────
if ($action === 'reject_spectator') {
    $espectadorId = (int)($_POST['espectadorId'] ?? 0);
    $salaId = (int)($_POST['salaId'] ?? 0);
    
    if (!$espectadorId || !$salaId) {
        echo json_encode(['ok'=>false,'error'=>'Missing espectadorId or salaId']);
        exit;
    }
    
    // Verify user is the teacher
    $clase = dbOne(
        "SELECT cp.instructorId FROM clases_programadas cp JOIN salas s ON s.salaId = :s WHERE s.salaId = :s",
        ['s' => $salaId]
    );
    
    if (!$clase || $clase['instructorid'] != $uid) {
        echo json_encode(['ok'=>false,'error'=>'Not authorized']);
        exit;
    }
    
    // Update spectator status
    dbExec(
        "UPDATE espectadores SET estado = 'rechazado', profesor_aprobo = :prof WHERE espectadorId = :id",
        ['prof' => $uid, 'id' => $espectadorId]
    );
    
    echo json_encode(['ok'=>true,'message'=>'Spectator rejected']);
    exit;
}

// ── GET SPECTATORS ─────────────────────────────────────────────────────────
if ($action === 'get_spectators') {
    $salaId = (int)($_GET['salaId'] ?? 0);
    
    if (!$salaId) {
        echo json_encode(['ok'=>false,'error'=>'Missing salaId']);
        exit;
    }
    
    $spectators = dbAll(
        "SELECT e.*, u.nombre, u.username FROM espectadores e
         JOIN usuarios u ON u.usuarioId = e.usuarioId
         WHERE e.salaId = :s AND e.estado = 'pendiente'
         ORDER BY e.created_at ASC",
        ['s' => $salaId]
    );
    
    echo json_encode(['ok'=>true,'spectators'=>$spectators]);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Unknown action']);
