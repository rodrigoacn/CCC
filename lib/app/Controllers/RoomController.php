<?php
// ─────────────────────────────────────────────────────────────────────────────
//  RoomController — handlers moved verbatim from api_mobile.php (Room domain)
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Controllers;

final class RoomController
{
    public static function joinRoom(array $body): void {
        $user   = getAuthUser();
        $sid    = (int)($body['sala_id'] ?? 0);
        if (!$sid) jsonOut(['error' => 'sala_id requerido'], 400);

        $sala = dbOne(
            "SELECT s.salaId AS id, s.claseId, s.activa, s.created_at, cp.claseId AS clase_id, cp.precio_base AS precio, cp.instructorId, cp.alumnos_max
             FROM salas s
             JOIN clases_programadas cp ON cp.claseId = s.claseId
             WHERE s.salaId = ? AND s.activa = true",
            [$sid]
        );
        if (!$sala) jsonOut(['error' => 'Sala no encontrada o inactiva'], 404);

        if ($user['rol'] === 'estudiante' && (int)$user['creditos'] < (int)$sala['precio']) {
            jsonOut(['error' => 'Créditos insuficientes'], 402);
        }

        // Check for reconnection (paused session within 5-min grace)
        $uid = (int)$user['id'];
        $existing = dbOne(
            "SELECT sesionId FROM sesiones_clase
             WHERE claseId = :c AND estudianteId = :u
               AND ultima_salida IS NOT NULL
               AND ultima_salida >= NOW() - INTERVAL 5 MINUTE
             ORDER BY ultima_salida DESC LIMIT 1",
            ['c' => $sala['clase_id'], 'u' => $uid]
        );

        if ($existing) {
            // Reconnect: reset pause
            dbExec(
                "UPDATE sesiones_clase SET inicio = NOW(), ultima_salida = NULL WHERE sesionId = :id",
                ['id' => $existing['sesionId']]
            );
        } else {
            // Check if already have an active session
            $active = dbOne(
                "SELECT sesionId FROM sesiones_clase
                 WHERE claseId = :c AND estudianteId = :u AND fin IS NULL",
                ['c' => $sala['clase_id'], 'u' => $uid]
            );
            if (!$active) {
                dbExec(
                    "INSERT INTO sesiones_clase
                        (claseId, estudianteId, instructorId, salaId, inicio, precio_usd, espectador)
                     VALUES (:c, :u, :i, :s, NOW(), :p, 1)",
                    [
                        'c' => $sala['clase_id'],
                        'u' => $uid,
                        'i' => $sala['instructorId'],
                        's' => $sid,
                        'p' => (float)$sala['precio'],
                    ]
                );
            }
        }

        // Insert/update participant record
        dbExec(
            "INSERT INTO participantes_sala (salaId, usuarioId, joined_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE joined_at = NOW()",
            [$sid, $uid]
        );

        jsonOut(['sala' => $sala]);
    }

    public static function leaveRoom(array $body): void {
        $user = getAuthUser();
        $sid  = (int)($body['sala_id'] ?? 0);
        $uid  = (int)$user['id'];

        // If the user is the instructor, also close the room
        if ($user['rol'] === 'instructor' || $user['rol'] === 'both') {
            dbExec("UPDATE salas SET activa = false WHERE salaId = ?", [$sid]);
            dbExec(
                "UPDATE participantes_sala SET activo = false, left_at = NOW()
                 WHERE salaId = ?",
                [$sid]
            );
            jsonOut(['ok' => true, 'closed' => true]);
            return;
        }

        // Pause sesiones_clase for reconnection (5-min grace)
        dbExec(
            "UPDATE sesiones_clase
             SET segundos_acumulados = segundos_acumulados + TIMESTAMPDIFF(SECOND, inicio, NOW()),
                 ultima_salida = NOW(),
                 inicio = NOW()
             WHERE salaId = :s AND estudianteId = :u AND fin IS NULL
               AND (ultima_salida IS NULL OR ultima_salida < NOW() - INTERVAL 5 MINUTE)",
            ['s' => $sid, 'u' => $uid]
        );

        // Mark participant as inactive
        dbExec(
            "UPDATE participantes_sala SET activo = false, left_at = NOW()
             WHERE salaId = ? AND usuarioId = ?",
            [$sid, $uid]
        );
        jsonOut(['ok' => true]);
    }

    public static function roomStatus(): void {
        $user = getAuthUser();
        $sid  = (int)($_GET['sala_id'] ?? 0);

        $sala = dbOne(
            "SELECT s.salaId AS id, s.claseId, s.activa, s.created_at, cp.titulo AS clase, cp.precio_base AS precio FROM salas s
             JOIN clases_programadas cp ON cp.claseId = s.claseId WHERE s.salaId = ?",
            [$sid]
        );
        if (!$sala) jsonOut(['error' => 'Sala no encontrada'], 404);

        $participantes = dbAll(
            "SELECT u.usuarioId AS id, u.nombre, u.rol FROM participantes_sala p
             JOIN usuarios u ON u.usuarioId = p.usuarioId
             WHERE p.salaId = ? AND p.activo = true",
            [$sid]
        );
        $messages = dbAll(
            "SELECT m.*, u.nombre AS usuario FROM mensajes_chat m
             JOIN usuarios u ON u.usuarioId = m.usuarioId
             WHERE m.salaId = ? ORDER BY m.enviado_at ASC LIMIT 100",
            [$sid]
        );

        jsonOut(['sala' => $sala, 'participantes' => $participantes, 'messages' => $messages]);
    }

    public static function sendMessage(array $body): void {
        $user   = getAuthUser();
        $sid    = (int)($body['sala_id'] ?? 0);
        $msg    = trim($body['mensaje'] ?? '');
        if (!$sid || !$msg) jsonOut(['error' => 'Datos requeridos'], 400);

        dbExec("INSERT INTO mensajes_chat (salaId, usuarioId, mensaje) VALUES (?, ?, ?)",
               [$sid, $user['id'], $msg]);
        $row = dbOne(
            "SELECT m.mensajeId AS id, m.usuarioId AS usuario_id, m.salaId, m.mensaje, m.enviado_at AS created_at, u.nombre AS usuario FROM mensajes_chat m
             JOIN usuarios u ON u.usuarioId = m.usuarioId
             WHERE m.salaId = ? ORDER BY m.mensajeId DESC LIMIT 1",
            [$sid]
        );
        jsonOut(['mensaje' => $row]);
    }

    public static function messages(): void {
        $user   = getAuthUser();
        $sid    = (int)($_GET['sala_id'] ?? 0);
        $after  = (int)($_GET['after'] ?? 0);

        $sql    = "SELECT m.mensajeId AS id, m.usuarioId AS usuario_id, m.salaId, m.mensaje, m.enviado_at AS created_at, u.nombre AS usuario FROM mensajes_chat m
                   JOIN usuarios u ON u.usuarioId = m.usuarioId
                   WHERE m.salaId = ?";
        $params = [$sid];
        if ($after) { $sql .= " AND m.mensajeId > ?"; $params[] = $after; }
        $sql .= " ORDER BY m.enviado_at ASC LIMIT 50";

        jsonOut(['messages' => dbAll($sql, $params)]);
    }

    public static function signal(array $body): void {
        $user   = getAuthUser();
        $salaId = (int)($body['sala_id'] ?? 0);
        $tipo   = in_array($body['tipo'] ?? '', ['offer', 'answer', 'candidate', 'bye'], true)
                  ? $body['tipo'] : '';
        $payload = $body['payload'] ?? '';
        $toUid  = (int)($body['to_uid'] ?? 0) ?: null;

        if (!$salaId || !$tipo || $payload === '') {
            jsonOut(['error' => 'Datos de señal requeridos'], 400);
        }

        $inRoom = dbOne(
            "SELECT 1 FROM participantes_sala WHERE salaId = ? AND usuarioId = ? AND activo = true",
            [$salaId, $user['usuarioId'] ?? $user['id']]
        );
        if (!$inRoom) jsonOut(['error' => 'No estás en esta sala'], 403);

        dbExec(
            "INSERT INTO webrtc_signals (salaId, from_uid, to_uid, tipo, payload)
             VALUES (?, ?, ?, ?, ?)",
            [$salaId, $user['usuarioId'] ?? $user['id'], $toUid, $tipo, $payload]
        );

        jsonOut(['ok' => true]);
    }

    public static function pollSignals(): void {
        $user    = getAuthUser();
        $salaId  = (int)($_GET['sala_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? 0);
        $uid     = (int)($user['usuarioId'] ?? $user['id']);

        if (!$salaId) jsonOut(['error' => 'sala_id requerido'], 400);

        $rows = dbAll(
            "SELECT signalid AS \"signalId\", from_uid, tipo, payload FROM webrtc_signals
             WHERE salaId = ? AND signalid > ?
               AND (to_uid IS NULL OR to_uid = ?)
               AND from_uid != ?
             ORDER BY signalid ASC LIMIT 20",
            [$salaId, $afterId, $uid, $uid]
        );

        jsonOut(['signals' => $rows ?: []]);
    }

    public static function roomStudents(): void {
        $user = getAuthUser();
        $salaId = (int)($_GET['salaId'] ?? 0);
        if (!$salaId) jsonOut(['error' => 'Sala requerida'], 400);

        // Verify teacher
        $row = dbOne("SELECT instructorId FROM salas WHERE salaId = ?", [$salaId]);
        if (!$row || (int)$row['instructorId'] !== (int)$user['id']) {
            jsonOut(['error' => 'No autorizado'], 403);
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
             WHERE sc.claseId = (SELECT claseId FROM salas WHERE salaId = ?)
               AND sc.fin IS NULL
             GROUP BY sc.sesionId
             ORDER BY sc.inicio ASC",
            [$salaId]
        );

        $base = getBaseUrl();
        foreach ($students as &$st) {
            $st['avatar_url'] = $st['avatar'] ? $base . '/' . $st['avatar'] : '';
            $st['es_gratis'] = (int)($st['segundos_acumulados'] ?? 0) < 180;
        }

        jsonOut(['students' => $students]);
    }

    public static function kickStudent(array $body): void {
        $user        = getAuthUser();
        $salaId      = (int)($body['salaId'] ?? 0);
        $estudianteId = (int)($body['estudianteId'] ?? 0);
        $comentario  = trim($body['comentario'] ?? '');

        if (!$salaId || !$estudianteId || !$comentario) {
            jsonOut(['error' => 'Missing data'], 400);
        }

        $clase = dbOne("SELECT instructorId FROM salas WHERE salaId = ?", [$salaId]);
        if (!$clase || (int)$clase['instructorId'] !== (int)$user['id']) {
            jsonOut(['error' => 'Not authorized'], 403);
        }

        dbExec(
            "INSERT INTO sanciones (salaId, instructorId, estudianteId, comentario) VALUES (?, ?, ?, ?)",
            [$salaId, $user['id'], $estudianteId, $comentario]
        );

        dbExec(
            "UPDATE sesiones_clase sc
             JOIN salas s ON s.claseId = sc.claseId
             SET sc.fin = NOW(), sc.segundos_acumulados = COALESCE(sc.segundos_acumulados, 0)
             WHERE s.salaId = ? AND sc.estudianteId = ? AND sc.fin IS NULL",
            [$salaId, $estudianteId]
        );

        jsonOut(['ok' => true, 'message' => 'Student expelled']);
    }

    public static function startRoom(array $body): void {
        $user    = getAuthUser();
        if ($user['rol'] !== 'instructor' && $user['rol'] !== 'both') jsonOut(['error' => 'Solo instructores'], 403);

        $clase_id = (int)($body['clase_id'] ?? 0);
        $clase    = dbOne(
            "SELECT * FROM clases_programadas WHERE claseId = ? AND instructorId = ?",
            [$clase_id, $user['id']]
        );
        if (!$clase) jsonOut(['error' => 'Clase no encontrada'], 404);

        dbExec("UPDATE salas SET activa = false WHERE claseId = ? AND activa = true", [$clase_id]);
        dbExec("INSERT INTO salas (claseId, activa) VALUES (?, true)", [$clase_id]);

        $sala = dbOne("SELECT salaId AS id, claseId, activa, created_at FROM salas WHERE claseId = ? ORDER BY salaId DESC LIMIT 1", [$clase_id]);
        dbExec(
            "INSERT INTO participantes_sala (salaId, usuarioId, activo)
             VALUES (?, ?, true)
             ON DUPLICATE KEY UPDATE activo = true",
            [$sala['id'], $user['id']]
        );

        jsonOut(['sala' => $sala]);
    }

    public static function activeRooms(): void {
        $user  = getAuthUser();
        $rooms = dbAll(
            "SELECT s.salaId AS id, s.claseId, s.activa, s.created_at, cp.titulo AS clase, cp.precio_base AS precio
             FROM salas s JOIN clases_programadas cp ON cp.claseId = s.claseId
             WHERE cp.instructorId = ? AND s.activa = true",
            [$user['usuarioId']]
        );
        jsonOut(['rooms' => $rooms]);
    }

    public static function sessionStatus(): void {
        $user = getAuthUser();
        $uid  = (int)($user['usuarioId'] ?? $user['id'] ?? 0);
        $sid  = (int)($_GET['sesion_id'] ?? 0);

        $sesion = dbOne(
            "SELECT sc.sesionId, sc.claseId, sc.estudianteId, sc.pagado, sc.fin,
                    sc.precio_usd, sc.monto_local,
                    cp.titulo, cp.precio_base, cp.instructorId, cp.materiaId,
                    prof.nombre AS instructor_nombre, prof.avatar AS instructor_avatar
             FROM sesiones_clase sc
             JOIN clases_programadas cp ON cp.claseId = sc.claseId
             JOIN usuarios prof ON prof.usuarioId = cp.instructorId
             WHERE sc.sesionId = ? AND sc.estudianteId = ?",
            [$sid, $uid]
        );
        if (!$sesion) jsonOut(['error' => 'Sesión no encontrada'], 404);

        $precio = (float)($sesion['precio_usd'] > 0 ? $sesion['precio_usd'] : $sesion['precio_base']);

        jsonOut([
            'sesion' => [
                'sesionId'          => (int)$sesion['sesionId'],
                'claseId'           => (int)$sesion['claseId'],
                'pagado'            => (bool)$sesion['pagado'],
                'fin'               => $sesion['fin'],
                'precio'            => $precio,
                'titulo'            => $sesion['titulo'],
                'instructorId'      => (int)$sesion['instructorId'],
                'instructor_nombre' => $sesion['instructor_nombre'],
                'instructor_avatar' => $sesion['instructor_avatar'],
                'materiaId'         => (int)($sesion['materiaId'] ?? 0),
            ],
            'balance' => (int)$user['creditos'],
        ]);
    }

    public static function rateSession(array $body): void {
        $user = getAuthUser();
        $uid  = (int)$user['id'];
        $sala_id = (int)($body['sala_id'] ?? 0);
        $rating = (int)($body['rating'] ?? 0);
        $comentario = trim($body['comentario'] ?? '');
        if (!$sala_id || $rating < 1 || $rating > 5) jsonOut(['error' => 'Datos inválidos'], 400);

        // Find the professor for this sala
        $row = dbOne(
            "SELECT cp.instructorId FROM salas s JOIN clases_programadas cp ON cp.claseId = s.claseId WHERE s.salaId = ?",
            [$sala_id]
        );
        if (!$row) jsonOut(['error' => 'Sala no encontrada'], 404);

        $profId = (int)$row['instructorId'];

        // Find session for this student in this class
        $sesion = dbOne(
            "SELECT sc.sesionId FROM sesiones_clase sc
             JOIN salas s ON s.claseId = sc.claseId
             WHERE s.salaId = ? AND sc.estudianteId = ? AND sc.fin IS NOT NULL
             ORDER BY sc.fin DESC LIMIT 1",
            [$sala_id, $uid]
        );
        $sesionId = $sesion ? (int)$sesion['sesionId'] : 0;

        // Save review
        if ($sesionId) {
            $existing = dbOne("SELECT resenaId FROM resenas WHERE sesionId = ?", [$sesionId]);
            if ($existing) {
                dbExec("UPDATE resenas SET rating = ?, comentario = ? WHERE sesionId = ?", [$rating, $comentario, $sesionId]);
            } else {
                dbExec(
                    "INSERT INTO resenas (sesionId, estudianteId, profesorId, rating, comentario) VALUES (?, ?, ?, ?, ?)",
                    [$sesionId, $uid, $profId, $rating, $comentario]
                );
            }
        }

        // Update average
        $prof = dbOne("SELECT calificacion, num_resenas FROM usuarios WHERE usuarioId = ?", [$profId]);
        $curAvg = (float)($prof['calificacion'] ?? 0);
        $curCount = (int)($prof['num_resenas'] ?? 0);
        $newCount = $curCount + 1;
        $newAvg = ($curAvg * $curCount + $rating) / max(1, $newCount);

        dbExec("UPDATE usuarios SET calificacion = ?, num_resenas = ? WHERE usuarioId = ?", [round($newAvg,2), $newCount, $profId]);
        jsonOut(['ok' => true]);
    }
}
