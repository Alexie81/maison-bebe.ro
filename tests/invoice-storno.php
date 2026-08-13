<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\AccountingStockPostingService;
use MaisonBebe\Services\EInvoiceUblService;
use MaisonBebe\Services\InvoiceAccountingExportService;
use MaisonBebe\Services\InvoiceStornoService;
use MaisonBebe\Services\XlsxService;

$pdo = Database::connection();
$suffix = strtoupper(substr(bin2hex(random_bytes(7)), 0, 12));
$productId = $variantId = $orderId = $orderItemId = $seriesId = $invoiceId = $stornoId = 0;
$artifactPaths = [];
$valuationRunIds = [];
$temp = null;
$failed = false;
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$zipEntries = static function (string $zip): array {
    $entries = [];
    $offset = 0;
    while ($offset + 30 <= strlen($zip) && substr($zip, $offset, 4) === "PK\x03\x04") {
        $header = unpack('vneed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLength/vextraLength', substr($zip, $offset + 4, 26));
        $nameStart = $offset + 30;
        $name = substr($zip, $nameStart, $header['nameLength']);
        $dataStart = $nameStart + $header['nameLength'] + $header['extraLength'];
        $contents = substr($zip, $dataStart, $header['compressed']);
        if ((int) $header['method'] === 8) $contents = gzinflate($contents);
        $entries[$name] = $contents;
        $offset = $dataStart + $header['compressed'];
    }
    return $entries;
};

try {
    $companyId = (int) $pdo->query('SELECT id FROM company_profiles WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
    $warehouseId = (int) $pdo->query('SELECT id FROM accounting_warehouses WHERE is_default=1 ORDER BY id LIMIT 1')->fetchColumn();
    $assert($companyId > 0 && $warehouseId > 0, 'Lipsesc firma sau gestiunea implicită pentru testul storno.');

    $pdo->prepare("INSERT INTO invoice_series (company_profile_id,prefix,next_number,document_type,is_active) VALUES (?,?,2,'invoice',1)")
        ->execute([$companyId, 'QS' . substr($suffix, 0, 8)]);
    $seriesId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO products (name,slug,sku,status) VALUES (?,?,?,'active')")
        ->execute(['Produs storno ' . $suffix, 'produs-storno-' . strtolower($suffix), 'ST-P-' . $suffix]);
    $productId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_variants (product_id,sku,price_minor,cost_minor,stock_qty,track_inventory,track_accounting_stock,is_active) VALUES (?,?,?,?,20,0,1,1)')
        ->execute([$productId, 'ST-V-' . $suffix, 6050, 2500]);
    $variantId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        "INSERT INTO orders (order_number,public_token,idempotency_key,email,phone,customer_type,customer_snapshot_json,subtotal_minor,tax_total_minor,grand_total_minor,payment_method,payment_status,shipping_method) "
        . "VALUES (?,?,?,?,?,'company',?,10000,2100,12100,'cod','unpaid','courier')"
    )->execute([
        'QA-STORNO-' . $suffix,
        hash('sha256', 'storno-public-' . $suffix),
        hash('sha256', 'storno-idem-' . $suffix),
        'storno-' . strtolower($suffix) . '@example.test',
        '0700000000',
        json_encode(['company_name'=>'Client Storno SRL'], JSON_UNESCAPED_UNICODE),
    ]);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO order_items (order_id,product_id,variant_id,name_snapshot,sku_snapshot,unit_price_minor,quantity,total_minor,customization_json) "
        . "VALUES (?,?,?,?,?,6050,2,12100,'{}')"
    )->execute([$orderId, $productId, $variantId, 'Produs storno test', 'ST-V-' . $suffix]);
    $orderItemId = (int) $pdo->lastInsertId();

    $issuer = [
        'legal_name'=>'Maison Bebe Test SRL', 'tax_id'=>'RO26283407', 'vat_code'=>'RO26283407',
        'registration_number'=>'J03/1326/2009', 'billing_email'=>'contabilitate@example.test',
        'address'=>['line1'=>'Strada Test 1', 'city'=>'București', 'county'=>'București', 'country'=>'RO'],
    ];
    $customer = [
        'company_name'=>'Client Storno SRL', 'tax_id'=>'RO12345678', 'email'=>'client@example.test',
        'address'=>['line1'=>'Strada Client 2', 'city'=>'Cluj-Napoca', 'county'=>'Cluj', 'country'=>'RO'],
    ];
    $originalNumber = 'QS' . substr($suffix, 0, 8) . '000001';
    $pdo->prepare(
        "INSERT INTO invoices (order_id,company_profile_id,series_id,document_type,customer_type,number,status,currency,issue_date,delivery_date,due_date,issuer_snapshot_json,customer_snapshot_json,subtotal_minor,vat_minor,grand_total_minor,document_hash,issued_at) "
        . "VALUES (?,?,?,'invoice','company',?,'issued','RON','2004-06-14','2004-06-14','2004-06-29',?,?,10000,2100,12100,?,NOW())"
    )->execute([$orderId, $companyId, $seriesId, $originalNumber, json_encode($issuer, JSON_UNESCAPED_UNICODE), json_encode($customer, JSON_UNESCAPED_UNICODE), hash('sha256', 'original-' . $suffix)]);
    $invoiceId = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO invoice_items (invoice_id,order_item_id,name,sku,quantity,unit_price_minor,vat_rate,vat_minor,total_minor,sort_order) VALUES (?,?,?,?,2,5000,21,2100,10000,0)')
        ->execute([$invoiceId, $orderItemId, 'Produs storno test', 'ST-V-' . $suffix]);

    (new AccountingStockPostingService())->postSalesInvoiceOutflow($invoiceId, 'qa-storno-out-' . $suffix);
    $balance = $pdo->prepare('SELECT current_quantity FROM accounting_stock_balances WHERE product_variant_id=? AND warehouse_id=?');
    $balance->execute([$variantId, $warehouseId]);
    $assert(abs((float) $balance->fetchColumn() + 2.0) < 0.0001, 'Factura inițială nu a scăzut stocul contabil cu 2 bucăți.');

    $service = new InvoiceStornoService();
    $stornoId = $service->issueFull($invoiceId, '2004-06-15', 'Comandă anulată integral în test', true);
    $duplicateId = $service->issueFull($invoiceId, '2004-06-15', 'A doua solicitare idempotentă', true);
    $assert($duplicateId === $stornoId, 'Repetarea operațiunii a creat un al doilea storno.');

    $statement = $pdo->prepare('SELECT * FROM invoices WHERE id=?');
    $statement->execute([$stornoId]);
    $storno = $statement->fetch();
    $assert($storno && $storno['document_type'] === 'storno' && (int) $storno['parent_invoice_id'] === $invoiceId, 'Factura storno nu este legată de original.');
    $assert($storno['status'] === 'issued' && (int) $storno['grand_total_minor'] === -12100, 'Factura storno nu are statusul sau totalul negativ corect.');
    $statement->execute([$invoiceId]);
    $original = $statement->fetch();
    $assert($original['status'] === 'issued' && (int) $original['grand_total_minor'] === 12100, 'Factura inițială a fost rescrisă sau anulată direct.');
    $itemStatement = $pdo->prepare('SELECT quantity,total_minor,vat_minor FROM invoice_items WHERE invoice_id=?');
    $itemStatement->execute([$stornoId]);
    $stornoItem = $itemStatement->fetch();
    $assert((float) $stornoItem['quantity'] === -2.0 && (int) $stornoItem['total_minor'] === -10000 && (int) $stornoItem['vat_minor'] === -2100, 'Poziția storno nu este inversată integral.');
    $balance->execute([$variantId, $warehouseId]);
    $assert(abs((float) $balance->fetchColumn()) < 0.0001, 'Returul fizic nu a refăcut stocul contabil.');

    $artifact = $pdo->prepare("SELECT path FROM invoice_artifacts WHERE invoice_id=? AND artifact_type='pdf' ORDER BY id DESC LIMIT 1");
    $artifact->execute([$stornoId]);
    $pdfPath = (string) $artifact->fetchColumn();
    $assert($pdfPath !== '' && is_file(BASE_PATH . '/storage' . $pdfPath), 'PDF-ul facturii storno nu a fost generat.');
    $assert(str_contains((string) file_get_contents(BASE_PATH . '/storage' . $pdfPath), 'FACTURA STORNO'), 'PDF-ul nu identifică vizibil documentul ca storno.');

    $ubl = (new EInvoiceUblService())->generate($stornoId);
    $assert($ubl['standard'] === 'CN' && ($ubl['validation_standard'] ?? '') === 'FCN' && str_contains($ubl['xml'], '<CreditNote'), 'Storno nu este generat ca UBL CreditNote pentru standardul ANAF CN/FCN.');
    $assert(str_contains($ubl['xml'], '<cbc:CreditNoteTypeCode>381</cbc:CreditNoteTypeCode>'), 'Codul fiscal 381 lipsește din CreditNote.');
    $assert(!str_contains($ubl['xml'], '<cbc:DocumentTypeCode>'), 'CreditNote nu trebuie să includă DocumentTypeCode în referința facturii inițiale.');
    $assert(str_contains($ubl['xml'], $originalNumber) && str_contains($ubl['xml'], '<cbc:CreditedQuantity unitCode="C62">2</cbc:CreditedQuantity>'), 'XML-ul nu referă factura inițială sau cantitatea creditată.');

    $bundle = (new InvoiceAccountingExportService())->exportPeriod('2004-06-15', '2004-06-15');
    $entries = $zipEntries($bundle['binary']);
    $xmlName = 'e-factura/' . $ubl['filename'];
    $assert(isset($entries[$xmlName]) && str_contains($entries[$xmlName], '<CreditNote'), 'Pachetul contabil nu include XML-ul storno CreditNote.');
    $temp = tempnam(sys_get_temp_dir(), 'mb-storno-xlsx-');
    file_put_contents($temp, $entries[$bundle['xlsx_filename']]);
    $sheet = (new XlsxService())->import($temp);
    $flat = json_encode($sheet, JSON_UNESCAPED_UNICODE);
    $assert(str_contains((string) $flat, 'Factură storno') && str_contains((string) $flat, $originalNumber), 'Registrul XLSX nu marchează storno și factura inițială.');

    echo "Invoice full storno regression test: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Invoice full storno regression test: FAIL - {$exception->getMessage()}\n");
    $failed = true;
} finally {
    if ($temp && is_file($temp)) @unlink($temp);
    if ($variantId) {
        $runs = $pdo->prepare('SELECT DISTINCT av.valuation_run_id FROM accounting_stock_valuations av JOIN accounting_stock_movements m ON m.id=av.movement_id WHERE m.product_variant_id=?');
        $runs->execute([$variantId]);
        $valuationRunIds = array_map('intval', $runs->fetchAll(PDO::FETCH_COLUMN));
        $pdo->prepare('DELETE av FROM accounting_stock_valuations av JOIN accounting_stock_movements m ON m.id=av.movement_id WHERE m.product_variant_id=?')->execute([$variantId]);
        $pdo->prepare('DELETE FROM accounting_stock_balances WHERE product_variant_id=?')->execute([$variantId]);
        $pdo->prepare('DELETE FROM accounting_stock_movements WHERE product_variant_id=? ORDER BY reversal_of_movement_id IS NULL ASC,id DESC')->execute([$variantId]);
    }
    if ($invoiceId || $stornoId) {
        $ids = array_values(array_filter([$invoiceId, $stornoId]));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $paths = $pdo->prepare("SELECT path FROM invoice_artifacts WHERE invoice_id IN ($placeholders)");
        $paths->execute($ids);
        $artifactPaths = $paths->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("DELETE FROM efactura_submissions WHERE invoice_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM invoice_artifacts WHERE invoice_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM invoice_events WHERE invoice_id IN ($placeholders)")->execute($ids);
        $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id IN ($placeholders)")->execute($ids);
    }
    foreach ($artifactPaths as $relative) {
        $path = BASE_PATH . '/storage' . $relative;
        if (is_file($path)) @unlink($path);
    }
    if ($stornoId) $pdo->prepare('DELETE FROM invoices WHERE id=?')->execute([$stornoId]);
    if ($invoiceId) $pdo->prepare('DELETE FROM invoices WHERE id=?')->execute([$invoiceId]);
    if ($orderItemId) $pdo->prepare('DELETE FROM order_items WHERE id=?')->execute([$orderItemId]);
    if ($orderId) $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$orderId]);
    if ($valuationRunIds) {
        $placeholders = implode(',', array_fill(0, count($valuationRunIds), '?'));
        $pdo->prepare("DELETE FROM accounting_valuation_runs WHERE id IN ($placeholders)")->execute($valuationRunIds);
    }
    if ($variantId) $pdo->prepare('DELETE FROM product_variants WHERE id=?')->execute([$variantId]);
    if ($productId) $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$productId]);
    if ($seriesId) $pdo->prepare('DELETE FROM invoice_series WHERE id=?')->execute([$seriesId]);
}

exit($failed ? 1 : 0);
