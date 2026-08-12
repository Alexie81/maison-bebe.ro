<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\AccountingStockPostingService;
use MaisonBebe\Services\InvoiceService;

$pdo = Database::connection();
$suffix = strtoupper(substr(bin2hex(random_bytes(7)), 0, 12));
$productIds = [];
$variantIds = [];
$orderItemIds = [];
$orderId = 0;
$invoiceId = 0;
$runIds = [];
$failed = false;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $catalog = [
        'set' => ['Set cadou mixt QA', 'SET', 5000, 0, 40],
        'component_a' => ['Body din set QA', 'BODY', 1200, 600, 40],
        'component_b' => ['Păturică din set QA', 'BLANKET', 1600, 800, 40],
        'normal_a' => ['Produs normal A QA', 'NORMAL-A', 2000, 900, 40],
        'normal_b' => ['Produs normal B QA', 'NORMAL-B', 3000, 1400, 40],
    ];

    foreach ($catalog as $key => [$name, $skuPart, $priceMinor, $costMinor, $stockQty]) {
        $productSku = 'QA-MIX-' . $skuPart . '-P-' . $suffix;
        $variantSku = 'QA-MIX-' . $skuPart . '-V-' . $suffix;
        $pdo->prepare("INSERT INTO products (name,slug,sku,status) VALUES (?,?,?,'active')")
            ->execute([$name, strtolower(str_replace(' ', '-', $name)) . '-' . strtolower($suffix), $productSku]);
        $productIds[$key] = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO product_variants '
            . '(product_id,sku,price_minor,cost_minor,stock_qty,track_inventory,track_accounting_stock,is_active) '
            . 'VALUES (?,?,?,?,?,1,?,1)'
        )->execute([$productIds[$key], $variantSku, $priceMinor, $costMinor, $stockQty, $key === 'set' ? 0 : 1]);
        $variantIds[$key] = (int) $pdo->lastInsertId();
    }

    $warehouseId = (int) $pdo->query(
        'SELECT id FROM accounting_warehouses WHERE is_default=1 AND is_active=1 ORDER BY id LIMIT 1'
    )->fetchColumn();
    $companyId = (int) $pdo->query('SELECT id FROM company_profiles ORDER BY id LIMIT 1')->fetchColumn();
    $assert($warehouseId > 0 && $companyId > 0, 'Lipsește gestiunea sau profilul companiei pentru test.');

    $pdo->prepare(
        "INSERT INTO orders "
        . "(order_number,public_token,idempotency_key,email,phone,customer_snapshot_json,subtotal_minor,grand_total_minor,payment_method,shipping_method) "
        . "VALUES (?,?,?,?,?,'{}',19000,19000,'bank','test')"
    )->execute([
        'QA-MIX-' . $suffix,
        hash('sha256', 'mixed-public-' . $suffix),
        hash('sha256', 'mixed-idempotency-' . $suffix),
        'qa-mixed-' . $suffix . '@example.test',
        '0700000000',
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $setCustomization = [
        'product_set' => [
            'product_id' => $productIds['set'],
            'variant_id' => $variantIds['set'],
            'name' => $catalog['set'][0],
            'sku' => 'QA-MIX-SET-V-' . $suffix,
            'components' => [
                [
                    'product_id' => $productIds['component_a'],
                    'variant_id' => $variantIds['component_a'],
                    'name' => $catalog['component_a'][0],
                    'variant' => 'Standard',
                    'sku' => 'QA-MIX-BODY-V-' . $suffix,
                    'quantity' => 2,
                    'price_minor' => 1200,
                    'cost_minor' => 600,
                    'track_inventory' => true,
                    'track_accounting_stock' => true,
                ],
                [
                    'product_id' => $productIds['component_b'],
                    'variant_id' => $variantIds['component_b'],
                    'name' => $catalog['component_b'][0],
                    'variant' => 'Standard',
                    'sku' => 'QA-MIX-BLANKET-V-' . $suffix,
                    'quantity' => 1,
                    'price_minor' => 1600,
                    'cost_minor' => 800,
                    'track_inventory' => true,
                    'track_accounting_stock' => true,
                ],
            ],
        ],
    ];

    $orderItem = $pdo->prepare(
        'INSERT INTO order_items '
        . '(order_id,product_id,variant_id,name_snapshot,sku_snapshot,unit_price_minor,quantity,total_minor,customization_json) '
        . 'VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $orderRows = [
        ['set', 5000, 2, 10000, $setCustomization],
        ['normal_a', 2000, 3, 6000, []],
        ['normal_b', 3000, 1, 3000, []],
    ];
    foreach ($orderRows as [$key, $unitPrice, $quantity, $total, $customization]) {
        $skuPart = $catalog[$key][1];
        $orderItem->execute([
            $orderId,
            $productIds[$key],
            $variantIds[$key],
            $catalog[$key][0],
            'QA-MIX-' . $skuPart . '-V-' . $suffix,
            $unitPrice,
            $quantity,
            $total,
            json_encode($customization, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
        $orderItemIds[$key] = (int) $pdo->lastInsertId();
    }

    $pdo->prepare(
        "INSERT INTO invoices "
        . "(order_id,company_profile_id,document_type,customer_type,number,status,currency,issue_date,issuer_snapshot_json,customer_snapshot_json,subtotal_minor,grand_total_minor) "
        . "VALUES (?,?,'invoice','individual',?,'draft','RON',CURDATE(),'{}','{\"first_name\":\"Client\",\"last_name\":\"Test mixt\"}',19000,19000)"
    )->execute([$orderId, $companyId, 'QA-MIX-OUT-' . $suffix]);
    $invoiceId = (int) $pdo->lastInsertId();

    $invoiceItem = $pdo->prepare(
        'INSERT INTO invoice_items '
        . '(invoice_id,order_item_id,name,sku,quantity,unit_price_minor,vat_minor,total_minor,sort_order) '
        . 'VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $invoiceService = new InvoiceService();
    $sortOrder = 0;
    foreach ($orderRows as [$key, $unitPrice, $quantity, $total, $customization]) {
        $item = [
            'id' => $orderItemIds[$key],
            'name_snapshot' => $catalog[$key][0],
            'sku_snapshot' => 'QA-MIX-' . $catalog[$key][1] . '-V-' . $suffix,
            'quantity' => $quantity,
            'unit_price_minor' => $unitPrice,
            'total_minor' => $total,
            'customization_json' => json_encode($customization, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
        foreach ($invoiceService->expandOrderItemForInvoice($item, 1.21) as $line) {
            $invoiceItem->execute([
                $invoiceId,
                $orderItemIds[$key],
                $line['name'],
                $line['sku'],
                $line['quantity'],
                $line['unit_price_minor'],
                $line['vat_minor'],
                $line['total_minor'],
                $sortOrder++,
            ]);
        }
    }

    $invoiceLines = $pdo->prepare('SELECT name,sku,quantity FROM invoice_items WHERE invoice_id=? ORDER BY sort_order,id');
    $invoiceLines->execute([$invoiceId]);
    $invoiceLines = $invoiceLines->fetchAll();
    $expectedSkus = [
        'QA-MIX-BODY-V-' . $suffix,
        'QA-MIX-BLANKET-V-' . $suffix,
        'QA-MIX-NORMAL-A-V-' . $suffix,
        'QA-MIX-NORMAL-B-V-' . $suffix,
    ];
    $assert(count($invoiceLines) === 4, 'Factura mixtă trebuie să aibă 4 linii: 2 componente din set și 2 produse normale.');
    $assert(array_column($invoiceLines, 'sku') === $expectedSkus, 'Factura mixtă nu conține SKU-urile în ordinea corectă.');
    $assert(str_contains($invoiceLines[0]['name'], 'din setul'), 'Componentele setului nu sunt marcate ca atare pe factură.');

    $posting = new AccountingStockPostingService();
    $draftResult = $posting->postSalesInvoiceOutflow($invoiceId, 'qa-set-mixed-draft-' . $suffix);
    $movementCount = $pdo->prepare(
        "SELECT COUNT(*) FROM accounting_stock_movements WHERE source_document_type='SALES_INVOICE' AND source_document_id=?"
    );
    $movementCount->execute([$invoiceId]);
    $assert($draftResult === [] && (int) $movementCount->fetchColumn() === 0, 'Factura în ciornă a scăzut Stocuri Conta.');

    $pdo->prepare("UPDATE invoices SET status='issued',issued_at=NOW() WHERE id=?")->execute([$invoiceId]);
    $postedVariants = $posting->postSalesInvoiceOutflow($invoiceId, 'qa-set-mixed-issued-' . $suffix);
    $posting->postSalesInvoiceOutflow($invoiceId, 'qa-set-mixed-repeat-' . $suffix);

    $expectedVariantIds = [
        $variantIds['component_a'],
        $variantIds['component_b'],
        $variantIds['normal_a'],
        $variantIds['normal_b'],
    ];
    sort($expectedVariantIds);
    sort($postedVariants);
    $assert($postedVariants === $expectedVariantIds, 'Emiterea facturii nu a postat toate componentele setului și produsele normale.');

    $movements = $pdo->prepare(
        "SELECT product_variant_id,quantity_out FROM accounting_stock_movements "
        . "WHERE source_document_type='SALES_INVOICE' AND source_document_id=? AND movement_type='SALES_INVOICE_OUT'"
    );
    $movements->execute([$invoiceId]);
    $movementByVariant = [];
    foreach ($movements->fetchAll() as $movement) {
        $movementByVariant[(int) $movement['product_variant_id']] = (float) $movement['quantity_out'];
    }
    $expectedQuantities = [
        $variantIds['component_a'] => 4.0,
        $variantIds['component_b'] => 2.0,
        $variantIds['normal_a'] => 3.0,
        $variantIds['normal_b'] => 1.0,
    ];
    $assert(count($movementByVariant) === 4, 'Reîncercarea emiterii a dublat ieșirile din Stocuri Conta.');
    foreach ($expectedQuantities as $variantId => $quantity) {
        $assert(abs(($movementByVariant[$variantId] ?? 0.0) - $quantity) < 0.0001, 'Cantitate de ieșire incorectă pe factura mixtă.');
    }

    $balance = $pdo->prepare(
        'SELECT current_quantity FROM accounting_stock_balances WHERE product_variant_id=? AND warehouse_id=?'
    );
    foreach ($expectedQuantities as $variantId => $quantity) {
        $balance->execute([$variantId, $warehouseId]);
        $assert(abs((float) $balance->fetchColumn() + $quantity) < 0.0001, 'Sold contabil incorect după emiterea facturii mixte.');
    }

    $commercial = $pdo->prepare('SELECT stock_qty FROM product_variants WHERE id=?');
    foreach ($variantIds as $variantId) {
        $commercial->execute([$variantId]);
        $assert((int) $commercial->fetchColumn() === 40, 'Postarea contabilă a modificat încă o dată stocul comercial.');
    }

    $postedAt = $pdo->prepare('SELECT accounting_posted_at FROM invoices WHERE id=?');
    $postedAt->execute([$invoiceId]);
    $assert($postedAt->fetchColumn() !== null, 'Factura emisă nu a fost marcată ca postată în Stocuri Conta.');

    echo "Mixed set + normal products sales invoice regression test: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Mixed set + normal products sales invoice regression test: FAIL - {$exception->getMessage()}\n");
    $failed = true;
} finally {
    if ($variantIds) {
        $ids = array_values($variantIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare(
            "SELECT DISTINCT av.valuation_run_id FROM accounting_stock_valuations av "
            . "JOIN accounting_stock_movements m ON m.id=av.movement_id WHERE m.product_variant_id IN ($placeholders)"
        );
        $statement->execute($ids);
        $runIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare(
            "DELETE av FROM accounting_stock_valuations av JOIN accounting_stock_movements m ON m.id=av.movement_id "
            . "WHERE m.product_variant_id IN ($placeholders)"
        )->execute($ids);
        $pdo->prepare("DELETE FROM accounting_stock_balances WHERE product_variant_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM accounting_stock_movements WHERE product_variant_id IN ($placeholders)")->execute($ids);
    }
    if ($invoiceId) {
        $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id=?')->execute([$invoiceId]);
        $pdo->prepare('DELETE FROM invoices WHERE id=?')->execute([$invoiceId]);
    }
    if ($orderItemIds) {
        $ids = array_values($orderItemIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM order_items WHERE id IN ($placeholders)")->execute($ids);
    }
    if ($orderId) $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$orderId]);
    if ($runIds) {
        $placeholders = implode(',', array_fill(0, count($runIds), '?'));
        $pdo->prepare("DELETE FROM accounting_valuation_runs WHERE id IN ($placeholders)")->execute($runIds);
    }
    if ($variantIds) {
        $ids = array_values($variantIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM product_variants WHERE id IN ($placeholders)")->execute($ids);
    }
    if ($productIds) {
        $ids = array_values($productIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)")->execute($ids);
    }
    $pdo->prepare('DELETE FROM audit_logs WHERE correlation_id LIKE ?')->execute(['qa-set-mixed-%' . $suffix]);
}

exit($failed ? 1 : 0);
