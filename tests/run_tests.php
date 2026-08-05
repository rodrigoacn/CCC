<?php
error_reporting(E_ALL & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', 1);

$BASE = rtrim($argv[1] ?? 'http://localhost/CCC', '/');
$isLocal = strpos($BASE, 'localhost') !== false || strpos($BASE, '127.0.0.1') !== false;

$STUDENT_EMAIL = 'student@classexpress.app';
$OWNER_EMAIL   = 'rodrigoconejeros1994@gmail.com';
$PASS          = 'v6h470fdz0';

$pass = 0;
$fail = 0;
$skip = 0;

function check(string $name, bool $cond, string $detail = ''): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $name\n"; }
    else { $fail++; echo "  FAIL  $name" . ($detail !== '' ? "  [$detail]" : '') . "\n"; }
}

function http(string $url, array $opts = []): array {
    if (isset($opts['token'])) {
        $sep = strpos($url, '?') === false ? '?' : '&';
        $url = $url . $sep . 'token=' . urlencode($opts['token']);
    }
    $ch = curl_init($url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) { $headers[] = trim($line); return strlen($line); },
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => $opts['follow'] ?? false,
    ]);
    if (isset($opts['json'])) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json'], JSON_UNESCAPED_UNICODE)); }
    if (isset($opts['form'])) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opts['form'])); }
    $reqHeaders = [];
    if (isset($opts['json'])) $reqHeaders[] = 'Content-Type: application/json';
    if (isset($opts['token'])) $reqHeaders[] = 'Authorization: Bearer ' . $opts['token'];
    if ($reqHeaders) curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);
    if (isset($opts['jar'])) { curl_setopt($ch, CURLOPT_COOKIEJAR, $opts['jar']); curl_setopt($ch, CURLOPT_COOKIEFILE, $opts['jar']); }
    $body = curl_exec($ch);
    $res = [
        'code'     => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'location' => (string)(curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?? ''),
        'headers'  => $headers,
        'err'      => curl_error($ch),
    ];
    curl_close($ch);
    $res['body'] = (string)$body;
    $json = json_decode($res['body'], true);
    $res['json'] = is_array($json) ? $json : null;
    return $res;
}

function cookieValue(array $headers, string $name): string {
    foreach ($headers as $h) {
        if (stripos($h, 'Set-Cookie:') === 0 && stripos($h, $name . '=') !== false) {
            $parts = explode(';', substr($h, 11));
            $pair  = explode('=', trim($parts[0]), 2);
            if ($pair[0] === $name) return $pair[1] ?? '';
        }
    }
    return '';
}

function resetRateLimit(): void {
    $dir = sys_get_temp_dir() . '/ce_ratelimit';
    foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
    if (function_exists('getRedis')) {
        $r = @getRedis();
        if ($r) {
            foreach (['login', 'signup'] as $a) {
                foreach (['127.0.0.1', '::1'] as $ip) {
                    $r->del("ratelimit:$a:$ip");
                }
            }
        }
    }
}

echo "Suite funcional de endpoints — target: $BASE\n\n";
if ($isLocal) {
    resetRateLimit();
    echo "  (rate limit local reseteado)\n\n";
}
$jar = tempnam(sys_get_temp_dir(), 'cejar');
$jar2 = tempnam(sys_get_temp_dir(), 'cejar2');

echo "== WEB ==" . "\n";
$r = http("$BASE/login.php");
check('GET login.php responde 200', $r['code'] === 200, "code={$r['code']} err={$r['err']}");

$r = http("$BASE/login.php", ['form' => ['action' => 'signin', 'email' => $STUDENT_EMAIL, 'password' => 'incorrecta99']]);
check('web login con password incorrecta NO redirige', $r['code'] === 200 && ($r['location'] ?? '') === '', "code={$r['code']} loc=" . (string)$r['location']);

$r = http("$BASE/login.php", ['form' => ['action' => 'signin', 'email' => $STUDENT_EMAIL, 'password' => $PASS], 'jar' => $jar]);
$locOk = $r['code'] === 302 && $r['location'] !== null && (strpos($r['location'], 'materias.php') !== false || strpos($r['location'], 'pago.php') !== false);
check('web login valido redirige a la app', $locOk, "code={$r['code']} loc=" . (string)$r['location']);

$r = http("$BASE/menu.php", ['jar' => $jar]);
check('menu.php con sesion responde 200 sin redirect', $r['code'] === 200 && ($r['location'] ?? '') === '', "code={$r['code']} loc=" . (string)$r['location']);

$r = http("$BASE/materias.php", ['jar' => $jar]);
check('materias.php con sesion responde 200 sin redirect', $r['code'] === 200 && ($r['location'] ?? '') === '', "code={$r['code']} loc=" . (string)$r['location']);

$r = http("$BASE/logout.php", ['jar' => $jar]);
check('logout redirige', $r['code'] === 302, "code={$r['code']}");

$r = http("$BASE/");
check('raiz sin sesion redirige a login.php', $r['code'] === 302 && strpos((string)$r['location'], 'login.php') !== false, "code={$r['code']} loc=" . (string)$r['location']);

echo "\n== API" . " — auth ==" . "\n";
$r = http("$BASE/api_mobile.php?action=login", ['json' => ['email' => $STUDENT_EMAIL, 'password' => $PASS]]);
$token = $r['json']['token'] ?? '';
check('api login valido -> 200 con token', $r['code'] === 200 && $token !== '', "code={$r['code']} body=" . substr($r['body'], 0, 200));
check('api login devuelve el email correcto', strtolower($r['json']['user']['email'] ?? '') === $STUDENT_EMAIL, $r['json']['user']['email'] ?? '');
check('api login devuelve cuenta verificada', ($r['json']['user']['verificado'] ?? null) === true);
check('api login devuelve un id de usuario valido', (int)($r['json']['user']['id'] ?? 0) > 0, 'id=' . ($r['json']['user']['id'] ?? ''));

$r = http("$BASE/api_mobile.php?action=login", ['json' => ['email' => $STUDENT_EMAIL, 'password' => 'mala99']]);
check('api login password incorrecta -> 401', $r['code'] === 401 && isset($r['json']['error']), "code={$r['code']}");

$r = http("$BASE/api_mobile.php?action=login", ['json' => ['email' => $STUDENT_EMAIL]]);
check('api login sin password -> 400', $r['code'] === 400, "code={$r['code']}");

$r = http("$BASE/api_mobile.php?action=profile");
check('api profile sin token -> 401', $r['code'] === 401, "code={$r['code']}");

$r = http("$BASE/api_mobile.php?action=profile", ['token' => $token]);
check('api profile con token -> 200', $r['code'] === 200 && isset($r['json']['user']), "code={$r['code']} body=" . substr($r['body'], 0, 160));
check('api profile devuelve el mismo email', strtolower($r['json']['user']['email'] ?? '') === $STUDENT_EMAIL, $r['json']['user']['email'] ?? '');

echo "\n== API" . " — register ==" . "\n";
$r = http("$BASE/api_mobile.php?action=register", ['json' => ['nombre' => 'Test', 'email' => 'no-valido', 'password' => '123456', 'rol' => 'estudiante']]);
check('api register email invalido -> 400', $r['code'] === 400, "code={$r['code']}");

$r = http("$BASE/api_mobile.php?action=register", ['json' => ['nombre' => 'Test', 'email' => uniqid() . '@test.cl', 'password' => '123', 'rol' => 'estudiante']]);
check('api register password corta -> 400', $r['code'] === 400, "code={$r['code']}");

$r = http("$BASE/api_mobile.php?action=register", ['json' => ['email' => $STUDENT_EMAIL, 'password' => 'v6h470fdz0']]);
check('api register sin nombre -> 400', $r['code'] === 400, "code={$r['code']}");

$testEmail = 'test_' . date('YmdHis') . '@test.cl';
$r = http("$BASE/api_mobile.php?action=register", ['json' => ['nombre' => 'Test Funcional', 'email' => $testEmail, 'password' => 'v6h470fdz0', 'rol' => 'estudiante', 'pais_id' => 1]]);
check('api register nuevo -> 200 needs_verification', $r['code'] === 200 && ($r['json']['needs_verification'] ?? false) === true, "code={$r['code']} body=" . substr($r['body'], 0, 200));

$r = http("$BASE/api_mobile.php?action=register", ['json' => ['nombre' => 'Test Funcional', 'email' => $testEmail, 'password' => 'v6h470fdz0', 'rol' => 'estudiante']]);
check('api register email pendiente duplicado -> 409', $r['code'] === 409, "code={$r['code']} body=" . substr($r['body'], 0, 160));

$r = http("$BASE/api_mobile.php?action=login", ['json' => ['email' => $testEmail, 'password' => 'v6h470fdz0']]);
check('api login cuenta no verificada -> 403 NOT_VERIFIED', $r['code'] === 403 && ($r['json']['code'] ?? '') === 'NOT_VERIFIED', "code={$r['code']} body=" . substr($r['body'], 0, 160));

$r = http("$BASE/api_mobile.php?action=register", ['json' => ['nombre' => 'X', 'email' => $STUDENT_EMAIL, 'password' => 'v6h470fdz0', 'rol' => 'estudiante']]);
check('api register email verificado duplicado -> 409', $r['code'] === 409, "code={$r['code']}");

echo "\n== API" . " — catalogo ==" . "\n";
$r = http("$BASE/api_mobile.php?action=subjects");
check('api subjects -> 200 con lista', $r['code'] === 200 && isset($r['json']['subjects']), "code={$r['code']}");
check('api subjects trae materias', count($r['json']['subjects'] ?? []) > 0, "n=" . count($r['json']['subjects'] ?? []));

$r = http("$BASE/api_mobile.php?action=countries");
check('api countries -> 200 con lista', $r['code'] === 200 && isset($r['json']['countries']), "code={$r['code']}");

$r = http("$BASE/api_mobile.php?action=teachers");
check('api teachers -> 200 con lista', $r['code'] === 200 && isset($r['json']['teachers']), "code={$r['code']}");

$r = http("$BASE/api_mobile.php?action=classes&limit=10");
check('api classes -> 200 con lista y total', $r['code'] === 200 && isset($r['json']['classes']) && isset($r['json']['total']), "code={$r['code']} body=" . substr($r['body'], 0, 160));

$r = http("$BASE/api_mobile.php?action=languages");
check('api languages -> 200 con lista', $r['code'] === 200 && isset($r['json']['languages']), "code={$r['code']}");

echo "\n== API" . " — owner/roles ==" . "\n";
$r = http("$BASE/api_mobile.php?action=login", ['json' => ['email' => $OWNER_EMAIL, 'password' => $PASS]]);
$ot = $r['json']['token'] ?? '';
check('api login owner -> 200 con token', $r['code'] === 200 && $ot !== '', "code={$r['code']} body=" . substr($r['body'], 0, 200));

$r = http("$BASE/api_mobile.php?action=switch_role", ['json' => ['target_role' => 'student', 'password' => 'mala99'], 'token' => $ot]);
check('api switch_role con password incorrecta -> 401', $r['code'] === 401, "code={$r['code']}");

$r = http("$BASE/api_mobile.php?action=switch_role", ['json' => ['target_role' => 'student', 'password' => $PASS], 'token' => $ot]);
check('api switch_role valido -> ok o locked', in_array($r['code'], [200, 403], true), "code={$r['code']} body=" . substr($r['body'], 0, 160));

echo "\n== CRIPTOGRAFIA (BD local)" . " ==\n";
if (!$isLocal) {
    echo "  SKIP  (requiere acceso a la BD local)\n";
    $skip++;
} else {
    require __DIR__ . '/../db.php';
    if (!getDB()) {
        echo "  SKIP  (sin conexion a MySQL local)\n";
        $skip++;
    } else {
        $hash = dbOne("SELECT password FROM usuarios WHERE email = ?", [$STUDENT_EMAIL])['password'] ?? '';
        check('hash del estudiante verifica su password', $hash !== '' && password_verify($PASS, $hash));
        check('hash rechaza una password incorrecta', !password_verify('incorrecta99', $hash));
        $ownerHash = dbOne("SELECT password FROM usuarios WHERE email = ?", [$OWNER_EMAIL])['password'] ?? '';
        check('hash del owner verifica su password', $ownerHash !== '' && password_verify($PASS, $ownerHash));

        $r = http("$BASE/login.php", ['form' => ['action' => 'signin', 'email' => $STUDENT_EMAIL, 'password' => $PASS, 'recuerdame' => '1'], 'jar' => $jar2]);
        $cookieToken = cookieValue($r['headers'], 'ce_remember');
        $dbToken = dbOne("SELECT remember_token FROM usuarios WHERE email = ?", [$STUDENT_EMAIL])['remember_token'] ?? '';
        check('login "recuerdame" firma el token con sha256', $cookieToken !== '' && $dbToken === hash('sha256', $cookieToken), "cookie=" . substr($cookieToken, 0, 8) . ".. db=" . substr($dbToken, 0, 8) . "..");
        dbExec("UPDATE usuarios SET remember_token = NULL WHERE email = ?", [$STUDENT_EMAIL]);
        dbExec("DELETE FROM usuarios WHERE email LIKE 'test_%@test.cl' AND verificado = 0");
    }
}

@unlink($jar);
@unlink($jar2);

echo "\n==== RESUMEN ====\n";
echo "PASS: $pass   FAIL: $fail   SKIP: $skip\n";
exit($fail > 0 ? 1 : 0);
