<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = MaisonBebe\Core\Database::connection();
$provider = $pdo->query("SELECT environment,is_enabled,is_default,config_json FROM payment_providers WHERE code='stripe' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$diagnostics = (new MaisonBebe\Services\StripeService())->diagnostics();
$counts = [];
foreach (['orders','invoices','products','categories','collections'] as $table) {
    $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}
echo json_encode([
    'provider' => [
        'environment' => $provider['environment'] ?? null,
        'enabled' => (bool) ($provider['is_enabled'] ?? false),
        'default' => (bool) ($provider['is_default'] ?? false),
    ],
    'diagnostics' => [
        'key_mode' => $diagnostics['key_mode'] ?? null,
        'api_livemode' => $diagnostics['api_livemode'] ?? null,
        'webhook_configured' => $diagnostics['webhook_configured'] ?? null,
        'account_id' => $diagnostics['account_id'] ?? null,
    ],
    'counts' => $counts,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
