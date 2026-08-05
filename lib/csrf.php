<?php
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_validate(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $token = $_POST['csrf_token']
           ?? $_SERVER['HTTP_X_CSRF_TOKEN']
           ?? $_SERVER['HTTP_X-CSRF-TOKEN']
           ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!$token || !$expected) return false;
    return hash_equals($expected, $token);
}

function csrf_require(): void {
    if (!csrf_validate()) {
        http_response_code(419);
        die('CSRF token inválido. Recarga la página e inténtalo de nuevo.');
    }
}
