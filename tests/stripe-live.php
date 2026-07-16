<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;

$pdo = Database::connection();
$row = $pdo->query(
    "SELECT p.environment,p.is_enabled,c.encrypted_payload "
    . "FROM payment_providers p "
    . "JOIN payment_provider_credentials c ON c.provider_id=p.id "
    . "WHERE p.code='stripe' LIMIT 1"
)->fetch();
if (!$row || (int) $row['is_enabled'] !== 1 || $row['environment'] !== 'live') {
    throw new RuntimeException('Stripe live nu este activ.');
}
$credentials = json_decode(Encryptor::decrypt((string) $row['encrypted_payload']), true) ?: [];
$secret = trim((string) ($credentials['secret_key'] ?? ''));
if (!str_starts_with($secret, 'sk_live_')) {
    throw new RuntimeException('Cheia Stripe nu este live.');
}

$request = static function (
    string $method,
    string $path,
    array $params = [],
    ?string $idempotency = null
) use ($secret): array {
    $headers = ['Authorization: Bearer ' . $secret];
    if ($idempotency !== null) {
        $headers[] = 'Idempotency-Key: ' . $idempotency;
    }
    $url = 'https://api.stripe.com/v1' . $path;
    if ($method === 'GET' && $params) {
        $url .= '?' . http_build_query($params);
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($method !== 'GET') {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false || $error !== '') {
        throw new RuntimeException('Stripe API indisponibil: ' . $error);
    }
    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded) || $status >= 400) {
        throw new RuntimeException((string) ($decoded['error']['message'] ?? 'Răspuns Stripe invalid.'));
    }
    return $decoded;
};

$account = $request('GET', '/account');
if (empty($account['id']) || empty($account['charges_enabled'])) {
    throw new RuntimeException('Contul Stripe live nu poate accepta plăți.');
}

$run = bin2hex(random_bytes(8));
$sessionId = '';
$priceId = '';
$productId = '';
try {
    $session = $request('POST', '/checkout/sessions', [
        'mode' => 'payment',
        'locale' => 'ro',
        'success_url' => 'https://maison-bebe.ro/?qa_stripe=success',
        'cancel_url' => 'https://maison-bebe.ro/?qa_stripe=cancel',
        'client_reference_id' => 'codex-launch-audit-' . $run,
        'line_items[0][price_data][currency]' => 'ron',
        'line_items[0][price_data][unit_amount]' => '500',
        'line_items[0][price_data][product_data][name]' => 'Maison Bébé · audit lansare',
        'line_items[0][price_data][product_data][description]' => 'Obiect QA arhivat automat; nu este destinat vânzării.',
        'line_items[0][price_data][product_data][metadata][purpose]' => 'launch_readiness_audit',
        'line_items[0][quantity]' => '1',
        'metadata[purpose]' => 'launch_readiness_audit',
        'metadata[run]' => $run,
    ], 'maison-bebe-live-readiness-' . $run);

    $sessionId = (string) ($session['id'] ?? '');
    if (
        !str_starts_with($sessionId, 'cs_live_')
        || empty($session['livemode'])
        || ($session['status'] ?? '') !== 'open'
        || !str_starts_with((string) ($session['url'] ?? ''), 'https://checkout.stripe.com/')
    ) {
        throw new RuntimeException('Sesiunea Stripe live nu este validă.');
    }

    $lineItems = $request('GET', '/checkout/sessions/' . rawurlencode($sessionId) . '/line_items');
    $price = $lineItems['data'][0]['price'] ?? [];
    $priceId = (string) ($price['id'] ?? '');
    $productId = (string) ($price['product'] ?? '');
    if (
        !str_starts_with($priceId, 'price_')
        || !str_starts_with($productId, 'prod_')
        || empty($price['livemode'])
        || (int) ($price['unit_amount'] ?? 0) !== 500
        || strtolower((string) ($price['currency'] ?? '')) !== 'ron'
    ) {
        throw new RuntimeException('Linia de plată Stripe live este invalidă.');
    }
} finally {
    if ($sessionId !== '') {
        $current = $request('GET', '/checkout/sessions/' . rawurlencode($sessionId));
        if (($current['status'] ?? '') === 'open') {
            $expired = $request('POST', '/checkout/sessions/' . rawurlencode($sessionId) . '/expire');
            if (($expired['status'] ?? '') !== 'expired') {
                throw new RuntimeException('Sesiunea Stripe QA nu a putut fi expirată.');
            }
        }
    }
}

fwrite(STDOUT, "Stripe live Checkout session: OK (created, verified and expired without a charge)\n");
