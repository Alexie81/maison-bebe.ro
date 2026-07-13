<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

use MaisonBebe\Core\Database;

$pdo = Database::connection();
$checks = [
    'permissions' => (int) $pdo->query("SELECT COUNT(*) FROM permissions WHERE name <> '*'")->fetchColumn(),
    'roles' => (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn(),
    'admin_users' => (int) $pdo->query("SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.name <> 'customer'")->fetchColumn(),
    'settings_manage' => (int) $pdo->query("SELECT COUNT(*) FROM permissions WHERE name='settings.manage'")->fetchColumn(),
    'products_create' => (int) $pdo->query("SELECT COUNT(*) FROM permissions WHERE name='products.create'")->fetchColumn(),
    'billing_issue' => (int) $pdo->query("SELECT COUNT(*) FROM permissions WHERE name='billing.issue'")->fetchColumn(),
];

$checks['all_passed'] = $checks['permissions'] > 0
    && $checks['roles'] > 0
    && $checks['admin_users'] > 0
    && $checks['settings_manage'] === 1
    && $checks['products_create'] === 1
    && $checks['billing_issue'] === 1;

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
