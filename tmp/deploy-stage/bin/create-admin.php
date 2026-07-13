<?php
declare(strict_types=1);

use MaisonBebe\Core\Database;

require dirname(__DIR__) . '/bootstrap.php';

[$script, $email, $password, $firstName, $lastName] = array_pad($argv, 5, null);
if (!$email || !$password || !$firstName || !$lastName) {
    fwrite(STDERR, "Utilizare: php bin/create-admin.php email parola prenume nume\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Email invalid sau parolă mai scurtă de 12 caractere.\n");
    exit(1);
}

$pdo = Database::connection();
$pdo->beginTransaction();
try {
    $statement = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, last_name, status, email_verified_at) VALUES (?, ?, ?, ?, 'active', NOW()) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), first_name=VALUES(first_name), last_name=VALUES(last_name), status='active'");
    $statement->execute([mb_strtolower($email), password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName]);
    $userId = (int) ($pdo->lastInsertId() ?: $pdo->query('SELECT id FROM users WHERE email = ' . $pdo->quote(mb_strtolower($email)))->fetchColumn());
    $roleId = (int) $pdo->query("SELECT id FROM roles WHERE name = 'super_admin'")->fetchColumn();
    $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);
    $pdo->commit();
    echo "Super administrator creat/actualizat pentru {$email}.\n";
} catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
}

