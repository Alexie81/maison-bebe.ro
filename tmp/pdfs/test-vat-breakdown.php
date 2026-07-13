<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap.php';
use MaisonBebe\Core\Database;
use MaisonBebe\Services\PdfInvoiceRenderer;
$pdo = Database::connection();
$pdo->exec("UPDATE company_profiles SET vat_status='plătitor', vat_code='RO26283407' WHERE REPLACE(REPLACE(UPPER(tax_id),'RO',''),' ','')='26283407'");
$profile = $pdo->query("SELECT legal_name,tax_id,vat_status,vat_code FROM company_profiles WHERE is_active=1 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$invoice = $pdo->query("SELECT * FROM invoices WHERE status='issued' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$invoice) { throw new RuntimeException('Nu există factură locală pentru test.'); }
$stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order,id");
$stmt->execute([(int)$invoice['id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
$shipping = null;
foreach ($items as $item) { if ((string)$item['sku'] === 'TRANSPORT') { $shipping = $item; break; } }
$out = BASE_PATH . '/tmp/pdfs/test-vat-breakdown.pdf';
(new PdfInvoiceRenderer())->render($invoice, $items, $out);
echo json_encode([
  'profile'=>$profile,
  'invoice_id'=>(int)$invoice['id'],
  'shipping_net_minor'=>$shipping ? (int)$shipping['total_minor'] : null,
  'shipping_vat_minor'=>$shipping ? (int)$shipping['vat_minor'] : null,
  'shipping_gross_minor'=>$shipping ? (int)$shipping['total_minor'] + (int)$shipping['vat_minor'] : null,
  'pdf'=>$out,
  'pdf_bytes'=>filesize($out)
], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);