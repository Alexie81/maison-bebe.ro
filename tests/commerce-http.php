<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Env;
use MaisonBebe\Services\GiftBoxService;

$base = rtrim((string) Env::get('APP_URL', ''), '/');
$pdo = Database::connection();
$startedAt = date('Y-m-d H:i:s');
$maxCart = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM carts')->fetchColumn();
$maxWishlist = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM wishlists')->fetchColumn();
$maxCustomization = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM gift_box_customizations')->fetchColumn();
$couponCode = 'QA' . strtoupper(bin2hex(random_bytes(5)));
$couponId = 0;
$orderId = 0;

$safeRecipient = (string) $pdo->query(
    "SELECT COALESCE(NULLIF(reply_to_email,''),from_email) "
    . "FROM email_senders WHERE purpose='orders' AND is_active=1 LIMIT 1"
)->fetchColumn();
if (!filter_var($safeRecipient, FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($safeRecipient), '@maison-bebe.ro')) {
    throw new RuntimeException('Adresa internă sigură pentru test lipsește.');
}
$pdo->prepare(
    "INSERT INTO order_email_recipients (email,is_active,receive_new_orders,receive_failures) "
    . "VALUES (?,1,1,1) ON DUPLICATE KEY UPDATE is_active=1,receive_new_orders=1,receive_failures=1"
)->execute([$safeRecipient]);

$normal = $pdo->query(
    "SELECT p.id product_id,v.id variant_id FROM products p "
    . "JOIN product_variants v ON v.product_id=p.id "
    . "WHERE p.status='active' AND p.deleted_at IS NULL AND p.is_gift_box=0 "
    . "AND v.is_active=1 AND (v.track_inventory=0 OR v.stock_qty>=3) "
    . "ORDER BY p.id,v.id LIMIT 1"
)->fetch();
if (!$normal) {
    throw new RuntimeException('Nu există o variantă normală disponibilă pentru test.');
}

$giftService = new GiftBoxService();
$template = null;
$components = [];
foreach ($giftService->templates(true) as $candidate) {
    $available = $giftService->componentsFor((int) $candidate['id']);
    $minimum = max(0, (int) $candidate['min_components']);
    if ((int) $candidate['stock_qty'] > 0 && count($available) >= $minimum) {
        $template = $candidate;
        $components = array_slice($available, 0, $minimum);
        break;
    }
}
if (!$template) {
    throw new RuntimeException('Nu există o configurație Gift Box completă pentru test.');
}

$cookie = tempnam(sys_get_temp_dir(), 'mb-commerce-');
$request = static function (string $method, string $path, array $data = [], array $extraHeaders = []) use ($base, $cookie): array {
    $headers = [];
    $requestHeaders = $extraHeaders;
    if (!in_array($method, ['GET', 'POST'], true)) {
        $requestHeaders[] = 'Content-Type: application/json';
    }
    $curl = curl_init($base . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'MaisonBebeCommerceAudit/1.0',
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))][] = trim($value);
            }
            return strlen($line);
        },
    ]);
    if ($method === 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    } elseif ($method !== 'GET') {
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
    }
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    return [$status, is_string($body) ? $body : '', $headers, $error];
};
$csrf = static function (string $html): string {
    if (!preg_match('/name="_csrf"\s+value="([^"]+)"/', $html, $match)) {
        throw new RuntimeException('Tokenul CSRF nu a fost găsit.');
    }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
};
$hidden = static function (string $html, string $name): string {
    $quoted = preg_quote($name, '/');
    if (!preg_match('/name="' . $quoted . '"\s+value="([^"]*)"/', $html, $match)) {
        throw new RuntimeException('Câmpul ' . $name . ' nu a fost găsit.');
    }
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
};
$json = static function (array $response, int $expected = 200): array {
    [$status, $body, , $error] = $response;
    $decoded = json_decode($body, true);
    if ($status !== $expected || $error !== '' || !is_array($decoded)) {
        throw new RuntimeException('API status ' . $status . ': ' . $body . ' ' . $error);
    }
    return $decoded;
};

try {
    [$status, $home] = $request('GET', '/');
    if ($status !== 200) {
        throw new RuntimeException('Homepage indisponibil.');
    }
    $token = $csrf($home);
    $apiHeaders = ['Accept: application/json', 'X-CSRF-TOKEN: ' . $token];

    $wishOn = $json($request('POST', '/api/wishlist/toggle', [
        '_csrf' => $token,
        'product_id' => $normal['product_id'],
    ], $apiHeaders));
    $wishOff = $json($request('POST', '/api/wishlist/toggle', [
        '_csrf' => $token,
        'product_id' => $normal['product_id'],
    ], $apiHeaders));
    if (empty($wishOn['active']) || !empty($wishOff['active'])) {
        throw new RuntimeException('Toggle-ul de favorite nu este reversibil.');
    }
    echo "[OK] Wishlist toggle on/off\n";

    $added = $json($request('POST', '/api/cart/items', [
        '_csrf' => $token,
        'variant_id' => $normal['variant_id'],
        'quantity' => 1,
    ], $apiHeaders));
    $itemId = (int) ($added['item']['item_id'] ?? 0);
    if ($itemId < 1 || (int) ($added['cart_count'] ?? 0) !== 1) {
        throw new RuntimeException('Adăugarea în coș a eșuat.');
    }
    $updated = $json($request('PATCH', '/api/cart/items/' . $itemId, [
        '_csrf' => $token,
        'quantity' => 2,
    ], $apiHeaders));
    if ((int) ($updated['totals']['count'] ?? 0) !== 2) {
        throw new RuntimeException('Actualizarea cantității a eșuat.');
    }
    $removed = $json($request('DELETE', '/api/cart/items/' . $itemId, [
        '_csrf' => $token,
    ], $apiHeaders));
    if ((int) ($removed['totals']['count'] ?? -1) !== 0) {
        throw new RuntimeException('Ștergerea din coș a eșuat.');
    }
    echo "[OK] Cart add/update/remove\n";

    $gift = $json($request('POST', '/api/gift-box', [
        '_csrf' => $token,
        'template_id' => $template['id'],
        'components' => array_column($components, 'variant_id'),
        'recipient_name' => 'Client QA',
        'gift_message' => 'Test intern Gift Box',
    ], $apiHeaders));
    if (empty($gift['group']) || (int) ($gift['cart_count'] ?? 0) < 1) {
        throw new RuntimeException('Configurarea Gift Box a eșuat.');
    }
    echo "[OK] Gift Box configuration\n";

    $json($request('POST', '/api/cart/coupon', [
        '_csrf' => $token,
        'code' => 'COD-INVALID-QA',
    ], $apiHeaders), 422);
    $pdo->prepare(
        "INSERT INTO coupons "
        . "(code,discount_type,discount_value,minimum_order_minor,maximum_discount_minor,max_uses,max_uses_per_user,is_active) "
        . "VALUES (?,'percent',10,0,NULL,10,2,1)"
    )->execute([$couponCode]);
    $couponId = (int) $pdo->lastInsertId();
    $coupon = $json($request('POST', '/api/cart/coupon', [
        '_csrf' => $token,
        'code' => $couponCode,
    ], $apiHeaders));
    if ((int) ($coupon['totals']['discount_minor'] ?? 0) <= 0) {
        throw new RuntimeException('Cuponul valid nu a acordat reducerea.');
    }
    echo "[OK] Coupon invalid/valid evaluation\n";

    [$status, $checkout] = $request('GET', '/checkout');
    if ($status !== 200) {
        throw new RuntimeException('Checkout-ul nu s-a deschis.');
    }
    $idempotency = $hidden($checkout, 'idempotency_key');
    $payload = [
        '_csrf' => $csrf($checkout),
        'idempotency_key' => $idempotency,
        'email' => $safeRecipient,
        'phone' => '+40 700 000 000',
        'first_name' => 'Client',
        'last_name' => 'QA',
        'address' => 'Strada Testului 10',
        'address_2' => '',
        'city' => 'București',
        'county' => 'București',
        'postal_code' => '010101',
        'customer_type' => 'individual',
        'payment_method' => 'cod',
        'shipping_method' => 'curier',
        'terms' => '1',
        'gift_message' => 'Test intern, fără livrare.',
    ];
    [$status, , $headers] = $request('POST', '/checkout/create', $payload);
    $location = implode(',', $headers['location'] ?? []);
    if (!in_array($status, [302, 303], true) || !str_contains($location, '/comanda-confirmata/')) {
        throw new RuntimeException('Crearea comenzii ramburs a eșuat: ' . $status . ' ' . $location);
    }

    $order = $pdo->prepare('SELECT id,order_number,grand_total_minor FROM orders WHERE idempotency_key=? LIMIT 1');
    $order->execute([$idempotency]);
    $orderRow = $order->fetch();
    $orderId = (int) ($orderRow['id'] ?? 0);
    if ($orderId < 1) {
        throw new RuntimeException('Comanda nu a fost persistată.');
    }
    $request('POST', '/checkout/create', $payload);
    $duplicate = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE idempotency_key=?');
    $duplicate->execute([$idempotency]);
    if ((int) $duplicate->fetchColumn() !== 1) {
        throw new RuntimeException('Idempotency checkout a creat o comandă duplicată.');
    }
    echo "[OK] COD checkout and idempotency\n";

    $confirmationPath = (string) (parse_url($location, PHP_URL_PATH) ?: $location);
    $basePath = rtrim((string) parse_url($base, PHP_URL_PATH), '/');
    if ($basePath !== '' && str_starts_with($confirmationPath, $basePath . '/')) {
        $confirmationPath = substr($confirmationPath, strlen($basePath));
    }
    [$status, $confirmation] = $request('GET', $confirmationPath);
    if ($status !== 200 || !str_contains($confirmation, (string) $orderRow['order_number'])) {
        throw new RuntimeException('Confirmarea comenzii nu este accesibilă.');
    }
    echo "[OK] Order confirmation page\n";
} finally {
    if ($orderId > 0) {
        $items = $pdo->prepare('SELECT variant_id,quantity FROM order_items WHERE order_id=?');
        $items->execute([$orderId]);
        foreach ($items->fetchAll() as $item) {
            $pdo->prepare('UPDATE product_variants SET stock_qty=stock_qty+? WHERE id=?')
                ->execute([(int) $item['quantity'], (int) $item['variant_id']]);
        }
        foreach ([
            'invoice_issue_jobs', 'invoice_events', 'invoice_artifacts', 'invoice_items',
            'shipments', 'order_notes', 'order_status_history', 'payments',
            'order_addresses', 'order_items',
        ] as $table) {
            try {
                $pdo->prepare("DELETE FROM {$table} WHERE order_id=?")->execute([$orderId]);
            } catch (Throwable) {
            }
        }
        $pdo->prepare("DELETE FROM inventory_movements WHERE reference_type='order' AND reference_id=?")->execute([$orderId]);
        $pdo->prepare('DELETE FROM coupon_usages WHERE order_id=?')->execute([$orderId]);
        $pdo->prepare("DELETE FROM notifications WHERE entity_type='order' AND entity_id=?")->execute([$orderId]);
        $pdo->prepare("DELETE FROM email_queue WHERE correlation_id LIKE ?")->execute(['order.created:' . $orderId . '%']);
        try {
            $pdo->prepare('DELETE FROM invoices WHERE order_id=?')->execute([$orderId]);
        } catch (Throwable) {
        }
        $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$orderId]);
    }
    $pdo->prepare('DELETE FROM gift_box_customizations WHERE id>?')->execute([$maxCustomization]);
    $pdo->prepare('DELETE FROM carts WHERE id>?')->execute([$maxCart]);
    $pdo->prepare('DELETE FROM wishlists WHERE id>?')->execute([$maxWishlist]);
    if ($couponId > 0) {
        $pdo->prepare('DELETE FROM coupons WHERE id=?')->execute([$couponId]);
    }
    if (is_file($cookie)) {
        unlink($cookie);
    }
}
