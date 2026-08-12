<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use MaisonBebe\Core\HttpException;
use PDO;

final class ProductMappingService
{
    public function candidates(string $query = ''): array
    {
        $pdo = Database::connection();
        $query = trim($query);
        $scopeCondition = (new AccountingStockScopeService())->listingCondition('v');
        $sql = "SELECT v.id variant_id,v.product_id,
                       CASE WHEN v.track_accounting_stock=1 THEN v.sku ELSE p.sku END sku,
                       CASE WHEN v.track_accounting_stock=1 THEN v.ean ELSE NULL END ean,
                       v.stock_qty,CASE WHEN v.track_accounting_stock=1 THEN v.track_inventory ELSE 0 END track_inventory,
                       1 track_accounting_stock,v.is_active,
                       p.name product_name,p.status product_status,p.is_gift_box,
                       CASE WHEN v.track_accounting_stock=1 THEN COALESCE(GROUP_CONCAT(DISTINCT ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') ELSE 'Produs (fără variante)' END variant_name,
                       COALESCE(GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),'Fără categorie') category_name,
                       COALESCE(m.path,'/assets/images/packaging-reference.png') image_path,
                       CASE WHEN v.track_accounting_stock=1 THEN COALESCE(SUM(b.current_quantity),0)
                            ELSE COALESCE((SELECT SUM(scope_b.current_quantity) FROM accounting_stock_balances scope_b
                                JOIN product_variants scope_v ON scope_v.id=scope_b.product_variant_id
                                WHERE scope_v.product_id=p.id),0) END accounting_quantity
                FROM product_variants v
                JOIN products p ON p.id=v.product_id AND (p.deleted_at IS NULL OR p.is_gift_box=1)
                LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
                LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
                LEFT JOIN product_options po ON po.id=ov.option_id
                LEFT JOIN product_categories pc ON pc.product_id=p.id
                LEFT JOIN categories c ON c.id=pc.category_id
                LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
                LEFT JOIN media_assets m ON m.id=pi.media_id
                LEFT JOIN accounting_stock_balances b ON b.product_variant_id=v.id
                WHERE {$scopeCondition}";
        $params = [];
        if ($query !== '') {
            $sql .= " AND (v.sku LIKE ? OR p.sku LIKE ? OR v.ean LIKE ? OR p.name LIKE ? OR ov.value LIKE ? OR c.name LIKE ?
                       OR EXISTS(SELECT 1 FROM product_supplier_mappings psm WHERE psm.product_variant_id=v.id
                                 AND (psm.supplier_product_code LIKE ? OR psm.supplier_product_name LIKE ?)))";
            $like = '%' . $query . '%';
            $params = array_fill(0, 8, $like);
        }
        $sql .= ' GROUP BY v.id ORDER BY p.status=\'active\' DESC,p.name,variant_name LIMIT 500';
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $products = $statement->fetchAll();
        if (!$products) {
            return [];
        }

        $variantIds = array_values(array_unique(array_map(
            static fn(array $product): int => (int) $product['variant_id'],
            $products
        )));
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $mappings = $pdo->prepare(
            "SELECT psm.product_variant_id,psm.supplier_product_name,psm.supplier_product_code,
                    s.legal_name supplier_name,s.tax_id supplier_tax_id,s.tax_id_normalized supplier_tax_id_normalized
             FROM product_supplier_mappings psm
             JOIN accounting_suppliers s ON s.id=psm.supplier_id
             WHERE psm.product_variant_id IN ({$placeholders})
               AND TRIM(COALESCE(psm.supplier_product_name,''))<>''
             ORDER BY psm.updated_at DESC,psm.id DESC"
        );
        $mappings->execute($variantIds);
        $byVariant = [];
        foreach ($mappings->fetchAll() as $mapping) {
            $variantId = (int) $mapping['product_variant_id'];
            $supplierKey = trim((string) $mapping['supplier_tax_id_normalized'])
                ?: mb_strtolower(trim((string) $mapping['supplier_name']));
            if ($supplierKey === '' || isset($byVariant[$variantId][$supplierKey])) {
                continue;
            }
            $byVariant[$variantId][$supplierKey] = [
                'supplier_name' => $mapping['supplier_name'],
                'supplier_tax_id' => $mapping['supplier_tax_id'],
                'supplier_tax_id_normalized' => $mapping['supplier_tax_id_normalized'],
                'supplier_product_name' => $mapping['supplier_product_name'],
                'supplier_product_code' => str_starts_with((string) $mapping['supplier_product_code'], '@VARIANT:')
                    ? '' : $mapping['supplier_product_code'],
                'source' => 'remembered',
            ];
        }
        $history = $pdo->prepare(
            "SELECT nl.product_variant_id,sil.supplier_product_name,sil.supplier_product_code,
                    s.legal_name supplier_name,s.tax_id supplier_tax_id,s.tax_id_normalized supplier_tax_id_normalized
             FROM nir_lines nl
             JOIN nir_documents n ON n.id=nl.nir_document_id AND n.document_kind='receipt'
             JOIN supplier_invoice_lines sil ON sil.id=nl.supplier_invoice_line_id
             JOIN accounting_suppliers s ON s.id=n.supplier_id
             WHERE nl.product_variant_id IN ({$placeholders})
               AND n.status IN ('Confirmed','PartiallyReceived','Reversed')
               AND TRIM(COALESCE(sil.supplier_product_name,''))<>''
             ORDER BY COALESCE(n.confirmed_at,n.updated_at) DESC,nl.id DESC"
        );
        $history->execute($variantIds);
        foreach ($history->fetchAll() as $mapping) {
            $variantId = (int) $mapping['product_variant_id'];
            $supplierKey = trim((string) $mapping['supplier_tax_id_normalized'])
                ?: mb_strtolower(trim((string) $mapping['supplier_name']));
            if ($supplierKey === '' || isset($byVariant[$variantId][$supplierKey])) {
                continue;
            }
            $byVariant[$variantId][$supplierKey] = [
                'supplier_name' => $mapping['supplier_name'],
                'supplier_tax_id' => $mapping['supplier_tax_id'],
                'supplier_tax_id_normalized' => $mapping['supplier_tax_id_normalized'],
                'supplier_product_name' => $mapping['supplier_product_name'],
                'supplier_product_code' => $mapping['supplier_product_code'],
                'source' => 'confirmed_nir_history',
            ];
        }
        foreach ($products as &$product) {
            $product['supplier_mappings'] = array_values($byVariant[(int) $product['variant_id']] ?? []);
        }
        unset($product);
        return $products;
    }

    public function automatic(
        int $supplierId,
        ?string $sku,
        ?string $ean,
        ?string $supplierCode,
        ?PDO $pdo = null,
        ?string $supplierName = null
    ): ?array
    {
        $pdo ??= Database::connection();
        $sku = $this->normalizeIdentifier($sku);
        if ($sku !== '') {
            $match = $this->uniqueVariantMatch(
                $pdo,
                'v.sku=? OR (p.sku=? AND NOT EXISTS(SELECT 1 FROM product_variants ast WHERE ast.product_id=p.id AND ast.is_active=1 AND ast.track_accounting_stock=1) '
                    . 'AND v.id=(SELECT ast_anchor.id FROM product_variants ast_anchor WHERE ast_anchor.product_id=p.id ORDER BY ast_anchor.is_active DESC,ast_anchor.id LIMIT 1))',
                [$sku, $sku]
            );
            if ($match) {
                $match['association_status'] = 'automatic';
                $match['association_source'] = 'sku';
                return $match;
            }
        }
        $ean = $this->normalizeIdentifier($ean);
        if ($ean !== '') {
            $match = $this->uniqueVariantMatch($pdo, 'v.ean=?', [$ean]);
            if ($match) {
                $match['association_status'] = 'automatic';
                $match['association_source'] = 'ean';
                return $match;
            }
        }
        $supplierCode = trim((string) $supplierCode);
        if ($supplierId > 0 && $supplierCode !== '') {
            $statement = $pdo->prepare(
                'SELECT psm.product_id,psm.product_variant_id,psm.maison_bebe_sku sku,p.name product_name,'
                . "COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_name,
                   v.track_inventory,v.track_accounting_stock
                   FROM product_supplier_mappings psm JOIN products p ON p.id=psm.product_id
                   JOIN product_variants v ON v.id=psm.product_variant_id
                   LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
                   LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
                   LEFT JOIN product_options po ON po.id=ov.option_id
                   WHERE psm.supplier_id=? AND psm.supplier_product_code=? GROUP BY psm.id LIMIT 2"
            );
            $statement->execute([$supplierId, $supplierCode]);
            $rows = $statement->fetchAll();
            if (count($rows) === 1) {
                $rows[0]['association_status'] = 'automatic';
                $rows[0]['association_source'] = 'supplier_code';
                return $rows[0];
            }
        }
        $supplierName = trim((string) $supplierName);
        if ($supplierId > 0 && $supplierName !== '') {
            $statement = $pdo->prepare(
                'SELECT psm.product_id,psm.product_variant_id,psm.maison_bebe_sku sku,p.name product_name,'
                . "COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_name,
                   v.track_inventory,v.track_accounting_stock
                   FROM product_supplier_mappings psm JOIN products p ON p.id=psm.product_id
                   JOIN product_variants v ON v.id=psm.product_variant_id
                   LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
                   LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
                   LEFT JOIN product_options po ON po.id=ov.option_id
                   WHERE psm.supplier_id=? AND TRIM(psm.supplier_product_name)=? GROUP BY psm.id LIMIT 2"
            );
            $statement->execute([$supplierId, $supplierName]);
            $rows = $statement->fetchAll();
            if (count($rows) === 1) {
                $rows[0]['association_status'] = 'automatic';
                $rows[0]['association_source'] = 'supplier_name';
                return $rows[0];
            }
            $statement = $pdo->prepare(
                "SELECT nl.product_id,nl.product_variant_id,nl.sku_snapshot sku,
                        nl.product_name_snapshot product_name,nl.variant_name_snapshot variant_name,
                        CASE WHEN nl.online_stock_mode_snapshot='limited' THEN 1 ELSE 0 END track_inventory,
                        nl.track_accounting_stock_snapshot track_accounting_stock
                 FROM nir_lines nl
                 JOIN nir_documents n ON n.id=nl.nir_document_id AND n.document_kind='receipt'
                 JOIN supplier_invoice_lines sil ON sil.id=nl.supplier_invoice_line_id
                 WHERE n.supplier_id=? AND TRIM(sil.supplier_product_name)=?
                   AND n.status IN ('Confirmed','PartiallyReceived','Reversed')
                   AND nl.product_variant_id IS NOT NULL
                 GROUP BY nl.product_variant_id
                 ORDER BY MAX(COALESCE(n.confirmed_at,n.updated_at)) DESC
                 LIMIT 2"
            );
            $statement->execute([$supplierId, $supplierName]);
            $rows = $statement->fetchAll();
            if (count($rows) === 1) {
                $rows[0]['association_status'] = 'automatic';
                $rows[0]['association_source'] = 'confirmed_nir_history';
                return $rows[0];
            }
        }
        return null;
    }

    public function remember(
        int $supplierId,
        string $supplierCode,
        ?string $supplierName,
        ?string $supplierEan,
        int $variantId,
        ?PDO $pdo = null
    ): void {
        $pdo ??= Database::connection();
        $variant = $this->variant($variantId, $pdo);
        $supplierCode = trim($supplierCode);
        $supplierName = trim((string) $supplierName);
        if ($supplierId < 1 || ($supplierCode === '' && $supplierName === '') || !$variant) {
            throw new HttpException(422, 'Asocierea furnizor-produs nu este completă.');
        }
        $mappingKey = $supplierCode !== '' ? $supplierCode : '@VARIANT:' . $variantId;
        $pdo->prepare(
            'INSERT INTO product_supplier_mappings '
            . '(supplier_id,supplier_product_code,supplier_product_name,supplier_ean,product_id,product_variant_id,maison_bebe_sku,created_by,updated_by) '
            . 'VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE supplier_product_name=VALUES(supplier_product_name),'
            . 'supplier_ean=VALUES(supplier_ean),product_id=VALUES(product_id),product_variant_id=VALUES(product_variant_id),'
            . 'maison_bebe_sku=VALUES(maison_bebe_sku),updated_by=VALUES(updated_by)'
        )->execute([
            $supplierId, $mappingKey, $supplierName ?: null,
            $this->normalizeIdentifier($supplierEan) ?: null, $variant['product_id'], $variantId,
            $variant['sku'], Auth::id(), Auth::id(),
        ]);
        (new AccountingAuditService())->record(
            'nir.product_mapping.saved',
            'product_variant',
            $variantId,
            [],
            [
                'supplier_id' => $supplierId,
                'supplier_product_code' => $supplierCode ?: null,
                'supplier_product_name' => $supplierName ?: null,
                'sku' => $variant['sku'],
            ],
            null,
            null,
            $pdo
        );
    }

    public function variant(int $variantId, ?PDO $pdo = null): ?array
    {
        return (new AccountingStockScopeService())->resolveVariant($variantId, $pdo);
    }

    public function assertCatalogSkuIntegrity(?PDO $pdo = null): void
    {
        $pdo ??= Database::connection();
        $missing = (int) $pdo->query(
            "SELECT COUNT(*) FROM product_variants WHERE track_accounting_stock=1 AND TRIM(COALESCE(sku,''))=''"
        )->fetchColumn();
        if ($missing > 0) {
            throw new HttpException(422, 'Catalogul conține produse urmărite contabil fără SKU.');
        }
        $crossDuplicate = $pdo->query(
            'SELECT v.sku FROM product_variants v JOIN products p ON p.sku=v.sku WHERE v.track_accounting_stock=1 LIMIT 1'
        )->fetchColumn();
        if ($crossDuplicate !== false) {
            throw new HttpException(422, 'SKU-ul „' . $crossDuplicate . '” este duplicat între produs și variantă. Confirmarea a fost blocată.');
        }
    }

    private function uniqueVariantMatch(PDO $pdo, string $where, array $params): ?array
    {
        $statement = $pdo->prepare(
            "SELECT v.product_id,v.id product_variant_id,v.sku,p.name product_name,
                    COALESCE(GROUP_CONCAT(ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') variant_name,
                    v.track_inventory,v.track_accounting_stock
             FROM product_variants v JOIN products p ON p.id=v.product_id AND (p.deleted_at IS NULL OR p.is_gift_box=1)
             LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
             LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
             LEFT JOIN product_options po ON po.id=ov.option_id WHERE {$where} GROUP BY v.id LIMIT 2"
        );
        $statement->execute($params);
        $rows = $statement->fetchAll();
        if (count($rows) !== 1) return null;
        return (new AccountingStockScopeService())->resolveVariant((int) $rows[0]['product_variant_id'], $pdo);
    }

    private function normalizeIdentifier(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }
}
