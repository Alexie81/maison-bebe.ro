<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\AccountingStockScopeService;
use MaisonBebe\Services\ProductMappingService;

$pdo = Database::connection();
$suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
$productSku = 'SCOPE-P-' . strtoupper($suffix);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO products (name,slug,sku,status) VALUES (?,?,?,'active')")
        ->execute(['Produs contabil fără variante', 'produs-contabil-' . $suffix, $productSku]);
    $productId = (int) $pdo->lastInsertId();
    $insert = $pdo->prepare(
        'INSERT INTO product_variants (product_id,sku,price_minor,stock_qty,track_inventory,track_accounting_stock,is_active) '
        . 'VALUES (?,?,10000,0,0,?,?)'
    );
    $insert->execute([$productId, $productSku . '-01', 0, 1]);
    $anchorId = (int) $pdo->lastInsertId();
    $insert->execute([$productId, $productSku . '-02', 0, 1]);
    $secondId = (int) $pdo->lastInsertId();
    $insert->execute([$productId, $productSku . '-VECHI', 1, 0]);

    $scope = new AccountingStockScopeService();
    $condition = $scope->listingCondition('v');
    $statement = $pdo->prepare("SELECT v.id FROM product_variants v WHERE v.product_id=? AND {$condition} ORDER BY v.id");
    $statement->execute([$productId]);
    $listed = array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    $assert($listed === [$anchorId], 'Produsul fără variante urmărite nu apare ca o singură poziție contabilă.');

    $resolved = $scope->resolveVariant($secondId, $pdo);
    $assert((int) ($resolved['id'] ?? 0) === $anchorId, 'Varianta nu este redirecționată către poziția contabilă a produsului.');
    $assert((string) ($resolved['sku'] ?? '') === $productSku, 'Poziția contabilă nu folosește SKU-ul produsului.');
    $assert((int) ($resolved['is_product_scope'] ?? 0) === 1, 'Poziția nu este marcată ca produs fără variante.');

    $automatic = (new ProductMappingService())->automatic(0, $productSku, null, null, $pdo);
    $assert((int) ($automatic['product_variant_id'] ?? 0) === $anchorId, 'Căutarea după SKU-ul produsului nu găsește poziția contabilă.');
    $assert((string) ($automatic['sku'] ?? '') === $productSku, 'Asocierea automată nu păstrează SKU-ul produsului.');

    $pdo->rollBack();
    echo "Accounting product scope: OK\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Accounting product scope: FAIL - {$exception->getMessage()}\n");
    exit(1);
}
