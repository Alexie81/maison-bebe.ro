<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\EmailQueueService;

$pdo = Database::connection();
$recipient = (string) $pdo->query(
    "SELECT COALESCE(NULLIF(reply_to_email,''),from_email) "
    . "FROM email_senders WHERE purpose='orders' AND is_active=1 LIMIT 1"
)->fetchColumn();
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($recipient), '@maison-bebe.ro')) {
    throw new RuntimeException('Destinatarul QA trebuie să fie o adresă internă @maison-bebe.ro.');
}

$run = 'codex-email-audit-' . bin2hex(random_bytes(6));
$basePayload = [
    'order_id' => 0,
    'order_number' => 'QA-' . strtoupper(substr($run, -8)),
    'email' => $recipient,
    'first_name' => 'Client',
    'last_name' => 'QA',
    'total_minor' => 19400,
    'subtotal_minor' => 18900,
    'discount_minor' => 0,
    'shipping_minor' => 500,
    'items' => [[
        'name' => 'Produs demonstrativ Maison Bébé',
        'sku' => 'QA-LAUNCH',
        'options' => '0-3 luni',
        'quantity' => 1,
        'unit_price_minor' => 18900,
        'total_minor' => 18900,
        'image_url' => 'https://maison-bebe.ro/assets/images/packaging-reference.png',
    ]],
    'tracking_url' => 'https://maison-bebe.ro/urmarire-comanda',
];

$messages = [
    ['new_order_admin', '[QA] Comandă nouă', $basePayload + [
        'admin_url' => 'https://maison-bebe.ro/admin/comenzi',
    ]],
    ['order_confirmation_customer', '[QA] Confirmarea comenzii clientului', $basePayload],
    ['order_status', '[QA] Actualizarea statusului comenzii', $basePayload + [
        'status_label' => 'Pregătită pentru expediere',
        'message' => 'Acesta este un test intern de lansare. Nu există o comandă reală.',
    ]],
    ['invoice_customer', '[QA] Factura clientului', $basePayload + [
        'invoice_number' => 'QA-0001',
        'invoice_url' => 'https://maison-bebe.ro/',
    ]],
    ['password_reset', '[QA] Resetarea parolei', [
        'reset_url' => 'https://maison-bebe.ro/cont/resetare-parola',
    ]],
    ['welcome', '[QA] Bun venit în contul Maison Bébé', [
        'message' => 'Acesta este un test intern al emailului de cont.',
    ]],
    ['contact_admin', '[QA] Mesaj din formularul de contact', [
        'subject' => 'Test intern formular contact',
        'name' => 'Client QA',
        'email' => $recipient,
        'phone' => '+40 700 000 000',
        'message' => 'Acesta este un test intern de lansare; nu necesită răspuns.',
    ]],
];

$insert = $pdo->prepare(
    "INSERT INTO email_queue "
    . "(template_key,recipient,subject,payload_json,status,next_attempt_at,correlation_id) "
    . "VALUES (?,?,?,?,'pending',NOW(),?)"
);
$ids = [];
foreach ($messages as $index => [$template, $subject, $payload]) {
    $insert->execute([
        $template,
        $recipient,
        $subject . ' · ' . $run,
        json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $run . ':' . $index,
    ]);
    $ids[] = (int) $pdo->lastInsertId();
}

$metrics = (new EmailQueueService())->process(count($ids));
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$status = $pdo->prepare(
    "SELECT status,COUNT(*) total FROM email_queue WHERE id IN ({$placeholders}) GROUP BY status"
);
$status->execute($ids);
$states = array_column($status->fetchAll(), 'total', 'status');
if ((int) ($states['sent'] ?? 0) !== count($ids) || (int) ($metrics['sent'] ?? 0) < count($ids)) {
    $errors = $pdo->prepare(
        "SELECT template_key,status,last_error FROM email_queue WHERE id IN ({$placeholders}) AND status<>'sent'"
    );
    $errors->execute($ids);
    throw new RuntimeException('Emailurile QA nu au fost trimise: ' . json_encode($errors->fetchAll(), JSON_UNESCAPED_UNICODE));
}

fwrite(STDOUT, count($ids) . " transactional QA emails accepted by SMTP for {$recipient}\n");
