<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use RuntimeException;
use Throwable;

final class InvoiceService
{
    public function issueForOrder(int $orderId): int
    {
        $pdo = Database::connection();
        $existing = $pdo->prepare("SELECT id FROM invoices WHERE order_id=? AND document_type='invoice' AND status IN ('issuing','issued') ORDER BY id LIMIT 1");
        $existing->execute([$orderId]);
        if ($id = (int) $existing->fetchColumn()) {
            return $id;
        }
        $pdo->beginTransaction();
        try {
            $orderStatement = $pdo->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
            $orderStatement->execute([$orderId]);
            $order = $orderStatement->fetch();
            if (!$order) {
                throw new RuntimeException('Comanda nu a fost găsită.');
            }
            $company = $pdo->query('SELECT * FROM company_profiles WHERE is_active=1 ORDER BY id LIMIT 1 FOR UPDATE')->fetch();
            if (!$company || $company['tax_id'] === 'DE_COMPLETAT') {
                throw new RuntimeException('Completează datele fiscale ale firmei înainte de emitere.');
            }
            $series = $pdo->prepare("SELECT * FROM invoice_series WHERE company_profile_id=? AND document_type='invoice' AND is_active=1 ORDER BY id LIMIT 1 FOR UPDATE");
            $series->execute([$company['id']]);
            $series = $series->fetch();
            if (!$series) {
                throw new RuntimeException('Nu există o serie activă de facturi.');
            }
            $number = $series['prefix'] . str_pad((string) $series['next_number'], 6, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE invoice_series SET next_number=next_number+1 WHERE id=?')->execute([$series['id']]);
            $customer = json_decode((string) $order['customer_snapshot_json'], true) ?: [];
            $customer['email'] = $order['email'];
            $customer['phone'] = $order['phone'];
            $address = $pdo->prepare("SELECT snapshot_json FROM order_addresses WHERE order_id=? ORDER BY type='billing' DESC LIMIT 1");
            $address->execute([$orderId]);
            $customer['address'] = json_decode((string) $address->fetchColumn(), true) ?: [];
            $issuer = $company;
            $issuer['address'] = json_decode((string) $company['address_json'], true) ?: [];
            $templateVersion = $pdo->query('SELECT v.id FROM invoice_template_versions v JOIN invoice_templates t ON t.id=v.template_id WHERE t.is_active=1 AND t.is_default=1 ORDER BY v.version_no DESC LIMIT 1')->fetchColumn() ?: null;
            $connector = $pdo->query("SELECT id FROM invoice_connectors WHERE code='internal' AND is_enabled=1 LIMIT 1")->fetchColumn() ?: null;
            $statement = $pdo->prepare("INSERT INTO invoices (order_id,company_profile_id,series_id,template_version_id,connector_id,document_type,customer_type,number,status,currency,issue_date,due_date,issuer_snapshot_json,customer_snapshot_json,subtotal_minor,discount_minor,vat_minor,grand_total_minor,notes) VALUES (?,?,?,?,?,'invoice',?,?, 'issuing',?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL ? DAY),?,?,?,?,?,?,?)");
            $statement->execute([$orderId, $company['id'], $series['id'], $templateVersion, $connector, $order['customer_type'], $number, $order['currency'], (int) $company['default_due_days'], json_encode($issuer, JSON_UNESCAPED_UNICODE), json_encode($customer, JSON_UNESCAPED_UNICODE), $order['subtotal_minor'], $order['discount_total_minor'], $order['tax_total_minor'], $order['grand_total_minor'], $company['default_notes']]);
            $invoiceId = (int) $pdo->lastInsertId();
            $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
            $items->execute([$orderId]);
            $rows = $items->fetchAll();
            $insert = $pdo->prepare('INSERT INTO invoice_items (invoice_id,order_item_id,name,sku,quantity,unit_price_minor,discount_minor,vat_rate,vat_minor,total_minor,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($rows as $index => $item) {
                $insert->execute([$invoiceId, $item['id'], $item['name_snapshot'], $item['sku_snapshot'], $item['quantity'], $item['unit_price_minor'], $item['discount_minor'], 0, $item['tax_minor'], $item['total_minor'], $index]);
            }
            if ((int) $order['shipping_total_minor'] > 0) {
                $insert->execute([$invoiceId, null, 'Livrare', 'TRANSPORT', 1, $order['shipping_total_minor'], 0, 0, 0, $order['shipping_total_minor'], count($rows)]);
            }
            $pdo->prepare("INSERT INTO invoice_events (invoice_id,event_type,status,created_by) VALUES (?,'issue_started','issuing',?)")->execute([$invoiceId, Auth::id()]);
            $pdo->commit();

            $invoice = $pdo->prepare('SELECT * FROM invoices WHERE id=?');
            $invoice->execute([$invoiceId]);
            $invoice = $invoice->fetch();
            $pdfItems = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order');
            $pdfItems->execute([$invoiceId]);
            $relative = '/invoices/' . date('Y/m') . '/invoice-' . $invoiceId . '-' . bin2hex(random_bytes(8)) . '.pdf';
            $path = BASE_PATH . '/storage' . $relative;
            (new PdfInvoiceRenderer())->render($invoice, $pdfItems->fetchAll(), $path);
            $hash = hash_file('sha256', $path);
            $pdo->prepare("UPDATE invoices SET status='issued',document_hash=?,issued_at=NOW() WHERE id=?")->execute([$hash, $invoiceId]);
            $pdo->prepare("INSERT INTO invoice_artifacts (invoice_id,artifact_type,path,mime_type,sha256,size_bytes) VALUES (?,'pdf',?,'application/pdf',?,?)")->execute([$invoiceId, $relative, $hash, filesize($path)]);
            $pdo->prepare("INSERT INTO invoice_events (invoice_id,event_type,status,created_by) VALUES (?,'issued','issued',?)")->execute([$invoiceId, Auth::id()]);
            $payload = json_encode(['invoice_number' => $number, 'order_number' => $order['order_number'], 'invoice_url' => absolute_url('/factura/' . $hash)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $pdo->prepare("INSERT INTO email_queue (template_key,recipient,subject,payload_json,status,next_attempt_at,correlation_id) VALUES ('invoice_customer',?,?,?,'pending',NOW(),?)")->execute([$order['email'], 'Factura ' . $number . ' pentru comanda ' . $order['order_number'], $payload, 'invoice:' . $invoiceId]);
            return $invoiceId;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (isset($invoiceId)) {
                $pdo->prepare("UPDATE invoices SET status='failed' WHERE id=?")->execute([$invoiceId]);
                $pdo->prepare("INSERT INTO invoice_events (invoice_id,event_type,status,error_message,created_by) VALUES (?,'issue_failed','failed',?,?)")->execute([$invoiceId, mb_substr($exception->getMessage(), 0, 1000), Auth::id()]);
            }
            throw $exception;
        }
    }
}
