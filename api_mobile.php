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
        id SERIAL PRIMARY KEY,
        usuario_id INTEGER NOT NULL REFERENCES usuarios(usuarioid) ON DELETE CASCADE,
        token VARCHAR(64) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT NOW(),
        expires_at TIMESTAMP DEFAULT NOW() + INTERVAL '30 days'
    )");
} catch (Exception $_e) {
    // Table existed with wrong schema; recreate it
    $pdo->exec("DROP TABLE IF EXISTS mobile_tokens");
    $pdo->exec("CREATE TABLE mobile_tokens (
        id SERIAL PRIMARY KEY,
        usuario_id INTEGER NOT NULL REFERENCES usuarios(usuarioid) ON DELETE CASCADE,
        token VARCHAR(64) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT NOW(),
        expires_at TIMESTAMP DEFAULT NOW() + INTERVAL '30 days'
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
    ];
}

$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'login':             handleLogin($body);            break;
    case 'register':          handleRegister($body);         break;
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
    case 'payment':           handlePayment($body);          break;
    case 'teacher_dashboard': handleTeacherDashboard();      break;
    case 'create_class':      handleCreateClass($body);      break;
    case 'start_room':        handleStartRoom($body);        break;
    case 'active_rooms':      handleActiveRooms();           break;
    case 'countries':         handleCountries();             break;
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
        jsonOut(['error' => 'Cuenta no verificada. Contacta soporte.'], 403);
    }

    $token = bin2hex(random_bytes(32));
    dbExec("INSERT INTO mobile_tokens (usuario_id, token) VALUES (?, ?)
            ON CONFLICT (token) DO NOTHING", [$user['usuarioid'], $token]);

    jsonOut(['token' => $token, 'user' => formatUser($user)]);
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

    $exists = dbOne("SELECT usuarioid FROM usuarios WHERE email = ?", [$email]);
    if ($exists) jsonOut(['error' => 'Email ya registrado'], 409);

    $hash = password_hash($password, PASSWORD_DEFAULT);

    dbExec(
        "INSERT INTO usuarios (nombre, email, password, rol, pais_id, creditos, verificado, token_verificacion, ultimocontenido, ultimaclase, ultimasala)
         VALUES (?, ?, ?, ?, ?, 100, 1, '', '', '', '')",
        [$nombre, $email, $hash, $rol, $pais_id ?: null]
    );

    $user = dbOne("SELECT * FROM usuarios WHERE email = ?", [$email]);
    if (!$user) jsonOut(['error' => 'Error al crear la cuenta'], 500);

    // Auto-login: create a mobile token so the app can proceed immediately
    $token = bin2hex(random_bytes(32));
    dbExec(
        "INSERT INTO mobile_tokens (usuario_id, token) VALUES (?, ?) ON CONFLICT (token) DO NOTHING",
        [$user['usuarioid'], $token]
    );

    jsonOut(['token' => $token, 'user' => formatUser($user), 'message' => 'Cuenta creada exitosamente.']);
}

function handleProfile(): void {
    $user = getAuthUser();
    jsonOut(['user' => formatUser($user)]);
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
                ROUND(COALESCE(AVG(cp.rating), 4.0)::numeric, 1) AS rating,
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
    if ($search) { $sql .= " AND (cp.titulo ILIKE ? OR u.nombre ILIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    if ($active) { $sql .= " AND s.id IS NOT NULL"; }
    $sql .= " ORDER BY s.activa DESC NULLS LAST, cp.precio ASC LIMIT 50";

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
        "INSERT INTO participantes_sala (sala_id, usuario_id, rol)
         VALUES (?, ?, ?)
         ON CONFLICT (sala_id, usuario_id) DO UPDATE SET activo = true, joined_at = NOW()",
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
        "INSERT INTO participantes_sala (sala_id, usuario_id, rol)
         VALUES (?, ?, 'instructor')
         ON CONFLICT (sala_id, usuario_id) DO UPDATE SET activo = true",
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
