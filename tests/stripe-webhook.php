<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use MaisonBebe\Services\StripeService;

$pdo = Database::connection();
$eventId = 'evt_codex_' . bin2hex(random_bytes(10));
$payload = json_encode([
    'id' => $eventId,
    'type' => 'customer.created',
    'data' => ['object' => ['id' => 'cus_codex']],
], JSON_THROW_ON_ERROR);

try {
    (new StripeService())->handleWebhook($payload, 't=' . time() . ',v1=invalid');
    throw new RuntimeException('Webhook-ul cu semnătură invalidă nu a fost respins.');
} catch (RuntimeException $exception) {
    if ($exception->getMessage() === 'Webhook-ul cu semnătură invalidă nu a fost respins.') {
        throw $exception;
    }
}

$check = $pdo->prepare("SELECT COUNT(*) FROM payment_events WHERE provider='stripe' AND provider_event_id=?");
$check->execute([$eventId]);
if ((int) $check->fetchColumn() !== 0) {
    throw new RuntimeException('Evenimentul cu semnătură invalidă a fost salvat.');
}

$credentials = $pdo->query(
    "SELECT c.encrypted_payload FROM payment_provider_credentials c "
    . "JOIN payment_providers p ON p.id=c.provider_id WHERE p.code='stripe' LIMIT 1"
)->fetchColumn();

if (is_string($credentials) && $credentials !== '') {
    $decoded = json_decode(Encryptor::decrypt($credentials), true);
    $secret = trim((string) ($decoded['webhook_secret'] ?? ''));
    if ($secret !== '') {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $service = new StripeService();
        $first = $service->handleWebhook($payload, "t={$timestamp},v1={$signature}");
        $second = $service->handleWebhook($payload, "t={$timestamp},v1={$signature}");
        $check->execute([$eventId]);
        if ((int) $check->fetchColumn() !== 1 || empty($second['duplicate']) || isset($first['duplicate'])) {
            throw new RuntimeException('Idempotency webhook Stripe a eșuat.');
        }
    }
}

$pdo->prepare("DELETE FROM payment_events WHERE provider='stripe' AND provider_event_id=?")->execute([$eventId]);
fwrite(STDOUT, "Stripe webhook security regression test: OK\n");
