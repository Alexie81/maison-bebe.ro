<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\EInvoiceUblService;

$pdo = Database::connection();
$invoiceId = (int) $pdo->query("SELECT id FROM invoices WHERE status='issued' ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($invoiceId < 1) {
    throw new RuntimeException('Nu exista o factura emisa pentru test.');
}

$document = (new EInvoiceUblService())->generate($invoiceId);
$dom = new DOMDocument();
if (!$dom->loadXML($document['xml'])) {
    throw new RuntimeException('XML invalid sintactic.');
}

$xpath = new DOMXPath($dom);
$xpath->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
$xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
$xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

$checks = [
    'seller_name' => 'count(/ubl:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName[normalize-space(.) != ""]) = 1',
    'buyer_name' => 'count(/ubl:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName[normalize-space(.) != ""]) = 1',
    'vat_breakdown' => 'count(/ubl:Invoice/cac:TaxTotal/cac:TaxSubtotal) >= 1',
    'seller_subdivision' => 'starts-with(/ubl:Invoice/cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:CountrySubentity, "RO-")',
    'buyer_subdivision' => 'starts-with(/ubl:Invoice/cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cbc:CountrySubentity, "RO-")',
];

$result = ['invoice_id' => $invoiceId, 'filename' => $document['filename'], 'checks' => []];
foreach ($checks as $key => $expression) {
    $result['checks'][$key] = (bool) $xpath->evaluate($expression);
}

$lines = $xpath->query('/ubl:Invoice/cac:InvoiceLine');
$result['invoice_lines'] = $lines->length;
$result['line_tax_categories'] = [];
foreach ($lines as $line) {
    $result['line_tax_categories'][] = (int) $xpath->evaluate('count(cac:Item/cac:ClassifiedTaxCategory[cac:TaxScheme/cbc:ID="VAT"])', $line);
}

$result['all_passed'] = !in_array(false, $result['checks'], true)
    && $result['invoice_lines'] > 0
    && !in_array(0, $result['line_tax_categories'], true)
    && !array_filter($result['line_tax_categories'], static fn (int $count): bool => $count !== 1);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
