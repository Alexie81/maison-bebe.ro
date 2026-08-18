<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Services\GoogleAnalyticsService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $service = new GoogleAnalyticsService();
    $catalogItem = $service->productItem([
        'id'=>10,'sku'=>'ROCHITA','default_variant_sku'=>'ROCHITA-01','variant_count'=>1,
        'name'=>'Rochiță Ivory','brand'=>'Maison Bébé','category_name'=>'Rochițe','price_minor'=>49900,
    ], 2, 'shop_catalog', 'Magazin');
    $assert($catalogItem['item_id']==='ROCHITA-01'&&$catalogItem['item_list_id']==='shop_catalog'&&$catalogItem['index']===2,'Produsul din listă nu are identificatorii GA4 coerenți.');

    $cartTotals=[
        'items'=>[[
            'id'=>1,'product_id'=>10,'sku'=>'ROCHITA-01','name'=>'Rochiță Ivory','brand'=>'Maison Bébé','category_name'=>'Rochițe',
            'variant_label'=>'0-3 luni','price_minor'=>49900,'quantity'=>1,
        ],[
            'id'=>2,'product_id'=>11,'sku'=>'BODY-01','name'=>'Body și ștrampi','brand'=>'Maison Bébé','category_name'=>'Accesorii',
            'variant_label'=>'Standard','price_minor'=>9900,'quantity'=>1,
        ]],
        'subtotal_minor'=>59800,'discount_minor'=>9900,'shipping_minor'=>2500,'coupon'=>['code'=>'BUNVENIT'],
    ];
    $cartPayload=$service->cartPayload($cartTotals);
    $assert($cartPayload['value']===499.0&&$cartPayload['coupon']==='BUNVENIT'&&count($cartPayload['items'])===2,'Payloadul coșului nu păstrează valoarea netă și produsele.');
    $assert($cartPayload['items'][0]['item_variant']==='0-3 luni'&&isset($cartPayload['items'][0]['discount']),'Varianta sau reducerea lipsește din coș.');

    $mutation=$service->cartMutationPayload([$cartTotals['items'][0]],[1=>2]);
    $assert($mutation['value']===998.0&&$mutation['items'][0]['quantity']===2,'Cantitatea modificată nu este raportată corect.');
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

    $refund=$service->refund($order,$items);
    $assert($refund!==null&&$refund['transaction_id']==='MB-TEST-GA4-1'&&$refund['value']===499.0&&count($refund['items'])===2,'Rambursarea completă nu poate fi raportată coerent.');
    $unpaidStripe=$stripePending;$unpaidStripe['payment_status']='unpaid';
    $assert($service->refund($unpaidStripe,$items)===null,'O plată Stripe neîncasată produce rambursare GA4.');

    $analyticsJs=file_get_contents(dirname(__DIR__).'/public/assets/js/analytics.js')
        .file_get_contents(dirname(__DIR__).'/public/assets/js/app.js')
        .file_get_contents(dirname(__DIR__).'/app/Controllers/CommerceApiController.php')
        .file_get_contents(dirname(__DIR__).'/app/Controllers/CommerceController.php');
    foreach(['view_item','view_item_list','select_item','view_promotion','select_promotion','add_to_cart','remove_from_cart','view_cart','begin_checkout','add_shipping_info','add_payment_info','add_to_wishlist'] as $eventName){
        $assert(str_contains((string)$analyticsJs,$eventName),'Lipsește suportul JavaScript pentru '.$eventName.'.');
    }
    $assert(str_contains((string) $analyticsJs, 'maison_debug_check') && str_contains((string) $analyticsJs, 'debug_mode'), 'Modul controlat pentru GA4 DebugView lipsește.');

    echo "Google Analytics purchase event: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Google Analytics purchase event: FAIL - {$exception->getMessage()}\n");
    exit(1);
}
