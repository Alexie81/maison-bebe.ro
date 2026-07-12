<?php
declare(strict_types=1);

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use MaisonBebe\Core\SqlRunner;

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::connection();
$hasSchema = (bool) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'migrations'")->fetchColumn();

if (!$hasSchema) {
    echo "Import schema...\n";
    SqlRunner::runFile($pdo, BASE_PATH . '/database/schema.sql');
}

$pdo->exec("CREATE TABLE IF NOT EXISTS migrations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(190) NOT NULL UNIQUE, batch INT UNSIGNED NOT NULL, executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();

foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $migration) {
    $name = basename($migration);
    $check = $pdo->prepare('SELECT 1 FROM migrations WHERE migration = ?');
    $check->execute([$name]);
    if ($check->fetchColumn()) {
        continue;
    }
    echo "Migration: {$name}\n";
    SqlRunner::runFile($pdo, $migration);
    $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)')->execute([$name, $batch]);
}

echo "Import seed idempotent...\n";
SqlRunner::runFile($pdo, BASE_PATH . '/database/seed.sql');

$password = (string) env('SMTP_PASSWORD', '');
if ($password !== '') {
    $encrypted = Encryptor::encrypt($password);
    $sql = "INSERT INTO email_senders (purpose, from_email, from_name, reply_to_email, smtp_host, smtp_port, smtp_encryption, smtp_username, encrypted_password, is_active)
            VALUES (:purpose, :email, :name, :reply, :host, :port, :encryption, :username, :password, 1)
            ON DUPLICATE KEY UPDATE from_email=VALUES(from_email), from_name=VALUES(from_name), reply_to_email=VALUES(reply_to_email), smtp_host=VALUES(smtp_host), smtp_port=VALUES(smtp_port), smtp_encryption=VALUES(smtp_encryption), smtp_username=VALUES(smtp_username), encrypted_password=VALUES(encrypted_password), is_active=1";
    $statement = $pdo->prepare($sql);
    foreach (['orders', 'invoices', 'account', 'general'] as $purpose) {
        $statement->execute([
            'purpose' => $purpose,
            'email' => env('MAIL_FROM_ADDRESS', 'comenzi@maison-bebe.ro'),
            'name' => env('MAIL_FROM_NAME', 'Maison Bébé'),
            'reply' => env('MAIL_FROM_ADDRESS', 'comenzi@maison-bebe.ro'),
            'host' => env('SMTP_HOST', ''),
            'port' => (int) env('SMTP_PORT', 465),
            'encryption' => env('SMTP_ENCRYPTION', 'ssl'),
            'username' => env('SMTP_USERNAME', ''),
            'password' => $encrypted,
        ]);
    }
}

echo "Instalare/upgrade finalizat. Creează primul administrator cu bin/create-admin.php.\n";

