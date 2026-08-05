<?php
// ─────────────────────────────────────────────────────────────────────────────
//  TeacherController — handlers moved verbatim from api_mobile.php
//  (teacher_dashboard / create_class / class_action)
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Controllers;

final class TeacherController
{
    public static function teacherDashboard(): void {
        $user = getAuthUser();
        if ($user['rol'] !== 'instructor' && $user['rol'] !== 'both') jsonOut(['error' => 'Solo instructores'], 403);
        $uid = (int)$user['id'];

        $me = dbOne(
            "SELECT u.nombre, u.rol, u.calificacion, u.num_resenas, u.avatar,
                    pa.nombre AS pais, pa.simbolo, pa.codigo_moneda
             FROM usuarios u
             LEFT JOIN paises pa ON pa.paisId = u.pais_id
             WHERE u.usuarioId = ?",
            [$uid]
        );

        $stats = dbOne(
            "SELECT
                COUNT(DISTINCT cp.claseId)                                  AS total_clases,
                COALESCE(SUM(cp.activa), 0)                                  AS clases_activas,
                COUNT(DISTINCT sc.sesionId)                                  AS total_sesiones,
                COUNT(DISTINCT CASE WHEN sc.pagado=1 THEN sc.sesionId END)  AS sesiones_pagadas,
                COALESCE(SUM(CASE WHEN p.estado='completado' THEN p.monto_usd END), 0) AS ganancias_usd
             FROM clases_programadas cp
             LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId
             LEFT JOIN pagos           p  ON p.sesionId = sc.sesionId
             WHERE cp.instructorId = ?",
            [$uid]
        );

        $live = dbOne(
            "SELECT COUNT(*) AS n
             FROM participantes_sala ps
             JOIN salas s ON s.salaId = ps.salaId
             JOIN clases_programadas cp ON cp.salaId = s.salaId
             WHERE cp.instructorId = ?",
            [$uid]
        ) ?? ['n' => 0];

        $earningsByCurrency = dbAll(
            "SELECT p.moneda_local, p.simbolo_local,
                    SUM(p.monto_local) AS total, COUNT(*) AS num_pagos
             FROM pagos p
             WHERE p.profesorId = ? AND p.estado = 'completado'
             GROUP BY p.moneda_local, p.simbolo_local
             ORDER BY total DESC",
            [$uid]
        );

        $clases = dbAll(
            "SELECT cp.claseId AS id, cp.instructorId AS profesor_id, cp.materiaId AS materia_id,
                cp.precio_base AS precio, cp.precio_min, cp.precio_max, cp.codigo_moneda,
                cp.alumnos_min, cp.alumnos_max,
                cp.duracion_min AS duracion_minutos, cp.calificacion AS rating,
                cp.titulo, cp.descripcion, cp.activa, cp.created_at,
                m.nombre AS materia, s.salaId AS sala_id, s.activa AS sala_activa,
                COUNT(sc.sesionId) AS num_sesiones,
                COALESCE(SUM(CASE WHEN sc.pagado=1 THEN 1 ELSE 0 END), 0) AS num_pagados
             FROM clases_programadas cp
             JOIN materias m ON m.materiaId = cp.materiaId
             LEFT JOIN salas s ON s.claseId = cp.claseId AND s.activa = true
             LEFT JOIN sesiones_clase sc ON sc.claseId = cp.claseId
             WHERE cp.instructorId = ?
             GROUP BY cp.claseId, cp.instructorId, cp.materiaId, cp.precio_base, cp.precio_min, cp.precio_max,
                      cp.codigo_moneda, cp.alumnos_min, cp.alumnos_max, cp.duracion_min, cp.calificacion,
                      cp.titulo, cp.descripcion, cp.activa, cp.created_at, m.nombre, s.salaId, s.activa
             ORDER BY cp.activa DESC, cp.created_at DESC",
            [$uid]
        );

        $ganRow = dbOne(
            "SELECT COALESCE(SUM(ABS(p.monto_usd)), 0) AS total
             FROM pagos p
             WHERE p.monto_usd < 0
               AND p.profesorId IN (
                   SELECT DISTINCT ps.usuarioId FROM participantes_sala ps
                   JOIN salas s ON s.salaId = ps.salaId
                   JOIN clases_programadas cp ON cp.claseId = s.claseId
                   WHERE cp.instructorId = ?
               )",
            [$uid]
        );

        $sesiones = dbAll(
            "SELECT sc.sesionId AS id, sc.inicio, sc.fin, sc.ultima_salida, sc.duracion_min,
                    sc.monto_local, sc.moneda_local, sc.simbolo_local, sc.pagado,
                    u.nombre AS estudiante, cp.titulo AS clase, m.nombre AS materia
             FROM sesiones_clase sc
             JOIN clases_programadas cp ON cp.claseId = sc.claseId
             JOIN usuarios u ON u.usuarioId = sc.estudianteId
             LEFT JOIN materias m ON m.materiaId = cp.materiaId
             WHERE cp.instructorId = ?
             ORDER BY sc.inicio DESC LIMIT 15",
            [$uid]
        );

        jsonOut([
            'me'                  => $me,
            'stats'               => $stats,
            'live'                => (int)($live['n'] ?? 0),
            'earningsByCurrency'  => $earningsByCurrency,
            'ganancias'           => (float)($ganRow['total'] ?? 0),
            'clases'              => $clases,
            'sesiones'            => $sesiones,
        ]);
    }

    public static function createClass(array $body): void {
        $user       = getAuthUser();
        if ($user['rol'] !== 'instructor' && $user['rol'] !== 'both') jsonOut(['error' => 'Solo instructores'], 403);

        $titulo     = trim($body['titulo'] ?? '');
        $materia_id = (int)($body['materia_id'] ?? 0);
        $precio     = (float)($body['precio'] ?? 0);
        $descripcion = trim($body['descripcion'] ?? '');
        $duracion   = (int)($body['duracion'] ?? 60);

        if (!$titulo || !$materia_id || $precio <= 0) jsonOut(['error' => 'Datos requeridos'], 400);

        dbExec(
            "INSERT INTO clases_programadas (titulo, materiaId, instructorId, precio_base, descripcion, duracion_min, activa)
             VALUES (?, ?, ?, ?, ?, ?, true)",
            [$titulo, $materia_id, $user['id'], $precio, $descripcion, $duracion]
        );

        $clase = dbOne(
            "SELECT cp.claseId AS id, cp.instructorId AS profesor_id, cp.materiaId AS materia_id, cp.precio_base AS precio,
                cp.duracion_min AS duracion_minutos, cp.calificacion AS rating,
                cp.titulo, cp.descripcion, cp.alumnos_max, cp.activa, cp.created_at,
                m.nombre AS materia
             FROM clases_programadas cp
             JOIN materias m ON m.materiaId = cp.materiaId
             WHERE cp.instructorId = ? ORDER BY cp.claseId DESC LIMIT 1",
            [$user['id']]
        );
        jsonOut(['clase' => $clase]);
    }

    public static function classAction(array $body): void {
        $user = getAuthUser();
        if ($user['rol'] !== 'instructor' && $user['rol'] !== 'both') jsonOut(['error' => 'Solo instructores'], 403);
        $uid = (int)$user['id'];

        $action  = (string)($body['action'] ?? '');
        $claseId = (int)($body['clase_id'] ?? 0);
        if (!$claseId) jsonOut(['error' => 'Clase requerida'], 400);

        $clase = dbOne(
            "SELECT claseId, activa FROM clases_programadas WHERE claseId = ? AND instructorId = ?",
            [$claseId, $uid]
        );
        if (!$clase) jsonOut(['error' => 'Clase no encontrada'], 404);

        if ($action === 'activate' || $action === 'deactivate') {
            $activa = $action === 'activate' ? 1 : 0;
            dbExec("UPDATE clases_programadas SET activa = ? WHERE claseId = ? AND instructorId = ?", [$activa, $claseId, $uid]);
            jsonOut(['ok' => true, 'activa' => (bool)$activa]);
        } elseif ($action === 'delete') {
            dbExec("DELETE FROM clases_programadas WHERE claseId = ? AND instructorId = ?", [$claseId, $uid]);
            jsonOut(['ok' => true]);
        } else {
            jsonOut(['error' => 'Acción no válida'], 400);
        }
    }
}
