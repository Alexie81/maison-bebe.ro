<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class GoogleMerchantService
{
    public function __construct(private readonly GoogleMerchantClient $client = new GoogleMerchantClient()) {}

    public function isEnabled(): bool
    {
        return $this->client->isEnabled();
    }

    public function queueProduct(PDO $pdo, int $productId): void
    {
        if ($productId < 1) return;
        $pdo->prepare(
            "INSERT INTO google_merchant_sync_jobs (product_id,status,attempts,available_at,last_error) VALUES (?,'pending',0,NOW(),NULL)
             ON DUPLICATE KEY UPDATE status='pending',attempts=0,available_at=NOW(),last_error=NULL,updated_at=NOW()"
        )->execute([$productId]);
    }

    public function queueProductsForVariants(PDO $pdo, array $variantIds): array
    {
        $variantIds = array_values(array_unique(array_filter(array_map('intval', $variantIds))));
        if (!$variantIds) return [];
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $statement = $pdo->prepare(
            "SELECT DISTINCT product_id FROM product_variants WHERE id IN ({$placeholders})
             UNION SELECT DISTINCT set_product_id FROM product_set_components WHERE component_variant_id IN ({$placeholders})"
        );
        $statement->execute([...$variantIds, ...$variantIds]);
        $productIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        foreach ($productIds as $productId) $this->queueProduct($pdo, $productId);
        return $productIds;
    }

    public function queueAll(?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();
        $ids = $pdo->query("SELECT id FROM products WHERE status='active' AND deleted_at IS NULL AND is_gift_box=0 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) $this->queueProduct($pdo, (int) $id);
        return count($ids);
    }

    public function syncNow(int $productId): array
    {
        if (!$this->isEnabled()) return ['disabled' => true];
        try {
            $result = $this->syncProduct($productId);
            Database::connection()->prepare(
                "INSERT INTO google_merchant_sync_jobs (product_id,status,attempts,available_at,last_error,last_synced_at)
                 VALUES (?,'synced',0,NOW(),NULL,NOW()) ON DUPLICATE KEY UPDATE status='synced',attempts=0,
                 last_error=NULL,last_synced_at=NOW(),updated_at=NOW()"
            )->execute([$productId]);
            return $result;
        } catch (Throwable $exception) {
            Database::connection()->prepare(
                "INSERT INTO google_merchant_sync_jobs (product_id,status,attempts,available_at,last_error)
                 VALUES (?,'retry',1,DATE_ADD(NOW(),INTERVAL 60 SECOND),?) ON DUPLICATE KEY UPDATE status='retry',
                 attempts=GREATEST(attempts,1),available_at=DATE_ADD(NOW(),INTERVAL 60 SECOND),last_error=VALUES(last_error),updated_at=NOW()"
            )->execute([$productId, mb_substr($exception->getMessage(), 0, 1000)]);
            throw $exception;
        }
    }

    public function process(int $limit = 20): array
    {
        if (!$this->isEnabled()) return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'disabled' => true];
        $processed = $synced = $failed = 0;
        while ($processed < max(1, $limit)) {
            $job = $this->claimNextJob();
            if ($job === null) break;
            $processed++;
            try {
                $result = $this->syncProduct((int) $job['product_id']);
                Database::connection()->prepare("UPDATE google_merchant_sync_jobs SET status='synced',last_error=NULL,last_synced_at=NOW(),updated_at=NOW() WHERE product_id=?")
                    ->execute([(int) $job['product_id']]);
                $synced++;
            } catch (Throwable $exception) {
                $attempts = (int) $job['attempts'] + 1;
                $attention = $attempts >= 6;
                $delay = min(21600, 60 * (2 ** max(0, $attempts - 1)));
                Database::connection()->prepare(
                    "UPDATE google_merchant_sync_jobs SET status=?,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND),last_error=?,updated_at=NOW() WHERE product_id=?"
                )->execute([$attention ? 'requires_attention' : 'retry', $delay, mb_substr($exception->getMessage(), 0, 1000), (int) $job['product_id']]);
                $failed++;
                error_log('Google Merchant sync product ' . (int) $job['product_id'] . ': ' . $exception->getMessage());
            }
        }
        return compact('processed', 'synced', 'failed');
    }

    public function syncProduct(int $productId): array
    {
        if (!$this->isEnabled()) throw new RuntimeException('Google Merchant nu este activat.');
        $pdo = Database::connection();
        $productStatement = $pdo->prepare(
            "SELECT p.*,c.name category_name,COALESCE(m.path,'') primary_image
             FROM products p LEFT JOIN categories c ON c.id=p.primary_category_id
             LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
             LEFT JOIN media_assets m ON m.id=pi.media_id WHERE p.id=? LIMIT 1"
        );
        $productStatement->execute([$productId]);
        $product = $productStatement->fetch();
        if (!$product) throw new RuntimeException('Produsul local nu mai există.');

        $tracking = $pdo->prepare('SELECT * FROM google_merchant_product_sync WHERE product_id=? ORDER BY product_variant_id');
        $tracking->execute([$productId]);
        $trackedByVariant = [];
        foreach ($tracking->fetchAll() as $row) $trackedByVariant[(int) $row['product_variant_id']] = $row;

        $activeProduct = $product['status'] === 'active' && $product['deleted_at'] === null && (int) $product['is_gift_box'] === 0;
        $variantsStatement = $pdo->prepare(
            "SELECT v.*,COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'') option_label
             FROM product_variants v
             LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
             LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
             LEFT JOIN product_options po ON po.id=ov.option_id
             WHERE v.product_id=? GROUP BY v.id ORDER BY v.id"
        );
        $variantsStatement->execute([$productId]);
        $variants = $variantsStatement->fetchAll();
        $activeVariants = array_values(array_filter($variants, static fn(array $variant): bool => $activeProduct && (int) $variant['is_active'] === 1 && (int) $variant['price_minor'] > 0));
        $activeIds = array_map(static fn(array $variant): int => (int) $variant['id'], $activeVariants);
        $deleted = 0;
        foreach ($trackedByVariant as $variantId => $tracked) {
            if (in_array($variantId, $activeIds, true)) continue;
            $this->client->deleteProduct((string) $tracked['offer_id']);
            $pdo->prepare("UPDATE google_merchant_product_sync SET status='deleted',last_error=NULL,synced_at=NOW(),updated_at=NOW() WHERE product_variant_id=?")
                ->execute([$variantId]);
            $deleted++;
        }
        if (!$activeProduct) return ['inserted' => 0, 'unchanged' => 0, 'deleted' => $deleted];
        if (!$activeVariants) throw new RuntimeException('Produsul activ nu are nicio variantă activă cu preț valid.');
        if (trim((string) $product['primary_image']) === '') throw new RuntimeException('Produsul nu are fotografie principală pentru Google Merchant.');

        $additionalImages = $this->additionalImages($pdo, $productId);
        $setDefinition = (new ProductSetService())->definitionForProduct($productId, $pdo);
        $setAvailable = $setDefinition ? (new ProductSetService())->availableQuantity($setDefinition, $pdo) : null;
        $inserted = $unchanged = 0;
        foreach ($activeVariants as $variant) {
            $variantId = (int) $variant['id'];
            $payload = $this->productInput($pdo, $product, $variant, count($activeVariants), $additionalImages, $setAvailable);
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $tracked = $trackedByVariant[$variantId] ?? null;
            if ($tracked && $tracked['status'] === 'synced' && hash_equals((string) $tracked['payload_hash'], $hash)) {
                $unchanged++;
                continue;
            }
            try {
                $response = $this->client->insertProduct($payload);
                $pdo->prepare(
                    "INSERT INTO google_merchant_product_sync (product_variant_id,product_id,offer_id,product_input_name,payload_hash,status,last_error,synced_at)
                     VALUES (?,?,?,?,?,'synced',NULL,NOW()) ON DUPLICATE KEY UPDATE product_id=VALUES(product_id),offer_id=VALUES(offer_id),
                     product_input_name=VALUES(product_input_name),payload_hash=VALUES(payload_hash),status='synced',last_error=NULL,synced_at=NOW(),updated_at=NOW()"
                )->execute([$variantId, $productId, $payload['offerId'], $response['name'] ?? null, $hash]);
                $inserted++;
            } catch (Throwable $exception) {
                $pdo->prepare(
                    "INSERT INTO google_merchant_product_sync (product_variant_id,product_id,offer_id,payload_hash,status,last_error)
                     VALUES (?,?,?,?,'failed',?) ON DUPLICATE KEY UPDATE product_id=VALUES(product_id),offer_id=VALUES(offer_id),
                     payload_hash=VALUES(payload_hash),status='failed',last_error=VALUES(last_error),updated_at=NOW()"
                )->execute([$variantId, $productId, $payload['offerId'], $hash, mb_substr($exception->getMessage(), 0, 1000)]);
                throw $exception;
            }
        }
        return compact('inserted', 'unchanged', 'deleted');
    }

    public function previewProduct(int $productId): array
    {
        $pdo = Database::connection();
        $productStatement = $pdo->prepare(
            "SELECT p.*,c.name category_name,COALESCE(m.path,'') primary_image FROM products p
             LEFT JOIN categories c ON c.id=p.primary_category_id LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
             LEFT JOIN media_assets m ON m.id=pi.media_id WHERE p.id=? LIMIT 1"
        );
        $productStatement->execute([$productId]);
        $product = $productStatement->fetch();
        if (!$product) throw new RuntimeException('Produsul nu există.');
        $variants = $pdo->prepare(
            "SELECT v.*,COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'') option_label
             FROM product_variants v LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
             LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id LEFT JOIN product_options po ON po.id=ov.option_id
             WHERE v.product_id=? AND v.is_active=1 GROUP BY v.id ORDER BY v.id"
        );
        $variants->execute([$productId]);
        $rows = $variants->fetchAll();
        $setDefinition = (new ProductSetService())->definitionForProduct($productId, $pdo);
        $setAvailable = $setDefinition ? (new ProductSetService())->availableQuantity($setDefinition, $pdo) : null;
        return array_map(fn(array $variant): array => $this->productInput($pdo, $product, $variant, count($rows), $this->additionalImages($pdo, $productId), $setAvailable), $rows);
    }

    private function claimNextJob(): ?array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $job = $pdo->query("SELECT * FROM google_merchant_sync_jobs WHERE status IN ('pending','retry') AND available_at<=NOW() ORDER BY available_at,product_id LIMIT 1 FOR UPDATE")->fetch();
            if (!$job) {
                $pdo->commit();
                return null;
            }
            $pdo->prepare("UPDATE google_merchant_sync_jobs SET status='processing',attempts=attempts+1,updated_at=NOW() WHERE product_id=?")
                ->execute([(int) $job['product_id']]);
            $pdo->commit();
            return $job;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    private function productInput(PDO $pdo, array $product, array $variant, int $variantCount, array $additionalImages, ?int $setAvailable): array
    {
        $optionRows = $pdo->prepare(
            'SELECT po.name,ov.value FROM variant_option_values vov JOIN product_option_values ov ON ov.id=vov.option_value_id '
            . 'JOIN product_options po ON po.id=ov.option_id WHERE vov.variant_id=? ORDER BY po.sort_order,ov.sort_order'
        );
        $optionRows->execute([(int) $variant['id']]);
        $options = $optionRows->fetchAll();
        $optionLabel = trim((string) ($variant['option_label'] ?? ''));
        $title = trim((string) $product['name']) . ($variantCount > 1 && $optionLabel !== '' ? ' – ' . $optionLabel : '');
        $description = $this->plainText((string) ($product['short_description'] ?: $product['description_html'] ?: $product['name']));
        $availability = $setAvailable !== null
            ? ($setAvailable > 0 ? 'IN_STOCK' : 'OUT_OF_STOCK')
            : (((int) $variant['track_inventory'] === 0 || (int) $variant['stock_qty'] > 0) ? 'IN_STOCK' : 'OUT_OF_STOCK');
        $attributes = [
            'title' => mb_substr($title, 0, 150),
            'description' => mb_substr($description, 0, 5000),
            'link' => public_url('/produs/' . $product['slug']) . '?variant=' . rawurlencode((string) $variant['sku']),
            'imageLink' => public_url((string) $product['primary_image']),
            'availability' => $availability,
            'price' => [
                'amountMicros' => (string) ((int) $variant['price_minor'] * 10000),
                'currencyCode' => 'RON',
            ],
            'condition' => 'NEW',
        ];
        if ($additionalImages) $attributes['additionalImageLinks'] = array_slice($additionalImages, 0, 10);
        if ($setAvailable !== null) $attributes['isBundle'] = true;
        $brand = trim((string) ($product['brand'] ?? ''));
        if ($brand !== '') $attributes['brand'] = mb_substr($brand, 0, 70);
        $gtin = $this->validGtin((string) ($variant['ean'] ?? ''));
        if ($gtin !== null) $attributes['gtins'] = [$gtin]; else $attributes['identifierExists'] = false;
        if ($variantCount > 1) $attributes['itemGroupId'] = mb_substr((string) $product['sku'], 0, 50);
        if (trim((string) ($product['category_name'] ?? '')) !== '') $attributes['productTypes'] = [mb_substr((string) $product['category_name'], 0, 750)];
        if (!empty($variant['weight_grams'])) {
            $attributes['shippingWeight'] = ['value' => (float) $variant['weight_grams'], 'unit' => 'g'];
        }
        foreach ($options as $option) {
            $name = $this->normalize((string) $option['name']);
            $value = trim((string) $option['value']);
            if ($value === '') continue;
            if (in_array($name, ['culoare', 'color'], true)) $attributes['color'] = mb_substr($value, 0, 100);
            if (in_array($name, ['marime', 'size', 'dimensiune'], true)) $attributes['size'] = mb_substr($value, 0, 100);
            if (in_array($name, ['material'], true) && empty($attributes['material'])) $attributes['material'] = mb_substr($value, 0, 200);
        }
        if (empty($attributes['material']) && trim((string) ($product['material'] ?? '')) !== '') $attributes['material'] = mb_substr(trim((string) $product['material']), 0, 200);
        $compareAt = (int) ($variant['compare_at_price_minor'] ?? 0);
        if ($compareAt > (int) $variant['price_minor']) {
            $attributes['price']['amountMicros'] = (string) ($compareAt * 10000);
            $attributes['salePrice'] = ['amountMicros' => (string) ((int) $variant['price_minor'] * 10000), 'currencyCode' => 'RON'];
        }
        return [
            'offerId' => mb_substr((string) $variant['sku'], 0, 100),
            'contentLanguage' => $this->client->contentLanguage(),
            'feedLabel' => $this->client->feedLabel(),
            'productAttributes' => $attributes,
        ];
    }

    private function additionalImages(PDO $pdo, int $productId): array
    {
        $statement = $pdo->prepare(
            'SELECT m.path FROM product_images pi JOIN media_assets m ON m.id=pi.media_id '
            . 'WHERE pi.product_id=? AND pi.is_primary=0 ORDER BY pi.sort_order,pi.id LIMIT 10'
        );
        $statement->execute([$productId]);
        return array_map(static fn(string $path): string => public_url($path), $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function validGtin(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (!in_array(strlen($digits), [8, 12, 13, 14], true)) return null;
        $sum = 0;
        $length = strlen($digits);
        for ($index = $length - 2, $position = 1; $index >= 0; $index--, $position++) {
            $sum += (int) $digits[$index] * ($position % 2 === 1 ? 3 : 1);
        }
        $check = (10 - ($sum % 10)) % 10;
        return $check === (int) $digits[$length - 1] ? $digits : null;
    }

    private function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        return strtr($value, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
    }
}
