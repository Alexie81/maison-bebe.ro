<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use PDO;

final class ProductSetService
{
    public function definitionForProduct(int $productId, ?PDO $pdo = null): ?array
    {
        $pdo ??= Database::connection();
        $statement = $pdo->prepare('SELECT * FROM product_sets WHERE product_id=?');
        $statement->execute([$productId]);
        $definition = $statement->fetch();
        if (!$definition) {
            return null;
        }
        $definition['components'] = $this->components($productId, $pdo);
        return $definition;
    }

    public function snapshotForVariant(int $variantId, ?PDO $pdo = null): ?array
    {
        $pdo ??= Database::connection();
        $statement = $pdo->prepare(
            'SELECT ps.product_id,ps.allow_gift_box,ps.gift_box_template_id,p.name product_name,v.id variant_id,v.sku,v.price_minor '
            . 'FROM product_sets ps JOIN products p ON p.id=ps.product_id '
            . 'JOIN product_variants v ON v.product_id=ps.product_id '
            . 'WHERE v.id=? AND v.is_active=1 AND p.status=\'active\' AND p.deleted_at IS NULL'
        );
        $statement->execute([$variantId]);
        $set = $statement->fetch();
        if (!$set) {
            return null;
        }
        $components = $this->components((int) $set['product_id'], $pdo);
        if (!$components) {
            throw new HttpException(422, 'Setul nu are produse componente configurate.');
        }
        return [
            'product_id' => (int) $set['product_id'],
            'variant_id' => (int) $set['variant_id'],
            'name' => (string) $set['product_name'],
            'sku' => (string) $set['sku'],
            'price_minor' => (int) $set['price_minor'],
            'allow_gift_box' => (bool) $set['allow_gift_box'],
            'gift_box_template_id' => $set['gift_box_template_id'] !== null ? (int) $set['gift_box_template_id'] : null,
            'components' => array_map(static fn(array $component): array => [
                'product_id' => (int) $component['product_id'],
                'variant_id' => (int) $component['variant_id'],
                'name' => (string) $component['product_name'],
                'variant' => (string) ($component['variant_name'] ?: 'Standard'),
                'sku' => (string) $component['sku'],
                'quantity' => (int) $component['quantity'],
                'price_minor' => (int) $component['price_minor'],
                'cost_minor' => $component['cost_minor'] !== null ? (int) $component['cost_minor'] : null,
                'track_inventory' => (bool) $component['track_inventory'],
                'track_accounting_stock' => (bool) $component['track_accounting_stock'],
            ], $components),
        ];
    }

    public function withSnapshot(int $variantId, array $customization, ?PDO $pdo = null): array
    {
        $snapshot = $this->snapshotForVariant($variantId, $pdo);
        if ($snapshot) {
            $customization['product_set'] = $snapshot;
        }
        return $customization;
    }

    public function snapshotFromCustomization(array $customization): ?array
    {
        $snapshot = $customization['product_set'] ?? null;
        return is_array($snapshot) && !empty($snapshot['components']) ? $snapshot : null;
    }

    public function stockTargets(int $parentQuantity, array $customization): array
    {
        $snapshot = $this->snapshotFromCustomization($customization);
        if (!$snapshot) {
            return [];
        }
        $targets = [];
        foreach ((array) $snapshot['components'] as $component) {
            $variantId = (int) ($component['variant_id'] ?? 0);
            $componentQuantity = max(1, (int) ($component['quantity'] ?? 1));
            if ($variantId < 1) {
                throw new HttpException(422, 'Setul conține o componentă fără variantă validă.');
            }
            if (!isset($targets[$variantId])) {
                $targets[$variantId] = $component + ['variant_id' => $variantId, 'quantity_required' => 0];
            }
            $targets[$variantId]['quantity_required'] += $componentQuantity * $parentQuantity;
        }
        return array_values($targets);
    }

    public function assertTargetsAvailable(array $targets, ?PDO $pdo = null): void
    {
        $pdo ??= Database::connection();
        $statement = $pdo->prepare(
            'SELECT v.id,v.stock_qty,v.track_inventory,v.is_active,p.name,p.status,p.deleted_at '
            . 'FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=? FOR UPDATE'
        );
        foreach ($targets as $target) {
            $statement->execute([(int) $target['variant_id']]);
            $variant = $statement->fetch();
            $required = (int) $target['quantity_required'];
            if (!$variant || !(int) $variant['is_active'] || $variant['status'] !== 'active' || $variant['deleted_at'] !== null) {
                throw new HttpException(422, 'Un produs din set nu mai este disponibil.');
            }
            if ((int) $variant['track_inventory'] === 1 && (int) $variant['stock_qty'] < $required) {
                throw new HttpException(422, 'Stoc insuficient pentru „' . $variant['name'] . '” din set.');
            }
        }
    }

    public function availableQuantity(array $snapshot, ?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();
        $available = 100000000;
        $statement = $pdo->prepare('SELECT stock_qty,track_inventory,is_active FROM product_variants WHERE id=?');
        foreach ((array) ($snapshot['components'] ?? []) as $component) {
            $statement->execute([(int) ($component['variant_id'] ?? 0)]);
            $variant = $statement->fetch();
            if (!$variant || !(int) $variant['is_active']) {
                return 0;
            }
            if (!(int) $variant['track_inventory']) {
                continue;
            }
            $quantity = max(1, (int) ($component['quantity'] ?? 1));
            $available = min($available, intdiv(max(0, (int) $variant['stock_qty']), $quantity));
        }
        return $available;
    }

    public function adminCandidates(?int $excludeProductId = null, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $sql = "SELECT v.id variant_id,v.product_id,v.sku,v.stock_qty,v.track_inventory,p.name product_name,
                       COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_name,
                       COALESCE(m.path,'/assets/images/packaging-reference.png') image_path
                FROM product_variants v JOIN products p ON p.id=v.product_id
                LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
                LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
                LEFT JOIN product_options po ON po.id=ov.option_id
                LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
                LEFT JOIN media_assets m ON m.id=pi.media_id
                WHERE p.status='active' AND p.deleted_at IS NULL AND p.is_gift_box=0 AND v.is_active=1
                  AND NOT EXISTS(SELECT 1 FROM product_sets nested WHERE nested.product_id=p.id)";
        $params = [];
        if ($excludeProductId) {
            $sql .= ' AND p.id<>?';
            $params[] = $excludeProductId;
        }
        $sql .= ' GROUP BY v.id ORDER BY p.name,variant_name';
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    private function components(int $productId, PDO $pdo): array
    {
        $statement = $pdo->prepare(
            "SELECT psc.id,psc.quantity,psc.sort_order,v.id variant_id,v.product_id,v.sku,v.price_minor,v.cost_minor,
                    v.stock_qty,v.track_inventory,v.track_accounting_stock,v.is_active,p.name product_name,
                    COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_name,
                    COALESCE(m.path,'/assets/images/packaging-reference.png') image_path
             FROM product_set_components psc JOIN product_variants v ON v.id=psc.component_variant_id
             JOIN products p ON p.id=v.product_id
             LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
             LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
             LEFT JOIN product_options po ON po.id=ov.option_id
             LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
             LEFT JOIN media_assets m ON m.id=pi.media_id
             WHERE psc.set_product_id=? GROUP BY psc.id ORDER BY psc.sort_order,psc.id"
        );
        $statement->execute([$productId]);
        return $statement->fetchAll();
    }
}
