<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\AccountingStockPostingService;
use MaisonBebe\Services\ProductMappingService;

$pdo = Database::connection();
$suffix = strtoupper(substr(bin2hex(random_bytes(7)), 0, 12));
$productIds = [];
$variantIds = [];
$orderItemIds = [];
$salesInvoiceId = 0;
$stornoInvoiceId = 0;
$orderId = 0;
$runIds = [];
$failed = false;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $catalog = [
        ['Cutie cadou test', true, 'BOX', 900, 7],
        ['Produs Gift Box A', false, 'A', 600, 9],
        ['Produs Gift Box B', false, 'B', 450, 11],
    ];

    foreach ($catalog as [$name, $isGiftBox, $skuPart, $costMinor, $stockQty]) {
        $productSku = 'QA-GB-' . $skuPart . '-P-' . $suffix;
        $variantSku = 'QA-GB-' . $skuPart . '-V-' . $suffix;
        $pdo->prepare("INSERT INTO products (name,slug,sku,status,is_gift_box) VALUES (?,?,?,'active',?)")
            ->execute([$name, strtolower(str_replace(' ', '-', $name)) . '-' . strtolower($suffix), $productSku, $isGiftBox ? 1 : 0]);
        $productId = (int) $pdo->lastInsertId();
        $productIds[] = $productId;
        if ($isGiftBox) {
            $pdo->prepare('UPDATE products SET deleted_at=NOW() WHERE id=?')->execute([$productId]);
        }
        $pdo->prepare(
            'INSERT INTO product_variants '
            . '(product_id,sku,price_minor,cost_minor,stock_qty,track_inventory,track_accounting_stock,is_active) '
            . 'VALUES (?,?,?,?,?,1,1,1)'
        )->execute([$productId, $variantSku, $costMinor * 2, $costMinor, $stockQty]);
        $variantIds[] = (int) $pdo->lastInsertId();
    }

    $candidateIds = array_map('intval', array_column((new ProductMappingService())->candidates('QA-GB-BOX-V-' . $suffix), 'variant_id'));
    $assert(in_array($variantIds[0], $candidateIds, true), 'Cutia ascunsă din magazin nu apare în selectorul NIR.');

    $warehouseId = (int) $pdo->query(
        'SELECT id FROM accounting_warehouses WHERE is_default=1 AND is_active=1 ORDER BY id LIMIT 1'
    )->fetchColumn();
    $companyId = (int) $pdo->query('SELECT id FROM company_profiles ORDER BY id LIMIT 1')->fetchColumn();
    $assert($warehouseId > 0 && $companyId > 0, 'Lipsește gestiunea sau profilul companiei pentru test.');

    $pdo->prepare(
        "INSERT INTO orders "
        . "(order_number,public_token,idempotency_key,email,phone,customer_snapshot_json,subtotal_minor,grand_total_minor,payment_method,shipping_method) "
        . "VALUES (?,?,?,?,?,'{}',3900,3900,'bank','test')"
    )->execute([
        'QA-GB-' . $suffix,
        hash('sha256', 'gift-box-public-' . $suffix),
        hash('sha256', 'gift-box-idempotency-' . $suffix),
        'qa-gift-box-' . $suffix . '@example.test',
        '0700000000',
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $group = 'GB-' . substr($suffix, 0, 8);
    $customizations = [
        ['type' => 'gift_box', 'role' => 'box', 'group' => $group, 'components' => [
            ['product_id' => $productIds[1], 'variant_id' => $variantIds[1]],
            ['product_id' => $productIds[2], 'variant_id' => $variantIds[2]],
        ]],
        ['type' => 'gift_box', 'role' => 'component', 'group' => $group],
        ['type' => 'gift_box', 'role' => 'component', 'group' => $group],
    ];
    $names = ['Cutie cadou test', 'Produs Gift Box A', 'Produs Gift Box B'];
    $prices = [1800, 1200, 900];

    $orderItem = $pdo->prepare(
        'INSERT INTO order_items '
        . '(order_id,product_id,variant_id,name_snapshot,sku_snapshot,unit_price_minor,quantity,total_minor,customization_json) '
        . 'VALUES (?,?,?,?,?,?,1,?,?)'
    );
    foreach ($variantIds as $index => $variantId) {
        $sku = 'QA-GB-' . ($index === 0 ? 'BOX' : ($index === 1 ? 'A' : 'B')) . '-V-' . $suffix;
        $orderItem->execute([
            $orderId,
            $productIds[$index],
            $variantId,
            $names[$index],
            $sku,
            $prices[$index],
            $prices[$index],
            json_encode($customizations[$index], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $orderItemIds[] = (int) $pdo->lastInsertId();
    }

    $pdo->prepare(
        "INSERT INTO invoices "
        . "(order_id,company_profile_id,document_type,customer_type,number,status,currency,issue_date,issued_at,issuer_snapshot_json,customer_snapshot_json,subtotal_minor,grand_total_minor) "
        . "VALUES (?,?,'invoice','individual',?,'issued','RON','2002-01-10',NOW(),'{}','{\"first_name\":\"Client\",\"last_name\":\"Gift Box\"}',3900,3900)"
    )->execute([$orderId, $companyId, 'QA-GB-OUT-' . $suffix]);
    $salesInvoiceId = (int) $pdo->lastInsertId();

    $invoiceItem = $pdo->prepare(
        'INSERT INTO invoice_items '
        . '(invoice_id,order_item_id,name,sku,quantity,unit_price_minor,total_minor,sort_order) '
        . 'VALUES (?,?,?,?,1,?,?,?)'
    );
    foreach ($orderItemIds as $index => $orderItemId) {
        $sku = 'QA-GB-' . ($index === 0 ? 'BOX' : ($index === 1 ? 'A' : 'B')) . '-V-' . $suffix;
        $invoiceItem->execute([
            $salesInvoiceId,
            $orderItemId,
            $names[$index],
            $sku,
            $prices[$index],
            $prices[$index],
            $index,
        ]);
    }

    $posting = new AccountingStockPostingService();
    $postedVariants = $posting->postSalesInvoiceOutflow($salesInvoiceId, 'qa-gift-box-sale-' . $suffix);
    $posting->postSalesInvoiceOutflow($salesInvoiceId, 'qa-gift-box-sale-repeat-' . $suffix);

    sort($postedVariants);
    $expectedVariants = $variantIds;
    sort($expectedVariants);
    $assert($postedVariants === $expectedVariants, 'Ieșirea Gift Box nu include cutia și toate componentele.');

    $movementStatement = $pdo->prepare(
        "SELECT product_variant_id,quantity_out FROM accounting_stock_movements "
        . "WHERE source_document_type='SALES_INVOICE' AND source_document_id=? AND movement_type='SALES_INVOICE_OUT' ORDER BY id"
    );
    $movementStatement->execute([$salesInvoiceId]);
    $movements = $movementStatement->fetchAll();
    $assert(count($movements) === 3, 'Gift Box-ul trebuie să creeze exact 3 ieșiri: cutie + 2 produse.');
    foreach ($movements as $movement) {
        $assert(abs((float) $movement['quantity_out'] - 1.0) < 0.0001, 'Cantitatea scăzută pentru o componentă Gift Box este incorectă.');
    }

    $balanceStatement = $pdo->prepare(
        'SELECT current_quantity FROM accounting_stock_balances WHERE product_variant_id=? AND warehouse_id=?'
    );
    foreach ($variantIds as $variantId) {
        $balanceStatement->execute([$variantId, $warehouseId]);
        $assert(abs((float) $balanceStatement->fetchColumn() + 1.0) < 0.0001, 'Soldul Gift Box nu a fost scăzut cu o unitate.');
    }

    $commercialStatement = $pdo->prepare('SELECT stock_qty FROM product_variants WHERE id=?');
    foreach ($variantIds as $index => $variantId) {
        $commercialStatement->execute([$variantId]);
        $assert((int) $commercialStatement->fetchColumn() === $catalog[$index][4], 'Postarea contabilă a dublat scăderea stocului comercial.');
    }

    $pdo->prepare(
        "INSERT INTO invoices "
        . "(order_id,company_profile_id,parent_invoice_id,document_type,customer_type,number,status,currency,issue_date,issued_at,issuer_snapshot_json,customer_snapshot_json,subtotal_minor,grand_total_minor) "
        . "VALUES (?,?,?,'storno','individual',?,'issued','RON','2002-01-11',NOW(),'{}','{}',-3900,-3900)"
    )->execute([$orderId, $companyId, $salesInvoiceId, 'QA-GB-STORNO-' . $suffix]);
    $stornoInvoiceId = (int) $pdo->lastInsertId();

    $posting->reverseSalesInvoiceOutflow(
        $salesInvoiceId,
        $stornoInvoiceId,
        '2002-01-11',
        true,
        'qa-gift-box-storno-' . $suffix
    );
    $posting->reverseSalesInvoiceOutflow(
        $salesInvoiceId,
        $stornoInvoiceId,
        '2002-01-11',
        true,
        'qa-gift-box-storno-repeat-' . $suffix
    );

    $reversalStatement = $pdo->prepare(
        "SELECT COUNT(*) FROM accounting_stock_movements "
        . "WHERE source_document_type='SALES_INVOICE_REVERSAL' AND source_document_id=? "
        . "AND movement_type='SALES_INVOICE_REVERSAL_IN'"
    );
    $reversalStatement->execute([$stornoInvoiceId]);
    $assert((int) $reversalStatement->fetchColumn() === 3, 'Stornarea Gift Box nu a readus toate cele 3 articole o singură dată.');
    foreach ($variantIds as $variantId) {
        $balanceStatement->execute([$variantId, $warehouseId]);
        $assert(abs((float) $balanceStatement->fetchColumn()) < 0.0001, 'Stornarea nu a readus soldul Gift Box la zero.');
    }

    echo "Gift Box accounting stock regression test: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Gift Box accounting stock regression test: FAIL - {$exception->getMessage()}\n");
    $failed = true;
} finally {
    if ($variantIds) {
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $statement = $pdo->prepare(
            "SELECT DISTINCT av.valuation_run_id FROM accounting_stock_valuations av "
            . "JOIN accounting_stock_movements m ON m.id=av.movement_id WHERE m.product_variant_id IN ($placeholders)"
        );
        $statement->execute($variantIds);
        $runIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare(
            "DELETE av FROM accounting_stock_valuations av JOIN accounting_stock_movements m ON m.id=av.movement_id "
            . "WHERE m.product_variant_id IN ($placeholders)"
        )->execute($variantIds);
        $pdo->prepare("DELETE FROM accounting_stock_balances WHERE product_variant_id IN ($placeholders)")->execute($variantIds);
        $pdo->prepare(
            "DELETE FROM accounting_stock_movements WHERE product_variant_id IN ($placeholders) "
            . 'ORDER BY reversal_of_movement_id IS NULL ASC,id DESC'
        )->execute($variantIds);
    }
    if ($salesInvoiceId) {
        $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id=?')->execute([$salesInvoiceId]);
    }
    if ($stornoInvoiceId) {
        $pdo->prepare('DELETE FROM invoices WHERE id=?')->execute([$stornoInvoiceId]);
    }
    if ($salesInvoiceId) {
        $pdo->prepare('DELETE FROM invoices WHERE id=?')->execute([$salesInvoiceId]);
    }
    if ($orderItemIds) {
        $placeholders = implode(',', array_fill(0, count($orderItemIds), '?'));
        $pdo->prepare("DELETE FROM order_items WHERE id IN ($placeholders)")->execute($orderItemIds);
    }
    if ($orderId) {
        $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$orderId]);
    }
    if ($runIds) {
        $placeholders = implode(',', array_fill(0, count($runIds), '?'));
        $pdo->prepare("DELETE FROM accounting_valuation_runs WHERE id IN ($placeholders)")->execute($runIds);
    }
    if ($variantIds) {
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $pdo->prepare("DELETE FROM product_variants WHERE id IN ($placeholders)")->execute($variantIds);
    }
    if ($productIds) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)")->execute($productIds);
    }
    $pdo->prepare('DELETE FROM audit_logs WHERE correlation_id LIKE ?')->execute(['qa-gift-box-%' . $suffix]);
}

exit($failed ? 1 : 0);
