<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\GoogleMerchantService;

$pdo = Database::connection();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO products (name,slug,sku,brand,material,short_description,status,published_at) VALUES (?,?,?,?,?,?,'active',NOW())")
        ->execute(['Set Merchant Test', 'set-merchant-' . $suffix, 'GM-P-' . $suffix, 'Maison Bébé', 'Bumbac', 'Descriere pentru sincronizarea Google Merchant.']);
    $productId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_variants (product_id,sku,ean,price_minor,compare_at_price_minor,stock_qty,track_inventory,is_active) VALUES (?,?,?,?,?,4,1,1)')
        ->execute([$productId, 'GM-V-' . $suffix, null, 12500, 15000]);
    $variantId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_options (product_id,name,sort_order) VALUES (?,\'Culoare\',0)')->execute([$productId]);
    $optionId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_option_values (option_id,value,sort_order) VALUES (?,\'Crem\',0)')->execute([$optionId]);
    $valueId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO variant_option_values (variant_id,option_value_id) VALUES (?,?)')->execute([$variantId, $valueId]);
    for ($index = 1; $index <= 3; $index++) {
        $path = '/uploads/google-merchant-test-' . $suffix . '-' . $index . '.jpg';
        $pdo->prepare("INSERT INTO media_assets (path,mime_type,original_name,width,height) VALUES (?,'image/jpeg',?,1000,1000)")
            ->execute([$path, 'merchant-' . $index . '.jpg']);
        $mediaId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO product_images (product_id,media_id,alt_text,sort_order,is_primary) VALUES (?,?,?,?,?)')
            ->execute([$productId, $mediaId, 'Imagine ' . $index, $index * 10, $index === 1 ? 1 : 0]);
    }

    $service = new GoogleMerchantService();
    $preview = $service->previewProduct($productId);
    $assert(count($preview) === 1, 'Preview-ul Merchant nu conține varianta produsului.');
    $input = $preview[0];
    $attributes = $input['productAttributes'] ?? [];
    $assert(($input['offerId'] ?? '') === 'GM-V-' . $suffix, 'Offer ID nu folosește SKU-ul variantei.');
    $assert(($input['contentLanguage'] ?? '') === 'ro' && ($input['feedLabel'] ?? '') === 'RO', 'Țintirea Merchant nu este România/română.');
    $assert(str_contains((string) ($attributes['link'] ?? ''), '?variant=GM-V-'), 'Linkul Merchant nu selectează varianta exactă.');
    $assert(($attributes['brand'] ?? '') === 'Maison Bébé', 'Brandul nu este inclus în Merchant.');
    $assert(($attributes['color'] ?? '') === 'Crem', 'Opțiunea culoare nu este mapată.');
    $assert(($attributes['availability'] ?? '') === 'IN_STOCK', 'Disponibilitatea este greșită.');
    $assert(($attributes['price']['amountMicros'] ?? '') === '150000000', 'Prețul normal Merchant este greșit.');
    $assert(($attributes['salePrice']['amountMicros'] ?? '') === '125000000', 'Prețul promoțional Merchant este greșit.');
    $assert(count($attributes['additionalImageLinks'] ?? []) === 2, 'Nu sunt incluse toate imaginile suplimentare disponibile.');
    $assert(($attributes['identifierExists'] ?? null) === false, 'Produsul fără GTIN nu este marcat corect.');

    $service->queueProduct($pdo, $productId);
    $service->queueProduct($pdo, $productId);
    $count = $pdo->prepare('SELECT COUNT(*) FROM google_merchant_sync_jobs WHERE product_id=?');
    $count->execute([$productId]);
    $assert((int) $count->fetchColumn() === 1, 'Coada Merchant dublează aceeași modificare de produs.');
    $pdo->prepare("INSERT INTO products (name,slug,sku,status,is_gift_box,published_at) VALUES (?,?,?,'active',1,NOW())")
        ->execute(['Cutie exclusa Merchant', 'cutie-merchant-' . $suffix, 'GM-BOX-' . $suffix]);
    $giftBoxProductId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_variants (product_id,sku,price_minor,stock_qty,track_inventory,is_active) VALUES (?,?,1000,5,1,1)')
        ->execute([$giftBoxProductId, 'GM-BOX-V-' . $suffix]);
    $service->queueAll($pdo);
    $count->execute([$giftBoxProductId]);
    $assert((int) $count->fetchColumn() === 0, 'Cutiile Gift Box nu trebuie publicate in Google Merchant.');

    $pdo->rollBack();
    echo "Google Merchant catalog: OK\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
