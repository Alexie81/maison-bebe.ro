<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\GoogleMerchantClient;
use MaisonBebe\Services\GoogleMerchantService;

$service = new GoogleMerchantService();
$client = new GoogleMerchantClient();
if (!$service->isEnabled()) {
    fwrite(STDERR, "Google Merchant nu este configurat sau activat.\n");
    exit(1);
}

if (in_array('--check', $argv, true)) {
    $source = $client->dataSource();
    $primary = is_array($source['primaryProductDataSource'] ?? null) ? $source['primaryProductDataSource'] : [];
    echo json_encode([
        'ok' => true,
        'account' => $client->accountId(),
        'data_source' => $source['dataSourceId'] ?? null,
        'display_name' => $source['displayName'] ?? null,
        'input' => $source['input'] ?? null,
        'primary' => isset($source['primaryProductDataSource']),
        'countries' => $primary['countries'] ?? [],
        'content_language' => $primary['contentLanguage'] ?? null,
        'feed_label' => $primary['feedLabel'] ?? null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit;
}

$productId = 0;
foreach ($argv as $argument) {
    if (preg_match('/^--product=(\d+)$/', $argument, $match)) $productId = (int) $match[1];
}
$force = in_array('--force', $argv, true);
if ($productId > 0) {
    $service->queueProduct(Database::connection(), $productId);
    $result = $service->syncNow($productId, $force);
    echo json_encode(['product_id' => $productId, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit;
}

if ($force) {
    $ids = Database::connection()->query("SELECT id FROM products WHERE status='active' AND deleted_at IS NULL AND is_gift_box=0 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $totals = ['products'=>count($ids),'inserted'=>0,'unchanged'=>0,'deleted'=>0,'failed'=>0,'errors'=>[]];
    foreach ($ids as $id) {
        try {
            $result = $service->syncNow((int) $id, true);
            foreach (['inserted','unchanged','deleted'] as $key) $totals[$key] += (int) ($result[$key] ?? 0);
        } catch (Throwable $exception) {
            $totals['failed']++;
            $totals['errors'][] = ['product_id'=>(int)$id,'message'=>mb_substr($exception->getMessage(),0,500)];
        }
    }
    echo json_encode(['forced'=>true,'result'=>$totals], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit($totals['failed'] > 0 ? 2 : 0);
}

$queued = $service->queueAll();
$totals = ['processed' => 0, 'synced' => 0, 'failed' => 0];
do {
    $batch = $service->process(50);
    foreach ($totals as $key => $_) $totals[$key] += (int) ($batch[$key] ?? 0);
} while ((int) ($batch['processed'] ?? 0) === 50);
echo json_encode(['queued' => $queued, 'result' => $totals], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
