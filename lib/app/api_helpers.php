<?php
// ─────────────────────────────────────────────────────────────────────────────
//  Shared JSON-API helpers (moved from api_mobile.php)
//  Loaded only by the API front controllers (lib/app/MobileApi.php, SalaApi.php)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('jsonOut')) {
    function jsonOut(array $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('getAuthUser')) {
    function getAuthUser(): array {
        // Try multiple sources for Authorization header (XAMPP/FastCGI strips it)
        $header = $_SERVER['HTTP_AUTHORIZATION']
               ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
               ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? '') : '')
               ?? '';
        if (!preg_match('/^Bearer (.+)$/', $header, $m)) {
            if (!empty($_GET['token'])) {
                $header = 'Bearer ' . $_GET['token'];
                $m = ['', $_GET['token']];
            } else {
                jsonOut(['error' => 'No autorizado'], 401);
            }
        }
        $token = $m[1];
        $row = dbOne(
            "SELECT u.* FROM usuarios u
             JOIN mobile_tokens t ON t.usuario_id = u.usuarioId
             WHERE t.token = ? AND t.expires_at > NOW()",
            [$token]
        );
        if (!$row) jsonOut(['error' => 'Token inválido o expirado'], 401);
        $row['id'] = $row['usuarioId'] ?? $row['usuarioid'] ?? 0;
        return $row;
    }
}

if (!function_exists('getBaseUrl')) {
    function getBaseUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/CCC';
    }
}

    if (!function_exists('formatUser')) {
    function formatUser(array $u): array {
        $uid = (int)($u['usuarioid'] ?? $u['usuarioId'] ?? $u['id'] ?? 0);
        $avatar = $u['avatar'] ?? '';
        $paisId = (int)($u['pais_id'] ?? 0);
        // Get idiomas
        $idiomas = [];
        if ($uid) {
            $rows = dbAll(
                "SELECT i.nombre FROM usuario_idiomas ui JOIN idiomas i ON i.idiomaId = ui.idiomaId WHERE ui.usuarioId = ?",
                [$uid]
            );
            $idiomas = array_column($rows, 'nombre');
        }
        return [
            'id'         => $uid,
            'nombre'     => $u['nombre'],
            'email'      => $u['email'],
            'username'   => $u['username'] ?? '',
            'rol'        => $u['rol'],
            'creditos'   => (int)$u['creditos'],
            'verificado' => (bool)($u['verificado'] ?? false),
            'avatar'     => $avatar ? getBaseUrl() . '/' . $avatar : '',
            'biografia'  => $u['biografia'] ?? '',
            'pais_id'    => $paisId,
            'idiomas'    => $idiomas,
            'calificacion' => (float)($u['calificacion'] ?? 0),
            'num_resenas'  => (int)($u['num_resenas'] ?? 0),
            'idioma_preferido' => $u['idioma_preferido'] ?? 'es',
            'ultima_materia'   => (int)($u['ultimaMateria'] ?? 0),
            'last_role_switch' => $u['last_role_switch'] ?? null,
        ];
    }
}

if (!function_exists('getPendingPaymentSessionId')) {
    function getPendingPaymentSessionId(int $usuarioId): ?int {
        $row = dbOne(
            "SELECT sesionId FROM sesiones_clase
             WHERE estudianteId = ? AND pagado = 0 AND fin IS NOT NULL
             ORDER BY fin ASC LIMIT 1",
            [$usuarioId]
        );
        return $row ? (int)$row['sesionId'] : null;
    }
}

if (!function_exists('buildVerifyLink')) {
    function buildVerifyLink(string $token): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host . '/verify.php?token=' . urlencode($token);
    }
}

if (!function_exists('sendVerificationEmail')) {
    function sendVerificationEmail(string $email, string $nombre, string $token): bool {
        require_once __DIR__ . '/../../email_helper.php';
        return ceSendVerify($email, $nombre, buildVerifyLink($token));
    }
}
