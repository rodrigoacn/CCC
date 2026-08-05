<?php
// ─────────────────────────────────────────────────────────────────────────────
//  web_bootstrap.php — shared helpers for the web (session) pages.
//  Consolidates the remember-me auto-login, auth guard and uid lookup that
//  were previously duplicated across menu.php / index.php / pages.
//  Requires db.php to be loaded first.
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('ce_start_session')) {
    function ce_start_session(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
    }
}

if (!function_exists('ce_remember_autologin')) {
    // Auto-login via remember-me cookie (rotates the token on each hit).
    // Same behavior as the old blocks in menu.php and index.php.
    function ce_remember_autologin(): void {
        if (isset($_SESSION['usuarioId'])) return;
        if (empty($_COOKIE['ce_remember'])) return;

        $token = $_COOKIE['ce_remember'];
        $hash  = hash('sha256', $token);
        $row = dbOne(
            "SELECT usuarioId, nombre, rol, creditos, idioma_preferido FROM usuarios
             WHERE remember_token = :t AND remember_token IS NOT NULL AND eliminado = 0 LIMIT 1",
            ['t' => $hash]
        );
        if ($row) {
            $newToken = bin2hex(random_bytes(32));
            $newHash  = hash('sha256', $newToken);
            dbExec("UPDATE usuarios SET remember_token = :t WHERE usuarioId = :id", ['t'=>$newHash, 'id'=>$row['usuarioId']]);
            $_SESSION['usuarioId'] = (int)$row['usuarioId'];
            $_SESSION['nombre']    = $row['nombre'];
            $_SESSION['rol']       = $row['rol'];
            $_SESSION['creditos']  = (int)($row['creditos'] ?? 0);
            if (!empty($row['idioma_preferido'])) {
                $_SESSION['_lang'] = $row['idioma_preferido'];
                setcookie('ce_lang', $row['idioma_preferido'], time() + 86400 * 30, '/', '', false, false);
            }
            setcookie('ce_remember', $newToken, time() + 30*24*60*60, '/', '', !empty($_SERVER['HTTPS']), true);
        } else {
            // Invalid token — clear cookie
            setcookie('ce_remember', '', time() - 3600, '/', '', !empty($_SERVER['HTTPS']), true);
        }
    }
}

if (!function_exists('ce_require_login')) {
    function ce_require_login(string $redirect = 'login.php'): void {
        if (!isset($_SESSION['usuarioId'])) {
            header('Location: ' . $redirect);
            exit;
        }
    }
}

if (!function_exists('ce_uid')) {
    function ce_uid(): int {
        return (int)($_SESSION['usuarioId'] ?? 0);
    }
}

if (!function_exists('ce_user')) {
    // Current session user as an array (fresh from DB, with cached row fallback).
    function ce_user(): array {
        $uid = ce_uid();
        if (!$uid) return [];
        static $cache = null;
        if ($cache !== null && (int)$cache['usuarioId'] === $uid) return $cache;
        $cache = dbOne("SELECT * FROM usuarios WHERE usuarioId = ?", [$uid]) ?: [];
        return $cache;
    }
}

if (!function_exists('ce_handle_subject_themes')) {
    // Shared POST handler for subject pages: caps theme selection to 5 and
    // redirects to the teacher search with the chosen materia + temas.
    // Mirrors the old per-page block; also validates the CSRF token the same
    // way tecnologia.php (the reference page) always did.
    function ce_handle_subject_themes(int $materiaId): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['temas'])) return;
        require_once __DIR__ . '/../csrf.php';
        csrf_require();
        $temas = array_slice((array)$_POST['temas'], 0, 5);
        $qs = http_build_query(['materia' => $materiaId, 'temas' => implode(',', $temas)]);
        header("Location: profesores.php?$qs");
        exit;
    }
}

if (!function_exists('timeAgo')) {
    // Short relative-time label used by profesores.php ("hace 5 min", "2 h", "3 d").
    function timeAgo(string|int $datetime): string {
        $ts = is_numeric($datetime) ? (int)$datetime : strtotime((string)$datetime);
        $diff = max(0, time() - $ts);
        if ($diff < 60)        return 'ahora';
        if ($diff < 3600)      return floor($diff / 60) . ' min';
        if ($diff < 86400)     return floor($diff / 3600) . ' h';
        if ($diff < 604800)    return floor($diff / 86400) . ' d';
        if ($diff < 2592000)   return floor($diff / 604800) . ' sem';
        if ($diff < 31536000)  return floor($diff / 2592000) . ' mes';
        return floor($diff / 31536000) . ' a';
    }
}
