<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;

if (count($argv) < 4) {
    throw new RuntimeException('Utilizare: php activate_stripe_live.php <public> <secret> <webhook-json>');
}

[$script, $publicKey, $secretKey, $webhookFile] = $argv;
if (!str_starts_with($publicKey, 'pk_live_') || !str_starts_with($secretKey, 'sk_live_')) {
    throw new RuntimeException('Cheile furnizate nu sunt chei Stripe Live.');
}
$webhook = json_decode((string) file_get_contents($webhookFile), true, flags: JSON_THROW_ON_ERROR);
$webhookSecret = trim((string) ($webhook['secret'] ?? ''));
if (!str_starts_with($webhookSecret, 'whsec_')) {
    throw new RuntimeException('Secretul webhook nu a fost primit de la Stripe.');
}

$pdo = Database::connection();
$statement = $pdo->prepare("SELECT id,config_json FROM payment_providers WHERE code='stripe' LIMIT 1");
$statement->execute();
$provider = $statement->fetch(PDO::FETCH_ASSOC);
if (!$provider) {
    throw new RuntimeException('Providerul Stripe lipsește.');
}
$config = json_decode((string) ($provider['config_json'] ?? '{}'), true) ?: [];
$config['public_key'] = $publicKey;
$config['publishable_key'] = $publicKey;
$config['webhook_url'] = 'https://maison-bebe.ro/webhooks/plati/stripe';
$config['wallets'] = ['card' => true, 'apple_pay' => true, 'google_pay' => true];
$encrypted = Encryptor::encrypt(json_encode([
    'secret_key' => $secretKey,
    'webhook_secret' => $webhookSecret,
], JSON_THROW_ON_ERROR));

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE payment_providers SET environment='live',is_enabled=1,is_default=1,config_json=? WHERE id=?")
        ->execute([json_encode($config, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), (int) $provider['id']]);
    $pdo->prepare("UPDATE payment_providers SET is_default=0 WHERE id<>?")->execute([(int) $provider['id']]);
    $pdo->prepare('INSERT INTO payment_provider_credentials (provider_id,encrypted_payload,updated_by) VALUES (?,?,1) ON DUPLICATE KEY UPDATE encrypted_payload=VALUES(encrypted_payload),updated_by=VALUES(updated_by)')
        ->execute([(int) $provider['id'], $encrypted]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo "STRIPE_LIVE=OK\n";
