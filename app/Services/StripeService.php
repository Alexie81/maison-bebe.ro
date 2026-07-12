<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use RuntimeException;
use Throwable;

final class StripeService
{
    private ?array $provider = null;
    private ?array $credentials = null;

    public function isEnabled(): bool
    {
        $provider = $this->provider(false);
        return $provider !== null && (int) $provider['is_enabled'] === 1 && $this->secretKey(false) !== null;
    }

    public function syncProduct(int $productId): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $pdo = Database::connection();
        $statement = $pdo->prepare("SELECT p.*,m.path image_path FROM products p LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1 LEFT JOIN media_assets m ON m.id=pi.media_id WHERE p.id=? LIMIT 1");
        $statement->execute([$productId]);
        $product = $statement->fetch();
        if (!$product) {
            return null;
        }

        $active = $product['status'] === 'active' && empty($product['deleted_at']);
        $stripeProductId = trim((string) ($product['stripe_product_id'] ?? ''));

        if ($stripeProductId === '' && !$active) {
            return null;
        }

        $productParams = [
            'name' => (string) $product['name'],
            'active' => $active ? 'true' : 'false',
            'description' => $this->plainText((string) ($product['short_description'] ?: $product['description_html'] ?: 'Maison BÃ©bÃ©')), 
            'metadata[local_product_id]' => (string) $product['id'],
            'metadata[sku]' => (string) $product['sku'],
            'metadata[slug]' => (string) $product['slug'],
        ];
        if (!empty($product['image_path'])) {
            $productParams['images[0]'] = absolute_url((string) $product['image_path']);
        }

        try {
            if ($stripeProductId === '') {
                $stripeProduct = $this->request('POST', '/products', $productParams, 'product-create-' . $productId . '-' . substr(hash('sha256', (string) $product['updated_at']), 0, 12));
                $stripeProductId = (string) $stripeProduct['id'];
                $pdo->prepare('UPDATE products SET stripe_product_id=?,stripe_synced_at=NOW(),stripe_sync_error=NULL WHERE id=?')->execute([$stripeProductId, $productId]);
            } else {
                $stripeProduct = $this->request('POST', '/products/' . rawurlencode($stripeProductId), $productParams, 'product-update-' . $productId . '-' . substr(hash('sha256', (string) $product['updated_at']), 0, 12));
                $pdo->prepare('UPDATE products SET stripe_synced_at=NOW(),stripe_sync_error=NULL WHERE id=?')->execute([$productId]);
            }

            $variants = $pdo->prepare('SELECT * FROM product_variants WHERE product_id=? ORDER BY id');
            $variants->execute([$productId]);
            $defaultPrice = null;
            foreach ($variants->fetchAll() as $variant) {
                $priceId = $this->syncVariantPrice($stripeProductId, $product, $variant, $active);
                if ($defaultPrice === null && $priceId !== null && $active && (int) $variant['is_active'] === 1) {
                    $defaultPrice = $priceId;
                }
            }

            if ($defaultPrice !== null) {
                $this->request('POST', '/products/' . rawurlencode($stripeProductId), ['default_price' => $defaultPrice], 'product-default-price-' . $productId . '-' . $defaultPrice);
            }

            return ['product_id' => $stripeProductId, 'default_price' => $defaultPrice];
        } catch (Throwable $exception) {
            $pdo->prepare('UPDATE products SET stripe_sync_error=? WHERE id=?')->execute([mb_substr($exception->getMessage(), 0, 500), $productId]);
            throw $exception;
        }
    }

    public function archiveProduct(int $productId): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->syncProduct($productId);
    }

    public function createCheckoutSession(int $orderId): string
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Stripe nu este configurat sau activ.');
        }

        $pdo = Database::connection();
        $orderStatement = $pdo->prepare('SELECT * FROM orders WHERE id=? LIMIT 1');
        $orderStatement->execute([$orderId]);
        $order = $orderStatement->fetch();
        if (!$order) {
            throw new RuntimeException('Comanda nu existÄƒ.');
        }

        $itemsStatement = $pdo->prepare('SELECT oi.*,p.id product_id,p.slug,p.stripe_product_id,v.stripe_price_id FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id LEFT JOIN product_variants v ON v.id=oi.variant_id WHERE oi.order_id=? ORDER BY oi.id');
        $itemsStatement->execute([$orderId]);
        $items = $itemsStatement->fetchAll();
        foreach (array_unique(array_filter(array_map(static fn(array $item): int => (int) ($item['product_id'] ?? 0), $items))) as $productId) {
            $this->syncProduct($productId);
        }
        $itemsStatement->execute([$orderId]);
        $items = $itemsStatement->fetchAll();

        $params = [
            'mode' => 'payment',
            'client_reference_id' => (string) $order['order_number'],
            'customer_email' => (string) $order['email'],
            'locale' => 'ro',
            'billing_address_collection' => 'required',
            'success_url' => absolute_url('/comanda-confirmata/' . $order['public_token']) . '?stripe_session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => absolute_url('/comanda-confirmata/' . $order['public_token']) . '?plata=anulata',
            'metadata[local_order_id]' => (string) $order['id'],
            'metadata[order_number]' => (string) $order['order_number'],
            'metadata[public_token]' => (string) $order['public_token'],
            'payment_intent_data[metadata][local_order_id]' => (string) $order['id'],
            'payment_intent_data[metadata][order_number]' => (string) $order['order_number'],
        ];

        $lineIndex = 0;
        if ((int) $order['discount_total_minor'] > 0) {
            $params['line_items[0][price_data][currency]'] = strtolower((string) $order['currency']);
            $params['line_items[0][price_data][unit_amount]'] = (string) max(50, (int) $order['grand_total_minor']);
            $params['line_items[0][price_data][product_data][name]'] = 'Comanda ' . $order['order_number'] . ' Maison BÃ©bÃ©';
            $params['line_items[0][quantity]'] = '1';
            $lineIndex = 1;
        } else {
            foreach ($items as $item) {
                $priceId = trim((string) ($item['stripe_price_id'] ?? ''));
                if ($priceId !== '') {
                    $params['line_items[' . $lineIndex . '][price]'] = $priceId;
                    $params['line_items[' . $lineIndex . '][quantity]'] = (string) max(1, (int) $item['quantity']);
                } else {
                    $params['line_items[' . $lineIndex . '][price_data][currency]'] = strtolower((string) $order['currency']);
                    $params['line_items[' . $lineIndex . '][price_data][unit_amount]'] = (string) max(50, (int) $item['unit_price_minor']);
                    $params['line_items[' . $lineIndex . '][price_data][product_data][name]'] = (string) $item['name_snapshot'];
                    $params['line_items[' . $lineIndex . '][quantity]'] = (string) max(1, (int) $item['quantity']);
                }
                $lineIndex++;
            }
            if ((int) $order['shipping_total_minor'] > 0) {
                $params['line_items[' . $lineIndex . '][price_data][currency]'] = strtolower((string) $order['currency']);
                $params['line_items[' . $lineIndex . '][price_data][unit_amount]'] = (string) (int) $order['shipping_total_minor'];
                $params['line_items[' . $lineIndex . '][price_data][product_data][name]'] = 'Livrare Maison BÃ©bÃ©';
                $params['line_items[' . $lineIndex . '][quantity]'] = '1';
            }
        }

        $session = $this->request('POST', '/checkout/sessions', $params, 'checkout-session-order-' . $orderId);
        $pdo->prepare("UPDATE payments SET provider_payment_id=?,status='pending',metadata_json=?,updated_at=NOW() WHERE order_id=? AND provider='stripe'")->execute([(string) $session['id'], json_encode(['checkout_session' => $session['id'], 'url' => $session['url'] ?? null], JSON_UNESCAPED_SLASHES), $orderId]);
        return (string) $session['url'];
    }

    public function handleWebhook(string $payload, string $signature): array
    {
        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
            throw new RuntimeException('Payload Stripe invalid.');
        }

        $validSignature = $this->verifySignature($payload, $signature);
        $pdo = Database::connection();
        $pdo->prepare("INSERT INTO payment_events (provider,provider_event_id,event_type,signature_valid,payload_json,processing_status) VALUES ('stripe',?,?,?,?, 'received') ON DUPLICATE KEY UPDATE provider_event_id=provider_event_id")->execute([(string) $event['id'], (string) $event['type'], $validSignature ? 1 : 0, json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);

        $object = $event['data']['object'] ?? [];
        $orderId = (int) ($object['metadata']['local_order_id'] ?? 0);
        $payment = null;
        if ($orderId > 0) {
            $paymentStatement = $pdo->prepare("SELECT id FROM payments WHERE order_id=? AND provider='stripe' ORDER BY id DESC LIMIT 1");
            $paymentStatement->execute([$orderId]);
            $payment = (int) $paymentStatement->fetchColumn() ?: null;
        }

        if ((string) $event['type'] === 'checkout.session.completed' && $orderId > 0 && ($object['payment_status'] ?? '') === 'paid') {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE payments SET status='succeeded',provider_payment_id=COALESCE(provider_payment_id,?),metadata_json=?,updated_at=NOW() WHERE order_id=? AND provider='stripe'")->execute([(string) ($object['id'] ?? ''), json_encode(['checkout_session' => $object['id'] ?? null, 'payment_intent' => $object['payment_intent'] ?? null], JSON_UNESCAPED_SLASHES), $orderId]);
                $pdo->prepare("UPDATE orders SET payment_status='paid',order_status=IF(order_status='new','confirmed',order_status),updated_at=NOW() WHERE id=?")->execute([$orderId]);
                $pdo->prepare("INSERT INTO order_status_history (order_id,old_status,new_status,public_label,public_message,is_public,source) VALUES (?,NULL,'confirmed','PlatÄƒ confirmatÄƒ','Plata online a fost confirmatÄƒ È™i pregÄƒtim comanda.',1,'stripe')")->execute([$orderId]);
                $pdo->prepare("UPDATE payment_events SET payment_id=?,processing_status='processed',processed_at=NOW() WHERE provider='stripe' AND provider_event_id=?")->execute([$payment, (string) $event['id']]);
                $pdo->commit();
                $invoiceCheck=$pdo->prepare("SELECT id FROM invoices WHERE order_id=? AND status='issued' LIMIT 1");$invoiceCheck->execute([$orderId]);
                if($invoiceCheck->fetchColumn()){(new InvoiceService())->issueForOrder($orderId,false);}
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $pdo->prepare("UPDATE payment_events SET processing_status='failed',error_message=? WHERE provider='stripe' AND provider_event_id=?")->execute([mb_substr($exception->getMessage(), 0, 1000), (string) $event['id']]);
                throw $exception;
            }
        } elseif (in_array((string) $event['type'], ['checkout.session.expired', 'payment_intent.payment_failed'], true) && $orderId > 0) {
            $pdo->prepare("UPDATE payments SET status='failed',updated_at=NOW() WHERE order_id=? AND provider='stripe'")->execute([$orderId]);
            $pdo->prepare("UPDATE payment_events SET payment_id=?,processing_status='processed',processed_at=NOW() WHERE provider='stripe' AND provider_event_id=?")->execute([$payment, (string) $event['id']]);
        } else {
            $pdo->prepare("UPDATE payment_events SET payment_id=?,processing_status='ignored',processed_at=NOW() WHERE provider='stripe' AND provider_event_id=?")->execute([$payment, (string) $event['id']]);
        }

        return ['event' => (string) $event['id'], 'type' => (string) $event['type']];
    }

    private function syncVariantPrice(string $stripeProductId, array $product, array $variant, bool $productActive): ?string
    {
        $pdo = Database::connection();
        $variantActive = $productActive && (int) $variant['is_active'] === 1 && (int) $variant['price_minor'] > 0;
        $priceId = trim((string) ($variant['stripe_price_id'] ?? ''));
        $storedMinor = isset($variant['stripe_price_minor']) ? (int) $variant['stripe_price_minor'] : null;

        if (!$variantActive) {
            if ($priceId !== '') {
                $this->request('POST', '/prices/' . rawurlencode($priceId), ['active' => 'false'], 'price-archive-' . $variant['id'] . '-' . time());
            }
            $pdo->prepare('UPDATE product_variants SET stripe_synced_at=NOW(),stripe_sync_error=NULL WHERE id=?')->execute([(int) $variant['id']]);
            return null;
        }

        if ($priceId === '' || $storedMinor !== (int) $variant['price_minor']) {
            if ($priceId !== '') {
                $this->request('POST', '/prices/' . rawurlencode($priceId), ['active' => 'false'], 'price-replaced-' . $variant['id'] . '-' . $variant['price_minor']);
            }
            $price = $this->request('POST', '/prices', [
                'product' => $stripeProductId,
                'currency' => 'ron',
                'unit_amount' => (string) (int) $variant['price_minor'],
                'nickname' => (string) $variant['sku'],
                'metadata[local_product_id]' => (string) $product['id'],
                'metadata[local_variant_id]' => (string) $variant['id'],
                'metadata[sku]' => (string) $variant['sku'],
            ], 'price-create-' . $variant['id'] . '-' . $variant['price_minor']);
            $priceId = (string) $price['id'];
        } else {
            $this->request('POST', '/prices/' . rawurlencode($priceId), [
                'active' => 'true',
                'metadata[local_product_id]' => (string) $product['id'],
                'metadata[local_variant_id]' => (string) $variant['id'],
                'metadata[sku]' => (string) $variant['sku'],
            ], 'price-update-' . $variant['id'] . '-' . substr(hash('sha256', (string) $variant['updated_at']), 0, 12));
        }

        $pdo->prepare('UPDATE product_variants SET stripe_price_id=?,stripe_price_minor=?,stripe_synced_at=NOW(),stripe_sync_error=NULL WHERE id=?')->execute([$priceId, (int) $variant['price_minor'], (int) $variant['id']]);
        return $priceId;
    }

    private function request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): array
    {
        $secret = $this->secretKey(true);
        $url = 'https://api.stripe.com/v1' . $path;
        $headers = ['Authorization: Bearer ' . $secret];
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: maison-bebe-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $idempotencyKey);
        }
        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('cURL nu a putut fi iniÈ›ializat.');
        }
        if ($method === 'GET' && $params) {
            $url .= '?' . http_build_query($params);
        }
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
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
        if ($body === false) {
            throw new RuntimeException('Stripe API nu rÄƒspunde: ' . $error);
        }
        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('RÄƒspuns Stripe invalid.');
        }
        if ($status >= 400) {
            throw new RuntimeException((string) ($decoded['error']['message'] ?? 'Eroare Stripe API.'));
        }
        $this->markHealth('healthy', 'Stripe API test/sync OK.');
        return $decoded;
    }

    private function provider(bool $required = true): ?array
    {
        if ($this->provider !== null) {
            return $this->provider;
        }
        $statement = Database::connection()->prepare('SELECT p.*,c.encrypted_payload FROM payment_providers p LEFT JOIN payment_provider_credentials c ON c.provider_id=p.id WHERE p.code=\'stripe\' LIMIT 1');
        $statement->execute();
        $provider = $statement->fetch();
        if (!$provider) {
            if ($required) {
                throw new RuntimeException('Providerul Stripe nu existÄƒ Ã®n baza de date.');
            }
            return null;
        }
        return $this->provider = $provider;
    }

    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }
        $provider = $this->provider();
        if (empty($provider['encrypted_payload'])) {
            throw new RuntimeException('Cheia secretÄƒ Stripe lipseÈ™te.');
        }
        $decoded = json_decode(Encryptor::decrypt((string) $provider['encrypted_payload']), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Credentialele Stripe sunt invalide.');
        }
        return $this->credentials = $decoded;
    }

    private function secretKey(bool $required): ?string
    {
        try {
            $secret = trim((string) ($this->credentials()['secret_key'] ?? ''));
            if ($secret !== '') {
                return $secret;
            }
        } catch (Throwable $exception) {
            if ($required) {
                throw $exception;
            }
        }
        if ($required) {
            throw new RuntimeException('Cheia secretÄƒ Stripe lipseÈ™te.');
        }
        return null;
    }

    private function webhookSecret(): ?string
    {
        try {
            $secret = trim((string) ($this->credentials()['webhook_secret'] ?? ''));
            return $secret !== '' ? $secret : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function verifySignature(string $payload, string $signature): bool
    {
        $secret = $this->webhookSecret();
        if ($secret === null) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $signature) as $piece) {
            [$key, $value] = array_pad(explode('=', $piece, 2), 2, '');
            $parts[$key][] = $value;
        }
        $timestamp = (int) ($parts['t'][0] ?? 0);
        if ($timestamp <= 0 || abs(time() - $timestamp) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($parts['v1'] ?? [] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }
        return false;
    }

    private function markHealth(string $status, string $message): void
    {
        $provider = $this->provider(false);
        if (!$provider) {
            return;
        }
        Database::connection()->prepare('INSERT INTO payment_provider_health (provider_id,status,message,checked_at) VALUES (?,?,?,NOW())')->execute([(int) $provider['id'], $status, mb_substr($message, 0, 500)]);
    }

    private function plainText(string $html): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        return mb_substr($text, 0, 500) ?: 'Maison BÃ©bÃ©';
    }
}
