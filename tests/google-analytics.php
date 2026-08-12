<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Services\GoogleAnalyticsService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $service = new GoogleAnalyticsService();
    $order = [
        'order_number' => 'MB-TEST-GA4-1',
        'order_status' => 'new',
        'payment_method' => 'cod',
        'payment_status' => 'unpaid',
        'currency' => 'RON',
        'subtotal_minor' => 59800,
        'discount_total_minor' => 9900,
        'shipping_total_minor' => 2500,
        'tax_total_minor' => 9093,
        'grand_total_minor' => 52400,
        'coupon_code' => 'BUNVENIT',
        'email' => 'nu-se-trimite@example.test',
        'phone' => '0700000000',
    ];
    $items = [[
        'product_id' => 10,
        'sku_snapshot' => 'ROCHITA-01',
        'name_snapshot' => 'Rochiță Ivory',
        'options_json' => json_encode(['label' => '0-3 luni'], JSON_UNESCAPED_UNICODE),
        'unit_price_minor' => 49900,
        'quantity' => 1,
        'total_minor' => 49900,
    ], [
        'product_id' => 11,
        'sku_snapshot' => 'BODY-01',
        'name_snapshot' => 'Body și ștrampi',
        'options_json' => '{}',
        'unit_price_minor' => 9900,
        'quantity' => 1,
        'total_minor' => 9900,
    ]];

    $purchase = $service->purchase($order, $items);
    $assert($purchase !== null, 'Comanda ramburs nu produce evenimentul purchase.');
    $assert($purchase['transaction_id'] === 'MB-TEST-GA4-1', 'Lipsește transaction_id.');
    $assert($purchase['value'] === 499.0, 'Valoarea produselor după reducere este incorectă.');
    $assert($purchase['shipping'] === 25.0 && $purchase['currency'] === 'RON', 'Livrarea sau moneda sunt incorecte.');
    $assert(count($purchase['items']) === 2 && $purchase['items'][0]['item_variant'] === '0-3 luni', 'Produsele ori varianta nu sunt transmise.');
    $encoded = json_encode($purchase, JSON_UNESCAPED_UNICODE);
    $assert(!str_contains((string) $encoded, '@') && !str_contains((string) $encoded, '0700000000'), 'Evenimentul conține date personale.');

    $stripePending = $order;
    $stripePending['payment_method'] = 'stripe';
    $stripePending['payment_status'] = 'unpaid';
    $assert($service->purchase($stripePending, $items) === null, 'Cardul neplătit produce conversie falsă.');
    $stripePending['payment_status'] = 'paid';
    $assert($service->purchase($stripePending, $items) !== null, 'Cardul confirmat de Stripe nu produce conversia.');

    $cancelled = $order;
    $cancelled['order_status'] = 'cancelled';
    $assert($service->purchase($cancelled, $items) === null, 'Comanda anulată produce conversie.');

    echo "Google Analytics purchase event: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Google Analytics purchase event: FAIL - {$exception->getMessage()}\n");
    exit(1);
}
