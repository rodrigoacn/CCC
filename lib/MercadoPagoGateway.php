<?php
// ─────────────────────────────────────────────────────────────────────────────
//  MercadoPago Gateway — ClassExpress Checkout Pro
//  Bunny Software E.I.R.L.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/mercadopago_config.php';
require_once __DIR__ . '/../db.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use MercadoPago\SDK;

class MercadoPagoGateway {

    private static $initialized = false;

    public static function init(): void {
        if (self::$initialized) return;
        SDK::setAccessToken(MP_ACCESS_TOKEN);
        self::$initialized = true;
    }

    /**
     * Create a Checkout Pro preference for credits or tokens.
     *
     * @param int    $usuarioId
     * @param string $type       'credits' or 'tokens'
     * @param int    $quantity   Number of credits or tokens
     * @param float  $amountUsd  Price in USD
     * @param string $currency   Currency code (CLP, USD, etc.)
     * @return array{preference_id: string, checkout_url: string, sandbox_url: string|null}
     */
    public static function createPreference(
        int $usuarioId,
        string $type,
        int $quantity,
        float $amountUsd,
        string $currency = ''
    ): array {
        self::init();

        $currencyId = $currency ?: mpGetCurrencyId();
        $unitPrice  = mpGetPriceInCurrency($amountUsd, $currencyId);

        $title = $type === 'credits'
            ? "ClassExpress - {$quantity} Créditos"
            : "ClassExpress - {$quantity} MonedasCE";

        // External reference for matching payments to users
        $externalRef = "ce_{$usuarioId}_{$type}_{$quantity}_" . time();

        // Build preference using raw SDK post (entity mapping has issues with nested objects)
        $baseUrl = mpGetBaseUrl();
        $prefData = [
            'items' => [[
                'id'          => 'ce_' . $type,
                'title'       => $title,
                'description' => "Compra de {$quantity} {$type} en ClassExpress",
                'category_id' => 'digital_content',
                'quantity'    => 1,
                'unit_price'  => $unitPrice,
                'currency_id' => $currencyId,
            ]],
            'external_reference'    => $externalRef,
            'statement_descriptor'  => MP_STATEMENT_DESCRIPTOR,
            'binary_mode'           => false,
            'back_urls'             => [
                'success' => "{$baseUrl}/mp_success.php",
                'failure' => "{$baseUrl}/mp_failure.php",
                'pending' => "{$baseUrl}/mp_pending.php",
            ],
            'notification_url'     => "{$baseUrl}/mp_webhook.php",
        ];

        // Add auto_return only if using HTTPS (not localhost)
        if (strpos($baseUrl, 'https://') === 0) {
            $prefData['auto_return'] = 'approved';
        }

        // Add payer info if available
        $user = dbOne("SELECT nombre, email FROM usuarios WHERE usuarioId = ?", [$usuarioId]);
        if ($user) {
            $prefData['payer'] = [
                'name'  => $user['nombre'],
                'email' => $user['email'],
            ];
        }

        // Use SDK::post directly to avoid entity serialization issues
        $response = SDK::post('/checkout/preferences', ['json_data' => $prefData]);
        if ($response['code'] >= 200 && $response['code'] < 300) {
            $prefResult = $response['body'] ?? $response;
            $preferenceId = $prefResult['id'] ?? '';
            $initPoint    = $prefResult['init_point'] ?? '';
            $sandboxInit  = $prefResult['sandbox_init_point'] ?? '';
        } else {
            $errorMsg = $response['body']['message'] ?? $response['body'] ?? 'Unknown error';
            throw new \Exception('MercadoPago error: ' . json_encode($errorMsg));
        }

        // Save checkout session in BD
        dbExec(
            "INSERT INTO checkout_sessions
                (usuario_id, type, quantity, amount_usd, amount_local, currency,
                 preference_id, external_reference, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
            [
                $usuarioId,
                $type,
                $quantity,
                $amountUsd,
                $unitPrice,
                $currencyId,
                $preferenceId,
                $externalRef,
            ]
        );

        return [
            'preference_id' => $preferenceId,
            'checkout_url'  => $initPoint,
            'sandbox_url'   => $sandboxInit ?: null,
        ];
    }

    /**
     * Look up a payment by ID.
     */
    public static function getPayment(int $paymentId): ?array {
        self::init();

        $response = SDK::get("/v1/payments/{$paymentId}");
        if ($response['code'] >= 200 && $response['code'] < 300) {
            return $response['body'] ?? null;
        }
        return null;
    }

    /**
     * Process a payment notification (webhook).
     * Returns the processed payment data or null.
     */
    public static function processWebhook(array $body): ?array {
        // Get payment ID from notification
        $paymentId = null;
        if (isset($body['data']['id'])) {
            $paymentId = (int)$body['data']['id'];
        } elseif (isset($body['resource'])) {
            // resource URL: /v1/payments/12345
            if (preg_match('#/v1/payments/(\d+)#', $body['resource'], $m)) {
                $paymentId = (int)$m[1];
            }
        }

        if (!$paymentId) return null;

        $payment = self::getPayment($paymentId);
        if (!$payment) return null;

        // Process based on status
        $status       = $payment['status'] ?? '';
        $extRef       = $payment['external_reference'] ?? '';
        $mpAmount     = (float)($payment['transaction_amount'] ?? 0);
        $currencyId   = $payment['currency_id'] ?? 'CLP';

        if (!$extRef) return null;

        // Find checkout session
        $session = dbOne(
            "SELECT * FROM checkout_sessions WHERE external_reference = ?",
            [$extRef]
        );
        if (!$session) return null;

        // Already processed?
        if (in_array($session['status'], ['approved', 'rejected', 'refunded'])) {
            return $payment;
        }

        // Update checkout session
        dbExec(
            "UPDATE checkout_sessions SET payment_id = ?, status = ? WHERE id = ?",
            [$paymentId, $status, $session['id']]
        );

        if ($status === 'approved') {
            self::fulfillOrder((int)$session['usuario_id'], $session['type'], (int)$session['quantity']);
        }

        return $payment;
    }

    /**
     * Fulfill an order: add credits or tokens to user account.
     */
    public static function fulfillOrder(int $usuarioId, string $type, int $quantity): void {
        if ($type === 'credits') {
            dbExec("UPDATE usuarios SET creditos = creditos + ? WHERE usuarioId = ?", [$quantity, $usuarioId]);
        } elseif ($type === 'tokens') {
            dbExec("UPDATE usuarios SET tokens = tokens + ? WHERE usuarioId = ?", [$quantity, $usuarioId]);
        } elseif ($type === 'ads_free') {
            // Registra la compra "sin anuncios por 1 semana" (se aplica globalmente
            // desde la fecha de pago hasta +1 semana).
            dbExec(
                "INSERT INTO ads_free_compras (monto_clp, valido_hasta, estado)
                 VALUES (5000, DATE_ADD(NOW(), INTERVAL 1 WEEK), 'activo')"
            );
            return;
        }

        // Log in compras_tokens
        $fee = calcularFeeRodrigo((float)$quantity);
        dbExec(
            "INSERT INTO compras_tokens (usuario_id, cantidad, monto_usd, fee_rodrigo, metodo_pago)
             VALUES (?, ?, ?, ?, 'mercadopago')",
            [$usuarioId, $quantity, $quantity, $fee]
        );
    }

    /**
     * Verify a payment by external_reference (for polling after checkout).
     */
    public static function checkPaymentStatus(string $externalReference): array {
        $session = dbOne(
            "SELECT * FROM checkout_sessions WHERE external_reference = ?",
            [$externalReference]
        );
        if (!$session) return ['status' => 'not_found'];

        return [
            'status'    => $session['status'],
            'type'      => $session['type'],
            'quantity'  => (int)$session['quantity'],
            'payment_id' => $session['payment_id'] ?? null,
        ];
    }
}
