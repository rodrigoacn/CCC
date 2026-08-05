<?php
// ─────────────────────────────────────────────────────────────────────────────
//  WalletController — handlers moved verbatim from api_mobile.php
//  (credits / topup / buy_tokens / create_checkout / checkout_status /
//   payment / withdraw_tokens / withdrawal_history / admin_withdrawals /
//   admin_process_withdrawal)
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Controllers;

final class WalletController
{
    public static function credits(): void {
        $user    = getAuthUser();
        $uid     = (int)($user['usuarioId'] ?? $user['id'] ?? 0);
        $history = dbAll(
            "SELECT p.pagoId AS id, -COALESCE(p.monto_local, 0) AS monto, 'class' AS tipo, cp.titulo AS descripcion, p.created_at
             FROM pagos p JOIN sesiones_clase sc ON sc.sesionId = p.sesionId JOIN clases_programadas cp ON cp.claseId = sc.claseId
             WHERE p.estudianteId = ?
             UNION ALL
             SELECT ct.id AS id, ct.monto_usd AS monto, 'tokens' AS tipo, ct.cantidad AS descripcion, ct.created_at
             FROM compras_tokens ct WHERE ct.usuario_id = ?
             ORDER BY created_at DESC LIMIT 30",
            [$uid, $uid]
        );
        foreach ($history as &$h) {
            if ($h['tipo'] === 'tokens') $h['descripcion'] = 'Compra de tokens: ' . (int)$h['descripcion'];
        }
        unset($h);
        $userData = dbOne("SELECT creditos, tokens FROM usuarios WHERE usuarioId = ?", [$uid]);
        jsonOut([
            'balance'        => (int)$user['creditos'],
            'tokens'         => (float)($userData['tokens'] ?? 0),
            'history'        => $history,
        ]);
    }

    public static function topup(array $body): void {
        $user   = getAuthUser();
        $amount = (int)($body['amount'] ?? 0);
        if ($amount < 1 || $amount > 1000) jsonOut(['error' => 'Monto inválido (1-1000)'], 400);

        $uid = (int)($user['usuarioId'] ?? $user['id'] ?? 0);

        require_once __DIR__ . '/../../../lib/MercadoPagoGateway.php';
        try {
            $checkout = MercadoPagoGateway::createPreference($uid, 'credits', $amount, (float)$amount);
            jsonOut([
                'checkout_url'  => $checkout['checkout_url'],
                'preference_id' => $checkout['preference_id'],
            ]);
        } catch (\Exception $e) {
            jsonOut(['error' => 'Error creating checkout: ' . $e->getMessage()], 500);
        }
    }

    public static function buyTokens(array $body): void {
        $user   = getAuthUser();
        $amount = (int)($body['amount'] ?? 0);
        $prices = [10 => 10, 25 => 25, 50 => 50, 100 => 100, 200 => 200];
        if (!isset($prices[$amount])) jsonOut(['error' => 'Paquete inválido'], 400);

        $uid = (int)($user['usuarioId'] ?? $user['id'] ?? 0);

        require_once __DIR__ . '/../../../lib/MercadoPagoGateway.php';
        try {
            $checkout = MercadoPagoGateway::createPreference($uid, 'tokens', $amount, (float)$prices[$amount]);
            jsonOut([
                'checkout_url'  => $checkout['checkout_url'],
                'preference_id' => $checkout['preference_id'],
            ]);
        } catch (\Exception $e) {
            jsonOut(['error' => 'Error creating checkout: ' . $e->getMessage()], 500);
        }
    }

    public static function createCheckout(array $body): void {
        $user     = getAuthUser();
        $uid      = (int)($user['usuarioId'] ?? $user['id'] ?? 0);
        $type     = $body['type'] ?? '';
        $quantity = (int)($body['quantity'] ?? 0);

        if (!in_array($type, ['credits', 'tokens'])) jsonOut(['error' => 'Tipo inválido (credits/tokens)'], 400);

        $prices = [10 => 10, 25 => 25, 50 => 50, 100 => 100, 200 => 200];
        if ($type === 'tokens') {
            if (!isset($prices[$quantity])) jsonOut(['error' => 'Paquete inválido'], 400);
            $amountUsd = (float)$prices[$quantity];
        } else {
            if ($quantity < 1 || $quantity > 1000) jsonOut(['error' => 'Cantidad inválida (1-1000)'], 400);
            $amountUsd = (float)$quantity;
        }

        require_once __DIR__ . '/../../../lib/MercadoPagoGateway.php';
        try {
            $checkout = MercadoPagoGateway::createPreference($uid, $type, $quantity, $amountUsd);
            jsonOut([
                'checkout_url'  => $checkout['checkout_url'],
                'preference_id' => $checkout['preference_id'],
            ]);
        } catch (\Exception $e) {
            jsonOut(['error' => 'Error creating checkout: ' . $e->getMessage()], 500);
        }
    }

    public static function checkoutStatus(): void {
        $user = getAuthUser();
        $extRef = trim($_GET['external_reference'] ?? '');
        if (!$extRef) jsonOut(['error' => 'external_reference requerido'], 400);

        require_once __DIR__ . '/../../../lib/MercadoPagoGateway.php';
        $status = MercadoPagoGateway::checkPaymentStatus($extRef);
        jsonOut($status);
    }

    public static function payment(array $body): void {
        $user = getAuthUser();
        $uid  = (int)($user['usuarioId'] ?? $user['id'] ?? 0);
        $sesionId = (int)($body['sesion_id'] ?? 0);
        $salaId   = (int)($body['sala_id'] ?? 0);

        if ($sesionId) {
            // Pay a finished session (sesion_id) — mirrors web pago.php
            $sesion = dbOne(
                "SELECT sc.*, cp.titulo, cp.instructorId, cp.claseId, cp.precio_base
                 FROM sesiones_clase sc
                 JOIN clases_programadas cp ON cp.claseId = sc.claseId
                 WHERE sc.sesionId = ? AND sc.estudianteId = ?",
                [$sesionId, $uid]
            );
            if (!$sesion) jsonOut(['error' => 'Sesión no encontrada'], 404);
            if ((int)$sesion['pagado']) jsonOut(['error' => 'La sesión ya fue pagada'], 400);

            $precio = (float)($sesion['precio_usd'] > 0 ? $sesion['precio_usd'] : $sesion['precio_base']);
            if ((int)$user['creditos'] < (int)$precio) jsonOut(['error' => 'Créditos insuficientes'], 402);

            dbExec("INSERT INTO pagos (sesionId, estudianteId, profesorId, monto_usd, estado) VALUES (?, ?, ?, ?, 'completado')",
                   [$sesionId, $uid, (int)$sesion['instructorId'], $precio]);
            dbExec("UPDATE sesiones_clase SET pagado = 1 WHERE sesionId = ?", [$sesionId]);
            dbExec("UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ?", [$precio, $uid]);

            $updated = dbOne("SELECT creditos FROM usuarios WHERE usuarioId = ?", [$uid]);
            jsonOut([
                'ok'                 => true,
                'creditos_restantes' => (int)$updated['creditos'],
                'recibo'             => "Pagaste {$precio} crédito(s) por «{$sesion['titulo']}»",
            ]);
        }

        // Legacy sala_id payment path
        $sala = dbOne(
            "SELECT s.*, cp.precio_base AS precio, cp.titulo, cp.instructorId, cp.claseId
             FROM salas s
             JOIN clases_programadas cp ON cp.claseId = s.claseId
             WHERE s.salaId = ?",
            [$salaId]
        );
        if (!$sala) jsonOut(['error' => 'Sala no encontrada'], 404);
        if ((int)$user['creditos'] < (int)$sala['precio']) jsonOut(['error' => 'Créditos insuficientes'], 402);

        $sesion = dbOne(
            "SELECT sesionId FROM sesiones_clase
             WHERE claseId = ? AND estudianteId = ? AND pagado = 0 AND fin IS NULL LIMIT 1",
            [(int)$sala['claseId'], $uid]
        );

        dbExec("UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ?", [$sala['precio'], $uid]);
        if ($sesion) {
            dbExec("INSERT INTO pagos (sesionId, estudianteId, profesorId, monto_usd, estado) VALUES (?, ?, ?, ?, 'completado')",
                   [(int)$sesion['sesionId'], $uid, (int)$sala['instructorId'], $sala['precio']]);
            dbExec("UPDATE sesiones_clase SET pagado = 1 WHERE sesionId = ?", [(int)$sesion['sesionId']]);
        }

        $updated = dbOne("SELECT creditos FROM usuarios WHERE usuarioId = ?", [$uid]);
        jsonOut([
            'ok'                 => true,
            'creditos_restantes' => (int)$updated['creditos'],
            'recibo'             => "Pagaste {$sala['precio']} crédito(s) por «{$sala['titulo']}»",
        ]);
    }

    public static function withdrawTokens(array $body): void {
        $auth = getAuthUser();
        $uid  = (int)$auth['id'];
        $cantidad = (int)($body['cantidad'] ?? 0);
        $cuenta = trim($body['cuenta_bancaria'] ?? '');
        $banco = trim($body['nombre_banco'] ?? '');
        $tipoCuenta = trim($body['tipo_cuenta'] ?? 'corriente');
        $paypalEmail = trim($body['paypal_email'] ?? '');
        $metodoRetiro = trim($body['metodo_retiro'] ?? 'banco');

        $user = dbOne("SELECT rol, creditos FROM usuarios WHERE usuarioId = ?", [$uid]);
        if (!$user || ($user['rol'] === 'estudiante' || $user['rol'] === 'student')) {
            jsonOut(['error' => 'Only teachers can withdraw tokens'], 403);
        }

        if ($cantidad <= 0) {
            jsonOut(['error' => 'Invalid amount'], 400);
        }

        $minWithdraw = 10;
        if ($cantidad < $minWithdraw) {
            jsonOut(['error' => "Minimum withdrawal is {$minWithdraw} tokens"], 400);
        }

        if ($cantidad > (float)$user['creditos']) {
            jsonOut(['error' => 'Insufficient balance'], 400);
        }

        if ($metodoRetiro === 'paypal' && empty($paypalEmail)) {
            jsonOut(['error' => 'PayPal email is required'], 400);
        } elseif ($metodoRetiro === 'banco' && (empty($cuenta) || empty($banco))) {
            jsonOut(['error' => 'Bank account and bank name are required'], 400);
        }

        $pending = dbOne("SELECT COUNT(*) AS cnt FROM retiros_tokens WHERE usuario_id = ? AND estado = 'pendiente'", [$uid]);
        if ($pending && (int)$pending['cnt'] > 0) {
            jsonOut(['error' => 'You already have a pending withdrawal request'], 400);
        }

        $exchangeRate = 950;
        $comisionPct = 0.15;
        $montoUsd = (float)$cantidad;
        $comision = round($montoUsd * $comisionPct, 2);
        $neto = round($montoUsd - $comision, 2);
        $montoClp = (int)round($neto * $exchangeRate);

        $pdo = getDB();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET creditos = creditos - ? WHERE usuarioId = ? AND creditos >= ?");
            $stmt->execute([$cantidad, $uid, $cantidad]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                jsonOut(['error' => 'Insufficient balance or concurrent request'], 400);
            }

            $ins = $pdo->prepare(
                "INSERT INTO retiros_tokens (usuario_id, cantidad, monto_usd, monto_clp, comision, neto_pagar, cuenta_bancaria, nombre_banco, tipo_cuenta, paypal_email, estado)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')"
            );
            $ins->execute([$uid, $cantidad, $montoUsd, $montoClp, $comision, $neto, $metodoRetiro === 'banco' ? $cuenta : '', $metodoRetiro === 'banco' ? $banco : 'PayPal', $metodoRetiro === 'banco' ? $tipoCuenta : 'paypal', $paypalEmail]);

            $pdo->commit();
            jsonOut([
                'ok' => true,
                'message' => 'Withdrawal request created',
                'tokens_deducted' => $cantidad,
                'comision' => $comision,
                'neto_pagar_usd' => $neto,
                'neto_pagar_clp' => $montoClp,
                'exchange_rate' => $exchangeRate,
            ]);
        } catch (\Exception $e) {
            $pdo->rollBack();
            jsonOut(['error' => 'Error processing withdrawal: ' . $e->getMessage()], 500);
        }
    }

    public static function withdrawalHistory(): void {
        $uid = (int)getAuthUser()['id'];

        $rows = dbAll(
            "SELECT id AS retiroId, cantidad, monto_usd, monto_clp, comision, neto_pagar, nombre_banco, tipo_cuenta, paypal_email, estado, admin_note, created_at, procesado_at
             FROM retiros_tokens WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 50",
            [$uid]
        );
        jsonOut(['ok' => true, 'withdrawals' => $rows]);
    }

    public static function adminWithdrawals(): void {
        $auth = getAuthUser();
        $uid  = (int)$auth['id'];

        if ($auth['rol'] !== 'admin') {
            jsonOut(['error' => 'Admin only'], 403);
        }

        $estado = $_GET['estado'] ?? '';
        $sql = "SELECT r.*, u.nombre, u.email FROM retiros_tokens r JOIN usuarios u ON u.usuarioId = r.usuario_id";
        $params = [];
        if ($estado) {
            $sql .= " WHERE r.estado = ?";
            $params[] = $estado;
        }
        $sql .= " ORDER BY r.created_at DESC LIMIT 100";

        $rows = dbAll($sql, $params);
        jsonOut(['ok' => true, 'withdrawals' => $rows]);
    }

    public static function adminProcessWithdrawal(array $body): void {
        $auth = getAuthUser();
        $uid  = (int)$auth['id'];

        if ($auth['rol'] !== 'admin') {
            jsonOut(['error' => 'Admin only'], 403);
        }

        $retiroId = (int)($body['retiro_id'] ?? 0);
        $action = $body['action'] ?? '';
        $note = trim($body['note'] ?? '');

        if (!$retiroId || !in_array($action, ['approve', 'reject'])) {
            jsonOut(['error' => 'Invalid parameters'], 400);
        }

        $retiro = dbOne("SELECT * FROM retiros_tokens WHERE id = ?", [$retiroId]);
        if (!$retiro) { jsonOut(['error' => 'Withdrawal not found'], 404); }
        if ($retiro['estado'] !== 'pendiente') {
            jsonOut(['error' => 'Withdrawal already processed'], 400);
        }

        $newState = $action === 'approve' ? 'completado' : 'rechazado';

        $pdo = getDB();
        $pdo->beginTransaction();
        try {
            dbExec(
                "UPDATE retiros_tokens SET estado = ?, admin_note = ?, procesado_por = ?, procesado_at = NOW() WHERE id = ?",
                [$newState, $note, $uid, $retiroId]
            );

            if ($action === 'reject') {
                dbExec(
                    "UPDATE usuarios SET creditos = creditos + ? WHERE usuarioId = ?",
                    [$retiro['cantidad'], $retiro['usuario_id']]
                );
            }

            $pdo->commit();
            jsonOut(['ok' => true, 'message' => 'Withdrawal ' . $newState]);
        } catch (\Exception $e) {
            $pdo->rollBack();
            jsonOut(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}
