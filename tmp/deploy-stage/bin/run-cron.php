<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\AwbQueueService;
use MaisonBebe\Services\EmailQueueService;

$pdo = Database::connection();
if ((int) $pdo->query("SELECT GET_LOCK('maison_bebe_cron',0)")->fetchColumn() !== 1) {
    echo "Un alt worker rulează deja.\n";
    exit;
}
$pdo->prepare("INSERT INTO cron_runs (job_name,status,started_at) VALUES ('main','running',NOW())")->execute();
$runId = (int) $pdo->lastInsertId();
try {
    $scheduled = $pdo->query("SELECT id,slug FROM blog_posts WHERE status='scheduled' AND scheduled_at<=NOW() FOR UPDATE")->fetchAll();
    $publish = $pdo->prepare("UPDATE blog_posts SET status='published',published_at=COALESCE(published_at,NOW()) WHERE id=? AND status='scheduled'");
    $event = $pdo->prepare("INSERT INTO sitemap_events (entity_type,entity_id,event_type,payload_json,status,available_at) VALUES ('blog_post',?,'publish',?,'pending',NOW())");
    foreach ($scheduled as $post) {
        $publish->execute([$post['id']]);
        $event->execute([$post['id'], json_encode(['slug' => $post['slug']])]);
    }
    $pdo->exec("UPDATE stock_reservations SET status='expired' WHERE status='active' AND expires_at<=NOW()");
    $emails = (new EmailQueueService())->process(30);
    $awbs = (new AwbQueueService())->process(10);
    $pdo->exec("UPDATE sitemap_events SET status='processed',processed_at=NOW(),attempts=attempts+1 WHERE status='pending' AND available_at<=NOW()");
    $metrics = ['published' => count($scheduled), 'emails' => $emails, 'awbs' => $awbs];
    $pdo->prepare("UPDATE cron_runs SET status='success',metrics_json=?,finished_at=NOW() WHERE id=?")->execute([json_encode($metrics), $runId]);
    echo json_encode($metrics, JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $exception) {
    $pdo->prepare("UPDATE cron_runs SET status='failed',error_message=?,finished_at=NOW() WHERE id=?")->execute([mb_substr($exception->getMessage(), 0, 1000), $runId]);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('maison_bebe_cron')");
}
