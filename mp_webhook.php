<?php
// ─────────────────────────────────────────────────────────────────────────────
//  MercadoPago Webhook (IPN) Handler — ClassExpress
//  Receives payment notifications from MercadoPago
// ─────────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/db.php';

// Verify signature if webhook secret is set (recommended for production)
$webhookSecret = getenv('MP_WEBHOOK_SECRET') ?: '';
if ($webhookSecret) {
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $timestamp = $_SERVER['HTTP_X_TIMESTAMP'] ?? '';
    $body = file_get_contents('php://input');
    $expected = hash_hmac('sha256', "{$timestamp}:{$body}", $webhookSecret);
    if (!hash_equals($expected, $signature)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

// Parse notification
$notification = json_decode(file_get_contents('php://input'), true) ?? [];

$type   = $notification['type']   ?? '';
$action = $notification['action'] ?? '';

// We only care about payment notifications
if ($type !== 'payment') {
    http_response_code(200);
    echo json_encode(['ok' => true, 'ignored' => true]);
    exit;
}

require_once __DIR__ . '/lib/MercadoPagoGateway.php';

try {
    $result = MercadoPagoGateway::processWebhook($notification);
    http_response_code(200);
    echo json_encode(['ok' => true, 'processed' => $result !== null]);
} catch (\Exception $e) {
    error_log("MP Webhook error: " . $e->getMessage());
    http_response_code(200); // Return 200 so MP doesn't retry
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
