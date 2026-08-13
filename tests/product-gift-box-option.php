<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\CartService;
use MaisonBebe\Services\GiftBoxService;
use MaisonBebe\Services\InvoiceService;
use MaisonBebe\Services\ProductGiftBoxOptionService;

$pdo = Database::connection();
$suffix = strtoupper(substr(bin2hex(random_bytes(7)), 0, 12));
$productIds = [];
$variantIds = [];
$templateId = 0;
$cartId = 0;
$failed = false;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $offerService = new ProductGiftBoxOptionService();
    $offerService->ensureSchema($pdo);

    $pdo->prepare("INSERT INTO products (name,slug,sku,status,is_gift_box) VALUES (?,?,?,'active',1)")
        ->execute(['Cutie cadou QA', 'cutie-cadou-qa-' . strtolower($suffix), 'QA-BOX-P-' . $suffix]);
    $boxProductId = (int) $pdo->lastInsertId();
    $productIds[] = $boxProductId;
    $pdo->prepare('INSERT INTO product_variants (product_id,sku,price_minor,stock_qty,track_inventory,is_active) VALUES (?,?,1500,0,0,1)')
        ->execute([$boxProductId, 'QA-BOX-V-' . $suffix]);
    $boxVariantId = (int) $pdo->lastInsertId();
    $variantIds[] = $boxVariantId;

    $pdo->prepare('INSERT INTO gift_box_templates (product_id,name,slug,base_price_minor,stock_qty,rules_json,is_active,sort_order) VALUES (?,?,?,1500,0,?,1,999999)')
        ->execute([$boxProductId, 'Cutie cadou QA', 'cutie-template-qa-' . strtolower($suffix), json_encode(['catalog_scope'=>true,'product_ids'=>[999999999]], JSON_UNESCAPED_UNICODE)]);
    $templateId = (int) $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO products (name,slug,sku,status,is_gift_box) VALUES (?,?,?,'active',0)")
        ->execute(['Produs simplu QA', 'produs-simplu-qa-' . strtolower($suffix), 'QA-PRODUCT-P-' . $suffix]);
    $productId = (int) $pdo->lastInsertId();
    $productIds[] = $productId;
    $pdo->prepare('INSERT INTO product_variants (product_id,sku,price_minor,stock_qty,track_inventory,is_active) VALUES (?,?,9900,6,1,1)')
        ->execute([$productId, 'QA-PRODUCT-V-' . $suffix]);
    $variantId = (int) $pdo->lastInsertId();
    $variantIds[] = $variantId;

    $offerService->save($productId, $templateId, $pdo);
    $definition = $offerService->definitionForProduct($productId, $pdo);
    $assert((int) ($definition['gift_box_template_id'] ?? 0) === $templateId, 'Cutia nu a fost asociată produsului simplu.');
    $assert((int) ($definition['price_minor'] ?? 0) === 1500, 'Prețul cutiei nu este citit corect.');
    $offer = $offerService->offerForVariant($variantId, $pdo);
    $assert((int) ($offer['variant_id'] ?? 0) === $variantId, 'Oferta nu este disponibilă pentru varianta produsului simplu.');
    $boxTemplate = (new GiftBoxService())->template($templateId);
    $assert((int) ($boxTemplate['track_inventory'] ?? 1) === 0, 'Cutia nu păstrează stocul online nelimitat.');
    $assert((new GiftBoxService())->hasAvailableStock($boxTemplate, 20), 'Cutia cu stoc nelimitat este tratată ca indisponibilă.');
    $assert((new GiftBoxService())->componentsFor($templateId) === [], 'Filtrul configuratorului trebuie să rămână activ pentru lista produselor eligibile.');

    $_COOKIE[CartService::COOKIE] = bin2hex(random_bytes(32));
    $cart = new CartService();
    $cartId = (int) $cart->current()['id'];
    $result = (new GiftBoxService())->addProductWithBox([
        'gift_box_template_id' => $templateId,
        'variant_id' => $variantId,
        'quantity' => 2,
        'customization' => [],
    ], $cart);
    $assert(!empty($result['active']), 'Produsul cu ambalare nu a fost adăugat în coș.');
    $assert(!empty($result['box']), 'Filtrul de produse eligibile din configurator a blocat greșit oferta directă a cutiei.');

    $totals = $cart->totals();
    $assert(count($totals['items']) === 2, 'Coșul trebuie să conțină cutia și produsul ca două poziții contabile.');
    $assert((int) $totals['subtotal_minor'] === 22800, 'Totalul produsului cu două cutii este incorect.');
    $roles = [];
    foreach ($totals['items'] as $item) {
        $customization = json_decode((string) ($item['customization_json'] ?? ''), true) ?: [];
        $roles[(string) ($customization['role'] ?? '')] = $item;
    }
    $assert(isset($roles['box'], $roles['component']), 'Grupul nu păstrează separat cutia și produsul comandat.');
    $assert(($roles['box']['quantity'] ?? 0) === 2 && ($roles['component']['quantity'] ?? 0) === 2, 'Cantitatea nu este sincronizată în grup.');

    $invoiceLines = [];
    foreach ($roles as $item) {
        $invoiceLines = array_merge($invoiceLines, (new InvoiceService())->expandOrderItemForInvoice([
            'name_snapshot' => $item['name'],
            'sku_snapshot' => $item['sku'],
            'quantity' => (int) $item['quantity'],
            'unit_price_minor' => (int) $item['price_minor'],
            'total_minor' => (int) $item['price_minor'] * (int) $item['quantity'],
            'customization_json' => $item['customization_json'],
        ], 1.21));
    }
    $assert(count($invoiceLines) === 2, 'Factura trebuie să afișeze produsul și cutia pe poziții separate.');

    echo "Product gift-box option regression test: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Product gift-box option regression test: FAIL - {$exception->getMessage()}\n");
    $failed = true;
} finally {
    if ($cartId) {
        $pdo->prepare('DELETE FROM cart_items WHERE cart_id=?')->execute([$cartId]);
        $pdo->prepare('DELETE FROM carts WHERE id=?')->execute([$cartId]);
    }
    if ($productIds) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $pdo->prepare("DELETE FROM product_gift_box_options WHERE product_id IN ($placeholders)")->execute($productIds);
    }
    if ($templateId) $pdo->prepare('DELETE FROM gift_box_templates WHERE id=?')->execute([$templateId]);
    if ($variantIds) {
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $pdo->prepare("DELETE FROM product_variants WHERE id IN ($placeholders)")->execute($variantIds);
    }
    if ($productIds) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)")->execute($productIds);
    }
}

exit($failed ? 1 : 0);
