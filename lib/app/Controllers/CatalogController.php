<?php
// ─────────────────────────────────────────────────────────────────────────────
//  CatalogController — handlers moved verbatim from api_mobile.php
//  (subjects / teachers / classes / class_detail / countries)
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Controllers;

final class CatalogController
{
    public static function subjects(): void {
        $subjects = dbAll(
            "SELECT m.materiaId AS id, m.nombre, m.imagen, m.pagina, m.orden,
                (SELECT COUNT(*) FROM clases_programadas cp WHERE cp.materiaId = m.materiaId AND cp.activa = true) AS clases_activas
             FROM materias m ORDER BY m.nombre"
        );

        $colors = [
            'Mathematics'         => '#2563EB',
            'History'             => '#D97706',
            'Literature'          => '#DC2626',
            'Chemistry'           => '#7C3AED',
            'Biology'             => '#059669',
            'Physics'             => '#0284C7',
            'Geography'           => '#0D9488',
            'Art and Music'       => '#EA580C',
            'Physical Education'  => '#E11D48',
            'Foreign Languages'   => '#DB2777',
            'Technology'          => '#0891B2',
        ];
        $icons = [
            'Mathematics'         => 'calculator',
            'History'             => 'book-open',
            'Literature'          => 'feather',
            'Chemistry'           => 'zap',
            'Biology'             => 'activity',
            'Physics'             => 'cpu',
            'Geography'           => 'map',
            'Art and Music'       => 'pen-tool',
            'Physical Education'  => 'heart',
            'Foreign Languages'   => 'globe',
            'Technology'          => 'monitor',
        ];

        foreach ($subjects as &$s) {
            $s['color']          = $colors[$s['nombre']] ?? '#66ddbd';
            $s['icono']          = $icons[$s['nombre']] ?? 'book';
            $s['clases_activas'] = (int)$s['clases_activas'];
        }

        jsonOut(['subjects' => $subjects]);
    }

    public static function teachers(): void {
        $sid    = (int)($_GET['subject_id'] ?? 0);
        $params = [];
        $sql    = "SELECT u.usuarioId AS id, u.nombre, u.email, u.rol, u.creditos,
                    ROUND(COALESCE(AVG(u.calificacion), 4.0), 1) AS rating,
                    COUNT(DISTINCT cp.claseId) AS clases_count
                   FROM usuarios u
                   LEFT JOIN clases_programadas cp ON cp.instructorId = u.usuarioId
                   WHERE u.rol = 'instructor'";
        if ($sid) { $sql .= " AND cp.materiaId = ?"; $params[] = $sid; }
        $sql .= " GROUP BY u.usuarioId, u.nombre, u.email, u.rol, u.creditos ORDER BY rating DESC, clases_count DESC";

        jsonOut(['teachers' => dbAll($sql, $params)]);
    }

    public static function classes(): void {
        $sid     = (int)($_GET['subject_id'] ?? 0);
        $search  = trim($_GET['search'] ?? '');
        $active  = ($_GET['active_only'] ?? '') === 'true';
        $sort    = trim($_GET['sort'] ?? 'relevance');
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $limit   = min(50, max(10, (int)($_GET['limit'] ?? 20)));
        $offset  = ($page - 1) * $limit;
        $params  = [];
        $uid     = 0;

        $token   = '';
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $token = $m[1];
        }
        if (!$token) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
                $token = $m[1];
            }
        }
        if ($token) {
            $session = dbOne("SELECT usuario_id FROM mobile_tokens WHERE token = ?", [$token]);
            if ($session) $uid = (int)$session['usuario_id'];
        }

        $sql = "SELECT cp.claseId AS id, cp.titulo, cp.descripcion, cp.precio_base AS precio, cp.duracion_min AS duracion_minutos, cp.calificacion AS rating, cp.alumnos_max, cp.alumnos_activos, cp.activa, cp.created_at,
                       m.materiaId AS materia_id, m.nombre AS materia,
                       u.usuarioId AS profesor_id, u.nombre AS profesor,
                       (SELECT s.activa FROM salas s WHERE s.claseId = cp.claseId AND s.activa = true LIMIT 1) AS sala_activa,
                       (SELECT COALESCE(SUM(sc.segundos_acumulados), 0) FROM sesiones_clase sc WHERE sc.claseId = cp.claseId) AS total_visto";
        if ($uid) {
            $sql .= ", IF(f.id IS NOT NULL, 1, 0) AS es_amigo";
        } else {
            $sql .= ", 0 AS es_amigo";
        }
        $sql .= " FROM clases_programadas cp
                  JOIN materias m ON m.materiaId = cp.materiaId
                  JOIN usuarios u ON u.usuarioId = cp.instructorId";
        if ($uid) {
            $sql .= " LEFT JOIN relaciones f ON f.seguidoId = u.usuarioId AND f.seguidorId = ? AND f.estado = 'following'";
            $params[] = $uid;
        }
        $sql .= " WHERE cp.activa = true";

        if ($sid)    { $sql .= " AND cp.materiaId = ?"; $params[] = $sid; }
        if ($search) {
            $sql .= " AND (cp.titulo LIKE ? OR u.nombre LIKE ? OR m.nombre LIKE ? OR cp.descripcion LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($active) { $sql .= " AND EXISTS (SELECT 1 FROM salas s WHERE s.claseId = cp.claseId AND s.activa = true)"; }

        $countSql = "SELECT COUNT(*) AS total FROM ($sql) AS _count";
        $total = (int)(dbOne($countSql, $params)['total'] ?? 0);

        switch ($sort) {
            case 'price_asc':   $sql .= " ORDER BY cp.precio_base ASC"; break;
            case 'price_desc':  $sql .= " ORDER BY cp.precio_base DESC"; break;
            case 'rating':      $sql .= " ORDER BY cp.calificacion DESC, total_visto DESC"; break;
            case 'popular':     $sql .= " ORDER BY total_visto DESC"; break;
            case 'newest':      $sql .= " ORDER BY cp.created_at DESC"; break;
            case 'relevance':
            default:
                if ($uid) {
                    $sql .= " ORDER BY es_amigo DESC, sala_activa IS NULL, sala_activa DESC, total_visto DESC, cp.precio_base ASC";
                } else {
                    $sql .= " ORDER BY sala_activa IS NULL, sala_activa DESC, total_visto DESC, cp.precio_base ASC";
                }
                break;
        }
        $sql .= " LIMIT $limit OFFSET $offset";

        jsonOut(['classes' => dbAll($sql, $params), 'total' => $total, 'page' => $page, 'pages' => (int)ceil($total / $limit)]);
    }

    public static function classDetail(): void {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonOut(['error' => 'ID requerido'], 400);

        $clase = dbOne(
            "SELECT cp.claseId AS id, cp.instructorId AS profesor_id, cp.materiaId AS materia_id, cp.precio_base AS precio,
                cp.duracion_min AS duracion_minutos, cp.calificacion AS rating,
                cp.titulo, cp.descripcion, cp.alumnos_max, cp.activa, cp.created_at,
                m.nombre AS materia, u.nombre AS profesor,
                s.salaId AS sala_id, s.activa AS sala_activa
             FROM clases_programadas cp
             JOIN materias m ON m.materiaId = cp.materiaId
             JOIN usuarios u ON u.usuarioId = cp.instructorId
             LEFT JOIN salas s ON s.claseId = cp.claseId AND s.activa = true
             WHERE cp.claseId = ?",
            [$id]
        );
        if (!$clase) jsonOut(['error' => 'Clase no encontrada'], 404);
        jsonOut(['clase' => $clase]);
    }

    public static function countries(): void {
        $rows = dbAll("SELECT paisid AS id, nombre, codigo_iso AS codigo, codigo_moneda, simbolo FROM paises ORDER BY nombre");
        jsonOut(['countries' => $rows]);
    }
}
