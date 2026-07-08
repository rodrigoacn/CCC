<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

$pdo = getDB();
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS mobile_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        token VARCHAR(64) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 30 DAY),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioid) ON DELETE CASCADE
    )");
} catch (Exception $_e) {
    // Table existed with wrong schema; recreate it
    $pdo->exec("DROP TABLE IF EXISTS mobile_tokens");
    $pdo->exec("CREATE TABLE mobile_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        token VARCHAR(64) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 30 DAY),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(usuarioid) ON DELETE CASCADE
    )");
}

function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getAuthUser(): array {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer (.+)$/', $header, $m)) {
        jsonOut(['error' => 'No autorizado'], 401);
    }
    $token = $m[1];
    $row = dbOne(
        "SELECT u.* FROM usuarios u
         JOIN mobile_tokens t ON t.usuario_id = u.usuarioid
         WHERE t.token = ? AND t.expires_at > NOW()",
        [$token]
    );
    if (!$row) jsonOut(['error' => 'Token inválido o expirado'], 401);
    return $row;
}

function formatUser(array $u): array {
    return [
        'id'         => (int)($u['usuarioid'] ?? $u['id'] ?? 0),
        'nombre'     => $u['nombre'],
        'email'      => $u['email'],
        'rol'        => $u['rol'],
        'creditos'   => (int)$u['creditos'],
        'verificado' => (bool)($u['verificado'] ?? false),
        'avatar'     => $u['avatar'] ?? '',
    ];
}

function getPendingPaymentSessionId(int $usuarioId): ?int {
    $row = dbOne(
        "SELECT sesionId FROM sesiones_clase
         WHERE estudianteId = ? AND pagado = 0 AND fin IS NOT NULL
         ORDER BY fin ASC LIMIT 1",
        [$usuarioId]
    );
    return $row ? (int)$row['sesionid'] : null;
}

function buildVerifyLink(string $token): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host . '/verify.php?token=' . urlencode($token);
}

function sendVerificationEmail(string $email, string $nombre, string $token): bool {
    require_once __DIR__ . '/email_helper.php';
    return ceSendVerify($email, $nombre, buildVerifyLink($token));
}

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'login':             handleLogin($body);            break;
    case 'register':          handleRegister($body);         break;
    case 'resend_verification': handleResendVerification($body); break;
    case 'verify_email':      handleVerifyEmail($body);      break;
    case 'profile':           handleProfile();               break;
    case 'subjects':          handleSubjects();              break;
    case 'teachers':          handleTeachers();              break;
    case 'classes':           handleClasses();               break;
    case 'class_detail':      handleClassDetail();           break;
    case 'credits':           handleCredits();               break;
    case 'topup':             handleTopup($body);            break;
    case 'join_room':         handleJoinRoom($body);         break;
    case 'leave_room':        handleLeaveRoom($body);        break;
    case 'room_status':       handleRoomStatus();            break;
    case 'send_message':      handleSendMessage($body);      break;
    case 'messages':          handleMessages();              break;
    case 'signal':            handleSignal($body);           break;
    case 'poll_signals':      handlePollSignals();           break;
    case 'payment':           handlePayment($body);          break;
    case 'rate_session':       handleRateSession($body);      break;
    case 'teacher_dashboard': handleTeacherDashboard();      break;
    case 'create_class':      handleCreateClass($body);      break;
    case 'start_room':        handleStartRoom($body);        break;
    case 'active_rooms':      handleActiveRooms();           break;
    case 'countries':         handleCountries();             break;
    case 'delete_account':    handleDeleteAccount($body);    break;
    case 'add_tokens':        handleAddTokens($body);        break;
    case 'withdraw_tokens':   handleWithdrawTokens($body);   break;
    default:                  jsonOut(['error' => 'Acción no encontrada'], 404);
}

function handleLogin(array $body): void {
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    if (!$email || !$password) jsonOut(['error' => 'Email y contraseña requeridos'], 400);

    $user = dbOne("SELECT * FROM usuarios WHERE email = ?", [$email]);
    if (!$user || !password_verify($password, $user['password'])) {
        jsonOut(['error' => 'Credenciales incorrectas'], 401);
    }
    if (empty($user['verificado'])) {
        jsonOut(['error' => 'Cuenta no verificada. Revisa tu correo o solicita un nuevo enlace.', 'code' => 'NOT_VERIFIED'], 403);
    }

    $token = bin2hex(random_bytes(32));
    dbExec("INSERT IGNORE INTO mobile_tokens (usuario_id, token) VALUES (?, ?)", [$user['usuarioid'], $token]);

    jsonOut([
        'token' => $token,
        'user'  => array_merge(formatUser($user), [
            'pendingPaymentSessionId' => getPendingPaymentSessionId((int)$user['usuarioid']),
        ]),
    ]);
}

function handleRegister(array $body): void {
    $nombre   = trim($body['nombre'] ?? '');
    $email    = trim($body['email'] ?? '');
    $password = $body['password'] ?? '';
    $pais_id  = (int)($body['pais_id'] ?? 0);
    $rol      = in_array($body['rol'] ?? '', ['estudiante', 'instructor']) ? $body['rol'] : 'student';

    if (!$nombre || !$email || !$password) jsonOut(['error' => 'Todos los campos son requeridos'], 400);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonOut(['error' => 'Email inválido'], 400);
    if (strlen($password) < 6) jsonOut(['error' => 'La contraseña debe tener al menos 6 caracteres'], 400);

    $exists = dbOne("SELECT usuarioid, verificado FROM usuarios WHERE email = ?", [$email]);
    if ($exists) {
        if ($exists['verificado']) {
            jsonOut(['error' => 'Email ya registrado'], 409);
        }
        jsonOut(['error' => 'Email pendiente de verificación. Revisa tu correo o solicita un nuevo enlace.', 'code' => 'NOT_VERIFIED'], 409);
    }

    $hash  = password_hash($password, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32));

    dbExec(
        "INSERT INTO usuarios (nombre, email, password, rol, pais_id, creditos, verificado, token_verificacion, ultimocontenido, ultimaclase, ultimasala)
         VALUES (?, ?, ?, ?, ?, 100, 0, ?, '', '', '')",
        [$nombre, $email, $hash, $rol, $pais_id ?: null, $token]
    );

    sendVerificationEmail($email, $nombre, $token);

    jsonOut([
        'needs_verification' => true,
        'message' => 'Cuenta creada. Revisa tu correo y verifica tu cuenta antes de iniciar sesión.',
        'email' => $email,
    ]);
}

function handleResendVerification(array $body): void {
    $email = trim($body['email'] ?? '');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['error' => 'Email inválido'], 400);
    }

    $user = dbOne("SELECT usuarioid, nombre, verificado FROM usuarios WHERE email = ?", [$email]);
    if ($user && empty($user['verificado'])) {
        $token = bin2hex(random_bytes(32));
        dbExec("UPDATE usuarios SET token_verificacion = ? WHERE usuarioid = ?", [$token, $user['usuarioid']]);
        sendVerificationEmail($email, $user['nombre'], $token);
    }

    jsonOut(['message' => 'Si el correo está pendiente de verificación, enviamos un nuevo enlace.']);
}

function handleVerifyEmail(array $body): void {
    $token = trim($body['token'] ?? '');
    if (!$token) jsonOut(['error' => 'Token requerido'], 400);

    $user = dbOne("SELECT usuarioid, nombre, verificado FROM usuarios WHERE token_verificacion = ?", [$token]);
    if (!$user) jsonOut(['error' => 'Enlace inválido o expirado'], 400);
    if ($user['verificado']) {
        jsonOut(['message' => 'Tu correo ya estaba verificado. Puedes iniciar sesión.', 'already_verified' => true]);
    }

    dbExec("UPDATE usuarios SET verificado = 1, token_verificacion = '' WHERE usuarioid = ?", [$user['usuarioid']]);
    jsonOut(['message' => 'Correo verificado. Ya puedes iniciar sesión.', 'verified' => true]);
}

function handleProfile(): void {
    $user = getAuthUser();
    jsonOut(['user' => array_merge(formatUser($user), [
        'pendingPaymentSessionId' => getPendingPaymentSessionId((int)$user['usuarioid']),
    ])]);
}

function handleSubjects(): void {
    $subjects = dbAll(
        "SELECT m.*,
            (SELECT COUNT(*) FROM clases_programadas cp WHERE cp.materia_id = m.id AND cp.activa = true) AS clases_activas
         FROM materias m ORDER BY m.nombre"
    );

    $colors = [
        'Matemáticas'       => '#EF4444',
        'Historia'          => '#F59E0B',
        'Literatura'        => '#8B5CF6',
        'Química'           => '#10B981',
        'Biología'          => '#06B6D4',
        'Física'            => '#3B82F6',
        'Geografía'         => '#22C55E',
        'Arte'              => '#EC4899',
        'Educación Física'  => '#F97316',
        'Idiomas'           => '#6366F1',
        'Tecnología'        => '#14B8A6',
    ];
    $icons = [
        'Matemáticas'       => 'calculator',
        'Historia'          => 'book-open',
        'Literatura'        => 'feather',
        'Química'           => 'zap',
        'Biología'          => 'activity',
        'Física'            => 'cpu',
        'Geografía'         => 'map',
        'Arte'              => 'pen-tool',
        'Educación Física'  => 'heart',
        'Idiomas'           => 'globe',
        'Tecnología'        => 'monitor',
    ];

    foreach ($subjects as &$s) {
        $s['color']          = $colors[$s['nombre']] ?? '#5B6EF5';
        $s['icono']          = $icons[$s['nombre']] ?? 'book';
        $s['clases_activas'] = (int)$s['clases_activas'];
    }

    jsonOut(['subjects' => $subjects]);
}

function handleTeachers(): void {
    $sid    = (int)($_GET['subject_id'] ?? 0);
    $params = [];
    $sql    = "SELECT u.id, u.nombre, u.email, u.rol, u.creditos,
                ROUND(COALESCE(AVG(cp.rating), 4.0), 1) AS rating,
                COUNT(DISTINCT cp.id) AS clases_count
               FROM usuarios u
               LEFT JOIN clases_programadas cp ON cp.profesor_id = u.id
               WHERE u.rol = 'instructor'";
    if ($sid) { $sql .= " AND cp.materia_id = ?"; $params[] = $sid; }
    $sql .= " GROUP BY u.id, u.nombre, u.email, u.rol, u.creditos ORDER BY rating DESC, clases_count DESC";

    jsonOut(['teachers' => dbAll($sql, $params)]);
}

function handleClasses(): void {
    $sid     = (int)($_GET['subject_id'] ?? 0);
    $search  = trim($_GET['search'] ?? '');
    $active  = ($_GET['active_only'] ?? '') === 'true';
    $params  = [];

    $sql = "SELECT cp.*, m.nombre AS materia, u.nombre AS profesor,
               s.id AS sala_id, s.activa AS sala_activa
            FROM clases_programadas cp
            JOIN materias m ON m.id = cp.materia_id
            JOIN usuarios u ON u.id = cp.profesor_id
            LEFT JOIN salas s ON s.clase_id = cp.id AND s.activa = true
            WHERE cp.activa = true";

    if ($sid)    { $sql .= " AND cp.materia_id = ?"; $params[] = $sid; }
    if ($search) { $sql .= " AND (cp.titulo LIKE ? OR u.nombre LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($active) { $sql .= " AND s.id IS NOT NULL"; }
    $sql .= " ORDER BY s.activa IS NULL, s.activa DESC, cp.precio ASC LIMIT 50";

    jsonOut(['classes' => dbAll($sql, $params)]);
}

function handleClassDetail(): void {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonOut(['error' => 'ID requerido'], 400);

    $clase = dbOne(
        "SELECT cp.*, m.nombre AS materia, u.nombre AS profesor,
            s.id AS sala_id, s.activa AS sala_activa
         FROM clases_programadas cp
         JOIN materias m ON m.id = cp.materia_id
         JOIN usuarios u ON u.id = cp.profesor_id
         LEFT JOIN salas s ON s.clase_id = cp.id AND s.activa = true
         WHERE cp.id = ?",
        [$id]
    );
    if (!$clase) jsonOut(['error' => 'Clase no encontrada'], 404);
    jsonOut(['clase' => $clase]);
}

function handleCredits(): void {
    $user    = getAuthUser();
    $history = dbAll(
        "SELECT * FROM pagos WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 30",
        [$user['id']]
    );
    jsonOut(['balance' => (int)$user['creditos'], 'history' => $history]);
}

function handleTopup(array $body): void {
    $user   = getAuthUser();
    $amount = (int)($body['amount'] ?? 0);
    if ($amount < 1 || $amount > 1000) jsonOut(['error' => 'Monto inválido (1-1000)'], 400);

    dbExec("UPDATE usuarios SET creditos = creditos + ? WHERE id = ?", [$amount, $user['id']]);
    dbExec("INSERT INTO pagos (usuario_id, monto, descripcion) VALUES (?, ?, ?)",
           [$user['id'], $amount, "Recarga de $amount créditos"]);

    $updated = dbOne("SELECT creditos FROM usuarios WHERE id = ?", [$user['id']]);
    jsonOut(['balance' => (int)$updated['creditos']]);
}

function handleJoinRoom(array $body): void {
    $user   = getAuthUser();
    $sid    = (int)($body['sala_id'] ?? 0);
    if (!$sid) jsonOut(['error' => 'sala_id requerido'], 400);

    $sala = dbOne(
        "SELECT s.*, cp.precio FROM salas s
         JOIN clases_programadas cp ON cp.id = s.clase_id
         WHERE s.id = ? AND s.activa = true",
        [$sid]
    );
    if (!$sala) jsonOut(['error' => 'Sala no encontrada o inactiva'], 404);

    if ($user['rol'] === 'estudiante' && (int)$user['creditos'] < (int)$sala['precio']) {
        jsonOut(['error' => 'Créditos insuficientes'], 402);
    }

    dbExec(
        "INSERT INTO participantes_sala (sala_id, usuario_id, rol, activo, joined_at)
         VALUES (?, ?, ?, true, NOW())
         ON DUPLICATE KEY UPDATE activo = true, joined_at = NOW()",
        [$sid, $user['id'], $user['rol']]
    );

    jsonOut(['sala' => $sala]);
}

function handleLeaveRoom(array $body): void {
    $user = getAuthUser();
    $sid  = (int)($body['sala_id'] ?? 0);
    dbExec(
        "UPDATE participantes_sala SET activo = false, left_at = NOW()
         WHERE sala_id = ? AND usuario_id = ?",
        [$sid, $user['id']]
    );
    jsonOut(['ok' => true]);
}

function handleRoomStatus(): void {
    $user = getAuthUser();
    $sid  = (int)($_GET['sala_id'] ?? 0);

    $sala = dbOne(
        "SELECT s.*, cp.titulo AS clase, cp.precio FROM salas s
         JOIN clases_programadas cp ON cp.id = s.clase_id WHERE s.id = ?",
        [$sid]
    );
    if (!$sala) jsonOut(['error' => 'Sala no encontrada'], 404);

    $participantes = dbAll(
        "SELECT u.id, u.nombre, u.rol FROM participantes_sala p
         JOIN usuarios u ON u.id = p.usuario_id
         WHERE p.sala_id = ? AND p.activo = true",
        [$sid]
    );
    $messages = dbAll(
        "SELECT m.*, u.nombre AS usuario FROM mensajes_chat m
         JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.sala_id = ? ORDER BY m.created_at ASC LIMIT 100",
        [$sid]
    );

    jsonOut(['sala' => $sala, 'participantes' => $participantes, 'messages' => $messages]);
}

function handleSendMessage(array $body): void {
    $user   = getAuthUser();
    $sid    = (int)($body['sala_id'] ?? 0);
    $msg    = trim($body['mensaje'] ?? '');
    if (!$sid || !$msg) jsonOut(['error' => 'Datos requeridos'], 400);

    dbExec("INSERT INTO mensajes_chat (sala_id, usuario_id, mensaje) VALUES (?, ?, ?)",
           [$sid, $user['id'], $msg]);
    $row = dbOne(
        "SELECT m.*, u.nombre AS usuario FROM mensajes_chat m
         JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.sala_id = ? ORDER BY m.id DESC LIMIT 1",
        [$sid]
    );
    jsonOut(['mensaje' => $row]);
}

function handleMessages(): void {
    $user   = getAuthUser();
    $sid    = (int)($_GET['sala_id'] ?? 0);
    $after  = (int)($_GET['after'] ?? 0);

    $sql    = "SELECT m.*, u.nombre AS usuario FROM mensajes_chat m
               JOIN usuarios u ON u.id = m.usuario_id
               WHERE m.sala_id = ?";
    $params = [$sid];
    if ($after) { $sql .= " AND m.id > ?"; $params[] = $after; }
    $sql .= " ORDER BY m.created_at ASC LIMIT 50";

    jsonOut(['messages' => dbAll($sql, $params)]);
}

function handlePayment(array $body): void {
    $user = getAuthUser();
    $sid  = (int)($body['sala_id'] ?? 0);

    $sala = dbOne(
        "SELECT s.*, cp.precio, cp.titulo FROM salas s
         JOIN clases_programadas cp ON cp.id = s.clase_id WHERE s.id = ?",
        [$sid]
    );
    if (!$sala) jsonOut(['error' => 'Sala no encontrada'], 404);
    if ((int)$user['creditos'] < (int)$sala['precio']) jsonOut(['error' => 'Créditos insuficientes'], 402);

    dbExec("UPDATE usuarios SET creditos = creditos - ? WHERE id = ?", [$sala['precio'], $user['id']]);
    dbExec("INSERT INTO pagos (usuario_id, monto, descripcion) VALUES (?, ?, ?)",
           [$user['id'], -(int)$sala['precio'], "Clase: " . $sala['titulo']]);

    $updated = dbOne("SELECT creditos FROM usuarios WHERE id = ?", [$user['id']]);
    jsonOut([
        'ok'                 => true,
        'creditos_restantes' => (int)$updated['creditos'],
        'recibo'             => "Pagaste {$sala['precio']} crédito(s) por «{$sala['titulo']}»",
    ]);
}

function handleRateSession(array $body): void {
    $user = getAuthUser();
    $sala_id = (int)($body['sala_id'] ?? 0);
    $rating = (int)($body['rating'] ?? 0);
    if (!$sala_id || $rating < 1 || $rating > 5) jsonOut(['error' => 'Datos inválidos'], 400);

    // Find the professor for this sala
    $row = dbOne(
        "SELECT cp.profesor_id FROM salas s JOIN clases_programadas cp ON cp.id = s.clase_id WHERE s.id = ?",
        [$sala_id]
    );
    if (!$row) jsonOut(['error' => 'Sala no encontrada'], 404);

    $profId = (int)$row['profesor_id'];
    $prof = dbOne("SELECT calificacion, num_resenas FROM usuarios WHERE id = ?", [$profId]);
    $curAvg = (float)($prof['calificacion'] ?? 0);
    $curCount = (int)($prof['num_resenas'] ?? 0);
    $newCount = $curCount + 1;
    $newAvg = ($curAvg * $curCount + $rating) / max(1, $newCount);

    dbExec("UPDATE usuarios SET calificacion = ?, num_resenas = ? WHERE id = ?", [round($newAvg,2), $newCount, $profId]);
    jsonOut(['ok' => true]);
}

function handleTeacherDashboard(): void {
    $user = getAuthUser();
    if ($user['rol'] !== 'instructor') jsonOut(['error' => 'Solo instructores'], 403);

    $clases = dbAll(
        "SELECT cp.*, m.nombre AS materia, s.id AS sala_id, s.activa AS sala_activa
         FROM clases_programadas cp
         JOIN materias m ON m.id = cp.materia_id
         LEFT JOIN salas s ON s.clase_id = cp.id AND s.activa = true
         WHERE cp.profesor_id = ? ORDER BY cp.id DESC",
        [$user['id']]
    );

    $ganRow = dbOne(
        "SELECT COALESCE(SUM(ABS(p.monto)), 0) AS total
         FROM pagos p
         WHERE p.monto < 0
           AND p.usuario_id IN (
               SELECT DISTINCT ps.usuario_id FROM participantes_sala ps
               JOIN salas s ON s.id = ps.sala_id
               JOIN clases_programadas cp ON cp.id = s.clase_id
               WHERE cp.profesor_id = ?
           )",
        [$user['id']]
    );

    $sesiones = dbAll(
        "SELECT sc.*, cp.titulo AS clase FROM sesiones_clase sc
         JOIN clases_programadas cp ON cp.id = sc.clase_id
         WHERE cp.profesor_id = ? ORDER BY sc.created_at DESC LIMIT 10",
        [$user['id']]
    );

    jsonOut([
        'ganancias' => (float)($ganRow['total'] ?? 0),
        'clases'    => $clases,
        'sesiones'  => $sesiones,
    ]);
}

function handleCreateClass(array $body): void {
    $user       = getAuthUser();
    if ($user['rol'] !== 'instructor') jsonOut(['error' => 'Solo instructores'], 403);

    $titulo     = trim($body['titulo'] ?? '');
    $materia_id = (int)($body['materia_id'] ?? 0);
    $precio     = (float)($body['precio'] ?? 0);
    $descripcion = trim($body['descripcion'] ?? '');
    $duracion   = (int)($body['duracion'] ?? 60);

    if (!$titulo || !$materia_id || $precio <= 0) jsonOut(['error' => 'Datos requeridos'], 400);

    dbExec(
        "INSERT INTO clases_programadas (titulo, materia_id, profesor_id, precio, descripcion, duracion_minutos, activa)
         VALUES (?, ?, ?, ?, ?, ?, true)",
        [$titulo, $materia_id, $user['id'], $precio, $descripcion, $duracion]
    );

    $clase = dbOne(
        "SELECT cp.*, m.nombre AS materia FROM clases_programadas cp
         JOIN materias m ON m.id = cp.materia_id
         WHERE cp.profesor_id = ? ORDER BY cp.id DESC LIMIT 1",
        [$user['id']]
    );
    jsonOut(['clase' => $clase]);
}

function handleStartRoom(array $body): void {
    $user    = getAuthUser();
    if ($user['rol'] !== 'instructor') jsonOut(['error' => 'Solo instructores'], 403);

    $clase_id = (int)($body['clase_id'] ?? 0);
    $clase    = dbOne(
        "SELECT * FROM clases_programadas WHERE id = ? AND profesor_id = ?",
        [$clase_id, $user['id']]
    );
    if (!$clase) jsonOut(['error' => 'Clase no encontrada'], 404);

    dbExec("UPDATE salas SET activa = false WHERE clase_id = ? AND activa = true", [$clase_id]);
    dbExec("INSERT INTO salas (clase_id, activa) VALUES (?, true)", [$clase_id]);

    $sala = dbOne("SELECT * FROM salas WHERE clase_id = ? ORDER BY id DESC LIMIT 1", [$clase_id]);
    dbExec(
        "INSERT INTO participantes_sala (sala_id, usuario_id, rol, activo)
         VALUES (?, ?, 'instructor', true)
         ON DUPLICATE KEY UPDATE activo = true",
        [$sala['id'], $user['id']]
    );

    jsonOut(['sala' => $sala]);
}

function handleActiveRooms(): void {
    $user  = getAuthUser();
    $rooms = dbAll(
        "SELECT s.*, cp.titulo AS clase, cp.precio
         FROM salas s JOIN clases_programadas cp ON cp.id = s.clase_id
         WHERE cp.profesor_id = ? AND s.activa = true",
        [$user['id']]
    );
    jsonOut(['rooms' => $rooms]);
}

function handleCountries(): void {
    $rows = dbAll("SELECT paisid AS id, nombre, codigo_iso AS codigo, moneda, codigo_moneda, simbolo FROM paises ORDER BY nombre");
    jsonOut(['countries' => $rows]);
}

function handleDeleteAccount(array $body): void {
    $user = getAuthUser();
    $password = $body['password'] ?? '';
    
    if (!$password) {
        jsonOut(['error' => 'Contraseña requerida'], 400);
    }
    
    $userData = dbOne("SELECT password FROM usuarios WHERE usuarioid = ?", [(int)$user['usuarioid']]);
    if (!$userData || !password_verify($password, $userData['password'])) {
        jsonOut(['error' => 'Contraseña incorrecta'], 401);
    }
    
    dbExec("DELETE FROM usuarios WHERE usuarioid = ?", [(int)$user['usuarioid']]);
    jsonOut(['ok' => true, 'message' => 'Cuenta eliminada correctamente']);
}

function handleSignal(array $body): void {
    $user   = getAuthUser();
    $salaId = (int)($body['sala_id'] ?? 0);
    $tipo   = in_array($body['tipo'] ?? '', ['offer', 'answer', 'candidate', 'bye'], true)
              ? $body['tipo'] : '';
    $payload = $body['payload'] ?? '';
    $toUid  = (int)($body['to_uid'] ?? 0) ?: null;

    if (!$salaId || !$tipo || $payload === '') {
        jsonOut(['error' => 'Datos de señal requeridos'], 400);
    }

    $inRoom = dbOne(
        "SELECT 1 FROM participantes_sala WHERE sala_id = ? AND usuario_id = ? AND activo = true",
        [$salaId, $user['usuarioid'] ?? $user['id']]
    );
    if (!$inRoom) jsonOut(['error' => 'No estás en esta sala'], 403);

    dbExec(
        "INSERT INTO webrtc_signals (sala_id, from_uid, to_uid, tipo, payload)
         VALUES (?, ?, ?, ?, ?)",
        [$salaId, $user['usuarioid'] ?? $user['id'], $toUid, $tipo, $payload]
    );

    jsonOut(['ok' => true]);
}

function handleAddTokens(array $body): void {
    session_start();
    if (!isset($_SESSION['usuarioId'])) {
        jsonOut(['error' => 'No autenticado'], 401);
    }
    
    $uid = (int)$_SESSION['usuarioId'];
    $tokens = (float)($body['tokens'] ?? 0);
    
    if ($tokens <= 0) {
        jsonOut(['error' => 'Cantidad de tokens inválida'], 400);
    }
    
    // Update user's token balance
    dbExec(
        "UPDATE usuarios SET tokens = tokens + ? WHERE usuarioId = ?",
        [$tokens, $uid]
    );
    
    // Record the token addition
    dbExec(
        "INSERT INTO compras_tokens (usuario_id, cantidad, monto_usd, metodo_pago, created_at)
         VALUES (?, ?, ?, 'clase_terminada', NOW())",
        [$uid, $tokens, $tokens] // 1 token = 1 USD
    );
    
    jsonOut(['ok' => true, 'tokens' => $tokens]);
}

function handleWithdrawTokens(array $body): void {
    session_start();
    if (!isset($_SESSION['usuarioId'])) {
        jsonOut(['error' => 'No autenticado'], 401);
    }
    
    $uid = (int)$_SESSION['usuarioId'];
    $cantidad = (float)($body['cantidad'] ?? 0);
    $cuenta = trim($body['cuenta_bancaria'] ?? '');
    $banco = trim($body['nombre_banco'] ?? '');
    
    // Check if user is a teacher
    $user = dbOne("SELECT rol, tokens FROM usuarios WHERE usuarioId = ?", [$uid]);
    if (!$user || ($user['rol'] === 'estudiante' || $user['rol'] === 'student')) {
        jsonOut(['error' => 'Solo profesores pueden retirar tokens'], 403);
    }
    
    if ($cantidad <= 0) {
        jsonOut(['error' => 'Cantidad inválida'], 400);
    }
    
    if ($cantidad > (float)$user['tokens']) {
        jsonOut(['error' => 'Saldo insuficiente'], 400);
    }
    
    if (empty($cuenta) || empty($banco)) {
        jsonOut(['error' => 'Cuenta bancaria y nombre del banco requeridos'], 400);
    }
    
    // Deduct tokens from user balance
    dbExec(
        "UPDATE usuarios SET tokens = tokens - ? WHERE usuarioId = ?",
        [$cantidad, $uid]
    );
    
    // Create withdrawal record
    dbExec(
        "INSERT INTO retiros_tokens (usuario_id, cantidad, cuenta_bancaria, nombre_banco, estado)
         VALUES (?, ?, ?, ?, 'pendiente')",
        [$uid, $cantidad, $cuenta, $banco]
    );
    
    jsonOut(['ok' => true, 'message' => 'Solicitud de retiro creada']);
}

function handlePollSignals(): void {
    $user    = getAuthUser();
    $salaId  = (int)($_GET['sala_id'] ?? 0);
    $afterId = (int)($_GET['after_id'] ?? 0);
    $uid     = (int)($user['usuarioid'] ?? $user['id']);

    if (!$salaId) jsonOut(['error' => 'sala_id requerido'], 400);

    $rows = dbAll(
        "SELECT signalid AS \"signalId\", from_uid, tipo, payload FROM webrtc_signals
         WHERE sala_id = ? AND signalid > ?
           AND (to_uid IS NULL OR to_uid = ?)
           AND from_uid != ?
         ORDER BY signalid ASC LIMIT 20",
        [$salaId, $afterId, $uid, $uid]
    );

    jsonOut(['signals' => $rows ?: []]);
}
