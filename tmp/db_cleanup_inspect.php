<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = MaisonBebe\Core\Database::connection();
$targets = ['orders', 'invoice_documents', 'products', 'categories', 'collections', 'invoice_series'];
foreach ($targets as $table) {
    try {
        $count = $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        echo $table . '=' . $count . PHP_EOL;
    } catch (Throwable) {
        echo $table . '=ERR' . PHP_EOL;
    }
}

echo "FK\n";
$statement = $pdo->query(<<<'SQL'
SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME IN ('orders','invoices','invoice_series','products','product_variants','product_options','product_option_values','categories','collections','gift_box_templates','gift_box_components')
ORDER BY REFERENCED_TABLE_NAME, TABLE_NAME
SQL);
foreach ($statement->fetchAll(PDO::FETCH_NUM) as $row) {
    echo implode('|', $row) . PHP_EOL;
}
