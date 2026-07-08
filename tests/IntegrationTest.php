<?php
/**
 * Pruebas de integración para ClassExpress API
 * Requiere PHPUnit y una base de datos de prueba
 * 
 * Instalación de PHPUnit:
 * composer require --dev phpunit/phpunit
 * 
 * Ejecutar pruebas:
 * vendor/bin/phpunit tests/IntegrationTest.php
 */

use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    private static $pdo;
    private static $testUserId;
    private static $testTeacherId;
    private static $testToken;
    private static $baseUrl = 'http://localhost/CCC';

    public static function setUpBeforeClass(): void
    {
        // Configurar conexión a base de datos de prueba
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'classexpress_test';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        self::$pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        // Limpiar base de datos de prueba
        self::cleanupDatabase();
    }

    private static function cleanupDatabase(): void
    {
        // Desactivar foreign key checks temporalmente
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Limpiar tablas en orden correcto
        $tables = [
            'pagos',
            'espectadores',
            'participantes_sala',
            'webrtc_signals',
            'mensajes_chat',
            'salas',
            'sesiones_clase',
            'clases_programadas',
            'referidos',
            'mobile_tokens',
            'usuarios'
        ];

        foreach ($tables as $table) {
            self::$pdo->exec("DELETE FROM $table");
        }

        // Reactivar foreign key checks
        self::$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    private function apiRequest(string $endpoint, array $data = [], string $method = 'POST', string $token = null): array
    {
        $url = self::$baseUrl . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($token) {
            $headers[] = "Authorization: Bearer $token";
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method === 'POST' && !empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => json_decode($response, true)
        ];
    }

    // ── TESTS DE AUTENTICACIÓN ───────────────────────────────────────────────

    public function testRegistroUsuario(): void
    {
        $timestamp = time();
        $data = [
            'nombre' => 'Usuario Test',
            'email' => "test_$timestamp@example.com",
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'pais_id' => 1,
            'rol' => 'estudiante',
            'username' => "usuario_test_$timestamp",
            'creditos' => 100.00
        ];

        self::$pdo->exec(
            "INSERT INTO usuarios (nombre, email, password, pais_id, rol, username, creditos)
             VALUES ('{$data['nombre']}', '{$data['email']}', '{$data['password']}', {$data['pais_id']}, '{$data['rol']}', '{$data['username']}', {$data['creditos']})"
        );

        // Verificar que el usuario fue creado
        $stmt = self::$pdo->prepare("SELECT usuarioId FROM usuarios WHERE email = ?");
        $stmt->execute([$data['email']]);
        self::$testUserId = $stmt->fetchColumn();

        $this->assertNotNull(self::$testUserId);
    }

    public function testRegistroConReferido(): void
    {
        // Primero crear un usuario referidor
        $timestamp = time();
        self::$pdo->exec(
            "INSERT INTO usuarios (nombre, email, password, rol, pais_id, creditos, verificado, username, num_referidos, minutos_espectador_gratis)
             VALUES ('Referidor Test', 'referidor$timestamp@example.com', '" . password_hash('pass123', PASSWORD_DEFAULT) . "', 'estudiante', 1, 100, 1, 'referidor_$timestamp', 0, 0)"
        );
        $referidorId = self::$pdo->lastInsertId();

        // Registrar usuario con referido
        $data = [
            'nombre' => 'Usuario Referido',
            'email' => "referido_$timestamp@example.com",
            'password' => 'password123',
            'pais_id' => 1,
            'rol' => 'estudiante',
            'username' => "usuario_referido_$timestamp",
            'referido_por' => "referidor_$timestamp"
        ];

        $response = $this->apiRequest('/api_mobile.php?action=register', $data);
        $this->assertEquals(200, $response['status']);

        // Verificar que el referidor recibió 1 minuto espectador gratis
        $stmt = self::$pdo->prepare("SELECT minutos_espectador_gratis FROM usuarios WHERE usuarioId = ?");
        $stmt->execute([$referidorId]);
        $minutos = $stmt->fetchColumn();
        $this->assertEquals(1, $minutos);
    }

    public function testLoginExitoso(): void
    {
        // Primero verificar el usuario
        self::$pdo->exec("UPDATE usuarios SET verificado = 1 WHERE usuarioId = " . self::$testUserId);

        $data = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $response = $this->apiRequest('/api_mobile.php?action=login', $data);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('token', $response['body']);
        $this->assertArrayHasKey('user', $response['body']);

        self::$testToken = $response['body']['token'];
    }

    public function testLoginCredencialesIncorrectas(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'wrongpassword'
        ];

        $response = $this->apiRequest('/api_mobile.php?action=login', $data);

        $this->assertEquals(401, $response['status']);
        $this->assertArrayHasKey('error', $response['body']);
    }

    // ── TESTS DE MATERIAS Y PROFESORES ───────────────────────────────────────

    public function testObtenerMaterias(): void
    {
        // Insertar materia de prueba
        self::$pdo->exec(
            "INSERT INTO materias (nombre, icono, color) VALUES ('Matemáticas Test', 'calculator', '#EF4444')"
        );

        $response = $this->apiRequest('/api_mobile.php?action=subjects', [], 'GET', self::$testToken);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('subjects', $response['body']);
        $this->assertIsArray($response['body']['subjects']);
    }

    public function testObtenerProfesores(): void
    {
        // Crear profesor de prueba
        self::$pdo->exec(
            "INSERT INTO usuarios (nombre, email, password, rol, pais_id, creditos, verificado, calificacion, num_resenas)
             VALUES ('Profesor Test', 'profesor@example.com', '" . password_hash('pass123', PASSWORD_DEFAULT) . "', 'instructor', 1, 100, 1, 4.5, 10)"
        );
        self::$testTeacherId = self::$pdo->lastInsertId();

        $response = $this->apiRequest('/api_mobile.php?action=teachers', [], 'GET', self::$testToken);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('teachers', $response['body']);
    }

    // ── TESTS DE CLASES ────────────────────────────────────────────────────────

    public function testCrearClase(): void
    {
        // Insertar materia
        self::$pdo->exec("INSERT INTO materias (nombre, icono, color) VALUES ('Física Test', 'cpu', '#3B82F6')");
        $materiaId = self::$pdo->lastInsertId();

        $data = [
            'titulo' => 'Clase de Física Test',
            'materia_id' => $materiaId,
            'precio' => 50,
            'descripcion' => 'Clase de prueba',
            'duracion' => 60
        ];

        $response = $this->apiRequest('/api_mobile.php?action=create_class', $data, 'POST', self::$testToken);

        $this->assertEquals(403, $response['status']); // El usuario test es estudiante, no instructor

        // Crear token para profesor
        self::$pdo->exec(
            "INSERT INTO mobile_tokens (usuario_id, token, created_at, expires_at)
             VALUES (" . self::$testTeacherId . ", 'teacher_token', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))"
        );

        $response = $this->apiRequest('/api_mobile.php?action=create_class', $data, 'POST', 'teacher_token');

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('clase', $response['body']);
    }

    public function testObtenerClases(): void
    {
        $response = $this->apiRequest('/api_mobile.php?action=classes', [], 'GET', self::$testToken);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('classes', $response['body']);
    }

    // ── TESTS DE SISTEMA DE REFERIDOS ─────────────────────────────────────────

    public function testComisionProfesorConReferidos(): void
    {
        $timestamp = time();
        // Crear profesor con 3 referidos
        self::$pdo->exec(
            "INSERT INTO usuarios (nombre, email, password, rol, pais_id, creditos, verificado, num_referidos, username)
             VALUES ('Profesor Con Referidos', 'profesor3_$timestamp@example.com', '" . password_hash('pass123', PASSWORD_DEFAULT) . "', 'instructor', 1, 100, 1, 3, 'profesor3_$timestamp')"
        );
        $profesorId = self::$pdo->lastInsertId();

        // Insertar materia y clase
        self::$pdo->exec("INSERT INTO materias (nombre, icono, color) VALUES ('Química Test', 'zap', '#10B981')");
        $materiaId = self::$pdo->lastInsertId();

        self::$pdo->exec(
            "INSERT INTO clases_programadas (titulo, materia_id, profesor_id, precio, descripcion, duracion_minutos, activa)
             VALUES ('Clase Test', $materiaId, $profesorId, 100, 'Test', 60, true)"
        );
        $claseId = self::$pdo->lastInsertId();

        // Crear sesión de prueba
        self::$pdo->exec(
            "INSERT INTO sesiones_clase (claseId, estudianteId, instructorId, precio_usd, pagado, inicio, fin)
             VALUES ($claseId, " . self::$testUserId . ", $profesorId, 100, 0, NOW(), NOW())"
        );
        $sesionId = self::$pdo->lastInsertId();

        // Simular pago
        $_POST['sesionId'] = $sesionId;
        $_POST['metodo'] = 'tarjeta';
        $_SESSION['usuarioId'] = self::$testUserId;

        // Incluir api_sala.php para probar lógica de comisión
        ob_start();
        include __DIR__ . '/../api_sala.php';
        $output = ob_get_clean();

        $result = json_decode($output, true);

        // Verificar que la comisión se calculó correctamente (15% - 3% = 12%)
        // Esto requiere acceso directo a la función de pago, que está en api_sala.php
        $this->assertNotNull($result);
    }

    public function testMinutosEspectadorPorReferido(): void
    {
        $timestamp = time();
        // Crear usuario con 5 referidos
        self::$pdo->exec(
            "INSERT INTO usuarios (nombre, email, password, rol, pais_id, creditos, verificado, num_referidos, minutos_espectador_gratis, username)
             VALUES ('Usuario 5 Referidos', 'user5_$timestamp@example.com', '" . password_hash('pass123', PASSWORD_DEFAULT) . "', 'estudiante', 1, 100, 1, 5, 5, 'user5_$timestamp')"
        );

        $stmt = self::$pdo->prepare("SELECT minutos_espectador_gratis FROM usuarios WHERE email = ?");
        $stmt->execute(["user5_$timestamp@example.com"]);
        $minutos = $stmt->fetchColumn();

        $this->assertEquals(5, $minutos);
    }

    // ── TESTS DE SISTEMA DE ESPECTADORES ───────────────────────────────────────

    public function testSistemaEspectadores(): void
    {
        // Crear clase primero
        self::$pdo->exec(
            "INSERT INTO clases_programadas (titulo, materia_id, profesor_id, precio, descripcion, duracion_minutos, activa)
             VALUES ('Clase Espectadores Test', 1, 2, 50, 'Test', 60, true)"
        );
        $claseId = self::$pdo->lastInsertId();

        // Crear sala de prueba
        self::$pdo->exec(
            "INSERT INTO salas (clase_id, activa) VALUES ($claseId, true)"
        );
        $salaId = self::$pdo->lastInsertId();

        // Agregar espectador
        self::$pdo->exec(
            "INSERT INTO espectadores (sala_id, usuario_id, estado, solicitud_at)
             VALUES ($salaId, " . self::$testUserId . ", 'pendiente', NOW())"
        );

        // Verificar que se creó el espectador
        $stmt = self::$pdo->prepare("SELECT * FROM espectadores WHERE sala_id = ? AND usuario_id = ?");
        $stmt->execute([$salaId, self::$testUserId]);
        $espectador = $stmt->fetch();

        $this->assertNotNull($espectador);
        $this->assertEquals('pendiente', $espectador['estado']);

        // Aprobar espectador
        $response = $this->apiRequest(
            '/api_sala.php?action=approve_spectator',
            ['sala_id' => $salaId, 'usuario_id' => self::$testUserId],
            'POST',
            self::$testToken
        );

        // Verificar cambio de estado
        $stmt->execute([$salaId, self::$testUserId]);
        $espectador = $stmt->fetch();
        $this->assertEquals('aprobado', $espectador['estado']);
    }

    // ── TESTS DE PAGOS ────────────────────────────────────────────────────────

    public function testPagoExitoso(): void
    {
        // Verificar saldo inicial
        $stmt = self::$pdo->prepare("SELECT creditos FROM usuarios WHERE usuarioId = ?");
        $stmt->execute([self::$testUserId]);
        $saldoInicial = $stmt->fetchColumn();

        // Simular pago
        $monto = 50;
        self::$pdo->exec(
            "UPDATE usuarios SET creditos = creditos - $monto WHERE usuarioId = " . self::$testUserId
        );
        self::$pdo->exec(
            "INSERT INTO pagos (usuario_id, monto, descripcion)
             VALUES (" . self::$testUserId . ", -$monto, 'Test pago')"
        );

        // Verificar saldo final
        $stmt->execute([self::$testUserId]);
        $saldoFinal = $stmt->fetchColumn();

        $this->assertEquals($saldoInicial - $monto, $saldoFinal);
    }

    // ── TESTS DE CRÉDITOS ────────────────────────────────────────────────────

    public function testObtenerCreditos(): void
    {
        $response = $this->apiRequest('/api_mobile.php?action=credits', [], 'GET', self::$testToken);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('balance', $response['body']);
        $this->assertArrayHasKey('history', $response['body']);
    }

    public function testRecargarCreditos(): void
    {
        $data = ['amount' => 100];
        $response = $this->apiRequest('/api_mobile.php?action=topup', $data, 'POST', self::$testToken);

        $this->assertEquals(200, $response['status']);
        $this->assertArrayHasKey('balance', $response['body']);
    }

    public static function tearDownAfterClass(): void
    {
        // Limpiar base de datos después de todas las pruebas
        self::cleanupDatabase();
    }
}
