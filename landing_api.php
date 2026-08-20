<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_helper.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);

// ── Oferta 5000 CLP: checkout sin anuncios por 1 semana ──
if (($input['action'] ?? '') === 'ads_free_checkout') {
    require_once __DIR__ . '/lib/MercadoPagoGateway.php';

    $montoCLP = (int)($input['monto'] ?? 0);
    if ($montoCLP !== 5000) {
        echo json_encode(['error' => 'Monto inválido.']);
        exit;
    }

    $yaActivo = dbOne("SELECT id FROM ads_free_compras WHERE estado='activo' AND valido_hasta > NOW() LIMIT 1");
    if ($yaActivo) {
        echo json_encode(['error' => 'Ya tienes anuncios desactivados.']);
        exit;
    }

    $baseUrl = mpGetBaseUrl();
    $prefData = [
        'items' => [[
            'id'          => 'ce_ads_free',
            'title'       => 'ClassExpress — Sin anuncios por 1 semana',
            'description' => 'Oferta por tiempo limitado: sin anuncios durante 1 semana.',
            'category_id' => 'digital_content',
            'quantity'    => 1,
            'unit_price'  => $montoCLP,
            'currency_id' => 'CLP',
        ]],
        'external_reference'   => 'ads_free_' . bin2hex(random_bytes(8)),
        'statement_descriptor' => MP_STATEMENT_DESCRIPTOR,
        'binary_mode'          => false,
        'back_urls'            => [
            'success' => "{$baseUrl}/mp_success.php",
            'failure' => "{$baseUrl}/mp_failure.php",
            'pending' => "{$baseUrl}/mp_pending.php",
        ],
        'notification_url' => "{$baseUrl}/mp_webhook.php",
    ];
    if (strpos($baseUrl, 'https://') === 0) {
        $prefData['auto_return'] = 'approved';
    }

    try {
        MercadoPagoGateway::init();
        $response = \MercadoPago\SDK::post('/checkout/preferences', ['json_data' => $prefData]);
        if ($response['code'] >= 200 && $response['code'] < 300) {
            $prefResult = $response['body'] ?? $response;
            $preferenceId = $prefResult['id'] ?? '';
            $initPoint    = $prefResult['init_point'] ?? '';

            dbExec(
                "INSERT INTO checkout_sessions
                    (usuario_id, type, quantity, amount_usd, amount_local, currency,
                     preference_id, external_reference, status)
                 VALUES (NULL, 'ads_free', 1, ?, ?, 'CLP', ?, ?, 'pending')",
                [$montoCLP / 950, $montoCLP, $preferenceId, $prefData['external_reference']]
            );

            echo json_encode([
                'checkout_url'  => $initPoint,
                'preference_id' => $preferenceId,
            ]);
        } else {
            $errorMsg = $response['body']['message'] ?? $response['body'] ?? 'Unknown error';
            echo json_encode(['error' => 'MercadoPago error: ' . json_encode($errorMsg)]);
        }
    } catch (\Throwable $e) {
        echo json_encode(['error' => 'Error al crear el pago: ' . $e->getMessage()]);
    }
    exit;
}

$email = trim($input['email'] ?? '');
$rol   = $input['rol'] ?? '';

// Validate
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Correo electrónico no válido.']);
    exit;
}

if (!in_array($rol, ['estudiante', 'instructor'], true)) {
    echo json_encode(['error' => 'Rol no válido.']);
    exit;
}

// Owner emergency access: entering the owner access email in the signup form
// unlocks the login page for this session (bypasses the IP allowlist).
$ownerAccessEmail = strtolower(trim(getenv('LOGIN_OWNER_ACCESS_EMAIL') ?: ''));
if ($ownerAccessEmail !== '' && strtolower(trim($email)) === $ownerAccessEmail) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['ce_emergency'] = true;
    echo json_encode([
        'ok'       => true,
        'redirect' => 'login.php',
        'message'  => 'Acceso desbloqueado. Redirigiendo al login...'
    ]);
    exit;
}

// Check if email exists in usuarios table
$db = getDB();
if (!$db) {
    echo json_encode(['error' => 'Error de conexión. Intenta de nuevo.']);
    exit;
}

$user = dbOne(
    "SELECT usuarioId, nombre, email FROM usuarios WHERE email = :email LIMIT 1",
    ['email' => $email]
);

if (!$user) {
    echo json_encode(['error' => 'Este correo no existe en nuestros registros. Crea tu cuenta primero en la plataforma.']);
    exit;
}

// Check if already pre-registered
$existing = dbOne(
    "SELECT id FROM landing_preregistros WHERE email = :email LIMIT 1",
    ['email' => $email]
);

if ($existing) {
    // Already registered, just return current counts
    $counts = getCounts();
    echo json_encode([
        'ok' => true,
        'message' => 'Ya estás registrado. ¡Pronto te contactaremos!',
        'students' => $counts['students'],
        'teachers' => $counts['teachers']
    ]);
    exit;
}

// Insert pre-registration
dbExec(
    "INSERT INTO landing_preregistros (email, rol) VALUES (:email, :rol)",
    ['email' => $email, 'rol' => $rol]
);

// Get updated counts
$counts = getCounts();

// Send thank-you email via Brevo
$nombre = $user['nombre'] ?? 'Usuario';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl  = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'classexpress.online');

sendThankYouEmail($email, $nombre, $rol, $baseUrl);

    echo json_encode([
        'ok' => true,
        'message' => '¡Gracias, ' . htmlspecialchars($nombre) . '! Te avisaremos cuando ClassExpress esté listo.',
        'students' => $counts['students'],
        'teachers' => $counts['teachers']
    ]);
exit;

function getCounts(): array {
    $baseStudents = 154;
    $baseTeachers = 201;
    $extraStudents = 0;
    $extraTeachers = 0;

    $rows = dbAll("SELECT rol, COUNT(*) AS cnt FROM landing_preregistros GROUP BY rol");
    foreach ($rows as $r) {
        if ($r['rol'] === 'estudiante') $extraStudents = (int)$r['cnt'];
        if ($r['rol'] === 'instructor') $extraTeachers = (int)$r['cnt'];
    }

    return [
        'students' => $baseStudents + $extraStudents,
        'teachers' => $baseTeachers + $extraTeachers
    ];
}

function sendThankYouEmail(string $email, string $nombre, string $rol, string $baseUrl): void {
    $rolLabel = $rol === 'estudiante' ? 'estudiante' : 'profesor';
    $currentYear = date('Y');
    $subject  = '¡Gracias por tu interés en ClassExpress!';

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f4f6fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<div style="max-width:560px;margin:0 auto;padding:40px 24px;">
    <div style="text-align:center;margin-bottom:32px;">
        <h1 style="font-size:28px;color:#66ddbd;margin:0;">ClassExpress</h1>
    </div>
    <div style="background:#ffffff;border:1px solid #dbe2ee;border-radius:16px;padding:32px;">
        <h2 style="color:#1e293b;font-size:22px;margin:0 0 16px;">¡Hola {$nombre}!</h2>
        <p style="color:#64748b;font-size:16px;line-height:1.6;margin:0 0 20px;">
            Gracias por registrarte como <strong style="color:#66ddbd;">{$rolLabel}</strong> en ClassExpress.
        </p>
        <p style="color:#64748b;font-size:16px;line-height:1.6;margin:0 0 20px;">
            Estamos preparando todo para el lanzamiento. Serás de los primeros en acceder a nuestra plataforma de
            <strong style="color:#1e293b;">clases particulares en tiempo real</strong> por videoconferencia.
        </p>
        <div style="background:#eef1f8;border-radius:12px;padding:20px;margin:20px 0;">
            <p style="color:#64748b;font-size:14px;margin:0 0 8px;">Lo que recibirás al lanzarte:</p>
            <ul style="color:#1e293b;font-size:14px;line-height:2;margin:0;padding-left:20px;">
                <li>100 créditos de bienvenida</li>
                <li>Acceso a todas las materias</li>
                <li>Videoconferencia HD en vivo</li>
                <li>Chat en tiempo real</li>
            </ul>
        </div>
        <p style="color:#64748b;font-size:16px;line-height:1.6;margin:20px 0 0;">
            Te contactaremos pronto con más novedades.
        </p>
    </div>
    <div style="text-align:center;padding:24px 0;">
        <p style="color:#94a3b8;font-size:12px;margin:0;">
            &copy; {$currentYear} ClassExpress — Bunny Software E.I.R.L.
        </p>
    </div>
</div>
</body>
</html>
HTML;

    // Use Brevo
    $apiKey = getenv('BREVO_API_KEY') ?: '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';

    $payload = json_encode([
        'sender'    => ['name' => 'ClassExpress', 'email' => 'noreply@classexpress.online'],
        'to'        => [['email' => $email, 'name' => $nombre]],
        'subject'   => $subject,
        'htmlContent' => $html,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}
