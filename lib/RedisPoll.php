<?php
// ─────────────────────────────────────────────────────────────────────────────
//  RedisPoll.php — Redis-backed polling for classroom real-time data
//  Replaces MySQL polling for: chat, WebRTC signals, spectators, students
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/RedisConnection.php';
require_once __DIR__ . '/../db.php';

if (!function_exists('pollChat')) {

// ── CHAT ─────────────────────────────────────────────────────────────────────
// Store last N messages per sala in Redis list; read by polling clients
function pollChatWrite(int $salaId, int $uid, string $alias, string $mensaje): int {
    $r = getRedis();
    $msgId = (int)dbExec(
        "INSERT INTO mensajes_chat (salaId, usuarioId, alias, mensaje) VALUES (:s,:u,:a,:m)",
        ['s'=>$salaId, 'u'=>$uid, 'a'=>$alias, 'm'=>$mensaje]
    );
    if ($r) {
        $entry = json_encode([
            'mensajeId' => $msgId,
            'alias'     => $alias,
            'mensaje'   => $mensaje,
            'enviado_at'=> date('Y-m-d H:i:s'),
        ]);
        $r->lPush("poll:chat:$salaId", $entry);
        $r->lTrim("poll:chat:$salaId", 0, 99);
        $r->expire("poll:chat:$salaId", 3600);
    }
    return $msgId;
}

function pollChatRead(int $salaId, int $afterId = 0): array {
    $r = getRedis();
    if ($r) {
        $raw = $r->lRange("poll:chat:$salaId", 0, -1);
        $msgs = [];
        foreach ($raw as $json) {
            $entry = json_decode($json, true);
            if ($entry && (int)($entry['mensajeId'] ?? 0) > $afterId) {
                $msgs[] = $entry;
            }
        }
        if (!empty($msgs)) {
            usort($msgs, fn($a,$b) => ($a['mensajeId'] ?? 0) <=> ($b['mensajeId'] ?? 0));
            return array_slice($msgs, -30);
        }
    }
    // Fallback to MySQL
    return dbAll(
        "SELECT mensajeId, alias, mensaje, enviado_at FROM mensajes_chat
         WHERE salaId=:s AND mensajeId > :a ORDER BY mensajeId ASC LIMIT 30",
        ['s'=>$salaId, 'a'=>$afterId]
    );
}

// ── WEBRTC SIGNALS ───────────────────────────────────────────────────────────
function pollSignalWrite(int $salaId, int $fromUid, ?int $toUid, string $tipo, string $payload): int {
    $r = getRedis();
    $sigId = (int)dbExec(
        "INSERT INTO webrtc_signals (salaId, from_uid, to_uid, tipo, payload)
         VALUES (:s, :f, :t, :tp, :p)",
        ['s'=>$salaId, 'f'=>$fromUid, 't'=>$toUid, 'tp'=>$tipo, 'p'=>$payload]
    );
    if ($r) {
        $entry = json_encode([
            'signalId' => $sigId,
            'from_uid' => $fromUid,
            'to_uid'   => $toUid,
            'tipo'     => $tipo,
            'payload'  => $payload,
        ]);
        $r->lPush("poll:sig:$salaId", $entry);
        $r->lTrim("poll:sig:$salaId", 0, 199);
        $r->expire("poll:sig:$salaId", 3600);
    }
    return $sigId;
}

function pollSignalRead(int $salaId, int $userId, int $afterId = 0): array {
    $r = getRedis();
    if ($r) {
        $raw = $r->lRange("poll:sig:$salaId", 0, -1);
        $sigs = [];
        foreach ($raw as $json) {
            $entry = json_decode($json, true);
            if (!$entry) continue;
            $sigId = (int)($entry['signalId'] ?? 0);
            if ($sigId <= $afterId) continue;
            $toUid = $entry['to_uid'] ?? null;
            $fromUid = (int)($entry['from_uid'] ?? 0);
            if ($toUid !== null && (int)$toUid !== $userId) continue;
            if ($fromUid === $userId) continue;
            $sigs[] = $entry;
        }
        if (!empty($sigs)) {
            usort($sigs, fn($a,$b) => ($a['signalId'] ?? 0) <=> ($b['signalId'] ?? 0));
            return array_slice($sigs, -20);
        }
    }
    // Fallback to MySQL
    return dbAll(
        "SELECT signalid AS signalId, from_uid, tipo, payload FROM webrtc_signals
         WHERE salaId=:s AND signalid > :a
           AND (to_uid IS NULL OR to_uid=:u) AND from_uid != :u2
         ORDER BY signalid ASC LIMIT 20",
        ['s'=>$salaId, 'a'=>$afterId, 'u'=>$userId, 'u2'=>$userId]
    );
}

// ── SPECTATORS ───────────────────────────────────────────────────────────────
function pollSpectatorsRead(int $salaId): array {
    $r = getRedis();
    $cacheKey = "poll:spectators:$salaId";
    if ($r) {
        $cached = $r->get($cacheKey);
        if ($cached !== false) return json_decode($cached, true);
    }
    $result = dbAll(
        "SELECT e.*, u.nombre, u.username FROM espectadores e
         JOIN usuarios u ON u.usuarioId = e.usuarioId
         WHERE e.salaId = :s AND e.estado = 'pendiente'
         ORDER BY e.created_at ASC",
        ['s' => $salaId]
    );
    if ($r) {
        $r->setex($cacheKey, 5, json_encode($result));
    }
    return $result;
}

// ── STUDENTS IN ROOM ────────────────────────────────────────────────────────
function pollStudentsRead(int $salaId, string $baseUrl = ''): array {
    $r = getRedis();
    $cacheKey = "poll:students:$salaId";
    if ($r) {
        $cached = $r->get($cacheKey);
        if ($cached !== false) return json_decode($cached, true);
    }
    $students = dbAll(
        "SELECT sc.sesionId, sc.estudianteId, sc.espectador, sc.pagado,
                sc.inicio, sc.segundos_acumulados, sc.ultima_salida,
                u.nombre, u.username, u.avatar, u.rol, u.biografia,
                p.nombre AS pais, p.codigo_moneda, p.simbolo,
                GROUP_CONCAT(DISTINCT i.nombre SEPARATOR ', ') AS idiomas
         FROM sesiones_clase sc
         JOIN usuarios u ON u.usuarioId = sc.estudianteId
         LEFT JOIN paises p ON p.paisId = u.pais_id
         LEFT JOIN usuario_idiomas ui ON ui.usuarioId = u.usuarioId
         LEFT JOIN idiomas i ON i.idiomaId = ui.idiomaId
         WHERE sc.claseId = (SELECT claseId FROM salas WHERE salaId = :s2)
           AND sc.fin IS NULL
         GROUP BY sc.sesionId
         ORDER BY sc.inicio ASC",
        ['s'=>$salaId, 's2'=>$salaId]
    );
    foreach ($students as &$st) {
        $st['avatar_url'] = $st['avatar'] ? ($baseUrl . '/' . $st['avatar']) : '';
        $st['es_gratis'] = (int)($st['segundos_acumulados'] ?? 0) < 180;
    }
    if ($r) {
        $r->setex($cacheKey, 5, json_encode($students));
    }
    return $students;
}

} // End function_exists check
