<?php
// ─────────────────────────────────────────────────────────────────────────────
//  MobileApi — front controller for api_mobile.php
//  CORS + dispatch to domain controllers. JSON output identical to old file.
// ─────────────────────────────────────────────────────────────────────────────

namespace App;

use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\RoomController;
use App\Controllers\SocialController;
use App\Controllers\TeacherController;
use App\Controllers\WalletController;

final class MobileApi
{
    private const ROUTES = [
        'login'                  => [AuthController::class, 'login'],
        'register'               => [AuthController::class, 'register'],
        'resend_verification'    => [AuthController::class, 'resendVerification'],
        'verify_email'           => [AuthController::class, 'verifyEmail'],
        'forgot_password'        => [AuthController::class, 'forgotPassword'],
        'reset_password'         => [AuthController::class, 'resetPassword'],
        'profile'                => [AuthController::class, 'profile'],
        'delete_account'         => [AuthController::class, 'deleteAccount'],
        'switch_role'            => [AuthController::class, 'switchRole'],
        'update_avatar'          => [AuthController::class, 'updateAvatar'],
        'languages'              => [AuthController::class, 'languages'],
        'update_languages'       => [AuthController::class, 'updateLanguages'],
        'set_ui_language'        => [AuthController::class, 'setUILanguage'],

        'subjects'               => [CatalogController::class, 'subjects'],
        'teachers'               => [CatalogController::class, 'teachers'],
        'classes'                => [CatalogController::class, 'classes'],
        'class_detail'           => [CatalogController::class, 'classDetail'],
        'countries'              => [CatalogController::class, 'countries'],

        'join_room'              => [RoomController::class, 'joinRoom'],
        'leave_room'             => [RoomController::class, 'leaveRoom'],
        'room_status'            => [RoomController::class, 'roomStatus'],
        'send_message'           => [RoomController::class, 'sendMessage'],
        'messages'               => [RoomController::class, 'messages'],
        'signal'                 => [RoomController::class, 'signal'],
        'poll_signals'           => [RoomController::class, 'pollSignals'],
        'room_students'          => [RoomController::class, 'roomStudents'],
        'kick_student'           => [RoomController::class, 'kickStudent'],
        'start_room'             => [RoomController::class, 'startRoom'],
        'active_rooms'           => [RoomController::class, 'activeRooms'],
        'session_status'         => [RoomController::class, 'sessionStatus'],
        'rate_session'           => [RoomController::class, 'rateSession'],

        'credits'                => [WalletController::class, 'credits'],
        'topup'                  => [WalletController::class, 'topup'],
        'buy_tokens'             => [WalletController::class, 'buyTokens'],
        'create_checkout'        => [WalletController::class, 'createCheckout'],
        'checkout_status'        => [WalletController::class, 'checkoutStatus'],
        'payment'                => [WalletController::class, 'payment'],
        'withdraw_tokens'        => [WalletController::class, 'withdrawTokens'],
        'withdrawal_history'     => [WalletController::class, 'withdrawalHistory'],
        'admin_withdrawals'      => [WalletController::class, 'adminWithdrawals'],
        'admin_process_withdrawal' => [WalletController::class, 'adminProcessWithdrawal'],

        'teacher_dashboard'      => [TeacherController::class, 'teacherDashboard'],
        'create_class'           => [TeacherController::class, 'createClass'],
        'class_action'           => [TeacherController::class, 'classAction'],

        'friends'                => [SocialController::class, 'friends'],
        'follow'                 => [SocialController::class, 'follow'],
        'unfriend'               => [SocialController::class, 'unfriend'],
        'send_dm'                => [SocialController::class, 'sendDirectMessage'],
        'get_dms'                => [SocialController::class, 'getDirectMessages'],
        'search_people'          => [SocialController::class, 'searchPeople'],
        'user_profile'           => [SocialController::class, 'userProfile'],
        'resenas_profesor'       => [SocialController::class, 'resenasProfesor'],
    ];

    public static function dispatch(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = (
            $origin === 'null' ||
            preg_match('#^https?://localhost(:\d+)?$#', $origin) ||
            $origin === 'https://classexpress.app' ||
            $origin === 'https://classexpress.online' ||
            $origin === 'http://classexpress.online'
        );
        header('Access-Control-Allow-Origin: ' . ($allowed ? $origin : 'http://localhost'));
        if ($allowed) {
            header('Access-Control-Allow-Credentials: true');
        }
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        require_once __DIR__ . '/../../db.php';
        require_once __DIR__ . '/../../lib/BusinessLogic.php';
        require_once __DIR__ . '/api_helpers.php';
        require_once __DIR__ . '/Controllers/AuthController.php';
        require_once __DIR__ . '/Controllers/CatalogController.php';
        require_once __DIR__ . '/Controllers/RoomController.php';
        require_once __DIR__ . '/Controllers/SocialController.php';
        require_once __DIR__ . '/Controllers/TeacherController.php';
        require_once __DIR__ . '/Controllers/WalletController.php';

        $action = $_GET['action'] ?? '';
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        // Allow token in POST body or GET param as fallback for XAMPP/Windows
        if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($body['token'])) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $body['token'];
        }
        if (empty($_SERVER['HTTP_AUTHORIZATION']) && !empty($_GET['token'])) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $_GET['token'];
        }

        if (!isset(self::ROUTES[$action])) {
            jsonOut(['error' => 'Acción no encontrada'], 404);
        }

        [$class, $method] = self::ROUTES[$action];
        $class::$method($body);
    }
}
