<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use PDO;

final class ProductGiftBoxOptionService
{
    private static bool $schemaReady = false;

    public function ensureSchema(?PDO $pdo = null): void
    {
        if (self::$schemaReady) return;
        $pdo ??= Database::connection();
        $exists = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='product_gift_box_options'"
        )->fetchColumn();
        if ($exists) {
            self::$schemaReady = true;
            return;
        }
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS product_gift_box_options ('
            . 'product_id BIGINT UNSIGNED PRIMARY KEY,'
            . 'gift_box_template_id BIGINT UNSIGNED NOT NULL,'
            . 'created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,'
            . 'KEY idx_product_gift_box_template (gift_box_template_id),'
            . 'CONSTRAINT fk_product_gift_box_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,'
            . 'CONSTRAINT fk_product_gift_box_template FOREIGN KEY (gift_box_template_id) REFERENCES gift_box_templates(id) ON DELETE CASCADE'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        self::$schemaReady = true;
    }

    public function definitionForProduct(int $productId, ?PDO $pdo = null): ?array
    {
        $pdo ??= Database::connection();
        $this->ensureSchema($pdo);
        $statement = $pdo->prepare(
            "SELECT o.product_id,o.gift_box_template_id,t.name,t.base_price_minor price_minor,t.stock_qty,t.is_active,
                    COALESCE(m.path,'/assets/images/giftbox-clean-v4.png') image_path
             FROM product_gift_box_options o
             JOIN gift_box_templates t ON t.id=o.gift_box_template_id AND t.deleted_at IS NULL
             LEFT JOIN media_assets m ON m.id=t.image_id
             WHERE o.product_id=? LIMIT 1"
        );
        $statement->execute([$productId]);
        $offer = $statement->fetch();
        if (!$offer) return null;
        $offer['allow_gift_box'] = true;
        return $offer;
    }

    public function offerForVariant(int $variantId, ?PDO $pdo = null): ?array
    {
        $pdo ??= Database::connection();
        $this->ensureSchema($pdo);
        $statement = $pdo->prepare(
            "SELECT o.gift_box_template_id,p.id product_id,p.name,v.id variant_id,v.sku,v.price_minor
             FROM product_gift_box_options o
             JOIN products p ON p.id=o.product_id AND p.status='active' AND p.deleted_at IS NULL AND p.is_gift_box=0
             JOIN product_variants v ON v.product_id=p.id AND v.is_active=1
             JOIN gift_box_templates t ON t.id=o.gift_box_template_id AND t.is_active=1 AND t.deleted_at IS NULL
             WHERE v.id=? LIMIT 1"
        );
        $statement->execute([$variantId]);
        $offer = $statement->fetch();
        if (!$offer) return null;
        return [
            'product_id' => (int) $offer['product_id'],
            'variant_id' => (int) $offer['variant_id'],
            'name' => (string) $offer['name'],
            'sku' => (string) $offer['sku'],
            'price_minor' => (int) $offer['price_minor'],
            'allow_gift_box' => true,
            'gift_box_template_id' => (int) $offer['gift_box_template_id'],
        ];
    }

    public function save(int $productId, ?int $templateId, ?PDO $pdo = null): void
    {
        $pdo ??= Database::connection();
        $this->ensureSchema($pdo);
        if (!$templateId) {
            $pdo->prepare('DELETE FROM product_gift_box_options WHERE product_id=?')->execute([$productId]);
            return;
        }
        $pdo->prepare(
            'INSERT INTO product_gift_box_options (product_id,gift_box_template_id) VALUES (?,?) '
            . 'ON DUPLICATE KEY UPDATE gift_box_template_id=VALUES(gift_box_template_id),updated_at=NOW()'
        )->execute([$productId, $templateId]);
    }
}
