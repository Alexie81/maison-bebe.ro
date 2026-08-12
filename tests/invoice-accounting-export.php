<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\InvoiceAccountingExportService;
use MaisonBebe\Services\XlsxService;

$pdo = Database::connection();
$suffix = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
$failed = false;
$temp = null;
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$storedEntries = static function (string $zip): array {
    $entries = [];
    $offset = 0;
    $length = strlen($zip);
    while ($offset + 30 <= $length && substr($zip, $offset, 4) === "PK\x03\x04") {
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
    $pdo->beginTransaction();
    $companyId = (int) $pdo->query('SELECT id FROM company_profiles ORDER BY id LIMIT 1')->fetchColumn();
    $assert($companyId > 0, 'Lipsește profilul companiei pentru testul exportului contabil.');

    $pdo->prepare(
        "INSERT INTO orders (order_number,public_token,idempotency_key,email,phone,customer_type,customer_snapshot_json,subtotal_minor,tax_total_minor,grand_total_minor,payment_method,payment_status,shipping_method) "
        . "VALUES (?,?,?,?,?,'company',?,10000,2100,12100,'cod','unpaid','courier')"
    )->execute([
        'QA-EXPORT-' . $suffix,
        hash('sha256', 'export-public-' . $suffix),
        hash('sha256', 'export-idem-' . $suffix),
        'contabilitate-' . strtolower($suffix) . '@example.test',
        '0700000000',
        json_encode(['company_name' => 'Client contabilitate SRL'], JSON_UNESCAPED_UNICODE),
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $issuer = [
        'legal_name' => 'Maison Bebe Test SRL', 'tax_id' => 'RO26283407', 'vat_code' => 'RO26283407',
        'registration_number' => 'J03/1326/2009', 'billing_email' => 'contabilitate@example.test',
        'address' => ['line1' => 'Str. Test nr. 1', 'city' => 'București', 'county' => 'București', 'country' => 'RO'],
    ];
    $customer = [
        'company_name' => 'Client contabilitate SRL', 'tax_id' => 'RO12345678', 'email' => 'client@example.test', 'phone' => '0712000000',
        'address' => ['line1' => 'Bd. Test nr. 2', 'city' => 'Cluj-Napoca', 'county' => 'Cluj', 'country' => 'RO'],
    ];
    $pdo->prepare(
        "INSERT INTO invoices (order_id,company_profile_id,document_type,customer_type,number,status,currency,issue_date,delivery_date,due_date,issuer_snapshot_json,customer_snapshot_json,subtotal_minor,vat_minor,grand_total_minor,document_hash,issued_at) "
        . "VALUES (?,?,'invoice','company',?,'issued','RON','2002-04-05','2002-04-05','2002-04-20',?,?,10000,2100,12100,?,NOW())"
    )->execute([
        $orderId,
        $companyId,
        'QA-EXPORT-' . $suffix,
        json_encode($issuer, JSON_UNESCAPED_UNICODE),
        json_encode($customer, JSON_UNESCAPED_UNICODE),
        hash('sha256', 'invoice-' . $suffix),
    ]);
    $invoiceId = (int) $pdo->lastInsertId();
    $insertItem = $pdo->prepare('INSERT INTO invoice_items (invoice_id,name,sku,quantity,unit_price_minor,vat_rate,vat_minor,total_minor,sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
    $insertItem->execute([$invoiceId, 'Produs contabil A', 'QA-A-' . $suffix, 2, 2500, 21, 1050, 5000, 0]);
    $insertItem->execute([$invoiceId, 'Produs contabil B', 'QA-B-' . $suffix, 1, 5000, 21, 1050, 5000, 1]);

    $bundle = (new InvoiceAccountingExportService())->exportPeriod('2002-04-05', '2002-04-05');
    $assert(str_starts_with($bundle['binary'], "PK\x03\x04"), 'Pachetul contabil nu este o arhivă ZIP validă.');
    $assert((int) $bundle['invoice_count'] >= 1 && (int) $bundle['line_count'] >= 2, 'Exportul nu a inclus factura și liniile ei.');
    $entries = $storedEntries($bundle['binary']);
    $xlsxName = (string) $bundle['xlsx_filename'];
    $xmlName = 'e-factura/RO-eFactura-QA-EXPORT-' . $suffix . '.xml';
    $assert(isset($entries[$xlsxName]), 'Registrul XLSX lipsește din pachetul ZIP.');
    $assert(isset($entries[$xmlName]), 'Fișierul RO e-Factura lipsește din pachetul ZIP.');
    $assert(str_contains($entries[$xmlName], 'QA-EXPORT-' . $suffix), 'XML-ul RO e-Factura nu conține numărul facturii.');
    $assert(str_contains($entries[$xmlName], 'Produs contabil A') && str_contains($entries[$xmlName], 'Produs contabil B'), 'XML-ul nu conține toate pozițiile facturii.');

    $temp = tempnam(sys_get_temp_dir(), 'mb-invoice-export-');
    file_put_contents($temp, $entries[$xlsxName]);
    $imported = (new XlsxService())->import($temp);
    $flat = json_encode($imported, JSON_UNESCAPED_UNICODE);
    $assert(str_contains((string) $flat, 'Număr factură') && str_contains((string) $flat, 'QA-EXPORT-' . $suffix), 'Registrul XLSX nu conține datele contabile ale facturii.');

    $sampleDirectory = trim((string) getenv('MAISON_ACCOUNTING_EXPORT_SAMPLE_DIR'));
    if ($sampleDirectory !== '') {
        if (!is_dir($sampleDirectory) && !mkdir($sampleDirectory, 0775, true) && !is_dir($sampleDirectory)) {
            throw new RuntimeException('Directorul de verificare pentru export nu a putut fi creat.');
        }
        file_put_contents($sampleDirectory . DIRECTORY_SEPARATOR . $xlsxName, $entries[$xlsxName]);
        file_put_contents($sampleDirectory . DIRECTORY_SEPARATOR . (string) $bundle['filename'], $bundle['binary']);
    }

    echo "Invoice accounting ZIP export regression test: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Invoice accounting ZIP export regression test: FAIL - {$exception->getMessage()}\n");
    $failed = true;
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($temp && is_file($temp)) @unlink($temp);
}

exit($failed ? 1 : 0);
