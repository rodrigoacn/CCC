<?php
// ─────────────────────────────────────────────────────────────────────────────
//  ClassExpress - PayPal Payouts Integration
//  Envía dinero a profesores vía PayPal Payouts API
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../db.php';

class PayPalPayouts
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;

    public function __construct()
    {
        $this->clientId  = getenv('PAYPAL_CLIENT_ID')  ?: '';
        $this->clientSecret = getenv('PAYPAL_CLIENT_SECRET') ?: '';
        $mode = getenv('PAYPAL_MODE') ?: 'sandbox';
        $this->baseUrl = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Get OAuth2 access token from PayPal
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        $ch = curl_init("{$this->baseUrl}/v1/oauth2/token");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_USERPWD        => "{$this->clientId}:{$this->clientSecret}",
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("PayPal OAuth failed (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = time() + ($data['expires_in'] ?? 3600) - 60;
        return $this->accessToken;
    }

    /**
     * Send a single payout to a PayPal email
     *
     * @param string $email    Recipient PayPal email
     * @param float  $amount   Amount in USD
     * @param string $currency Currency code (default USD)
     * @param string $note     Note for the recipient
     * @return array  ['batch_id' => ..., 'status' => ..., 'items' => [...]]
     */
    public function sendPayout(string $email, float $amount, string $currency = 'USD', string $note = 'ClassExpress withdrawal'): array
    {
        $token = $this->getAccessToken();

        $batchId = 'ce_payout_' . time() . '_' . bin2hex(random_bytes(4));
        $itemId  = 'item_' . time() . '_' . bin2hex(random_bytes(4));

        $payload = json_encode([
            'sender_batch_header' => [
                'sender_batch_id'  => $batchId,
                'email_subject'    => 'You have a payment from ClassExpress',
                'email_message'    => 'You have received a payment from ClassExpress.',
            ],
            'items' => [
                [
                    'recipient_type' => 'EMAIL',
                    'amount'         => [
                        'value'    => number_format($amount, 2, '.', ''),
                        'currency' => $currency,
                    ],
                    'receiver'       => $email,
                    'note'           => $note,
                    'sender_item_id' => $itemId,
                ],
            ],
        ]);

        $requestId = 'req_' . bin2hex(random_bytes(8));

        $ch = curl_init("{$this->baseUrl}/v1/payments/payouts");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'PayPal-Request-Id: ' . $requestId,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception("PayPal Payout failed (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);

        return [
            'batch_id' => $data['batch_header']['payout_batch_id'] ?? $batchId,
            'status'   => $data['batch_header']['batch_status'] ?? 'UNKNOWN',
            'items'    => $data['items'] ?? [],
            'request_id' => $requestId,
        ];
    }

    /**
     * Check the status of a payout batch
     */
    public function getBatchStatus(string $batchId): array
    {
        $token = $this->getAccessToken();

        $ch = curl_init("{$this->baseUrl}/v1/payments/payouts/{$batchId}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("PayPal batch status failed (HTTP {$httpCode}): {$response}");
        }

        return json_decode($response, true);
    }
}
