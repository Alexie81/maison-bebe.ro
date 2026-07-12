<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

$pdo = MaisonBebe\Core\Database::connection();
$company = $pdo->query(
    'SELECT legal_name, trade_name, tax_id, registration_number, vat_status, vat_code, '
    . 'address_json, billing_email, phone, website FROM company_profiles '
    . 'WHERE is_active=1 ORDER BY id LIMIT 1'
)->fetch(PDO::FETCH_ASSOC);

echo json_encode($company, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
