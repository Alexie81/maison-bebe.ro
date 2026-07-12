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
use MaisonBebe\Services\EmailQueueService;
use MaisonBebe\Services\NewsletterService;
use MaisonBebe\Services\SmtpMailer;
use Throwable;

final class SettingsController extends Controller
{
    private function admin(string $view, array $data = []): string
    {
        return view($view, $data + ['adminUser' => Auth::user(), 'notice' => Session::flash('admin_notice'), 'error' => Session::flash('admin_error')], 'layouts/admin');
    }

    public function email(Request $request): string
    {
        $pdo = Database::connection();
        (new NewsletterService())->ensureSchema($pdo);
        $senders = $pdo->query("SELECT id,purpose,from_email,from_name,reply_to_email,smtp_host,smtp_port,smtp_encryption,smtp_username,is_active,health_status,last_health_message,last_tested_at,encrypted_password IS NOT NULL has_password FROM email_senders ORDER BY FIELD(purpose,'orders','invoices','recovery','account','marketing','general')")->fetchAll();
        $recipients = $pdo->query('SELECT * FROM order_email_recipients ORDER BY is_active DESC,email')->fetchAll();
        $queue = $pdo->query("SELECT status,COUNT(*) total FROM email_queue GROUP BY status")->fetchAll();
        return $this->admin('admin/settings-email', compact('senders', 'recipients', 'queue'));
    }

    public function saveEmail(Request $request, string $purpose): never
    {
        if (!in_array($purpose, ['orders', 'invoices', 'recovery', 'account', 'marketing', 'general'], true)) {
            throw new HttpException(404, 'Profil email necunoscut.');
        }
        $email = mb_strtolower(trim((string) $request->input('from_email', '')));
        $host = trim((string) $request->input('smtp_host', ''));
        $username = trim((string) $request->input('smtp_username', ''));
        $encryption = (string) $request->input('smtp_encryption', 'ssl');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $host === '' || $username === '' || !in_array($encryption, ['ssl', 'tls', 'none'], true)) {
            throw new HttpException(422, 'Configurația SMTP nu este validă.');
        }
        $pdo = Database::connection();
        $password = (string) $request->input('smtp_password', '');
        $encrypted = $password !== '' ? Encryptor::encrypt($password) : null;
        $statement = $pdo->prepare('UPDATE email_senders SET from_email=?,from_name=?,reply_to_email=?,smtp_host=?,smtp_port=?,smtp_encryption=?,smtp_username=?,is_active=?,health_status=IF(? IS NULL,health_status,\'not_tested\'),encrypted_password=COALESCE(?,encrypted_password),updated_by=? WHERE purpose=?');
        $statement->execute([$email, trim((string) $request->input('from_name', 'Maison Bébé')) ?: 'Maison Bébé', trim((string) $request->input('reply_to_email', '')) ?: null, $host, max(1, min(65535, (int) $request->input('smtp_port', 465))), $encryption, $username, $request->input('is_active') ? 1 : 0, $encrypted, $encrypted, Auth::id(), $purpose]);
        $this->audit('email_sender.updated', 'email_sender', null, ['purpose' => $purpose, 'from_email' => $email]);
        Session::flash('admin_notice', 'Profilul de email a fost salvat. Parola nu este afișată și rămâne criptată.');
        Response::redirect('/admin/setari/email');
    }

    public function saveRecipients(Request $request): never
    {
        $lines = preg_split('/[\r\n,;]+/', (string) $request->input('recipients', '')) ?: [];
        $emails = [];
        foreach ($lines as $line) {
            $email = mb_strtolower(trim($line));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[$email] = true;
            }
        }
        if (!$emails) {
            throw new HttpException(422, 'Adaugă cel puțin o adresă validă pentru primirea comenzilor.');
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        $pdo->exec('UPDATE order_email_recipients SET is_active=0,receive_new_orders=0');
        $statement = $pdo->prepare('INSERT INTO order_email_recipients (email,is_active,receive_new_orders,receive_failures) VALUES (?,1,1,1) ON DUPLICATE KEY UPDATE is_active=1,receive_new_orders=1,receive_failures=1');
        foreach (array_keys($emails) as $email) {
            $statement->execute([$email]);
        }
        $pdo->commit();
        $this->audit('order_recipients.updated', 'setting', null, ['recipients' => array_keys($emails)]);
        Session::flash('admin_notice', 'Destinatarii interni pentru comenzi au fost actualizați.');
        Response::redirect('/admin/setari/email');
    }

    public function testEmail(Request $request, string $purpose): never
    {
        try {
            $statement = Database::connection()->prepare('SELECT * FROM email_senders WHERE purpose=? LIMIT 1');
            $statement->execute([$purpose]);
            $profile = $statement->fetch();
            if (!$profile) {
                throw new HttpException(404, 'Profil email necunoscut.');
            }
            $profile['password'] = !empty($profile['encrypted_password']) ? Encryptor::decrypt((string) $profile['encrypted_password']) : '';
            $message = (new SmtpMailer())->test($profile);
            Database::connection()->prepare("UPDATE email_senders SET health_status='healthy',last_health_message=?,last_tested_at=NOW() WHERE purpose=?")->execute([$message, $purpose]);
            Session::flash('admin_notice', $message);
        } catch (Throwable $exception) {
            Database::connection()->prepare("UPDATE email_senders SET health_status='error',last_health_message=?,last_tested_at=NOW() WHERE purpose=?")->execute([mb_substr($exception->getMessage(), 0, 500), $purpose]);
            Session::flash('admin_error', 'Test SMTP eșuat: ' . $exception->getMessage());
        }
        Response::redirect('/admin/setari/email');
    }

    public function payments(Request $request): string
    {
        $items = Database::connection()->query('SELECT p.*,h.status health_status,h.message health_message,h.checked_at FROM payment_providers p LEFT JOIN payment_provider_health h ON h.id=(SELECT id FROM payment_provider_health WHERE provider_id=p.id ORDER BY checked_at DESC LIMIT 1) ORDER BY p.sort_order')->fetchAll();
        return $this->admin('admin/settings-payments', compact('items'));
    }

    public function payment(Request $request, string $provider): string
    {
        $statement = Database::connection()->prepare('SELECT p.*,c.id credential_id FROM payment_providers p LEFT JOIN payment_provider_credentials c ON c.provider_id=p.id WHERE p.code=?');
        $statement->execute([$provider]);
        $item = $statement->fetch();
        if (!$item) {
            throw new HttpException(404, 'Procesator necunoscut.');
        }
        return $this->admin('admin/settings-payment', compact('item'));
    }

    public function savePayment(Request $request, string $provider): never
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT id FROM payment_providers WHERE code=?');
        $statement->execute([$provider]);
        $id = (int) $statement->fetchColumn();
        if (!$id) {
            throw new HttpException(404, 'Procesator necunoscut.');
        }
        $environment = (string) $request->input('environment', 'test');
        if (!in_array($environment, ['test', 'live', 'sandbox'], true)) {
            throw new HttpException(422, 'Mediu invalid.');
        }
        $config = ['public_key' => trim((string) $request->input('public_key', '')), 'webhook_url' => absolute_url('/webhooks/plati/' . $provider)];
        $pdo->prepare('UPDATE payment_providers SET environment=?,is_enabled=?,config_json=? WHERE id=?')->execute([$environment, $request->input('is_enabled') ? 1 : 0, json_encode($config), $id]);
        $secret = trim((string) $request->input('secret_key', ''));
        if ($secret !== '') {
            $payload = Encryptor::encrypt(json_encode(['secret_key' => $secret, 'webhook_secret' => trim((string) $request->input('webhook_secret', ''))], JSON_THROW_ON_ERROR));
            $pdo->prepare('INSERT INTO payment_provider_credentials (provider_id,encrypted_payload,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE encrypted_payload=VALUES(encrypted_payload),updated_by=VALUES(updated_by)')->execute([$id, $payload, Auth::id()]);
        }
        $this->audit('payment_provider.updated', 'payment_provider', $id, ['environment' => $environment]);
        Session::flash('admin_notice', 'Procesatorul a fost actualizat. Cheile secrete sunt criptate.');
        Response::redirect('/admin/setari/plati/' . $provider);
    }

    public function authentication(Request $request): string
    {
        $setting = $this->setting('google_auth', []);
        $setting['configured_secret'] = !empty($setting['encrypted_client_secret']);
        unset($setting['encrypted_client_secret']);
        return $this->admin('admin/settings-auth', compact('setting'));
    }

    public function saveAuthentication(Request $request): never
    {
        $current = $this->setting('google_auth', []);
        $secret = trim((string) $request->input('client_secret', ''));
        $value = ['enabled' => $request->input('enabled') ? true : false, 'client_id' => trim((string) $request->input('client_id', '')), 'redirect_uri' => absolute_url('/auth/google/callback'), 'encrypted_client_secret' => $secret !== '' ? Encryptor::encrypt($secret) : ($current['encrypted_client_secret'] ?? null)];
        $this->saveSetting('google_auth', $value);
        Session::flash('admin_notice', 'Configurarea Google Auth a fost salvată.');
        Response::redirect('/admin/setari/autentificare');
    }

    public function shipping(Request $request): string
    {
        $items = Database::connection()->query('SELECT p.*,c.id credential_id FROM shipping_providers p LEFT JOIN shipping_provider_credentials c ON c.provider_id=p.id ORDER BY p.is_default DESC,p.name')->fetchAll();
        $announcement=$this->setting('announcement_bar',['enabled'=>true,'text'=>'Livrare gratuită pentru comenzile de peste 500 lei, pregătite cu grijă ca un cadou.']);
        return $this->admin('admin/settings-shipping', compact('items','announcement'));
    }

    public function saveShipping(Request $request, string $provider): never
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT id FROM shipping_providers WHERE code=?');
        $statement->execute([$provider]);
        $id = (int) $statement->fetchColumn();
        if (!$id) {
            throw new HttpException(404, 'Curier necunoscut.');
        }
        $config = ['base_price_minor'=>max(0,(int)round(((float)$request->input('base_price',0))*100)),'free_threshold_minor'=>max(0,(int)round(((float)$request->input('free_threshold',0))*100)),'free_shipping_enabled'=>$request->input('free_shipping_enabled')?true:false,'api_url'=>trim((string)$request->input('api_url',''))];
        if($request->input('is_default')){
            $current=$this->setting('announcement_bar',['enabled'=>true,'text'=>'Livrare gratuită pentru comenzile de peste 500 lei, pregătite cu grijă ca un cadou.']);
            $text=trim((string)$request->input('announcement_text',''))?:((string)($current['text']??''));
            $enabled=$request->input('announcement_text',null)!==null?($request->input('announcement_enabled')?true:false):(bool)($current['enabled']??true);
            $this->saveSetting('announcement_bar',['enabled'=>$enabled,'text'=>$text]);
            $pdo->prepare('UPDATE shipping_providers SET is_default=0 WHERE id<>?')->execute([$id]);
        }
        $pdo->prepare('UPDATE shipping_providers SET is_enabled=?,is_default=?,environment=?,config_json=? WHERE id=?')->execute([$request->input('is_enabled') ? 1 : 0, $request->input('is_default') ? 1 : 0, (string) $request->input('environment', 'manual'), json_encode($config), $id]);
        $username = trim((string) $request->input('api_username', ''));
        $password = trim((string) $request->input('api_password', ''));
        if ($username !== '' || $password !== '') {
            $encrypted = Encryptor::encrypt(json_encode(compact('username', 'password'), JSON_THROW_ON_ERROR));
            $pdo->prepare('INSERT INTO shipping_provider_credentials (provider_id,encrypted_payload,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE encrypted_payload=VALUES(encrypted_payload),updated_by=VALUES(updated_by)')->execute([$id, $encrypted, Auth::id()]);
        }
        $this->audit('shipping_provider.updated', 'shipping_provider', $id, ['provider' => $provider]);
        Session::flash('admin_notice', 'Setările de livrare au fost salvate.');
        Response::redirect('/admin/setari/livrare');
    }

    private function setting(string $key, mixed $default): mixed
    {
        $statement = Database::connection()->prepare('SELECT value_json FROM settings WHERE setting_key=?');
        $statement->execute([$key]);
        $value = $statement->fetchColumn();
        return $value === false ? $default : (json_decode((string) $value, true) ?? $default);
    }

    private function saveSetting(string $key, mixed $value): void
    {
        Database::connection()->prepare('INSERT INTO settings (setting_key,value_json,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE value_json=VALUES(value_json),updated_by=VALUES(updated_by)')->execute([$key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), Auth::id()]);
        $this->audit('setting.updated', 'setting', null, ['key' => $key]);
    }

    private function audit(string $action, ?string $target, ?int $id, array $metadata): void
    {
        Database::connection()->prepare('INSERT INTO audit_logs (actor_user_id,action,target_type,target_id,ip_address,metadata_json) VALUES (?,?,?,?,?,?)')->execute([Auth::id(), $action, $target, $id, $_SERVER['REMOTE_ADDR'] ?? null, json_encode($metadata, JSON_UNESCAPED_UNICODE)]);
    }
}
