<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\EmailQueueService;

$pdo = Database::connection();
$started = date('Y-m-d H:i:s');
$pdo->prepare("INSERT INTO cron_runs (job_name,status,started_at) VALUES ('email_queue','running',?)")->execute([$started]);
$id = (int) $pdo->lastInsertId();
try {
    $metrics = (new EmailQueueService())->process((int) ($argv[1] ?? 20));
    $pdo->prepare("UPDATE cron_runs SET status='success',metrics_json=?,finished_at=NOW() WHERE id=?")->execute([json_encode($metrics), $id]);
    echo json_encode($metrics, JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    $pdo->prepare("UPDATE cron_runs SET status='failed',error_message=?,finished_at=NOW() WHERE id=?")->execute([mb_substr($exception->getMessage(), 0, 1000), $id]);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
