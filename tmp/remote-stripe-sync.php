<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$tokenFile = dirname(__DIR__) . '/.maison-stripe-token';
$provided = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
$expected = is_file($tokenFile) ? trim((string) file_get_contents($tokenFile)) : '';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(404);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    require __DIR__ . '/bootstrap.php';
    $pdo = MaisonBebe\Core\Database::connection();
    $ids = $pdo->query("SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $service = new MaisonBebe\Services\StripeService();
    $result = ['ok' => true, 'synced' => 0, 'errors' => []];
    foreach ($ids as $id) {
        try {
            $service->syncProduct((int) $id);
            $result['synced']++;
        } catch (Throwable $exception) {
            $result['errors'][(string) $id] = mb_substr($exception->getMessage(), 0, 180);
        }
    }
    $result['products'] = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
    $result['test_products'] = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND stripe_test_product_id IS NOT NULL")->fetchColumn();
    $result['test_prices'] = (int) $pdo->query("SELECT COUNT(*) FROM product_variants WHERE stripe_test_price_id IS NOT NULL")->fetchColumn();
    $result['product_sync_errors'] = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND stripe_test_sync_error IS NOT NULL")->fetchColumn();
    $result['price_sync_errors'] = (int) $pdo->query("SELECT COUNT(*) FROM product_variants WHERE stripe_test_sync_error IS NOT NULL")->fetchColumn();
    @unlink($tokenFile);
    @unlink(__FILE__);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
