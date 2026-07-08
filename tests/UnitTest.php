<?php
/**
 * Pruebas Unitarias - ClassExpress Business Logic
 * Pruebas de funciones de negocio sin dependencias de base de datos o HTTP
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/BusinessLogic.php';

class UnitTest extends TestCase
{
    // ── TESTS DE COMISIÓN DE REFERIDOS ─────────────────────────────────

    public function testCalcularComisionSinReferidos(): void
    {
        $comision = calcularComisionReferidos(0);
        $this->assertEquals(0.0, $comision);
    }

    public function testCalcularComisionUnReferido(): void
    {
        $comision = calcularComisionReferidos(1);
        $this->assertEquals(0.01, $comision); // 1%
    }

    public function testCalcularComisionTresReferidos(): void
    {
        $comision = calcularComisionReferidos(3);
        $this->assertEquals(0.03, $comision); // 3%
    }

    public function testCalcularComisionCincoReferidos(): void
    {
        $comision = calcularComisionReferidos(5);
        $this->assertEquals(0.05, $comision); // 5%
    }

    public function testCalcularComisionMasDeCincoReferidos(): void
    {
        $comision = calcularComisionReferidos(10);
        $this->assertEquals(0.05, $comision); // Máximo 5%
    }

    // ── TESTS DE PRECIO PROFESOR ───────────────────────────────────────

    public function testCalcularPrecioProfesorSinReferidos(): void
    {
        $precio = calcularPrecioProfesor(100.0, 0);
        // 15% comisión base = 85% para el profesor
        $this->assertEquals(85.0, $precio);
    }

    public function testCalcularPrecioProfesorConReferidos(): void
    {
        $precio = calcularPrecioProfesor(100.0, 3);
        // 15% - 3% = 12% comisión = 88% para el profesor
        $this->assertEquals(88.0, $precio);
    }

    public function testCalcularPrecioProfesorMaximaReduccion(): void
    {
        $precio = calcularPrecioProfesor(100.0, 10);
        // 15% - 5% = 10% comisión mínima = 90% para el profesor
        $this->assertEquals(90.0, $precio);
    }

    // ── TESTS DE MINUTOS ESPECTADOR GRATIS ─────────────────────────────

    public function testCalcularMinutosEspectadorSinReferidos(): void
    {
        $minutos = calcularMinutosEspectadorGratis(0);
        $this->assertEquals(0, $minutos);
    }

    public function testCalcularMinutosEspectadorUnReferido(): void
    {
        $minutos = calcularMinutosEspectadorGratis(1);
        $this->assertEquals(1, $minutos);
    }

    public function testCalcularMinutosEspectadorCincoReferidos(): void
    {
        $minutos = calcularMinutosEspectadorGratis(5);
        $this->assertEquals(5, $minutos);
    }

    public function testCalcularMinutosEspectadorMasDeCincoReferidos(): void
    {
        $minutos = calcularMinutosEspectadorGratis(10);
        $this->assertEquals(5, $minutos); // Máximo 5
    }

    // ── TESTS DE PRECIO POR MINUTO ────────────────────────────────────

    public function testCalcularPrecioPorMinuto(): void
    {
        $precio = calcularPrecioPorMinuto(60.0, 60);
        $this->assertEquals(1.0, $precio);
    }

    public function testCalcularPrecioPorMinutoDuracionCero(): void
    {
        $precio = calcularPrecioPorMinuto(60.0, 0);
        $this->assertEquals(0, $precio);
    }

    public function testCalcularPrecioPorMinutoFraccion(): void
    {
        $precio = calcularPrecioPorMinuto(50.0, 30);
        $this->assertEquals(50.0 / 30, $precio);
    }

    // ── TESTS DE COBRO ESTUDIANTE ─────────────────────────────────────

    public function testCalcularCobroEstudianteSinMinutosGratis(): void
    {
        $cobro = calcularCobroEstudiante(60.0, 60, 30, 0);
        // 30 minutos de 60 = 30
        $this->assertEquals(30.0, $cobro);
    }

    public function testCalcularCobroEstudianteConMinutosGratis(): void
    {
        $cobro = calcularCobroEstudiante(60.0, 60, 30, 5);
        // 30 - 5 = 25 minutos cobrables = 25
        $this->assertEquals(25.0, $cobro);
    }

    public function testCalcularCobroEstudianteDentroDeMinutosGratis(): void
    {
        $cobro = calcularCobroEstudiante(60.0, 60, 3, 5);
        // 3 minutos < 5 gratis = 0
        $this->assertEquals(0.0, $cobro);
    }

    public function testCalcularCobroEstudianteClaseCompleta(): void
    {
        $cobro = calcularCobroEstudiante(60.0, 60, 60, 0);
        $this->assertEquals(60.0, $cobro);
    }

    // ── TESTS DE VALIDACIÓN DE USERNAME ────────────────────────────────

    public function testValidarUsernameValido(): void
    {
        $this->assertTrue(validarUsername('usuario123'));
        $this->assertTrue(validarUsername('user_name'));
        $this->assertTrue(validarUsername('User123'));
    }

    public function testValidarUsernameDemasiadoCorto(): void
    {
        $this->assertFalse(validarUsername('ab'));
    }

    public function testValidarUsernameDemasiadoLargo(): void
    {
        $this->assertFalse(validarUsername(str_repeat('a', 51)));
    }

    public function testValidarUsernameCaracteresInvalidos(): void
    {
        $this->assertFalse(validarUsername('user-name'));
        $this->assertFalse(validarUsername('user.name'));
        $this->assertFalse(validarUsername('user name'));
    }

    // ── TESTS DE VALIDACIÓN DE EMAIL ─────────────────────────────────

    public function testValidarEmailValido(): void
    {
        $this->assertTrue(validarEmail('usuario@example.com'));
        $this->assertTrue(validarEmail('user.name@domain.co'));
        $this->assertTrue(validarEmail('user+tag@example.org'));
    }

    public function testValidarEmailInvalido(): void
    {
        $this->assertFalse(validarEmail('usuario'));
        $this->assertFalse(validarEmail('usuario@'));
        $this->assertFalse(validarEmail('@example.com'));
        $this->assertFalse(validarEmail('usuario@.com'));
    }

    // ── TESTS DE VALIDACIÓN DE PASSWORD ───────────────────────────────

    public function testValidarPasswordValido(): void
    {
        $this->assertTrue(validarPassword('password123'));
        $this->assertTrue(validarPassword('Password1!'));
    }

    public function testValidarPasswordDemasiadoCorto(): void
    {
        $this->assertFalse(validarPassword('pass'));
    }

    // ── TESTS DE CONVERSIÓN DE MONEDA ────────────────────────────────

    public function testConvertirMonedaUSD(): void
    {
        $monto = convertirMoneda(100.0, 1.0);
        $this->assertEquals(100.0, $monto);
    }

    public function testConvertirMonedaCLP(): void
    {
        $monto = convertirMoneda(100.0, 950.0);
        $this->assertEquals(95000.0, $monto);
    }

    public function testConvertirMonedaEUR(): void
    {
        $monto = convertirMoneda(100.0, 0.92);
        $this->assertEquals(92.0, $monto);
    }

    // ── TESTS DE REFERIDOS ───────────────────────────────────────────

    public function testPuedeReferirSinReferidos(): void
    {
        $this->assertTrue(puedeReferirMas(0));
    }

    public function testPuedeReferirConReferidos(): void
    {
        $this->assertTrue(puedeReferirMas(3));
    }

    public function testPuedeReferirMaximo(): void
    {
        $this->assertFalse(puedeReferirMas(5));
    }

    public function testPuedeReferirExcedido(): void
    {
        $this->assertFalse(puedeReferirMas(10));
    }
}
