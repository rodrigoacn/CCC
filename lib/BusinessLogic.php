<?php
// ─────────────────────────────────────────────────────────────────────────────
//  ClassExpress - Business Logic Functions
//  Funciones de negocio que pueden ser probadas unitariamente
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../db.php';

/**
 * Calcula la comisión para Rodrigo (5% extra en compras de tokens/créditos)
 * 
 * @param float $montoUsd Monto base en USD de la compra
 * @return float Comisión para Rodrigo
 */
function calcularFeeRodrigo(float $montoUsd): float
{
    return round($montoUsd * 0.05, 2);
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
 * @param int $minutosGratis Minutos gratis (por promoción)
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
