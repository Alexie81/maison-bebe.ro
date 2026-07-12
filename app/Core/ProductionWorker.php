<?php

declare(strict_types=1);

namespace MaisonBebe\Core;

use MaisonBebe\Services\AwbQueueService;
use MaisonBebe\Services\EmailQueueService;
use MaisonBebe\Services\NewsletterService;
use Throwable;

final class ProductionWorker
{
    public static function register(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        register_shutdown_function(static function (): void {
            if (http_response_code() >= 500) {
                return;
            }
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            try {
                $pdo = Database::connection();
                if ((int) $pdo->query("SELECT GET_LOCK('maison_bebe_web_worker',0)")->fetchColumn() !== 1) {
                    return;
                }
                try {
                    $scheduled = $pdo->query("SELECT id,slug FROM blog_posts WHERE status='scheduled' AND scheduled_at<=NOW() LIMIT 10")->fetchAll();
                    $publish = $pdo->prepare("UPDATE blog_posts SET status='published',published_at=COALESCE(published_at,NOW()) WHERE id=? AND status='scheduled'");
                    $event = $pdo->prepare("INSERT INTO sitemap_events (entity_type,entity_id,event_type,payload_json,status,available_at) VALUES ('blog_post',?,'publish',?,'pending',NOW())");
                    foreach ($scheduled as $post) {
                        $publish->execute([$post['id']]);
                        if ($publish->rowCount()) {
                            $event->execute([$post['id'], json_encode(['slug' => $post['slug']])]);
                            (new NewsletterService())->queueArticle($pdo, (int) $post['id']);
                        }
                    }
                    (new EmailQueueService())->process(5);
                    (new AwbQueueService())->process(2);
                    $pdo->exec("UPDATE sitemap_events SET status='processed',processed_at=NOW(),attempts=attempts+1 WHERE status='pending' AND available_at<=NOW()");
                    $pdo->exec("UPDATE stock_reservations SET status='expired' WHERE status='active' AND expires_at<=NOW()");
                } finally {
                    $pdo->query("SELECT RELEASE_LOCK('maison_bebe_web_worker')");
                }
            } catch (Throwable $exception) {
                error_log('Maison worker: ' . $exception->getMessage());
            }
        });
    }
}
