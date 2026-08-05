<?php
// ─────────────────────────────────────────────────────────────────────────────
//  AuthController — handlers moved verbatim from api_mobile.php (Auth domain)
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Controllers;

final class AuthController
{
    public static function login(array $body): void {
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
        dbExec("INSERT IGNORE INTO mobile_tokens (usuario_id, token) VALUES (?, ?)", [$user['usuarioId'], $token]);

        jsonOut([
            'token' => $token,
            'user'  => array_merge(formatUser($user), [
                'pendingPaymentSessionId' => getPendingPaymentSessionId((int)$user['usuarioId']),
            ]),
        ]);
    }

    public static function register(array $body): void {
        $nombre   = trim($body['nombre'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        $pais_id  = (int)($body['pais_id'] ?? 0);
        $rol      = in_array($body['rol'] ?? '', ['estudiante', 'instructor']) ? $body['rol'] : 'student';

        if (!$nombre || !$email || !$password) jsonOut(['error' => 'Todos los campos son requeridos'], 400);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonOut(['error' => 'Email inválido'], 400);
        if (strlen($password) < 6) jsonOut(['error' => 'La contraseña debe tener al menos 6 caracteres'], 400);

        $exists = dbOne("SELECT usuarioId, verificado FROM usuarios WHERE email = ?", [$email]);
        if ($exists) {
            if ($exists['verificado']) {
                jsonOut(['error' => 'Email ya registrado'], 409);
            }
            jsonOut(['error' => 'Email pendiente de verificación. Revisa tu correo o solicita un nuevo enlace.', 'code' => 'NOT_VERIFIED'], 409);
        }

        $hash  = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));

        $baseUser = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', strstr($email, '@', true)));
        if (strlen($baseUser) < 3) $baseUser = 'usuario';
        $username = $baseUser;
        $suffix = 1;
        while (dbOne("SELECT usuarioId FROM usuarios WHERE username = ?", [$username])) {
            $username = $baseUser . ($suffix++);
        }

        $nuevoId = dbExec(
            "INSERT INTO usuarios (nombre, email, username, password, rol, pais_id, creditos, verificado, token_verificacion, ultimoContenido, ultimaClase, ultimaSala)
             VALUES (?, ?, ?, ?, ?, ?, 100, 0, ?, '', '', '')",
            [$nombre, $email, $username, $hash, $rol, $pais_id ?: null, $token]
        );

        // Save languages
        $idiomas = $body['idiomas'] ?? [];
        if (!empty($idiomas) && is_array($idiomas)) {
            $stmt = getDB()->prepare("INSERT IGNORE INTO usuario_idiomas (usuarioId, idiomaId) VALUES (?, ?)");
            foreach ($idiomas as $iid) {
                $stmt->execute([$nuevoId, (int)$iid]);
            }
        }

        sendVerificationEmail($email, $nombre, $token);

        jsonOut([
            'needs_verification' => true,
            'message' => 'Cuenta creada. Revisa tu correo y verifica tu cuenta antes de iniciar sesión.',
            'email' => $email,
        ]);
    }

    public static function resendVerification(array $body): void {
        $email = trim($body['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonOut(['error' => 'Email inválido'], 400);
        }

        $user = dbOne("SELECT usuarioid, nombre, verificado FROM usuarios WHERE email = ?", [$email]);
        if ($user && empty($user['verificado'])) {
            $token = bin2hex(random_bytes(32));
            dbExec("UPDATE usuarios SET token_verificacion = ? WHERE usuarioid = ?", [$token, $user['usuarioId']]);
            sendVerificationEmail($email, $user['nombre'], $token);
        }

        jsonOut(['message' => 'Si el correo está pendiente de verificación, enviamos un nuevo enlace.']);
    }

    public static function verifyEmail(array $body): void {
        $token = trim($body['token'] ?? '');
        if (!$token) jsonOut(['error' => 'Token requerido'], 400);

        $user = dbOne("SELECT usuarioid, nombre, verificado FROM usuarios WHERE token_verificacion = ?", [$token]);
        if (!$user) jsonOut(['error' => 'Enlace inválido o expirado'], 400);
        if ($user['verificado']) {
            jsonOut(['message' => 'Tu correo ya estaba verificado. Puedes iniciar sesión.', 'already_verified' => true]);
        }

        dbExec("UPDATE usuarios SET verificado = 1, token_verificacion = '' WHERE usuarioid = ?", [$user['usuarioId']]);
        jsonOut(['message' => 'Correo verificado. Ya puedes iniciar sesión.', 'verified' => true]);
    }

    public static function forgotPassword(array $body): void {
        $email = trim($body['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonOut(['error' => 'Email inválido'], 400);
        }

        $row = dbOne("SELECT usuarioId, nombre, verificado FROM usuarios WHERE email = ?", [$email]);
        if ($row && $row['verificado']) {
            $token  = bin2hex(random_bytes(32));
            $expiry = time() + 3600;
            dbExec(
                "UPDATE usuarios SET reset_token = ?, reset_token_expiry = ? WHERE usuarioId = ?",
                [$token, $expiry, $row['usuarioId']]
            );

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $link     = $protocol . '://' . $host . '/reset_password.php?token=' . urlencode($token);

            require_once __DIR__ . '/../../../email_helper.php';
            ceSendReset($email, $row['nombre'], $link);
        }

        jsonOut(['message' => 'Si ese correo está registrado, recibirás un enlace para restablecer tu contraseña.']);
    }

    public static function resetPassword(array $body): void {
        $token    = trim($body['token'] ?? '');
        $password = $body['password'] ?? '';
        $confirm  = $body['confirm'] ?? '';

        if (!$token) jsonOut(['error' => 'Token requerido'], 400);

        $row = dbOne(
            "SELECT usuarioId, reset_token_expiry FROM usuarios WHERE reset_token = ?",
            [$token]
        );
        if (!$row) jsonOut(['error' => 'Enlace inválido o ya utilizado'], 400);
        if ((int)$row['reset_token_expiry'] < time()) {
            jsonOut(['error' => 'El enlace ha expirado. Solicita uno nuevo.'], 400);
        }

        if (strlen($password) < 6) {
            jsonOut(['error' => 'La contraseña debe tener al menos 6 caracteres'], 400);
        }
        if ($password !== $confirm) {
            jsonOut(['error' => 'Las contraseñas no coinciden'], 400);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        dbExec(
            "UPDATE usuarios SET password = ?, reset_token = '', reset_token_expiry = 0 WHERE usuarioId = ?",
            [$hash, $row['usuarioId']]
        );
        jsonOut(['message' => 'Tu contraseña ha sido actualizada correctamente.']);
    }

    public static function profile(): void {
        $user = getAuthUser();
        jsonOut(['user' => array_merge(formatUser($user), [
            'pendingPaymentSessionId' => getPendingPaymentSessionId((int)$user['usuarioId']),
        ])]);
    }

    public static function deleteAccount(array $body): void {
        $user = getAuthUser();
        $password = $body['password'] ?? '';

        if (!$password) {
            jsonOut(['error' => 'Contraseña requerida'], 400);
        }

        $userData = dbOne("SELECT password FROM usuarios WHERE usuarioId = ?", [(int)$user['usuarioId']]);
        if (!$userData || !password_verify($password, $userData['password'])) {
            jsonOut(['error' => 'Contraseña incorrecta'], 401);
        }

        dbExec("DELETE FROM usuarios WHERE usuarioId = ?", [(int)$user['usuarioId']]);
        jsonOut(['ok' => true, 'message' => 'Cuenta eliminada correctamente']);
    }

    public static function switchRole(array $body): void {
        $user = getAuthUser();
        $password = $body['password'] ?? '';
        $targetRole = $body['target_role'] ?? '';

        if (!$password) {
            jsonOut(['error' => 'Password required'], 400);
        }
        if (!in_array($targetRole, ['student', 'teacher'])) {
            jsonOut(['error' => 'Invalid target role'], 400);
        }

        $userData = dbOne("SELECT password, rol, last_role_switch FROM usuarios WHERE usuarioId = ?", [(int)$user['usuarioId']]);
        if (!$userData || !password_verify($password, $userData['password'])) {
            jsonOut(['error' => 'Wrong password'], 401);
        }

        if (!in_array($userData['rol'], ['both', 'instructor', 'instructor_pendiente'])) {
            jsonOut(['error' => 'Cannot switch role'], 400);
        }

        if ($userData['last_role_switch']) {
            $lastSwitch = strtotime($userData['last_role_switch']);
            $hoursSince = floor((time() - $lastSwitch) / 3600);
            if ($hoursSince < 24) {
                $remaining = 24 - $hoursSince;
                jsonOut(['error' => 'locked', 'hours' => $remaining, 'days' => 1, 'message' => "Locked for {$remaining} hours"], 403);
            }
        }

        dbExec("UPDATE usuarios SET last_role_switch = NOW() WHERE usuarioId = ?", [(int)$user['usuarioId']]);
        jsonOut(['ok' => true, 'message' => 'Role switched']);
    }

    public static function updateAvatar(): void {
        $user = getAuthUser();
        $uid  = (int)($user['usuarioId'] ?? $user['id']);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = $body['avatar'] ?? '';

        if (!$data) jsonOut(['error' => 'No se recibió la imagen'], 400);

        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $data, $m)) {
            jsonOut(['error' => 'Formato inválido. Usa data:image/...;base64,...'], 400);
        }

        $extMap = ['jpeg' => 'jpg', 'jpg' => 'jpg', 'png' => 'png', 'gif' => 'gif', 'webp' => 'webp'];
        $ext    = $extMap[$m[1]] ?? null;
        if (!$ext) jsonOut(['error' => 'Solo se permiten JPG, PNG, GIF y WEBP'], 400);

        $decoded = base64_decode($m[2], true);
        if ($decoded === false || strlen($decoded) > 5 * 1024 * 1024) {
            jsonOut(['error' => 'Imagen inválida o muy grande (máx. 5MB)'], 400);
        }

        $dir = __DIR__ . '/../../../uploads/avatars';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $name = 'avatar_' . $uid . '_' . time() . '.' . $ext;
        $dest = $dir . '/' . $name;

        if (file_put_contents($dest, $decoded) === false) {
            jsonOut(['error' => 'Error al guardar la imagen'], 500);
        }

        $path = 'uploads/avatars/' . $name;
        dbExec("UPDATE usuarios SET avatar = ? WHERE usuarioId = ?", [$path, $uid]);

        jsonOut(['ok' => true, 'avatar' => getBaseUrl() . '/' . $path]);
    }

    public static function languages(): void {
        $idiomas = dbAll("SELECT idiomaId AS id, nombre FROM idiomas ORDER BY nombre ASC");
        jsonOut(['languages' => $idiomas]);
    }

    public static function updateLanguages(array $body): void {
        $user = getAuthUser();
        $idiomas = $body['idiomas'] ?? [];
        if (!is_array($idiomas)) $idiomas = [];
        getDB()->prepare("DELETE FROM usuario_idiomas WHERE usuarioId = ?")->execute([$user['id']]);
        if (!empty($idiomas)) {
            $stmt = getDB()->prepare("INSERT IGNORE INTO usuario_idiomas (usuarioId, idiomaId) VALUES (?, ?)");
            foreach ($idiomas as $iid) {
                $stmt->execute([$user['id'], (int)$iid]);
            }
        }
        jsonOut(['ok' => true]);
    }

    public static function setUILanguage(array $body): void {
        $user = getAuthUser();
        $lang = $body['lang'] ?? '';
        $valid = ['es','en','fr','de','pt','it','zh','ja','ru','ar','hi','ko'];
        if (!in_array($lang, $valid)) jsonOut(['error' => 'Invalid language code'], 400);
        getDB()->prepare("UPDATE usuarios SET idioma_preferido = ? WHERE usuarioId = ?")->execute([$lang, $user['id']]);
        jsonOut(['ok' => true, 'lang' => $lang]);
    }
}
