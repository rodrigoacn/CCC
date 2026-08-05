<?php
// ─────────────────────────────────────────────────────────────────────────────
//  SocialController — handlers moved verbatim from api_mobile.php
//  (friends / follow / unfriend / send_dm / get_dms / search_people /
//   user_profile / resenas_profesor)
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Controllers;

final class SocialController
{
    public static function friends(): void {
        $user = getAuthUser();
        $uid  = (int)$user['id'];

        // Get users I follow
        $siguiendo = dbAll(
            "SELECT u.usuarioId AS usuarioid, u.nombre, u.username, u.avatar, u.rol, u.creditos, u.calificacion, u.num_resenas, r.created_at AS seguido_desde
             FROM relaciones r
             JOIN usuarios u ON u.usuarioId = r.seguidoId
             WHERE r.seguidorId = ? AND r.estado = 'following'
             ORDER BY r.created_at DESC",
            [$uid]
        );

        // Get my followers
        $seguidores = dbAll(
            "SELECT u.usuarioId AS usuarioid, u.nombre, u.username, u.avatar, u.rol, u.creditos, u.calificacion, u.num_resenas, r.created_at AS sigue_desde
             FROM relaciones r
             JOIN usuarios u ON u.usuarioId = r.seguidorId
             WHERE r.seguidoId = ? AND r.estado = 'following'
             ORDER BY r.created_at DESC",
            [$uid]
        );

        $me = dbOne("SELECT username FROM usuarios WHERE usuarioId = ?", [$uid]);

        $base = getBaseUrl();
        foreach ($siguiendo as &$s) { if (!empty($s['avatar'])) $s['avatar'] = $base . '/' . $s['avatar']; }
        foreach ($seguidores as &$s) { if (!empty($s['avatar'])) $s['avatar'] = $base . '/' . $s['avatar']; }

        jsonOut([
            'siguiendo'      => $siguiendo,
            'seguidores'     => $seguidores,
            'username'       => $me['username'] ?? '',
        ]);
    }

    public static function follow(array $body): void {
        $user     = getAuthUser();
        $uid      = (int)$user['id'];
        $targetId = (int)($body['usuario_id'] ?? 0);
        if (!$targetId || $targetId === $uid) jsonOut(['error' => 'Usuario inválido'], 400);

        $exists = dbOne("SELECT id, estado FROM relaciones WHERE seguidorId = ? AND seguidoId = ?", [$uid, $targetId]);
        if ($exists) {
            if ($exists['estado'] === 'following') {
                // Unfollow
                dbExec("DELETE FROM relaciones WHERE id = ?", [$exists['id']]);
                jsonOut(['ok' => true, 'siguiendo' => false]);
            } else {
                dbExec("UPDATE relaciones SET estado = 'following' WHERE id = ?", [$exists['id']]);
                jsonOut(['ok' => true, 'siguiendo' => true]);
            }
        } else {
            dbExec(
                "INSERT INTO relaciones (seguidorId, seguidoId, estado) VALUES (?, ?, 'following')",
                [$uid, $targetId]
            );
            jsonOut(['ok' => true, 'siguiendo' => true]);
        }
    }

    public static function unfriend(array $body): void {
        $user     = getAuthUser();
        $uid      = (int)$user['id'];
        $targetId = (int)($body['usuario_id'] ?? 0);
        if (!$targetId) jsonOut(['error' => 'Usuario requerido'], 400);

        dbExec("DELETE FROM relaciones WHERE (seguidorId = ? AND seguidoId = ?) OR (seguidorId = ? AND seguidoId = ?)",
            [$uid, $targetId, $targetId, $uid]);

        jsonOut(['ok' => true]);
    }

    public static function sendDirectMessage(array $body): void {
        $user  = getAuthUser();
        $uid   = (int)$user['id'];
        $toId  = (int)($body['destinatario_id'] ?? 0);
        $msg   = trim($body['mensaje'] ?? '');
        if (!$toId || !$msg) jsonOut(['error' => 'Datos requeridos'], 400);

        // Verify they are friends (follow each other or at least one follows)
        $rel = dbOne(
            "SELECT id FROM relaciones
             WHERE ((seguidorId = ? AND seguidoId = ?) OR (seguidorId = ? AND seguidoId = ?))
               AND estado = 'following'",
            [$uid, $toId, $toId, $uid]
        );
        if (!$rel) jsonOut(['error' => 'Solo puedes enviar mensajes a amigos'], 403);

        dbExec(
            "INSERT INTO mensajes_directos (remitente_id, destinatario_id, mensaje) VALUES (?, ?, ?)",
            [$uid, $toId, $msg]
        );

        $row = dbOne(
            "SELECT md.*, u.nombre AS remitente_nombre
             FROM mensajes_directos md
             JOIN usuarios u ON u.usuarioId = md.remitente_id
             WHERE md.id = LAST_INSERT_ID()"
        );

        jsonOut(['ok' => true, 'mensaje' => $row]);
    }

    public static function getDirectMessages(): void {
        $user  = getAuthUser();
        $uid   = (int)$user['id'];
        $conId = (int)($_GET['con'] ?? 0);
        $after = (int)($_GET['after'] ?? 0);

        if ($conId) {
            // Get messages with a specific user
            $where = "AND ((md.remitente_id = ? AND md.destinatario_id = ?) OR (md.remitente_id = ? AND md.destinatario_id = ?))";
            $par   = [$uid, $conId, $conId, $uid];
        } else {
            // Get all recent messages for this user
            $where = "AND (md.destinatario_id = ? OR md.remitente_id = ?)";
            $par   = [$uid, $uid];
        }

        if ($after) { $where .= " AND md.id > ?"; $par[] = $after; }

        $sql = "SELECT md.*, u.nombre AS remitente_nombre
                FROM mensajes_directos md
                JOIN usuarios u ON u.usuarioId = md.remitente_id
                WHERE 1=1 $where
                ORDER BY md.id DESC LIMIT 50";
        $par = array_merge($par, []);

        // Rebuild with proper ordering
        $msgs = dbAll($sql, $par);
        $msgs = array_reverse($msgs); // Most recent first for display

        // Mark as read
        if ($conId) {
            dbExec(
                "UPDATE mensajes_directos SET leido = 1 WHERE destinatario_id = ? AND remitente_id = ? AND leido = 0",
                [$uid, $conId]
            );
        }

        jsonOut(['mensajes' => $msgs]);
    }

    public static function searchPeople(): void {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 1) jsonOut(['error' => 'Consulta muy corta'], 400);

        $users = dbAll(
            "SELECT u.usuarioId AS id, u.nombre, u.username, u.avatar, u.rol, u.calificacion, u.num_resenas, u.biografia,
                    p.nombre AS pais
             FROM usuarios u
             LEFT JOIN paises p ON p.paisId = u.pais_id
             WHERE u.nombre LIKE ? OR u.username LIKE ?
             ORDER BY u.num_resenas DESC, u.calificacion DESC
             LIMIT 30",
            ["%$q%", "%$q%"]
        );

        foreach ($users as &$u) {
            if (!empty($u['avatar'])) $u['avatar'] = getBaseUrl() . '/' . $u['avatar'];
            $u['rating'] = (float)($u['calificacion'] ?? 0);
            $u['reviews'] = (int)($u['num_resenas'] ?? 0);
            unset($u['calificacion'], $u['num_resenas']);
        }

        jsonOut(['people' => $users]);
    }

    public static function userProfile(array $body): void {
        $targetId = (int)($body['usuario_id'] ?? $_GET['usuario_id'] ?? 0);
        if (!$targetId) jsonOut(['error' => 'Usuario requerido'], 400);

        $user = dbOne(
            "SELECT u.*, p.nombre AS pais, p.codigo_moneda, p.simbolo
             FROM usuarios u
             LEFT JOIN paises p ON p.paisId = u.pais_id
             WHERE u.usuarioId = ?",
            [$targetId]
        );
        if (!$user) jsonOut(['error' => 'Usuario no encontrado'], 404);

        // Get idiomas
        $idiomas = array_column(
            dbAll(
                "SELECT i.nombre FROM usuario_idiomas ui JOIN idiomas i ON i.idiomaId = ui.idiomaId WHERE ui.usuarioId = ?",
                [$targetId]
            ),
            'nombre'
        );

        $esProfesor = ($user['rol'] === 'instructor' || $user['rol'] === 'both');

        // Get reviews (only for teachers)
        $resenas = [];
        if ($esProfesor) {
            $resenas = dbAll(
                "SELECT r.*, u.nombre AS estudiante_nombre, u.avatar AS estudiante_avatar
                 FROM resenas r
                 JOIN usuarios u ON u.usuarioId = r.estudianteId
                 WHERE r.profesorId = ?
                 ORDER BY r.created_at DESC LIMIT 50",
                [$targetId]
            );
            foreach ($resenas as &$r) {
                if (!empty($r['estudiante_avatar'])) $r['estudiante_avatar'] = getBaseUrl() . '/' . $r['estudiante_avatar'];
            }
        }

        // Follow status
        $siguiendo = false;
        $authUser = getAuthUser();
        if ($authUser && (int)$authUser['id'] !== $targetId) {
            $rel = dbOne(
                "SELECT id FROM relaciones WHERE seguidorId = ? AND seguidoId = ? AND estado = 'following'",
                [(int)$authUser['id'], $targetId]
            );
            $siguiendo = (bool)$rel;
        }

        // Teacher's upcoming classes
        $clases = [];
        if ($esProfesor) {
            $clases = dbAll(
                "SELECT cp.claseId AS id, cp.titulo, cp.precio_base, cp.duracion_min AS duracion, cp.activa,
                        m.nombre AS materia, m.icono, m.color,
                        (SELECT COUNT(*) FROM sesiones_clase sc WHERE sc.claseId = cp.claseId AND sc.fin IS NULL) AS alumnos_activos
                 FROM clases_programadas cp
                 LEFT JOIN materias m ON m.materiaId = cp.materiaId
                 WHERE cp.instructorId = ? AND cp.activa = 1
                 ORDER BY cp.created_at DESC
                 LIMIT 10",
                [$targetId]
            );
        }

        $base = getBaseUrl();
        $profile = [
            'id'          => (int)$user['usuarioId'],
            'nombre'      => $user['nombre'],
            'username'    => $user['username'] ?? '',
            'email'       => $user['email'],
            'rol'         => $user['rol'],
            'avatar'      => $user['avatar'] ? $base . '/' . $user['avatar'] : '',
            'biografia'   => $user['biografia'] ?? '',
            'pais'        => $user['pais'] ?? '',
            'idiomas'     => $idiomas,
            'calificacion' => (float)($user['calificacion'] ?? 0),
            'num_resenas'  => (int)($user['num_resenas'] ?? 0),
            'privacidad'   => $user['privacidad'] ?? 'private',
            'created_at'  => $user['created_at'] ?? '',
            'resenas'     => $resenas,
            'siguiendo'   => $siguiendo,
            'clases'      => $clases,
        ];

        jsonOut(['profile' => $profile]);
    }

    public static function resenasProfesor(): void {
        $user = getAuthUser();
        $targetId = (int)($_GET['profesor_id'] ?? 0);
        if (!$targetId) $targetId = (int)$user['id'];

        $resenas = dbAll(
            "SELECT r.*, u.nombre AS estudiante_nombre, u.avatar AS estudiante_avatar
             FROM resenas r
             JOIN usuarios u ON u.usuarioId = r.estudianteId
             WHERE r.profesorId = ?
             ORDER BY r.created_at DESC LIMIT 50",
            [$targetId]
        );

        $base = getBaseUrl();
        foreach ($resenas as &$r) {
            if (!empty($r['estudiante_avatar'])) $r['estudiante_avatar'] = $base . '/' . $r['estudiante_avatar'];
        }

        jsonOut(['resenas' => $resenas]);
    }
}
