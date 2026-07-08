<?php
// ─────────────────────────────────────────────────────────────────────────────
//  ClassExpress - Business Logic Functions
//  Funciones de negocio que pueden ser probadas unitariamente
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Calcula la comisión del profesor basado en el número de referidos
 * 
 * @param int $numReferidos Número de referidos del profesor
 * @return float Comisión reducida (0-5%)
 */
function calcularComisionReferidos(int $numReferidos): float
{
    $reduccion = min($numReferidos, 5);
    return $reduccion * 0.01; // 1% por referido, máximo 5%
}

/**
 * Calcula el precio final del profesor después de la comisión de la plataforma
 * 
 * @param float $precio Precio base de la clase
 * @param int $numReferidos Número de referidos del profesor
 * @return float Precio final para el profesor
 */
function calcularPrecioProfesor(float $precio, int $numReferidos): float
{
    $comisionBase = 0.15; // 15% comisión base
    $reduccion = calcularComisionReferidos($numReferidos);
    $comisionFinal = max($comisionBase - $reduccion, 0.10); // Mínimo 10%
    
    return $precio * (1 - $comisionFinal);
}

/**
 * Calcula los minutos espectador gratis basado en el número de referidos
 * 
 * @param int $numReferidos Número de referidos del usuario
 * @return int Minutos espectador gratis (máximo 5 por referido, pero el límite es por usuario)
 */
function calcularMinutosEspectadorGratis(int $numReferidos): int
{
    return min($numReferidos, 5);
}

/**
 * Calcula el precio por minuto de una clase
 * 
 * @param float $precioTotal Precio total de la clase
 * @param int $duracionMinutos Duración en minutos
 * @return float Precio por minuto
 */
function calcularPrecioPorMinuto(float $precioTotal, int $duracionMinutos): float
{
    if ($duracionMinutos <= 0) return 0;
    return $precioTotal / $duracionMinutos;
}

/**
 * Calcula el cobro para un estudiante basado en el tiempo transcurrido
 * 
 * @param float $precioTotal Precio total de la clase
 * @param int $duracionMinutos Duración total en minutos
 * @param int $minutosTranscurridos Minutos que el estudiante estuvo en la clase
 * @param int $minutosGratis Minutos gratis (por referido o promoción)
 * @return float Monto a cobrar
 */
function calcularCobroEstudiante(float $precioTotal, int $duracionMinutos, int $minutosTranscurridos, int $minutosGratis = 0): float
{
    if ($minutosTranscurridos <= $minutosGratis) return 0;
    
    $minutosCobrables = $minutosTranscurridos - $minutosGratis;
    $precioPorMinuto = calcularPrecioPorMinuto($precioTotal, $duracionMinutos);
    
    return $precioPorMinuto * $minutosCobrables;
}

/**
 * Valida si un username es válido
 * 
 * @param string $username Username a validar
 * @return bool True si es válido
 */
function validarUsername(string $username): bool
{
    if (strlen($username) < 3 || strlen($username) > 50) return false;
    return preg_match('/^[a-zA-Z0-9_]+$/', $username) === 1;
}

/**
 * Valida si un email es válido
 * 
 * @param string $email Email a validar
 * @return bool True si es válido
 */
function validarEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valida si una contraseña es fuerte
 * 
 * @param string $password Contraseña a validar
 * @return bool True si es válida
 */
function validarPassword(string $password): bool
{
    if (strlen($password) < 8) return false;
    return true; // Puedes agregar más validaciones según requisitos
}

/**
 * Calcula el monto en moneda local basado en la tasa de cambio
 * 
 * @param float $montoUSD Monto en USD
 * @param float $tasaCambio Tasa de cambio (moneda local por 1 USD)
 * @return float Monto en moneda local
 */
function convertirMoneda(float $montoUSD, float $tasaCambio): float
{
    return $montoUSD * $tasaCambio;
}

/**
 * Verifica si un usuario puede referir más personas
 * 
 * @param int $numReferidosActuales Número actual de referidos
 * @return bool True si puede referir más
 */
function puedeReferirMas(int $numReferidosActuales): bool
{
    return $numReferidosActuales < 5;
}
