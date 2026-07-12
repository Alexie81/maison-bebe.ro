<?php

declare(strict_types=1);

namespace MaisonBebe\Controllers\Admin;

use MaisonBebe\Controllers\Controller;
use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Core\Response;
use MaisonBebe\Core\Session;
use MaisonBebe\Services\InvoiceService;
use Throwable;

final class BillingController extends Controller
{
    private function admin(string $view, array $data = []): string
    {
        return view($view, $data + ['adminUser' => Auth::user(), 'notice' => Session::flash('admin_notice'), 'error' => Session::flash('admin_error')], 'layouts/admin');
    }

    public function overview(Request $request): string
    {
        $pdo = Database::connection();
        $stats = ['issued' => (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status='issued'")->fetchColumn(), 'month_total' => (int) $pdo->query("SELECT COALESCE(SUM(grand_total_minor),0) FROM invoices WHERE status='issued' AND YEAR(issue_date)=YEAR(CURDATE()) AND MONTH(issue_date)=MONTH(CURDATE())")->fetchColumn(), 'attention' => (int) $pdo->query("SELECT COUNT(*) FROM invoices WHERE status IN ('failed','unknown_requires_sync')")->fetchColumn(), 'queued' => (int) $pdo->query("SELECT COUNT(*) FROM invoice_issue_jobs WHERE status IN ('pending','retry','requires_attention')")->fetchColumn()];
        $company = $pdo->query('SELECT * FROM company_profiles WHERE is_active=1 ORDER BY id LIMIT 1')->fetch() ?: null;
        $connectors = $pdo->query('SELECT * FROM invoice_connectors ORDER BY is_default DESC,name')->fetchAll();
        $recent = $pdo->query('SELECT i.*,o.order_number FROM invoices i LEFT JOIN orders o ON o.id=i.order_id ORDER BY i.created_at DESC LIMIT 8')->fetchAll();
        return $this->admin('admin/billing-overview', compact('stats', 'company', 'connectors', 'recent'));
    }

    public function company(Request $request): string
    {
        $pdo = Database::connection();
        $company = $pdo->query('SELECT * FROM company_profiles ORDER BY is_active DESC,id LIMIT 1')->fetch();
        $address = json_decode((string) ($company['address_json'] ?? '{}'), true) ?: [];
        $banks = $company ? $pdo->query('SELECT * FROM company_bank_accounts WHERE company_profile_id=' . (int) $company['id'] . ' ORDER BY is_default DESC')->fetchAll() : [];
        $series = $company ? $pdo->query('SELECT * FROM invoice_series WHERE company_profile_id=' . (int) $company['id'] . ' ORDER BY document_type,prefix')->fetchAll() : [];
        return $this->admin('admin/billing-company', compact('company', 'address', 'banks', 'series'));
    }

    public function saveCompany(Request $request): never
    {
        $legal = trim((string) $request->input('legal_name', ''));
        $tax = strtoupper(trim((string) $request->input('tax_id', '')));
        if ($legal === '' || $tax === '' || $tax === 'DE_COMPLETAT') {
            throw new HttpException(422, 'Denumirea legală și CUI-ul real sunt obligatorii.');
        }
        $address = ['line1' => trim((string) $request->input('address_line1', '')), 'city' => trim((string) $request->input('city', '')), 'county' => trim((string) $request->input('county', '')), 'postal_code' => trim((string) $request->input('postal_code', '')), 'country' => trim((string) $request->input('country', 'RO')) ?: 'RO'];
        $pdo = Database::connection();
        $id = (int) $request->input('id', 0);
        if ($id) {
            $pdo->prepare('UPDATE company_profiles SET legal_name=?,trade_name=?,tax_id=?,registration_number=?,vat_status=?,vat_code=?,fiscal_regime=?,share_capital=?,address_json=?,billing_email=?,phone=?,website=?,default_due_days=?,default_notes=? WHERE id=?')->execute([$legal, trim((string) $request->input('trade_name', '')), $tax, trim((string) $request->input('registration_number', '')), trim((string) $request->input('vat_status', '')), trim((string) $request->input('vat_code', '')), trim((string) $request->input('fiscal_regime', '')), trim((string) $request->input('share_capital', '')), json_encode($address, JSON_UNESCAPED_UNICODE), trim((string) $request->input('billing_email', '')), trim((string) $request->input('phone', '')), trim((string) $request->input('website', '')), max(0, (int) $request->input('default_due_days', 0)), trim((string) $request->input('default_notes', '')), $id]);
        } else {
            $pdo->prepare('INSERT INTO company_profiles (legal_name,trade_name,tax_id,registration_number,address_json,billing_email,phone,website) VALUES (?,?,?,?,?,?,?,?)')->execute([$legal, trim((string) $request->input('trade_name', '')), $tax, trim((string) $request->input('registration_number', '')), json_encode($address), trim((string) $request->input('billing_email', '')), trim((string) $request->input('phone', '')), trim((string) $request->input('website', ''))]);
            $id = (int) $pdo->lastInsertId();
        }
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $request->input('series_prefix', 'MB')) ?: 'MB');
        $next = max(1, (int) $request->input('series_next', 1));
        $pdo->prepare("INSERT INTO invoice_series (company_profile_id,prefix,next_number,document_type,is_active) VALUES (?,?,?,'invoice',1) ON DUPLICATE KEY UPDATE next_number=GREATEST(next_number,VALUES(next_number)),is_active=1")->execute([$id, $prefix, $next]);
        $iban = strtoupper(str_replace(' ', '', trim((string) $request->input('iban', ''))));
        if ($iban !== '') {
            $pdo->prepare('INSERT INTO company_bank_accounts (company_profile_id,iban,bank_name,currency,is_default) VALUES (?,?,?,\'RON\',1)')->execute([$id, $iban, trim((string) $request->input('bank_name', ''))]);
        }
        $this->audit('company_profile.updated', 'company_profile', $id);
        Session::flash('admin_notice', 'Datele firmei și seria de facturare au fost salvate.');
        Response::redirect('/admin/facturare/firma');
    }

    public function connectors(Request $request): string
    {
        $items = Database::connection()->query('SELECT c.*,cr.id credential_id FROM invoice_connectors c LEFT JOIN invoice_connector_credentials cr ON cr.connector_id=c.id ORDER BY c.is_default DESC,c.name')->fetchAll();
        return $this->admin('admin/billing-connectors', compact('items'));
    }

    public function saveConnector(Request $request, string $code): never
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT id,mode FROM invoice_connectors WHERE code=?');
        $statement->execute([$code]);
        $connector = $statement->fetch();
        if (!$connector) {
            throw new HttpException(404, 'Conectorul nu a fost găsit.');
        }
        $environment = (string) $request->input('environment', $connector['mode'] === 'internal' ? 'internal' : 'sandbox');
        if (!in_array($environment, ['sandbox','test','live','internal'], true)) {
            throw new HttpException(422, 'Mediul conectorului este invalid.');
        }
        $config = ['api_url' => trim((string) $request->input('api_url', '')), 'series' => trim((string) $request->input('series', ''))];
        $pdo->prepare('UPDATE invoice_connectors SET environment=?,is_enabled=?,is_default=?,config_json=?,last_health_status=? WHERE id=?')->execute([$environment, $request->input('is_enabled') ? 1 : 0, $request->input('is_default') ? 1 : 0, json_encode($config), $connector['mode'] === 'internal' ? 'healthy' : 'not_configured', $connector['id']]);
        $apiKey = trim((string) $request->input('api_key', ''));
        if ($apiKey !== '') {
            $secret = Encryptor::encrypt(json_encode(['api_key' => $apiKey, 'api_secret' => trim((string) $request->input('api_secret', ''))], JSON_THROW_ON_ERROR));
            $pdo->prepare('INSERT INTO invoice_connector_credentials (connector_id,encrypted_payload,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE encrypted_payload=VALUES(encrypted_payload),updated_by=VALUES(updated_by)')->execute([$connector['id'], $secret, Auth::id()]);
            $pdo->prepare("UPDATE invoice_connectors SET last_health_status='not_tested' WHERE id=?")->execute([$connector['id']]);
        }
        $this->audit('invoice_connector.updated', 'invoice_connector', (int) $connector['id']);
        Session::flash('admin_notice', 'Conectorul de facturare a fost salvat.');
        Response::redirect('/admin/facturare/conectori');
    }

    public function templates(Request $request): string
    {
        $items = Database::connection()->query('SELECT t.*,MAX(v.version_no) latest_version,COUNT(f.id) field_count FROM invoice_templates t LEFT JOIN invoice_template_versions v ON v.template_id=t.id LEFT JOIN invoice_template_fields f ON f.version_id=v.id GROUP BY t.id ORDER BY t.is_default DESC,t.name')->fetchAll();
        return $this->admin('admin/billing-templates', compact('items'));
    }

    public function chooseTemplate(Request $request, string $id): never
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        $pdo->exec('UPDATE invoice_templates SET is_default=0');
        $pdo->prepare('UPDATE invoice_templates SET is_default=1,is_active=1 WHERE id=?')->execute([(int) $id]);
        $pdo->commit();
        Session::flash('admin_notice', 'Șablonul implicit a fost actualizat.');
        Response::redirect('/admin/facturare/sabloane');
    }

    public function mapper(Request $request): string
    {
        $versions = Database::connection()->query('SELECT v.id,v.version_no,t.name template_name FROM invoice_template_versions v JOIN invoice_templates t ON t.id=v.template_id WHERE t.is_active=1 ORDER BY t.is_default DESC,t.name,v.version_no DESC')->fetchAll();
        $versionId = (int) $request->input('version_id', $versions[0]['id'] ?? 0);
        $statement = Database::connection()->prepare('SELECT * FROM invoice_template_fields WHERE version_id=? ORDER BY page_no,y,x');
        $statement->execute([$versionId]);
        return $this->admin('admin/billing-mapper', ['versions' => $versions, 'versionId' => $versionId, 'fields' => $statement->fetchAll()]);
    }

    public function saveMapper(Request $request): never
    {
        $allowed = ['invoice.number','invoice.issue_date','invoice.due_date','issuer.legal_name','issuer.tax_id','issuer.address','customer.name','customer.tax_id','customer.address','items.table','totals.subtotal','totals.vat','totals.grand_total','invoice.notes'];
        $variable = (string) $request->input('variable_name', '');
        $type = (string) $request->input('field_type', 'text');
        if (!in_array($variable, $allowed, true) || !in_array($type, ['text','image','table','array'], true)) {
            throw new HttpException(422, 'Câmpul selectat nu este permis în șablon.');
        }
        $version = (int) $request->input('version_id', 0);
        Database::connection()->prepare('INSERT INTO invoice_template_fields (version_id,variable_name,page_no,x,y,width,height,field_type,options_json) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$version, $variable, max(1, (int) $request->input('page_no', 1)), (float) $request->input('x', 0), (float) $request->input('y', 0), max(1, (float) $request->input('width', 100)), max(1, (float) $request->input('height', 20)), $type, json_encode(['font_size' => max(6, min(40, (int) $request->input('font_size', 10)))])]);
        Session::flash('admin_notice', 'Câmpul a fost adăugat în mapper.');
        Response::redirect('/admin/facturare/sabloane/mapper?version_id=' . $version);
    }

    public function invoices(Request $request): string
    {
        $items = Database::connection()->query('SELECT i.*,o.order_number,o.email FROM invoices i LEFT JOIN orders o ON o.id=i.order_id ORDER BY i.created_at DESC LIMIT 200')->fetchAll();
        return $this->admin('admin/invoices', compact('items'));
    }

    public function invoice(Request $request, string $id): string
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT i.*,o.order_number,o.email,a.path artifact_path FROM invoices i LEFT JOIN orders o ON o.id=i.order_id LEFT JOIN invoice_artifacts a ON a.invoice_id=i.id AND a.artifact_type=\'pdf\' WHERE i.id=? ORDER BY a.id DESC LIMIT 1');
        $statement->execute([(int) $id]);
        $invoice = $statement->fetch();
        if (!$invoice) {
            throw new HttpException(404, 'Factura nu a fost găsită.');
        }
        $items = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order');
        $items->execute([(int) $id]);
        $events = $pdo->prepare('SELECT * FROM invoice_events WHERE invoice_id=? ORDER BY created_at DESC');
        $events->execute([(int) $id]);
        return $this->admin('admin/invoice', ['invoice' => $invoice, 'items' => $items->fetchAll(), 'events' => $events->fetchAll()]);
    }

    public function issueOrder(Request $request, string $id): never
    {
        try {
            $invoiceId = (new InvoiceService())->issueForOrder((int) $id);
            Session::flash('admin_notice', 'Factura a fost emisă; emailul către client a fost pus în coadă.');
            Response::redirect('/admin/facturi/' . $invoiceId);
        } catch (Throwable $exception) {
            Session::flash('admin_error', $exception->getMessage());
            Response::redirect('/admin/comenzi/' . $id);
        }
    }

    public function efactura(Request $request): string
    {
        $pdo = Database::connection();
        $connections = $pdo->query('SELECT a.*,c.legal_name FROM anaf_connections a JOIN company_profiles c ON c.id=a.company_profile_id ORDER BY a.environment')->fetchAll();
        $submissions = $pdo->query('SELECT s.*,i.number invoice_number FROM efactura_submissions s JOIN invoices i ON i.id=s.invoice_id ORDER BY s.updated_at DESC LIMIT 50')->fetchAll();
        return $this->admin('admin/billing-efactura', compact('connections', 'submissions'));
    }

    public function saveEfactura(Request $request): never
    {
        $company = (int) Database::connection()->query('SELECT id FROM company_profiles WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
        if (!$company) {
            throw new HttpException(422, 'Completează mai întâi datele firmei.');
        }
        $environment = (string) $request->input('environment', 'production');
        if (!in_array($environment, ['test','production'], true)) {
            throw new HttpException(422, 'Mediu ANAF invalid.');
        }
        $config = ['client_id' => trim((string) $request->input('client_id', '')), 'redirect_uri' => absolute_url('/admin/facturare/efactura/callback')];
        Database::connection()->prepare("INSERT INTO anaf_connections (company_profile_id,environment,status,config_json) VALUES (?,?,'not_configured',?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json),status='not_configured',last_error=NULL")->execute([$company, $environment, json_encode($config, JSON_UNESCAPED_SLASHES)]);
        Session::flash('admin_notice', 'Conexiunea RO e-Factura a fost pregătită. Activarea necesită autorizarea OAuth ANAF a firmei.');
        Response::redirect('/admin/facturare/efactura');
    }

    private function audit(string $action, string $target, int $id): void
    {
        Database::connection()->prepare('INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address) VALUES (?,?,?,?,?)')->execute([Auth::id(), $action, $target, $id, $_SERVER['REMOTE_ADDR'] ?? null]);
    }
}
