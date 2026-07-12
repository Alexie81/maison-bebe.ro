<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use RuntimeException;
use Throwable;

final class InvoiceService
{
    private function regenerateInvoice(int $invoiceId, bool $useCurrentDefault = false): void
    {
        $pdo=Database::connection();
        if($useCurrentDefault){$version=$pdo->query('SELECT v.id FROM invoice_template_versions v JOIN invoice_templates t ON t.id=v.template_id WHERE t.is_active=1 AND t.is_default=1 ORDER BY v.version_no DESC LIMIT 1')->fetchColumn();if($version)$pdo->prepare('UPDATE invoices SET template_version_id=? WHERE id=?')->execute([(int)$version,$invoiceId]);}
        $statement=$pdo->prepare('SELECT * FROM invoices WHERE id=?');$statement->execute([$invoiceId]);$invoice=$statement->fetch();if(!$invoice)throw new RuntimeException('Factura nu a fost găsită.');
        $rows=$pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order,id');$rows->execute([$invoiceId]);
        $artifact=$pdo->prepare("SELECT id,path FROM invoice_artifacts WHERE invoice_id=? AND artifact_type='pdf' ORDER BY id DESC LIMIT 1");$artifact->execute([$invoiceId]);$stored=$artifact->fetch();
        $relative=$stored?(string)$stored['path']:('/invoices/'.date('Y/m').'/invoice-'.$invoiceId.'-'.bin2hex(random_bytes(8)).'.pdf');$path=BASE_PATH.'/storage'.$relative;
        (new PdfInvoiceRenderer())->render($invoice,$rows->fetchAll(),$path);$hash=hash_file('sha256',$path);
        $pdo->prepare("UPDATE invoices SET document_hash=?,updated_at=NOW() WHERE id=?")->execute([$hash,$invoiceId]);
        if($stored){$pdo->prepare('UPDATE invoice_artifacts SET sha256=?,size_bytes=? WHERE id=?')->execute([$hash,filesize($path),(int)$stored['id']]);}else{$pdo->prepare("INSERT INTO invoice_artifacts (invoice_id,artifact_type,path,mime_type,sha256,size_bytes) VALUES (?,'pdf',?,'application/pdf',?,?)")->execute([$invoiceId,$relative,$hash,filesize($path)]);}
        $pdo->prepare("INSERT INTO invoice_events (invoice_id,event_type,status,created_by,payload_json) VALUES (?,'regenerated','issued',?,?)")->execute([$invoiceId,Auth::id(),json_encode(['template_version_id'=>$invoice['template_version_id']],JSON_UNESCAPED_UNICODE)]);
    }

    public function sendToCustomer(int $invoiceId, bool $updated = false): void
    {
        $pdo=Database::connection();
        $statement=$pdo->prepare("SELECT i.number,i.document_hash,o.order_number,o.email FROM invoices i JOIN orders o ON o.id=i.order_id WHERE i.id=? AND i.status='issued' LIMIT 1");
        $statement->execute([$invoiceId]);$invoice=$statement->fetch();
        if(!$invoice||empty($invoice['email'])||empty($invoice['document_hash'])) throw new RuntimeException('Factura nu este pregătită pentru trimitere.');
        $payload=json_encode(['invoice_number'=>$invoice['number'],'order_number'=>$invoice['order_number'],'invoice_url'=>absolute_url('/factura/'.$invoice['document_hash']),'updated'=>$updated],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $correlation='invoice:'.$invoiceId.':'.date('YmdHis').':'.bin2hex(random_bytes(3));
        $subject=($updated?'Factura actualizată ':'Factura ').$invoice['number'].' pentru comanda '.$invoice['order_number'];
        $pdo->prepare("INSERT INTO email_queue (template_key,recipient,subject,payload_json,status,next_attempt_at,correlation_id) VALUES ('invoice_customer',?,?,?,'pending',NOW(),?)")->execute([$invoice['email'],$subject,$payload,$correlation]);
        $pdo->prepare("INSERT INTO invoice_events (invoice_id,event_type,status,created_by,payload_json) VALUES (?,'email_queued','pending',?,?)")->execute([$invoiceId,Auth::id(),json_encode(['recipient'=>$invoice['email'],'updated'=>$updated],JSON_UNESCAPED_UNICODE)]);
    }
    public function issueForOrder(int $orderId, bool $sendEmail = true): int
    {
        $pdo = Database::connection();
        $existing = $pdo->prepare("SELECT id FROM invoices WHERE order_id=? AND document_type='invoice' AND status IN ('issuing','issued') ORDER BY id LIMIT 1");
        $existing->execute([$orderId]);
        if ($id = (int) $existing->fetchColumn()) {
            $this->regenerateInvoice($id, true);
            if ($sendEmail) $this->sendToCustomer($id, true);
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
            $companyAddress=json_decode((string)($company['address_json']??'{}'),true)?:[];
            $bankReady=$company?(bool)$pdo->query("SELECT EXISTS(SELECT 1 FROM company_bank_accounts WHERE company_profile_id=".(int)$company['id']." AND iban<>'' AND bank_name<>'')")->fetchColumn():false;
            if (!$company || trim((string)$company['legal_name'])==='' || trim((string)$company['tax_id'])==='' || $company['tax_id'] === 'DE_COMPLETAT' || trim((string)$company['registration_number'])==='' || trim((string)($companyAddress['line1']??''))==='' || trim((string)($companyAddress['city']??''))==='' || trim((string)$company['billing_email'])==='' || !$bankReady) {
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
            if ($sendEmail) $this->sendToCustomer($invoiceId);
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
