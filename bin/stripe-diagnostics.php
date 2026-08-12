<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Services\StripeService;

try {
    $diagnostics = (new StripeService())->diagnostics();
    echo json_encode([
        'enabled' => (bool) ($diagnostics['enabled'] ?? false),
        'environment' => (string) ($diagnostics['environment'] ?? ''),
        'key_mode' => (string) ($diagnostics['key_mode'] ?? ''),
        'api_livemode' => (bool) ($diagnostics['api_livemode'] ?? false),
        'charges_enabled' => (bool) ($diagnostics['charges_enabled'] ?? false),
        'payouts_enabled' => (bool) ($diagnostics['payouts_enabled'] ?? false),
        'webhook_configured' => (bool) ($diagnostics['webhook_configured'] ?? false),
        'account_id' => (string) ($diagnostics['account_id'] ?? ''),
        'wallets' => (array) ($diagnostics['wallets'] ?? []),
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Stripe diagnostics failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

