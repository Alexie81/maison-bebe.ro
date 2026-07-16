<?php

declare(strict_types=1);

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Env;
use MaisonBebe\Services\StripeService;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require dirname(__DIR__) . '/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
$provided = (string) ($_SERVER['HTTP_X_MAISON_SIGNATURE'] ?? '');
if ($provided === '' && str_starts_with($authorization, 'Bearer ')) {
    $provided = substr($authorization, 7);
}
$expected = hash_hmac('sha256', 'catalog-maintenance-20260716', (string) Env::get('APP_KEY', ''));
if ($provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = Database::connection();
$action = (string) ($_POST['action'] ?? 'inspect');
$existing = array_flip($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
$count = static fn(string $table): int => isset($existing[$table]) ? (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn() : 0;
$productRows = [];
$archived = [];
$cleanupCommitted = false;

try {
    $stripe = (new StripeService())->diagnostics();
    $safeStripe = [
        'enabled' => (bool) ($stripe['enabled'] ?? false),
        'environment' => (string) ($stripe['environment'] ?? ''),
        'key_mode' => (string) ($stripe['key_mode'] ?? ''),
        'api_livemode' => (bool) ($stripe['api_livemode'] ?? false),
        'charges_enabled' => (bool) ($stripe['charges_enabled'] ?? false),
        'payouts_enabled' => (bool) ($stripe['payouts_enabled'] ?? false),
        'webhook_configured' => (bool) ($stripe['webhook_configured'] ?? false),
        'account_connected' => (string) ($stripe['account_id'] ?? '') !== '',
    ];
    $counts = [
        'products' => $count('products'),
        'categories' => $count('categories'),
        'collections' => $count('collections'),
        'articles' => $count('blog_posts'),
        'article_categories' => $count('blog_categories'),
    ];

    if ($action === 'inspect') {
        echo json_encode(['ok' => true, 'stripe' => $safeStripe, 'counts' => $counts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($action !== 'cleanup') {
        throw new RuntimeException('Acțiune invalidă.');
    }
    if (!$safeStripe['enabled'] || $safeStripe['environment'] !== 'live' || $safeStripe['key_mode'] !== 'live' || !$safeStripe['api_livemode'] || !$safeStripe['charges_enabled'] || !$safeStripe['webhook_configured'] || !$safeStripe['account_connected']) {
        throw new RuntimeException('Stripe live nu a trecut toate verificările; curățarea a fost oprită.');
    }

    $backupTables = [
        'products', 'product_variants', 'product_options', 'product_option_values', 'variant_option_values',
        'product_images', 'product_categories', 'categories', 'collections', 'collection_products',
        'blog_posts', 'blog_categories', 'blog_tags', 'blog_post_categories', 'blog_post_products',
        'blog_post_revisions', 'blog_post_tags', 'cart_items', 'inventory_movements', 'stock_reservations',
        'reviews', 'wishlist_items', 'coupon_products', 'coupon_categories', 'coupon_collections',
    ];
    $snapshot = ['created_at' => date(DATE_ATOM), 'counts_before' => $counts, 'tables' => []];
    foreach ($backupTables as $table) {
        if (isset($existing[$table])) {
            $snapshot['tables'][$table] = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    $backupDirectory = dirname(__DIR__) . '/storage/backups';
    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0750, true) && !is_dir($backupDirectory)) {
        throw new RuntimeException('Folderul de backup nu a putut fi creat.');
    }
    $backupPath = $backupDirectory . '/catalog-cleanup-' . date('Ymd-His') . '.json';
    $encoded = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (file_put_contents($backupPath, $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Backupul nu a putut fi scris.');
    }
    chmod($backupPath, 0640);

    $productRows = isset($existing['products']) ? $pdo->query('SELECT id,status,deleted_at FROM products ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) : [];
    $stripeService = new StripeService();
    foreach ($productRows as $product) {
        $pdo->prepare("UPDATE products SET status='archived',deleted_at=COALESCE(deleted_at,NOW()) WHERE id=?")->execute([(int) $product['id']]);
        $stripeService->archiveProduct((int) $product['id']);
        $archived[] = (int) $product['id'];
    }

    $pdo->beginTransaction();
    try {
        $delete = static function (PDO $pdo, array $existing, string $sql, string $table): void {
            if (isset($existing[$table])) {
                $pdo->exec($sql);
            }
        };
        $delete($pdo, $existing, 'DELETE ci FROM cart_items ci JOIN product_variants v ON v.id=ci.variant_id', 'cart_items');
        $delete($pdo, $existing, 'DELETE im FROM inventory_movements im JOIN product_variants v ON v.id=im.variant_id', 'inventory_movements');
        $delete($pdo, $existing, 'DELETE sr FROM stock_reservations sr JOIN product_variants v ON v.id=sr.variant_id', 'stock_reservations');
        $delete($pdo, $existing, 'DELETE FROM product_categories', 'product_categories');
        $delete($pdo, $existing, 'DELETE FROM blog_post_categories', 'blog_post_categories');
        $delete($pdo, $existing, 'DELETE FROM blog_post_tags', 'blog_post_tags');
        $delete($pdo, $existing, 'DELETE FROM blog_post_products', 'blog_post_products');
        $delete($pdo, $existing, 'DELETE FROM products', 'products');
        $delete($pdo, $existing, 'DELETE FROM collections', 'collections');
        $delete($pdo, $existing, 'DELETE FROM categories', 'categories');
        $delete($pdo, $existing, 'DELETE FROM blog_posts', 'blog_posts');
        $delete($pdo, $existing, 'DELETE FROM blog_categories', 'blog_categories');
        $delete($pdo, $existing, 'DELETE FROM blog_tags', 'blog_tags');
        if (isset($existing['seo_audit_results'])) {
            $pdo->exec("DELETE FROM seo_audit_results WHERE entity_type IN ('product','category','collection','article','post')");
        }
        if (isset($existing['sitemap_events'])) {
            $pdo->exec("DELETE FROM sitemap_events WHERE entity_type IN ('product','category','collection','article','post')");
        }
        $pdo->commit();
        $cleanupCommitted = true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $after = [
        'products' => $count('products'),
        'categories' => $count('categories'),
        'collections' => $count('collections'),
        'articles' => $count('blog_posts'),
        'article_categories' => $count('blog_categories'),
    ];
    @unlink(__FILE__);
    echo json_encode([
        'ok' => true,
        'stripe' => $safeStripe,
        'archived_stripe_products' => count($archived),
        'backup' => basename($backupPath),
        'counts_before' => $counts,
        'counts_after' => $after,
        'self_deleted' => !is_file(__FILE__),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (!$cleanupCommitted && $productRows && isset($existing['products'])) {
        foreach ($productRows as $product) {
            try {
                $restore = $pdo->prepare('UPDATE products SET status=?,deleted_at=? WHERE id=?');
                $restore->execute([(string) $product['status'], $product['deleted_at'], (int) $product['id']]);
                if (in_array((int) $product['id'], $archived, true)) {
                    (new StripeService())->syncProduct((int) $product['id']);
                }
            } catch (Throwable $ignored) {
            }
        }
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
