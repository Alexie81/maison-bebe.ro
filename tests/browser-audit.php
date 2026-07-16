<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Env;

$pdo = Database::connection();
require __DIR__ . '/admin-qa-user.php';
$admin = createAdminQaUser($pdo);

$password = 'Codex-Browser-' . bin2hex(random_bytes(12));
$originalHash = (string) $admin['password_hash'];
$pdo->prepare("UPDATE users SET password_hash=?,status='active' WHERE id=?")
    ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);

$command = implode(' ', [
    'node',
    escapeshellarg(__DIR__ . '/browser-audit.mjs'),
    escapeshellarg(rtrim((string) Env::get('APP_URL', ''), '/')),
    escapeshellarg((string) $admin['email']),
    escapeshellarg($password),
]);

try {
    passthru($command, $exitCode);
} finally {
    $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')
        ->execute([$originalHash, $admin['id']]);
    deleteAdminQaUser($pdo, (int) $admin['id']);
}

exit($exitCode);
