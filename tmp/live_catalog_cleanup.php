<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;

$execute = in_array('--execute', $argv, true);
$pdo = Database::connection();

$tables = [
    'awb_jobs', 'blog_post_products', 'cart_items', 'categories', 'collection_products', 'collections',
    'coupon_categories', 'coupon_products', 'coupon_usages', 'efactura_submissions', 'gift_box_components',
    'gift_box_customizations', 'gift_box_templates', 'inventory_movements', 'invoice_artifacts', 'invoice_events',
    'invoice_issue_jobs', 'invoice_items', 'invoice_series', 'invoices', 'order_addresses', 'order_items',
    'order_notes', 'order_status_history', 'orders', 'payment_events', 'payments', 'product_categories',
    'product_images', 'product_option_values', 'product_options', 'product_variants', 'products', 'reviews',
    'shipment_events', 'shipment_labels', 'shipments', 'stock_reservations', 'variant_option_values', 'wishlist_items',
];

$existing = array_flip($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
$snapshot = [
    'created_at' => date(DATE_ATOM),
    'database' => (string) $pdo->query('SELECT DATABASE()')->fetchColumn(),
    'tables' => [],
    'scoped' => [],
];

foreach ($tables as $table) {
    if (!isset($existing[$table])) {
        continue;
    }
    $snapshot['tables'][$table] = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
}

if (isset($existing['notifications'])) {
    $snapshot['scoped']['notifications'] = $pdo->query("SELECT * FROM notifications WHERE entity_type IN ('order','invoice') OR type='new_order'")->fetchAll(PDO::FETCH_ASSOC);
}
if (isset($existing['email_queue'])) {
    $snapshot['scoped']['email_queue'] = $pdo->query("SELECT * FROM email_queue WHERE template_key IN ('new_order_admin','order_confirmation_customer','order_status','invoice_customer')")->fetchAll(PDO::FETCH_ASSOC);
}
if (isset($existing['seo_audit_results'])) {
    $snapshot['scoped']['seo_audit_results'] = $pdo->query("SELECT * FROM seo_audit_results WHERE entity_type IN ('product','category','collection')")->fetchAll(PDO::FETCH_ASSOC);
}
if (isset($existing['sitemap_events'])) {
    $snapshot['scoped']['sitemap_events'] = $pdo->query("SELECT * FROM sitemap_events WHERE entity_type IN ('product','category','collection')")->fetchAll(PDO::FETCH_ASSOC);
}

$backupDirectory = dirname(__DIR__) . '/storage/backups';
if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0775, true) && !is_dir($backupDirectory)) {
    throw new RuntimeException('Folderul de backup nu a putut fi creat.');
}
$backupPath = $backupDirectory . '/pre-live-cleanup-' . date('Ymd-His') . '.json';
$encoded = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
if (file_put_contents($backupPath, $encoded, LOCK_EX) === false) {
    throw new RuntimeException('Backupul nu a putut fi scris.');
}

$summary = [];
foreach ($snapshot['tables'] as $table => $rows) {
    $summary[$table] = count($rows);
}
echo 'BACKUP=' . $backupPath . PHP_EOL;
echo 'COUNTS=' . json_encode($summary, JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (!$execute) {
    echo "DRY_RUN=1\n";
    exit(0);
}

$deleteTables = [
    'efactura_submissions', 'invoice_artifacts', 'invoice_events', 'invoice_issue_jobs', 'invoice_items', 'invoices',
    'payment_events', 'shipment_events', 'shipment_labels', 'awb_jobs', 'coupon_usages', 'order_addresses',
    'order_items', 'order_notes', 'order_status_history', 'payments', 'shipments', 'stock_reservations', 'orders',
    'gift_box_customizations', 'cart_items', 'blog_post_products', 'collection_products', 'coupon_products',
    'coupon_categories', 'product_categories', 'product_images', 'variant_option_values', 'product_option_values',
    'product_options', 'inventory_movements', 'reviews', 'wishlist_items', 'gift_box_components',
    'gift_box_templates', 'product_variants', 'products', 'collections', 'categories',
];

$pdo->beginTransaction();
try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($deleteTables as $table) {
        if (isset($existing[$table])) {
            $pdo->exec('DELETE FROM `' . $table . '`');
        }
    }
    if (isset($existing['notifications'])) {
        $pdo->exec("DELETE FROM notifications WHERE entity_type IN ('order','invoice') OR type='new_order'");
    }
    if (isset($existing['email_queue'])) {
        $pdo->exec("DELETE FROM email_queue WHERE template_key IN ('new_order_admin','order_confirmation_customer','order_status','invoice_customer')");
    }
    if (isset($existing['seo_audit_results'])) {
        $pdo->exec("DELETE FROM seo_audit_results WHERE entity_type IN ('product','category','collection')");
    }
    if (isset($existing['sitemap_events'])) {
        $pdo->exec("DELETE FROM sitemap_events WHERE entity_type IN ('product','category','collection')");
    }
    if (isset($existing['invoice_series'])) {
        $pdo->exec('UPDATE invoice_series SET next_number=1, updated_at=NOW()');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    throw $exception;
}

echo "CLEANUP=OK\n";
foreach (['orders', 'invoices', 'products', 'categories', 'collections'] as $table) {
    if (isset($existing[$table])) {
        echo $table . '=' . $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() . PHP_EOL;
    }
}
if (isset($existing['invoice_series'])) {
    echo 'invoice_series_next=' . implode(',', $pdo->query('SELECT next_number FROM invoice_series ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)) . PHP_EOL;
}
