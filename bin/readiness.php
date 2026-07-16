<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use MaisonBebe\Core\Env;
use MaisonBebe\Services\EmailQueueService;
use MaisonBebe\Services\SmtpMailer;
use MaisonBebe\Services\StripeService;

$network = in_array('--network', $argv, true);
$checks = [];
$add = static function (string $name, bool $ok, string $details, bool $required = true) use (&$checks): void {
    $checks[] = compact('name', 'ok', 'details', 'required');
};

foreach (['curl', 'fileinfo', 'json', 'mbstring', 'openssl', 'pdo_mysql'] as $extension) {
    $add('Extensie PHP: ' . $extension, extension_loaded($extension), extension_loaded($extension) ? 'disponibilă' : 'lipsește');
}

$environment = (string) Env::get('APP_ENV', 'production');
$appUrl = rtrim((string) Env::get('APP_URL', ''), '/');
$add('URL HTTPS', str_starts_with($appUrl, 'https://') || $environment !== 'production', $appUrl ?: 'APP_URL lipsește');
$add('Cheie aplicație', strlen((string) Env::get('APP_KEY', '')) >= 32, 'minimum 32 caractere');
$add('Cookie securizat', Env::bool('SESSION_SECURE', false) || $environment !== 'production', $environment === 'production' ? 'obligatoriu în producție' : 'mediu local');

$pdo = Database::connection();
$add('Conexiune MySQL', true, (string) $pdo->query('SELECT DATABASE()')->fetchColumn());
$tables = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();
$add('Schemă completă', $tables >= 95, $tables . ' tabele');

$catalog = $pdo->query(
    "SELECT "
    . "(SELECT COUNT(*) FROM products WHERE status='active' AND deleted_at IS NULL) products,"
    . "(SELECT COUNT(*) FROM categories WHERE is_active=1 AND deleted_at IS NULL) categories,"
    . "(SELECT COUNT(*) FROM collections WHERE is_active=1 AND deleted_at IS NULL) collections,"
    . "(SELECT COUNT(*) FROM gift_box_templates WHERE is_active=1) boxes"
)->fetch();
$add('Produse publicate', (int) $catalog['products'] > 0, (string) $catalog['products']);
$add('Categorii publicate', (int) $catalog['categories'] > 0, (string) $catalog['categories'], false);
$add('Colecții publicate', (int) $catalog['collections'] > 0, (string) $catalog['collections'], false);
$add('Cutii Gift Box', (int) $catalog['boxes'] > 0, (string) $catalog['boxes'], false);

$stripe = $pdo->query(
    "SELECT p.*,c.encrypted_payload FROM payment_providers p "
    . "LEFT JOIN payment_provider_credentials c ON c.provider_id=p.id "
    . "WHERE p.code='stripe' LIMIT 1"
)->fetch();
$stripeCredentials = [];
if ($stripe && !empty($stripe['encrypted_payload'])) {
    try {
        $stripeCredentials = json_decode(Encryptor::decrypt((string) $stripe['encrypted_payload']), true) ?: [];
    } catch (Throwable) {
        $stripeCredentials = [];
    }
}
$stripeEnabled = $stripe && (int) $stripe['is_enabled'] === 1;
$secretKey = trim((string) ($stripeCredentials['secret_key'] ?? ''));
$webhookSecret = trim((string) ($stripeCredentials['webhook_secret'] ?? ''));
$add('Stripe activ', $stripeEnabled, $stripe ? (string) $stripe['environment'] : 'provider absent');
$add('Cheie Stripe live', str_starts_with($secretKey, 'sk_live_'), $secretKey === '' ? 'lipsește' : (str_starts_with($secretKey, 'sk_live_') ? 'mod live' : 'nu este live'));
$add('Webhook Stripe', str_starts_with($webhookSecret, 'whsec_'), $webhookSecret === '' ? 'secret lipsă' : 'secret configurat');

$codEnabled = (bool) $pdo->query("SELECT EXISTS(SELECT 1 FROM payment_providers WHERE code='cod' AND is_enabled=1)")->fetchColumn();
$add('Plată ramburs', $codEnabled, $codEnabled ? 'activă' : 'inactivă', false);

$emailRows = $pdo->query(
    "SELECT purpose,smtp_host,smtp_port,smtp_encryption,smtp_username,is_active,"
    . "health_status,last_tested_at,encrypted_password FROM email_senders ORDER BY purpose"
)->fetchAll();
$emailByPurpose = [];
foreach ($emailRows as $row) {
    $emailByPurpose[(string) $row['purpose']] = $row;
}
foreach (['orders', 'invoices', 'recovery', 'account', 'general'] as $purpose) {
    $profile = $emailByPurpose[$purpose] ?? null;
    $configured = $profile
        && (int) $profile['is_active'] === 1
        && trim((string) $profile['smtp_host']) !== ''
        && trim((string) $profile['smtp_username']) !== ''
        && !empty($profile['encrypted_password']);
    $add('Email ' . $purpose, (bool) $configured, $profile ? (string) $profile['health_status'] : 'profil absent');
}

$recipients = (int) $pdo->query(
    "SELECT COUNT(*) FROM order_email_recipients WHERE is_active=1 AND receive_new_orders=1"
)->fetchColumn();
$add('Destinatari interni comenzi', $recipients > 0, (string) $recipients);

$queue = $pdo->query(
    "SELECT "
    . "SUM(status='pending') pending,"
    . "SUM(status='failed') failed,"
    . "SUM(status='requires_attention') attention "
    . "FROM email_queue"
)->fetch() ?: [];
$attention = (int) ($queue['attention'] ?? 0);
$add('Coadă email fără blocaje', $attention === 0, 'pending=' . (int) ($queue['pending'] ?? 0) . ', failed=' . (int) ($queue['failed'] ?? 0) . ', atenție=' . $attention);

$shipping = $pdo->query(
    "SELECT code,environment FROM shipping_providers "
    . "WHERE is_enabled=1 AND is_default=1 ORDER BY id LIMIT 1"
)->fetch();
$add('Livrare implicită', (bool) $shipping, $shipping ? $shipping['code'] . ' (' . $shipping['environment'] . ')' : 'lipsește');

$company = $pdo->query(
    "SELECT id,legal_name,tax_id,address_json,billing_email FROM company_profiles "
    . "WHERE is_active=1 ORDER BY id LIMIT 1"
)->fetch();
$companyAddress = $company ? json_decode((string) $company['address_json'], true) : [];
$companyReady = $company
    && trim((string) $company['legal_name']) !== ''
    && trim((string) $company['tax_id']) !== ''
    && trim((string) ($companyAddress['line1'] ?? '')) !== ''
    && filter_var((string) $company['billing_email'], FILTER_VALIDATE_EMAIL);
$add('Date fiscale firmă', (bool) $companyReady, $company ? (string) $company['legal_name'] : 'profil absent');

$invoiceReady = (bool) $pdo->query(
    "SELECT "
    . "EXISTS(SELECT 1 FROM invoice_series WHERE is_active=1) "
    . "AND EXISTS(SELECT 1 FROM invoice_templates WHERE is_active=1 AND is_default=1)"
)->fetchColumn();
$add('Facturare', $invoiceReady, $invoiceReady ? 'serie și șablon configurate' : 'configurație incompletă');

$googleRaw = $pdo->prepare("SELECT value_json FROM settings WHERE setting_key='google_auth' LIMIT 1");
$googleRaw->execute();
$google = json_decode((string) $googleRaw->fetchColumn(), true) ?: [];
$googleReady = !empty($google['enabled']) && !empty($google['client_id']) && !empty($google['encrypted_client_secret']);
$add('Autentificare Google', $googleReady, $googleReady ? 'configurată' : 'dezactivată sau incompletă', false);

$primaryAdmins = (int) $pdo->query(
    "SELECT COUNT(DISTINCT u.id) FROM users u "
    . "JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id "
    . "WHERE r.name='super_admin' AND u.deleted_at IS NULL AND u.status='active'"
)->fetchColumn();
$add('Administrator principal', $primaryAdmins === 1, (string) $primaryAdmins);

if ($network) {
    try {
        $diagnostics = (new StripeService())->diagnostics();
        $add(
            'Stripe API live',
            !empty($diagnostics['api_livemode']) && !empty($diagnostics['charges_enabled']),
            !empty($diagnostics['account_id'])
                ? $diagnostics['account_id'] . '; charges=' . (!empty($diagnostics['charges_enabled']) ? 'on' : 'off')
                : 'cont indisponibil'
        );
    } catch (Throwable $exception) {
        $add('Stripe API live', false, $exception->getMessage());
    }

    foreach ($emailRows as $row) {
        if ((int) $row['is_active'] !== 1) {
            continue;
        }
        try {
            $profile = (new EmailQueueService())->profile((string) $row['purpose']);
            (new SmtpMailer())->test($profile);
            $add('SMTP ' . $row['purpose'], true, 'conectare și autentificare reușite');
        } catch (Throwable $exception) {
            $add('SMTP ' . $row['purpose'], false, $exception->getMessage());
        }
    }
}

$failedRequired = false;
foreach ($checks as $check) {
    $label = $check['ok'] ? 'OK' : ($check['required'] ? 'FAIL' : 'WARN');
    echo '[' . $label . '] ' . $check['name'] . ': ' . $check['details'] . PHP_EOL;
    if (!$check['ok'] && $check['required']) {
        $failedRequired = true;
    }
}

exit($failedRequired ? 1 : 0);
