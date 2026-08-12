<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use PDO;

final class AccountingStockScopeService
{
    /**
     * Returns the accounting target for a catalog variant.
     *
     * If at least one variant is explicitly tracked, only the checked variants
     * are targets. If none is checked, the product is tracked once through a
     * stable internal anchor variant and is presented with the product SKU.
     */
    public function resolveVariant(int $variantId, ?PDO $pdo = null): ?array
    {
        if ($variantId < 1) return null;
        $pdo ??= Database::connection();
        $requested = $this->rawVariant($variantId, $pdo);
        if (!$requested) return null;
        if ((int) $requested['track_accounting_stock'] === 1 && (int) $requested['is_active'] === 1) {
            $requested['product_variant_id'] = (int) $requested['id'];
            $requested['variant_id'] = (int) $requested['id'];
            $requested['is_product_scope'] = 0;
            return $requested;
        }

        $tracked = $pdo->prepare('SELECT COUNT(*) FROM product_variants WHERE product_id=? AND is_active=1 AND track_accounting_stock=1');
        $tracked->execute([(int) $requested['product_id']]);
        if ((int) $tracked->fetchColumn() > 0) return null;

        $anchor = $pdo->prepare(
            "SELECT id FROM product_variants WHERE product_id=? ORDER BY is_active DESC,id LIMIT 1"
        );
        $anchor->execute([(int) $requested['product_id']]);
        $target = $this->rawVariant((int) $anchor->fetchColumn(), $pdo);
        if (!$target) return null;
        $target['sku'] = trim((string) $target['product_sku']) ?: (string) $target['sku'];
        $target['ean'] = null;
        $target['variant_name'] = 'Produs (fără variante)';
        $target['track_accounting_stock'] = 1;
        $target['product_variant_id'] = (int) $target['id'];
        $target['variant_id'] = (int) $target['id'];
        $target['is_product_scope'] = 1;
        return $target;
    }

    public function listingCondition(string $variantAlias = 'v'): string
    {
        return "(({$variantAlias}.track_accounting_stock=1 AND {$variantAlias}.is_active=1) OR ("
            . "NOT EXISTS(SELECT 1 FROM product_variants ast_tracked WHERE ast_tracked.product_id={$variantAlias}.product_id AND ast_tracked.is_active=1 AND ast_tracked.track_accounting_stock=1) "
            . "AND {$variantAlias}.id=(SELECT ast_anchor.id FROM product_variants ast_anchor WHERE ast_anchor.product_id={$variantAlias}.product_id ORDER BY ast_anchor.is_active DESC,ast_anchor.id LIMIT 1)))";
    }

    public function isProductScope(int $productId, ?PDO $pdo = null): bool
    {
        $pdo ??= Database::connection();
        $statement = $pdo->prepare('SELECT NOT EXISTS(SELECT 1 FROM product_variants WHERE product_id=? AND is_active=1 AND track_accounting_stock=1)');
        $statement->execute([$productId]);
        return (bool) $statement->fetchColumn();
    }

    private function rawVariant(int $variantId, PDO $pdo): ?array
    {
        $statement = $pdo->prepare(
            "SELECT v.*,p.sku product_sku,p.name product_name,p.status product_status,p.is_gift_box,
                    COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_name
             FROM product_variants v JOIN products p ON p.id=v.product_id
             LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
             LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
             LEFT JOIN product_options po ON po.id=ov.option_id
             WHERE v.id=? GROUP BY v.id"
        );
        $statement->execute([$variantId]);
        return $statement->fetch() ?: null;
    }
}
