<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = MaisonBebe\Core\Database::connection();
echo "EMAIL_TEMPLATES\n";
foreach ($pdo->query('SELECT template_key, status, COUNT(*) total FROM email_queue GROUP BY template_key,status ORDER BY template_key,status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo implode('|', $row) . PHP_EOL;
}
echo "NOTIFICATIONS\n";
foreach ($pdo->query('SELECT entity_type,type,COUNT(*) total FROM notifications GROUP BY entity_type,type ORDER BY entity_type,type')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo implode('|', $row) . PHP_EOL;
}
echo "INVOICE_ARTIFACTS\n";
foreach ($pdo->query('SELECT id,artifact_type,storage_path FROM invoice_artifacts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo implode('|', $row) . PHP_EOL;
}
