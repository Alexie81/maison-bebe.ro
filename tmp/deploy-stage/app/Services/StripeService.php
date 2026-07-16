<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use MaisonBebe\Core\Env;
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
        $statement = $pdo->prepare("SELECT p.*,COALESCE(m.path,gm.path,'/assets/images/packaging-reference.png') image_path FROM products p LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1 LEFT JOIN media_assets m ON m.id=pi.media_id LEFT JOIN gift_box_templates gt ON gt.product_id=p.id LEFT JOIN media_assets gm ON gm.id=gt.image_id WHERE p.id=? LIMIT 1");
        $statement->execute([$productId]);
        $product = $statement->fetch();
        if (!$product) {
            return null;
        }

        $columns = $this->catalogColumns();
        $active = $product['status'] === 'active' && empty($product['deleted_at']);
        $stripeProductId = trim((string) ($product[$columns['product_id']] ?? ''));

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
            'metadata[maison_environment]' => $this->isTestMode() ? 'test' : 'live',
            'url' => $this->publicCatalogUrl('/produs/' . $product['slug']),
        ];
        if (!empty($product['image_path'])) {
            $productParams['images[0]'] = $this->publicCatalogUrl((string) $product['image_path']);
        }

        try {
            if ($stripeProductId === '') {
                $stripeProduct = $this->request('POST', '/products', $productParams, 'product-create-' . $this->resourceFingerprint([$productId, $product['sku'], $product['slug'], $product['created_at'] ?? '']));
                $stripeProductId = (string) $stripeProduct['id'];
                $pdo->prepare("UPDATE products SET {$columns['product_id']}=?,{$columns['product_synced_at']}=NOW(),{$columns['product_error']}=NULL WHERE id=?")->execute([$stripeProductId, $productId]);
            } else {
                $stripeProduct = $this->request('POST', '/products/' . rawurlencode($stripeProductId), $productParams, 'product-update-' . $this->resourceFingerprint([$stripeProductId, $productId, $product['updated_at'] ?? '']));
                $pdo->prepare("UPDATE products SET {$columns['product_synced_at']}=NOW(),{$columns['product_error']}=NULL WHERE id=?")->execute([$productId]);
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
                $this->request('POST', '/products/' . rawurlencode($stripeProductId), ['default_price' => $defaultPrice], 'product-default-price-' . $this->resourceFingerprint([$stripeProductId, $productId, $defaultPrice]));
            }

            return ['product_id' => $stripeProductId, 'default_price' => $defaultPrice];
        } catch (Throwable $exception) {
            $pdo->prepare("UPDATE products SET {$columns['product_error']}=? WHERE id=?")->execute([mb_substr($exception->getMessage(), 0, 500), $productId]);
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

        $itemsStatement = $pdo->prepare('SELECT oi.*,p.id product_id,p.slug,p.stripe_product_id,p.stripe_test_product_id,v.stripe_price_id,v.stripe_test_price_id FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id LEFT JOIN product_variants v ON v.id=oi.variant_id WHERE oi.order_id=? ORDER BY oi.id');
        $itemsStatement->execute([$orderId]);
        $items = $itemsStatement->fetchAll();
        $testMode = $this->isTestMode();
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
            // Adresa completă a fost deja validată și salvată de checkout-ul local.
            // Stripe cere automat doar datele strict necesare cardului sau wallet-ului.
            'billing_address_collection' => 'auto',
            'success_url' => absolute_url('/comanda-confirmata/' . $order['public_token']) . '?plata=efectuata&stripe_session_id={CHECKOUT_SESSION_ID}',
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
                $priceId = trim((string) ($item[$testMode ? 'stripe_test_price_id' : 'stripe_price_id'] ?? ''));
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
                $params['line_items[' . $lineIndex . '][price_data][product_data][name]'] = 'Livrare prin curier';
                $params['line_items[' . $lineIndex . '][quantity]'] = '1';
            }
        }

        $sessionFingerprint = substr(hash('sha256', http_build_query($params)), 0, 20);
        $session = $this->request('POST', '/checkout/sessions', $params, 'checkout-session-' . $this->resourceFingerprint([$order['order_number'], $order['public_token'], $sessionFingerprint]));
        $pdo->prepare("UPDATE payments SET provider_payment_id=?,status='pending',metadata_json=?,updated_at=NOW() WHERE order_id=? AND provider='stripe'")->execute([(string) $session['id'], json_encode(['checkout_session' => $session['id'], 'url' => $session['url'] ?? null], JSON_UNESCAPED_SLASHES), $orderId]);
        return (string) $session['url'];
    }

    public function reconcileCheckoutSession(string $sessionId, string $publicToken): bool
    {
        if (!preg_match('/^cs_(?:test|live)_[A-Za-z0-9]+$/', $sessionId)) {
            return false;
        }
        $session = $this->request('GET', '/checkout/sessions/' . rawurlencode($sessionId));
        $sessionToken = (string) ($session['metadata']['public_token'] ?? '');
        if ($sessionToken === '' || !hash_equals($publicToken, $sessionToken)) {
            throw new RuntimeException('Sesiunea Stripe nu aparține acestei comenzi.');
        }
        if ((string) ($session['payment_status'] ?? '') !== 'paid') {
            return false;
        }
        $orderId = (int) ($session['metadata']['local_order_id'] ?? 0);
        if ($orderId < 1) {
            throw new RuntimeException('Sesiunea Stripe nu conține comanda locală.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT payment_status,order_status FROM orders WHERE id=? FOR UPDATE');
            $lock->execute([$orderId]);
            $order = $lock->fetch();
            if (!$order) {
                throw new RuntimeException('Comanda Stripe nu mai există.');
            }
            $pdo->prepare("UPDATE payments SET status='succeeded',provider_payment_id=?,metadata_json=?,updated_at=NOW() WHERE order_id=? AND provider='stripe'")->execute([(string) $session['id'], json_encode(['checkout_session' => $session['id'], 'payment_intent' => $session['payment_intent'] ?? null], JSON_UNESCAPED_SLASHES), $orderId]);
            if ((string) $order['payment_status'] !== 'paid') {
                $newStatus = (string) $order['order_status'] === 'new' ? 'confirmed' : (string) $order['order_status'];
                $pdo->prepare("UPDATE orders SET payment_status='paid',order_status=?,updated_at=NOW() WHERE id=?")->execute([$newStatus, $orderId]);
                $pdo->prepare("INSERT INTO order_status_history (order_id,old_status,new_status,public_label,public_message,is_public,source) VALUES (?,?,?,?,?,1,'stripe_return')")->execute([$orderId, (string) $order['order_status'], $newStatus, 'Plată confirmată', 'Plata online a fost confirmată și pregătim comanda.']);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        return true;
    }

    public function diagnostics(): array
    {
        $provider = $this->provider(false);
        $secret = $this->secretKey(false) ?? '';
        $account = $secret !== '' ? $this->request('GET', '/account') : [];
        $wallets = ['apple_pay' => 'unknown', 'google_pay' => 'unknown', 'card' => 'unknown'];
        if ($secret !== '') {
            try {
                $wallets = $this->walletState($this->defaultPaymentConfiguration());
            } catch (Throwable $exception) {
                $wallets['error'] = $exception->getMessage();
            }
        }
        return [
            'enabled' => $provider !== null && (int) $provider['is_enabled'] === 1,
            'environment' => (string) ($provider['environment'] ?? ''),
            'key_mode' => str_starts_with($secret, 'sk_test_') ? 'test' : (str_starts_with($secret, 'sk_live_') ? 'live' : 'missing'),
            'account_id' => (string) ($account['id'] ?? ''),
            // Stripe's Account object has no `livemode` field. A successful
            // /v1/account response authenticated with sk_live_ confirms live mode.
            'api_livemode' => str_starts_with($secret, 'sk_live_') && (string) ($account['id'] ?? '') !== '',
            'charges_enabled' => (bool) ($account['charges_enabled'] ?? false),
            'payouts_enabled' => (bool) ($account['payouts_enabled'] ?? false),
            'webhook_configured' => $this->webhookSecret() !== null,
            'wallets' => $wallets,
        ];
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
            $lastError = is_array($object['last_payment_error'] ?? null) ? $object['last_payment_error'] : [];
            $declineCode = (string) ($lastError['decline_code'] ?? $lastError['code'] ?? 'payment_failed');
            $publicMessage = match ($declineCode) {
                'insufficient_funds' => 'Plata nu a fost acceptată: fonduri insuficiente.',
                'card_declined' => 'Plata nu a fost acceptată de banca emitentă.',
                'expired_card' => 'Cardul folosit este expirat.',
                'incorrect_cvc' => 'Codul de securitate al cardului este incorect.',
                default => (string) $event['type'] === 'checkout.session.expired'
                    ? 'Sesiunea de plată a expirat înainte de finalizare.'
                    : 'Plata cu cardul a fost refuzată.',
            };
            $failureMetadata = json_encode([
                'stripe_event' => (string) $event['id'],
                'failure_code' => $declineCode,
                'failure_message' => (string) ($lastError['message'] ?? $publicMessage),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $pdo->prepare("UPDATE payments SET status='failed',metadata_json=?,updated_at=NOW() WHERE order_id=? AND provider='stripe'")->execute([$failureMetadata, $orderId]);
            $pdo->prepare("INSERT INTO order_status_history (order_id,old_status,new_status,public_label,public_message,is_public,source) SELECT id,order_status,order_status,'Plată nereușită',?,1,'stripe' FROM orders WHERE id=?")->execute([$publicMessage, $orderId]);
            $pdo->prepare("UPDATE payment_events SET payment_id=?,processing_status='processed',processed_at=NOW() WHERE provider='stripe' AND provider_event_id=?")->execute([$payment, (string) $event['id']]);
        } else {
            $pdo->prepare("UPDATE payment_events SET payment_id=?,processing_status='ignored',processed_at=NOW() WHERE provider='stripe' AND provider_event_id=?")->execute([$payment, (string) $event['id']]);
        }

        return ['event' => (string) $event['id'], 'type' => (string) $event['type']];
    }

    private function syncVariantPrice(string $stripeProductId, array $product, array $variant, bool $productActive): ?string
    {
        $pdo = Database::connection();
        $columns = $this->catalogColumns();
        $variantActive = $productActive && (int) $variant['is_active'] === 1 && (int) $variant['price_minor'] > 0;
        $priceId = trim((string) ($variant[$columns['price_id']] ?? ''));
        $storedMinor = isset($variant[$columns['price_minor']]) ? (int) $variant[$columns['price_minor']] : null;

        if (!$variantActive) {
            if ($priceId !== '') {
                $this->request('POST', '/prices/' . rawurlencode($priceId), ['active' => 'false'], 'price-archive-' . $variant['id'] . '-' . time());
            }
            $pdo->prepare("UPDATE product_variants SET {$columns['price_synced_at']}=NOW(),{$columns['price_error']}=NULL WHERE id=?")->execute([(int) $variant['id']]);
            return null;
        }

        if ($priceId === '' || $storedMinor !== (int) $variant['price_minor']) {
            if ($priceId !== '') {
                $this->request('POST', '/prices/' . rawurlencode($priceId), ['active' => 'false'], 'price-replaced-' . $this->resourceFingerprint([$priceId, $variant['id'], $variant['price_minor']]));
            }
            $price = $this->request('POST', '/prices', [
                'product' => $stripeProductId,
                'currency' => 'ron',
                'unit_amount' => (string) (int) $variant['price_minor'],
                'nickname' => (string) $variant['sku'],
                'metadata[local_product_id]' => (string) $product['id'],
                'metadata[local_variant_id]' => (string) $variant['id'],
                'metadata[sku]' => (string) $variant['sku'],
                'metadata[maison_environment]' => $this->isTestMode() ? 'test' : 'live',
            ], 'price-create-' . $this->resourceFingerprint([$stripeProductId, $product['id'], $variant['id'], $variant['sku'], $variant['price_minor']]));
            $priceId = (string) $price['id'];
        } else {
            $this->request('POST', '/prices/' . rawurlencode($priceId), [
                'active' => 'true',
                'metadata[local_product_id]' => (string) $product['id'],
                'metadata[local_variant_id]' => (string) $variant['id'],
                'metadata[sku]' => (string) $variant['sku'],
                'metadata[maison_environment]' => $this->isTestMode() ? 'test' : 'live',
            ], 'price-update-' . $this->resourceFingerprint([$priceId, $variant['id'], $variant['updated_at'] ?? '']));
        }

        $pdo->prepare("UPDATE product_variants SET {$columns['price_id']}=?,{$columns['price_minor']}=?,{$columns['price_synced_at']}=NOW(),{$columns['price_error']}=NULL WHERE id=?")->execute([$priceId, (int) $variant['price_minor'], (int) $variant['id']]);
        return $priceId;
    }

    public function enableWallets(): array
    {
        $configuration = $this->defaultPaymentConfiguration();
        if (empty($configuration['id'])) {
            throw new RuntimeException('Configurația implicită Stripe nu a fost găsită.');
        }
        $updated = $this->request('POST', '/payment_method_configurations/' . rawurlencode((string) $configuration['id']), [
            'apple_pay[display_preference][preference]' => 'on',
            'google_pay[display_preference][preference]' => 'on',
            'card[display_preference][preference]' => 'on',
        ]);
        return $this->walletState($updated);
    }

    private function defaultPaymentConfiguration(): array
    {
        $result = $this->request('GET', '/payment_method_configurations', ['limit' => 100]);
        $configurations = is_array($result['data'] ?? null) ? $result['data'] : [];
        foreach ($configurations as $configuration) {
            if (!empty($configuration['is_default']) && !empty($configuration['active'])) {
                return $configuration;
            }
        }
        foreach ($configurations as $configuration) {
            if (!empty($configuration['active'])) {
                return $configuration;
            }
        }
        return [];
    }

    private function walletState(array $configuration): array
    {
        $state = static function (array $wallet): string {
            if (!empty($wallet['available'])) return 'available';
            return (string) ($wallet['display_preference']['value'] ?? $wallet['display_preference']['preference'] ?? 'off');
        };
        return [
            'configuration_id' => (string) ($configuration['id'] ?? ''),
            'apple_pay' => $state((array) ($configuration['apple_pay'] ?? [])),
            'google_pay' => $state((array) ($configuration['google_pay'] ?? [])),
            'card' => $state((array) ($configuration['card'] ?? [])),
        ];
    }

    private function catalogColumns(): array
    {
        if ($this->isTestMode()) {
            return [
                'product_id' => 'stripe_test_product_id',
                'product_synced_at' => 'stripe_test_synced_at',
                'product_error' => 'stripe_test_sync_error',
                'price_id' => 'stripe_test_price_id',
                'price_minor' => 'stripe_test_price_minor',
                'price_synced_at' => 'stripe_test_synced_at',
                'price_error' => 'stripe_test_sync_error',
            ];
        }
        return [
            'product_id' => 'stripe_product_id',
            'product_synced_at' => 'stripe_synced_at',
            'product_error' => 'stripe_sync_error',
            'price_id' => 'stripe_price_id',
            'price_minor' => 'stripe_price_minor',
            'price_synced_at' => 'stripe_synced_at',
            'price_error' => 'stripe_sync_error',
        ];
    }

    private function resourceFingerprint(array $parts): string
    {
        $scope = rtrim((string) Env::get('APP_URL', ''), '/');
        $environment = $this->isTestMode() ? 'test' : 'live';
        return $environment . '-' . substr(
            hash('sha256', implode('|', array_map('strval', [$scope, ...$parts]))),
            0,
            32
        );
    }

    private function publicCatalogUrl(string $path): string
    {
        $path = trim($path);
        if (preg_match('#^https://#i', $path)) {
            return $path;
        }
        $base = rtrim((string) Env::get('STRIPE_PUBLIC_BASE_URL', ''), '/');
        if ($base === '') {
            $candidate = absolute_url('/' . ltrim($path, '/'));
            $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
            $scheme = strtolower((string) parse_url($candidate, PHP_URL_SCHEME));
            if ($scheme === 'https' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                return $candidate;
            }
            $base = 'https://maison-bebe.ro';
        }
        return $base . '/' . ltrim($path, '/');
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

    private function isTestMode(): bool
    {
        $provider = $this->provider();
        return in_array((string) $provider['environment'], ['test', 'sandbox'], true);
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
