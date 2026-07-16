<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use PDO;

final class NewsletterService
{
    private static bool $schemaReady = false;

    public function ensureSchema(?PDO $pdo = null): void
    {
        if (self::$schemaReady) return;
        $pdo ??= Database::connection();
        $purposeColumn = (string) ($pdo->query("SHOW COLUMNS FROM email_senders LIKE 'purpose'")->fetch()['Type'] ?? '');
        if (!str_contains($purposeColumn, "'marketing'")) $pdo->exec("ALTER TABLE email_senders MODIFY purpose ENUM('general','orders','invoices','account','recovery','marketing') NOT NULL");
        $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NULL,email VARCHAR(190) NOT NULL,product_updates TINYINT(1) NOT NULL DEFAULT 1,article_updates TINYINT(1) NOT NULL DEFAULT 1,status ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',unsubscribe_token CHAR(64) NOT NULL,subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,unsubscribed_at DATETIME NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_newsletter_email (email),UNIQUE KEY uq_newsletter_token (unsubscribe_token),KEY idx_newsletter_active (status,product_updates,article_updates),KEY idx_newsletter_user (user_id),CONSTRAINT fk_newsletter_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT INTO email_senders (purpose,from_email,from_name,reply_to_email,smtp_host,smtp_port,smtp_encryption,smtp_username,encrypted_password,is_active,health_status,last_health_message,last_tested_at) SELECT 'marketing',from_email,'Maison Bébé · Scrisori din Atelier',reply_to_email,smtp_host,smtp_port,smtp_encryption,smtp_username,encrypted_password,0,'not_tested',NULL,NULL FROM email_senders WHERE purpose='general' LIMIT 1 ON DUPLICATE KEY UPDATE purpose=VALUES(purpose)");
        self::$schemaReady = true;
    }

    public function subscribe(string $email, ?int $userId = null): array
    {
        $this->ensureSchema();
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adresa de email nu este validă.');
        }
        $pdo = Database::connection();
        $token = bin2hex(random_bytes(32));
        $statement = $pdo->prepare("INSERT INTO newsletter_subscribers (user_id,email,product_updates,article_updates,status,unsubscribe_token,subscribed_at,unsubscribed_at) VALUES (?,?,1,1,'active',?,NOW(),NULL) ON DUPLICATE KEY UPDATE user_id=COALESCE(VALUES(user_id),user_id),product_updates=1,article_updates=1,status='active',subscribed_at=NOW(),unsubscribed_at=NULL");
        $statement->execute([$userId,$email,$token]);
        $select = $pdo->prepare('SELECT * FROM newsletter_subscribers WHERE email=? LIMIT 1');
        $select->execute([$email]);
        return $select->fetch() ?: [];
    }

    public function unsubscribe(string $token): bool
    {
        $this->ensureSchema();
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }
        $statement = Database::connection()->prepare("UPDATE newsletter_subscribers SET status='unsubscribed',product_updates=0,article_updates=0,unsubscribed_at=NOW() WHERE unsubscribe_token=?");
        $statement->execute([$token]);
        return $statement->rowCount() > 0;
    }

    public function preferencesForUser(int $userId): ?array
    {
        $this->ensureSchema();
        $statement = Database::connection()->prepare('SELECT * FROM newsletter_subscribers WHERE user_id=? ORDER BY id DESC LIMIT 1');
        $statement->execute([$userId]);
        return $statement->fetch() ?: null;
    }

    public function updatePreferences(int $userId, string $email, bool $products, bool $articles): array
    {
        $subscriber = $this->preferencesForUser($userId);
        if (!$subscriber) {
            $subscriber = $this->subscribe($email, $userId);
        }
        $active = $products || $articles;
        $statement = Database::connection()->prepare("UPDATE newsletter_subscribers SET email=?,product_updates=?,article_updates=?,status=?,unsubscribed_at=IF(?='unsubscribed',NOW(),NULL) WHERE id=?");
        $status = $active ? 'active' : 'unsubscribed';
        $statement->execute([mb_strtolower($email),$products?1:0,$articles?1:0,$status,$status,$subscriber['id']]);
        return $this->preferencesForUser($userId) ?: [];
    }

    public function syncUserEmail(int $userId, string $email): void
    {
        $this->ensureSchema();
        Database::connection()->prepare('UPDATE newsletter_subscribers SET email=? WHERE user_id=?')->execute([mb_strtolower($email),$userId]);
    }

    public function queueProduct(PDO $pdo, int $productId): int
    {
        $statement = $pdo->prepare("SELECT p.id,p.name,p.slug,p.short_description,COALESCE(m.path,'/assets/images/packaging-reference.png') image_path FROM products p LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1 LEFT JOIN media_assets m ON m.id=pi.media_id WHERE p.id=? AND p.status='active' AND p.deleted_at IS NULL LIMIT 1");
        $statement->execute([$productId]);
        $product = $statement->fetch();
        return $product ? $this->queue($pdo,'product',$productId,'Produs nou la Maison Bébé: '.$product['name'],['title'=>$product['name'],'excerpt'=>$product['short_description'],'url'=>public_url('/produs/'.$product['slug']),'image_url'=>public_url($product['image_path'])]) : 0;
    }

    public function queueArticle(PDO $pdo, int $postId): int
    {
        $statement = $pdo->prepare("SELECT p.id,p.title,p.slug,p.excerpt,COALESCE(m.path,'/assets/images/packaging-reference.png') image_path FROM blog_posts p LEFT JOIN media_assets m ON m.id=p.featured_image_id WHERE p.id=? AND p.status='published' AND p.deleted_at IS NULL LIMIT 1");
        $statement->execute([$postId]);
        $post = $statement->fetch();
        return $post ? $this->queue($pdo,'article',$postId,'Poveste nouă din Atelier: '.$post['title'],['title'=>$post['title'],'excerpt'=>$post['excerpt'],'url'=>public_url('/atelier/'.$post['slug']),'image_url'=>public_url($post['image_path'])]) : 0;
    }

    private function queue(PDO $pdo, string $type, int $entityId, string $subject, array $payload): int
    {
        $this->ensureSchema($pdo);
        $preference = $type === 'product' ? 'product_updates' : 'article_updates';
        $subscribers = $pdo->query("SELECT id,email,unsubscribe_token FROM newsletter_subscribers WHERE status='active' AND {$preference}=1")->fetchAll();
        $check = $pdo->prepare('SELECT 1 FROM email_queue WHERE correlation_id=? LIMIT 1');
        $insert = $pdo->prepare("INSERT INTO email_queue (template_key,recipient,subject,payload_json,status,next_attempt_at,correlation_id) VALUES (?,?,?,?,'pending',NOW(),?)");
        $queued = 0;
        foreach ($subscribers as $subscriber) {
            $correlation = 'newsletter:'.$type.':'.$entityId.':'.$subscriber['id'];
            $check->execute([$correlation]);
            if ($check->fetchColumn()) {
                continue;
            }
            $data = $payload + ['unsubscribe_url'=>public_url('/newsletter/dezabonare/'.$subscriber['unsubscribe_token'])];
            $insert->execute(['newsletter_'.$type,$subscriber['email'],$subject,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$correlation]);
            $queued++;
        }
        return $queued;
    }
}