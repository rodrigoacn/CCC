<?php
// ─────────────────────────────────────────────────────────────────────────────
//  SalaApi — front controller for api_sala.php (session + CSRF + dispatch)
// ─────────────────────────────────────────────────────────────────────────────

namespace App;

use App\Controllers\SalaController;

final class SalaApi
{
    private const WRITE_ACTIONS = [
        'join', 'leave', 'chat', 'pay', 'kick_student',
        'approve_spectator', 'reject_spectator', 'end_class', 'start_class',
    ];

    public static function dispatch(): void
    {
        session_start();
        require_once __DIR__ . '/../../db.php';
        require_once __DIR__ . '/../../lib/csrf.php';
        require_once __DIR__ . '/../../lib/BusinessLogic.php';
        require_once __DIR__ . '/../../lib/RedisPoll.php';
        require_once __DIR__ . '/api_helpers.php';
        require_once __DIR__ . '/Controllers/SalaController.php';

        header('Content-Type: application/json');

        // Must be logged in
        if (!isset($_SESSION['usuarioId'])) {
            echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
            exit;
        }

        // CSRF check for state-mutating actions
        $uid    = (int)$_SESSION['usuarioId'];
        $action = $_POST['action'] ?? $_GET['action'] ?? '';

        if (in_array($action, self::WRITE_ACTIONS, true) && !csrf_validate()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'CSRF token invalid']);
            exit;
        }

        switch ($action) {
            case 'join':
            case 'leave':
            case 'pay':
            case 'chat':
            case 'signal':
            case 'approve_spectator':
            case 'end_class':
            case 'reject_spectator':
            case 'kick_student':
                SalaController::$action($uid);
                break;
            case 'messages':
            case 'signals':
            case 'poll_signals':
            case 'get_spectators':
            case 'students':
                SalaController::$action($uid);
                break;
            default:
                echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        }
    }
}
