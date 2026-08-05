<?php
// ─────────────────────────────────────────────────────────────────────────────
//  MercadoPago Configuration — ClassExpress
//  Bunny Software E.I.R.L. (Chile)
// ─────────────────────────────────────────────────────────────────────────────

// Credentials
define('MP_ACCESS_TOKEN',  getenv('MP_ACCESS_TOKEN')  ?: 'APP_USR-151981118092187-072618-5f45efad7a1c540ccbf24d70ade230af-3570251148');
define('MP_PUBLIC_KEY',     getenv('MP_PUBLIC_KEY')     ?: 'APP_USR-b10d1ed7-a254-488b-983a-c36f79f95412');
define('MP_WEBHOOK_SECRET', getenv('MP_WEBHOOK_SECRET') ?: '');

// Company info
define('MP_COMPANY_NAME',  'Bunny Software E.I.R.L.');
define('MP_COMPANY_RUT',   ''); // RUT here if needed
define('MP_STATEMENT_DESCRIPTOR', 'CLASSEXPRESS');

// Supported currencies (CLP for Chile)
define('MP_DEFAULT_CURRENCY', 'CLP');

// Currency conversion rates (approximate, for display only — MP handles actual conversion)
define('MP_CLP_PER_USD', 950);

// Credit/token pricing: 1 credit = 1 USD = 950 CLP
// In CLP, prices must be whole numbers (no centavos)
function mpGetCurrencyId(string $override = ''): string {
    if ($override) return strtoupper($override);
    return MP_DEFAULT_CURRENCY;
}

function mpUsdToClp(float $usd): int {
    return (int)round($usd * MP_CLP_PER_USD);
}

function mpGetPriceInCurrency(float $usd, string $currencyId): float {
    if ($currencyId === 'CLP') return (float)mpUsdToClp($usd);
    return $usd;
}

// Get base URL for callbacks
function mpGetBaseUrl(): string {
    // In production, always use HTTPS on classexpress.online
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host && strpos($host, 'localhost') === false) {
        return 'https://' . $host . '/CCC';
    }
    return 'https://classexpress.online/CCC';
}
