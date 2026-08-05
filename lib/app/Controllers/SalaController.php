<?php
// ─────────────────────────────────────────────────────────────────────────────
//  SalaController — handlers moved verbatim from api_sala.php
//  Auth is session-based; $uid is passed in by the SalaApi front controller.
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Controllers;

final class SalaController
{
    private static function checkRoomAccess(int $salaId, int $userId): bool {
        $row = dbOne(
            "SELECT 1 FROM salas s
             JOIN clases_programadas cp ON cp.claseId = s.claseId
             LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId AND sc.estudianteId = :uid AND sc.fin IS NULL
             WHERE s.salaId = :sid AND (cp.instructorId = :uid2 OR sc.sesionId IS NOT NULL)
             LIMIT 1",
            ['sid'=>$salaId, 'uid'=>$userId, 'uid2'=>$userId]
        );
        return (bool)$row;
    }

    // ── JOIN ─────────────────────────────────────────────────────────────────
    public static function join(int $uid): void {
        $claseId = (int)($_POST['claseId'] ?? 0);
        if (!$claseId) { jsonOut(['ok'=>false,'error'=>'Missing claseId']); }

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
        if (!$clase) { jsonOut(['ok'=>false,'error'=>'Class not found or inactive']); }

        // Check if already in spectator queue
        $psalaId = (int)($clase['salaid'] ?? 0);
        if ($psalaId) {
            $existing_spectator = dbOne(
                "SELECT espectadorId, estado FROM espectadores WHERE salaId = :s AND usuarioId = :u AND estado = 'pendiente'",
                ['s' => $psalaId, 'u' => $uid]
            );
            if ($existing_spectator) {
                jsonOut(['ok'=>false,'error'=>'Already in spectator queue, waiting for teacher approval']);
            }
        }

        // Check student count (only for approved participants)
        // Allow over-capacity: warn but don't block — teacher can approve extra
        $joined = dbOne(
            "SELECT COUNT(*) AS cnt FROM sesiones_clase WHERE claseId = :id AND fin IS NULL",
            ['id' => $claseId]
        )['cnt'] ?? 0;
        $overCapacity = $joined >= $clase['alumnos_max'];

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

        // Open or reuse session (with 5-min grace period for reconnection)
        $existing = dbOne(
            "SELECT sesionId, inicio, segundos_acumulados FROM sesiones_clase
             WHERE claseId=:c AND estudianteId=:u
               AND ultima_salida IS NOT NULL
               AND ultima_salida >= NOW() - INTERVAL 5 MINUTE
             ORDER BY ultima_salida DESC LIMIT 1",
            ['c'=>$claseId,'u'=>$uid]
        );

        if ($existing) {
            // Reconnect: keep accumulated seconds, reset inicio for this session
            $sesionId = $existing['sesionId'];
            dbExec(
                "UPDATE sesiones_clase SET inicio = NOW(), ultima_salida = NULL WHERE sesionId = :id",
                ['id' => $sesionId]
            );
        } else {
            // Check for existing active session (shouldn't exist, but just in case)
            $activeExisting = dbOne(
                "SELECT sesionId FROM sesiones_clase WHERE claseId=:c AND estudianteId=:u AND fin IS NULL",
                ['c'=>$claseId,'u'=>$uid]
            );
            if ($activeExisting) {
                $sesionId = $activeExisting['sesionId'];
            } else {
                $sesionId = dbExec(
                    "INSERT INTO sesiones_clase
                        (claseId, estudianteId, instructorId, salaId, inicio, precio_usd, monto_local, moneda_local, simbolo_local, espectador)
                     VALUES (:c, :u, :i, :s, NOW(), :pu, :ml, :mon, :sim, 1)",
                    [
                        'c'  => $claseId,
                        'u'  => $uid,
                        'i'  => $clase['instructorId'],
                        's'  => $clase['salaid'] ?? null,
                        'pu' => $precio_usd,
                        'ml' => $monto_local,
                        'mon'=> $moneda_local,
                        'sim'=> $simbolo,
                    ]
                );
            }
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

        jsonOut([
            'ok'                  => true,
            'sesionId'            => $sesionId,
            'precio_usd'          => $precio_usd,
            'monto_local'         => $monto_local,
            'moneda_local'        => $moneda_local,
            'simbolo'             => $simbolo,
            'clase_titulo'        => $clase['titulo'],
            'espectador'          => true,
            'overCapacity'        => $overCapacity,
            'segundos_acumulados' => (int)($existing['segundos_acumulados'] ?? 0),
        ]);
    }

    // ── LEAVE ────────────────────────────────────────────────────────────────
    public static function leave(int $uid): void {
        $sesionId    = (int)($_POST['sesionId'] ?? 0);
        $intentional = (int)($_POST['intentional'] ?? 0);
        if (!$sesionId) { jsonOut(['ok'=>false,'error'=>'Missing sesionId']); }

        $sesion = dbOne(
            "SELECT s.*, cp.instructorId, cp.precio_base, cp.alumnos_max,
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
        if (!$sesion) { jsonOut(['ok'=>false,'error'=>'Session not found']); }

        // Calculate duration and add to accumulated seconds
        $inicio       = new DateTime($sesion['inicio']);
        $fin          = new DateTime();
        $thisSegundos = max(0, $fin->getTimestamp() - $inicio->getTimestamp());
        $acumulado    = (int)$sesion['segundos_acumulados'] + $thisSegundos;
        $duracion_min = max(1, (int)round($acumulado / 60));

        // Count all currently active/paused students (within grace period)
        $all_active = dbOne(
            "SELECT COUNT(*) AS cnt FROM sesiones_clase
             WHERE claseId = :c
               AND (
                 (fin IS NULL AND ultima_salida IS NULL)
                 OR (ultima_salida IS NOT NULL AND ultima_salida >= NOW() - INTERVAL 5 MINUTE)
               )
               AND espectador = 0",
            ['c' => $sesion['claseId']]
        )['cnt'] ?? 1;
        $all_active = max(1, $all_active);

        $precio_base_usd = (float)$sesion['precio_base'];
        $precio_usd = round($precio_base_usd / $all_active, 2);
        $tasa        = (float)($sesion['tasa_usd'] ?? 1);
        $monto_local = round($precio_usd * $tasa, 2);
        $mon_local   = $sesion['mon_local'] ?? 'USD';
        $sim_local   = $sesion['sim_local'] ?? '$';

        if ($intentional) {
            // Intentional leave: fully close session, calculate charge
            dbExec(
                "UPDATE sesiones_clase
                 SET fin = NOW(), duracion_min = :d,
                     segundos_acumulados = :secs,
                     precio_usd = :pu, monto_local = :ml,
                     moneda_local = :mon, simbolo_local = :sim
                 WHERE sesionId = :id",
                [
                    'd'    => $duracion_min,
                    'secs' => $acumulado,
                    'pu'   => $precio_usd,
                    'ml'   => $monto_local,
                    'mon'  => $mon_local,
                    'sim'  => $sim_local,
                    'id'   => $sesionId,
                ]
            );

            jsonOut([
                'ok'           => true,
                'sesionId'     => $sesionId,
                'duracion_min' => $duracion_min,
                'precio_usd'   => $precio_usd,
                'monto_local'  => $monto_local,
                'moneda_local' => $mon_local,
                'simbolo'      => $sim_local,
                'prof_nombre'  => $sesion['prof_nombre'],
                'redirect'     => 'calificar.php?sesion=' . $sesionId,
            ]);
        } else {
            // Pause: give 5-min grace period for reconnection
            dbExec(
                "UPDATE sesiones_clase
                 SET inicio = NOW(), segundos_acumulados = :secs, ultima_salida = NOW()
                 WHERE sesionId=:id",
                ['secs'=>$acumulado,'id'=>$sesionId]
            );

            jsonOut([
                'ok'       => true,
                'paused'   => true,
                'sesionId' => $sesionId,
            ]);
        }
    }

    // ── PAY ──────────────────────────────────────────────────────────────────
    public static function pay(int $uid): void {
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
        if (!$sesion) { jsonOut(['ok'=>false,'error'=>'Session not found or already paid']); }

        // Calculate commission: 15% base for Rodrigo
        $comision_rodrigo = 0;

        if ($sesion['instructorId'] != 1) {
            $comision_rodrigo = round($sesion['precio_usd'] * 0.15, 2);
        }

        $db = getDB();
        if (!$db) { jsonOut(['ok'=>false,'error'=>'DB unavailable']); }

        $db->prepare(
            "INSERT INTO pagos (sesionId, estudianteId, profesorId, monto_usd, monto_local, moneda_local, simbolo_local, estado)
             VALUES (:sid,:est,:prof,:usd,:loc,:mon,:sim,'completado')"
        )->execute([
            'sid'  => $sesionId,
            'est'  => $uid,
            'prof' => $sesion['instructorId'],
            'usd'  => $sesion['precio_usd'],
            'loc'  => $sesion['monto_local'],
            'mon'  => $sesion['moneda_local'],
            'sim'  => $sesion['simbolo_local'] ?? '$',
        ]);

        dbExec("UPDATE sesiones_clase SET pagado=1 WHERE sesionId=:id", ['id'=>$sesionId]);

        jsonOut(['ok'=>true,'message'=>'Payment confirmed']);
    }

    // ── CHAT ─────────────────────────────────────────────────────────────────
    public static function chat(int $uid): void {
        $salaId  = (int)($_POST['salaId'] ?? 0);
        $mensaje = trim($_POST['mensaje'] ?? '');
        if (!$salaId || $mensaje === '') { jsonOut(['ok'=>false,'error'=>'Missing data']); }

        $user = dbOne("SELECT nombre FROM usuarios WHERE usuarioId=:id", ['id'=>$uid]);
        $alias = $user['nombre'] ?? 'Unknown';

        $msgId = pollChatWrite($salaId, $uid, $alias, htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'));

        jsonOut(['ok'=>true,'alias'=>$alias,'mensaje'=>htmlspecialchars($mensaje),'mensajeId'=>$msgId]);
    }

    // ── MESSAGES POLL ────────────────────────────────────────────────────────
    public static function messages(int $uid): void {
        $salaId  = (int)($_GET['salaId'] ?? 0);
        if (!self::checkRoomAccess($salaId, $uid)) { jsonOut(['ok'=>false,'error'=>'Access denied']); }
        $afterId = (int)($_GET['afterId'] ?? 0);
        $msgs = pollChatRead($salaId, $afterId);
        jsonOut(['ok'=>true,'messages'=>$msgs]);
    }

    // ── WEBRTC SIGNAL SEND ───────────────────────────────────────────────────
    public static function signal(int $uid): void {
        $salaId  = (int)($_POST['salaId'] ?? 0);
        if (!self::checkRoomAccess($salaId, $uid)) { jsonOut(['ok'=>false,'error'=>'Access denied']); }
        $toUid   = (int)($_POST['toUid'] ?? 0) ?: null;
        $tipo    = in_array($_POST['tipo'] ?? '', ['offer','answer','candidate','bye'])
                   ? $_POST['tipo'] : '';
        $payload = $_POST['payload'] ?? '';
        if (!$salaId || !$tipo || $payload === '') {
            jsonOut(['ok'=>false,'error'=>'Missing signal data']);
        }
        $sigId = pollSignalWrite($salaId, $uid, $toUid, $tipo, $payload);
        jsonOut(['ok'=>true,'signalId'=>$sigId]);
    }

    // ── WEBRTC SIGNAL POLL ───────────────────────────────────────────────────
    public static function signals(int $uid): void {
        $salaId  = (int)($_GET['salaId'] ?? 0);
        if (!self::checkRoomAccess($salaId, $uid)) { jsonOut(['ok'=>false,'error'=>'Access denied']); }
        $afterId = (int)($_GET['afterId'] ?? 0);
        $rows = pollSignalRead($salaId, $uid, $afterId);
        jsonOut(['ok'=>true,'signals'=>$rows]);
    }

    // ── WEBRTC SIGNAL POLL (lowercase col) ───────────────────────────────────
    public static function pollSignals(int $uid): void {
        $salaId  = (int)($_GET['salaId'] ?? 0);
        if (!self::checkRoomAccess($salaId, $uid)) { jsonOut(['ok'=>false,'error'=>'Access denied']); }
        $afterId = (int)($_GET['afterId'] ?? 0);
        $rows = pollSignalRead($salaId, $uid, $afterId);
        jsonOut(['ok'=>true,'signals'=>$rows]);
    }

    // ── APPROVE SPECTATOR ────────────────────────────────────────────────────
    public static function approveSpectator(int $uid): void {
        $espectadorId = (int)($_POST['espectadorId'] ?? 0);
        $salaId = (int)($_POST['salaId'] ?? 0);

        if (!$espectadorId || !$salaId) {
            jsonOut(['ok'=>false,'error'=>'Missing espectadorId or salaId']);
        }

        // Verify user is the teacher of this classroom
        $clase = dbOne(
            "SELECT instructorId FROM salas WHERE salaId = :s",
            ['s' => $salaId]
        );

        if (!$clase || $clase['instructorId'] != $uid) {
            jsonOut(['ok'=>false,'error'=>'Not authorized']);
        }

        // Update spectator status — allow even if over capacity (price gets split)
        dbExec(
            "UPDATE espectadores SET estado = 'aprobado', profesor_aprobo = :prof WHERE espectadorId = :id",
            ['prof' => $uid, 'id' => $espectadorId]
        );

        // Check current active count vs max (include paused sessions within grace)
        $claseFullInfo = dbOne(
            "SELECT cp.alumnos_max FROM clases_programadas cp WHERE cp.salaId = :s LIMIT 1",
            ['s' => $salaId]
        );
        $activeCount = dbOne(
            "SELECT COUNT(*) AS cnt FROM sesiones_clase sc
             JOIN clases_programadas cp ON cp.claseId = sc.claseId
             JOIN salas s ON s.salaId = :s
             WHERE sc.fin IS NULL AND sc.espectador = 0
               AND (sc.ultima_salida IS NULL OR sc.ultima_salida >= NOW() - INTERVAL 5 MINUTE)",
            ['s' => $salaId]
        )['cnt'] ?? 0;

        $isOverCapacity = $claseFullInfo && $activeCount >= $claseFullInfo['alumnos_max'];
        if ($isOverCapacity) {
            // Mark as sobrecupo — price will be split among more students
            dbExec(
                "UPDATE espectadores SET sobre_cupo = 1 WHERE espectadorId = :id",
                ['id' => $espectadorId]
            );
        }

        // Update session to no longer be spectator
        $espectador = dbOne(
            "SELECT usuarioId FROM espectadores WHERE espectadorId = :id",
            ['id' => $espectadorId]
        );

        if ($espectador) {
            dbExec(
                "UPDATE sesiones_clase SET espectador = 0 WHERE estudianteId = :u AND fin IS NULL",
                ['u' => $espectador['usuarioId']]
            );
        }

        jsonOut(['ok'=>true,'message'=>'Spectator approved']);
    }

    // ── END CLASS ───────────────────────────────────────────────────────────
    public static function endClass(int $uid): void {
        $claseId = (int)($_POST['claseId'] ?? 0);
        $salaId = (int)($_POST['salaId'] ?? 0);

        if (!$claseId || !$salaId) {
            jsonOut(['ok'=>false,'error'=>'Missing claseId or salaId']);
        }

        // Verify user is the teacher
        $clase = dbOne(
            "SELECT instructorId FROM clases_programadas WHERE claseId = :id",
            ['id' => $claseId]
        );

        if (!$clase || $clase['instructorId'] != $uid) {
            jsonOut(['ok'=>false,'error'=>'Not authorized']);
        }

        // Mark class as inactive
        dbExec("UPDATE clases_programadas SET activa = 0 WHERE claseId = :id", ['id' => $claseId]);

        // Calculate total tokens earned from all sessions ever in this class
        $sessions = dbAll(
            "SELECT sc.precio_usd, sc.pagado, sc.fin, sc.ultima_salida, sc.segundos_acumulados
             FROM sesiones_clase sc
             WHERE sc.claseId = :c AND sc.instructorId = :i",
            ['c' => $claseId, 'i' => $uid]
        );

        $tokens_ganados = 0;
        foreach ($sessions as $session) {
            // If it was paid, count it. Also close any paused sessions.
            if ($session['pagado']) {
                $tokens_ganados += (float)$session['precio_usd'];
            }
        }

        // Close all active AND paused sessions (close them permanently)
        dbExec(
            "UPDATE sesiones_clase
             SET fin = NOW(), duracion_min = COALESCE(duracion_min, GREATEST(1, ROUND(segundos_acumulados/60)))
             WHERE claseId = :c AND fin IS NULL",
            ['c' => $claseId]
        );

        jsonOut(['ok'=>true,'tokens_ganados'=>$tokens_ganados]);
    }

    // ── REJECT SPECTATOR ─────────────────────────────────────────────────────
    public static function rejectSpectator(int $uid): void {
        $espectadorId = (int)($_POST['espectadorId'] ?? 0);
        $salaId = (int)($_POST['salaId'] ?? 0);

        if (!$espectadorId || !$salaId) {
            jsonOut(['ok'=>false,'error'=>'Missing espectadorId or salaId']);
        }

        // Verify user is the teacher
        $clase = dbOne(
            "SELECT instructorId FROM salas WHERE salaId = :s",
            ['s' => $salaId]
        );

        if (!$clase || $clase['instructorId'] != $uid) {
            jsonOut(['ok'=>false,'error'=>'Not authorized']);
        }

        // Update spectator status
        dbExec(
            "UPDATE espectadores SET estado = 'rechazado', profesor_aprobo = :prof WHERE espectadorId = :id",
            ['prof' => $uid, 'id' => $espectadorId]
        );

        jsonOut(['ok'=>true,'message'=>'Spectator rejected']);
    }

    // ── GET SPECTATORS ───────────────────────────────────────────────────────
    public static function getSpectators(int $uid): void {
        $salaId = (int)($_GET['salaId'] ?? 0);

        if (!$salaId) {
            jsonOut(['ok'=>false,'error'=>'Missing salaId']);
        }

        if (!self::checkRoomAccess($salaId, $uid)) { jsonOut(['ok'=>false,'error'=>'Access denied']); }

        $spectators = pollSpectatorsRead($salaId);

        jsonOut(['ok'=>true,'spectators'=>$spectators]);
    }

    // ── GET STUDENTS IN ROOM (teacher only) ──────────────────────────────────
    public static function students(int $uid): void {
        $salaId = (int)($_GET['salaId'] ?? 0);
        if (!$salaId) { jsonOut(['ok'=>false,'error'=>'Missing salaId']); }

        // Verify teacher
        $clase = dbOne("SELECT instructorId FROM salas WHERE salaId = :s", ['s'=>$salaId]);
        if (!$clase || $clase['instructorId'] != $uid) {
            jsonOut(['ok'=>false,'error'=>'Not authorized']);
        }

        $baseUrl = getBaseUrl();
        $students = pollStudentsRead($salaId, $baseUrl);

        jsonOut(['ok'=>true, 'students'=>$students]);
    }

    // ── KICK STUDENT ─────────────────────────────────────────────────────────
    public static function kickStudent(int $uid): void {
        $salaId      = (int)($_POST['salaId'] ?? 0);
        $estudianteId = (int)($_POST['estudianteId'] ?? 0);
        $comentario  = trim($_POST['comentario'] ?? '');

        if (!$salaId || !$estudianteId || !$comentario) {
            jsonOut(['ok'=>false,'error'=>'Missing data']);
        }

        // Verify teacher
        $clase = dbOne("SELECT instructorId FROM salas WHERE salaId = :s", ['s'=>$salaId]);
        if (!$clase || $clase['instructorId'] != $uid) {
            jsonOut(['ok'=>false,'error'=>'Not authorized']);
        }

        // Register sanction
        dbExec(
            "INSERT INTO sanciones (salaId, instructorId, estudianteId, comentario)
             VALUES (:s, :i, :e, :c)",
            ['s'=>$salaId, 'i'=>$uid, 'e'=>$estudianteId, 'c'=>$comentario]
        );

        // Close all active sessions for this student in this class
        dbExec(
            "UPDATE sesiones_clase sc
             JOIN salas s ON s.claseId = sc.claseId
             SET sc.fin = NOW(), sc.segundos_acumulados = COALESCE(sc.segundos_acumulados, 0)
             WHERE s.salaId = :s AND sc.estudianteId = :e AND sc.fin IS NULL",
            ['s'=>$salaId, 'e'=>$estudianteId]
        );

        jsonOut(['ok'=>true, 'message'=>'Student expelled']);
    }
}
