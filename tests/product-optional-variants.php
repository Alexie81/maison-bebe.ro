<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\InvoiceService;
use MaisonBebe\Services\ProductOptionalVariantService;

$pdo = Database::connection();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
$pdo->beginTransaction();

try {
    $pdo->prepare("INSERT INTO products (name,slug,sku,status) VALUES (?,?,?,'active')")
        ->execute(['Rochiță test opțională', 'rochita-optionala-' . $suffix, 'OPT-P-' . $suffix]);
    $productId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_variants (product_id,sku,price_minor,stock_qty,track_inventory,is_active) VALUES (?,?,49900,4,1,1)')
        ->execute([$productId, 'OPT-V-' . $suffix]);
    $variantId = (int) $pdo->lastInsertId();

    $service = new ProductOptionalVariantService();
    $service->save($productId, [
        'optional_variant_name' => ['Body și ștrampi', 'Ambalaj simplu'],
        'optional_variant_price' => ['99.00', '0'],
    ], $pdo);
    $options = $service->forProduct($productId, true, $pdo);
    $assert(count($options) === 2, 'Opțiunile suplimentare nu au fost salvate.');
    $assert((int) $options[0]['price_delta_minor'] === 9900, 'Costul suplimentar de 99 lei este greșit.');
    $assert((int) $options[1]['price_delta_minor'] === 0, 'Opțiunea gratuită trebuie să aibă cost zero.');

    $customization = $service->withSnapshot($variantId, array_column($options, 'id'), [], $pdo);
    $assert($service->unitPrice(49900, $customization) === 59800, 'Prețul produsului cu opțiunea de 99 lei nu este corect.');
    $assert($service->label($customization) === 'Body și ștrampi, Ambalaj simplu', 'Denumirile opțiunilor nu sunt păstrate în comandă.');

    $invoiceItem = [
        'name_snapshot' => 'Rochiță de botez',
        'sku_snapshot' => 'OPT-V-' . $suffix,
        'quantity' => 1,
        'unit_price_minor' => 59800,
        'total_minor' => 59800,
        'customization_json' => json_encode($customization, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ];
    $lines = (new InvoiceService())->expandOrderItemForInvoice($invoiceItem, 1.21);
    $assert(count($lines) === 2, 'Produsul și opțiunea cu preț trebuie afișate pe linii separate în factură.');
    $assert(str_contains($lines[0]['name'], 'Rochiță de botez'), 'Factura nu mai păstrează denumirea produsului principal.');
    $assert(str_contains($lines[0]['name'], 'Ambalaj simplu'), 'Opțiunea inclusă gratuit nu este menționată lângă produs.');
    $assert(str_contains($lines[1]['name'], 'Body și ștrampi'), 'Factura nu desfășoară opțiunea cu preț.');
    $assert(array_sum(array_map(static fn(array $line): int => $line['total_minor'] + $line['vat_minor'], $lines)) === 59800, 'Factura nu păstrează totalul cu opțiunea suplimentară.');

    $pdo->rollBack();
    echo "Product optional variants: OK\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Product optional variants: FAIL - {$exception->getMessage()}\n");
    exit(1);
}
