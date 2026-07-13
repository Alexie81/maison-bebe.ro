<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$pdo = MaisonBebe\Core\Database::connection();
foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    if (preg_match('/order|invoice|product|categor|collection|gift_box|cart|wishlist|review|stock_reservation|inventory_movement|efactura|shipment|payment|awb/i', (string) $table)) {
        $count = $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', (string) $table) . '`')->fetchColumn();
        echo $table . '=' . $count . PHP_EOL;
    }
}
