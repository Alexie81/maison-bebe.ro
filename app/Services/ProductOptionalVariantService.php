<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use PDO;

final class ProductOptionalVariantService
{
    public function forProduct(int $productId, bool $activeOnly = false, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $sql = 'SELECT * FROM product_optional_variants WHERE product_id=?';
        if ($activeOnly) $sql .= ' AND is_active=1';
        $sql .= ' ORDER BY sort_order,id';
        $statement = $pdo->prepare($sql);
        $statement->execute([$productId]);
        return $statement->fetchAll();
    }

    public function save(int $productId, array $input, PDO $pdo): void
    {
        $ids = (array) ($input['optional_variant_id'] ?? []);
        $names = (array) ($input['optional_variant_name'] ?? []);
        $prices = (array) ($input['optional_variant_price'] ?? []);
        $kept = [];
        $upsert = $pdo->prepare(
            'INSERT INTO product_optional_variants (id,product_id,name,price_delta_minor,is_active,sort_order) VALUES (?,?,?,?,1,?) '
            . 'ON DUPLICATE KEY UPDATE name=VALUES(name),price_delta_minor=VALUES(price_delta_minor),is_active=1,sort_order=VALUES(sort_order),updated_at=NOW()'
        );
        foreach ($names as $index => $rawName) {
            $name = trim((string) $rawName);
            if ($name === '') continue;
            $price = (int) round((float) str_replace(',', '.', (string) ($prices[$index] ?? '0')) * 100);
            if ($price < 0) throw new HttpException(422, 'PreÈ›ul suplimentar al variantei opÈ›ionale nu poate fi negativ.');
            $id = max(0, (int) ($ids[$index] ?? 0));
            if ($id > 0) {
                $owner = $pdo->prepare('SELECT product_id FROM product_optional_variants WHERE id=?');
                $owner->execute([$id]);
                if ((int) $owner->fetchColumn() !== $productId) throw new HttpException(422, 'Varianta opÈ›ionalÄƒ nu aparÈ›ine acestui produs.');
            } else {
                $id = null;
            }
            $upsert->execute([$id, $productId, mb_substr($name, 0, 190), $price, $index * 10]);
            $kept[] = $id ?: (int) $pdo->lastInsertId();
        }
        if ($kept) {
            $placeholders = implode(',', array_fill(0, count($kept), '?'));
            $pdo->prepare("DELETE FROM product_optional_variants WHERE product_id=? AND id NOT IN ({$placeholders})")
                ->execute([$productId, ...$kept]);
        } else {
            $pdo->prepare('DELETE FROM product_optional_variants WHERE product_id=?')->execute([$productId]);
        }
    }

    public function withSnapshot(int $variantId, array $selectedIds, array $customization, PDO $pdo): array
    {
        $selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds))));
        unset($customization['optional_variant_ids'], $customization['optional_variants'], $customization['optional_variants_total_minor']);
        if (!$selectedIds) return $customization;
        $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
        $statement = $pdo->prepare(
            "SELECT pov.id,pov.name,pov.price_delta_minor FROM product_optional_variants pov
             JOIN product_variants v ON v.product_id=pov.product_id
             WHERE v.id=? AND pov.id IN ({$placeholders}) AND pov.is_active=1 ORDER BY pov.sort_order,pov.id"
        );
        $statement->execute([$variantId, ...$selectedIds]);
        $options = $statement->fetchAll();
        if (count($options) !== count($selectedIds)) throw new HttpException(422, 'O variantÄƒ opÈ›ionalÄƒ nu mai este disponibilÄƒ.');
        $customization['optional_variants'] = array_map(static fn(array $option): array => [
            'id' => (int) $option['id'],
            'name' => (string) $option['name'],
            'price_delta_minor' => (int) $option['price_delta_minor'],
        ], $options);
        $customization['optional_variants_total_minor'] = array_sum(array_column($customization['optional_variants'], 'price_delta_minor'));
        return $customization;
    }

    public function unitPrice(int $basePriceMinor, array $customization): int
    {
        return max(0, $basePriceMinor + (int) ($customization['optional_variants_total_minor'] ?? 0));
    }

    public function label(array $customization): string
    {
        return implode(', ', array_filter(array_map(
            static fn(array $option): string => trim((string) ($option['name'] ?? '')),
            (array) ($customization['optional_variants'] ?? [])
        )));
    }
}
