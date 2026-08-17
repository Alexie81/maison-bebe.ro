<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use DateTimeImmutable;
use MaisonBebe\Core\Database;
use RuntimeException;

final class AccountingStockPeriodExportService
{
    public function generate(string $from, string $to): array
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if (!$start || $start->format('Y-m-d') !== $from || !$end || $end->format('Y-m-d') !== $to || $start > $end) {
            throw new RuntimeException('Perioada stocurilor nu este validă.');
        }
        if ($end > new DateTimeImmutable('today') || (int) $start->diff($end)->format('%a') > 366) {
            throw new RuntimeException('Perioada stocurilor poate avea cel mult 366 de zile și nu poate include zile viitoare.');
        }

        $pdo = Database::connection();
        $scope = (new AccountingStockScopeService())->listingCondition('v');
        $items = $pdo->query(
            "SELECT v.id variant_id,v.track_accounting_stock raw_tracking_mode,CASE WHEN v.track_accounting_stock=1 THEN v.sku ELSE p.sku END sku,
                    p.id product_id,p.name product_name,p.is_gift_box,w.id warehouse_id,w.name warehouse_name,
                    CASE WHEN v.track_accounting_stock=1 THEN COALESCE(GROUP_CONCAT(DISTINCT ov.value ORDER BY po.sort_order SEPARATOR ' / '),'Standard') ELSE 'Produs (fără variante)' END variant_name,
                    COALESCE((SELECT ma.path FROM product_images pi JOIN media_assets ma ON ma.id=pi.media_id WHERE pi.product_id=p.id ORDER BY pi.is_primary DESC,pi.sort_order,pi.id LIMIT 1),'') image_path
             FROM product_variants v JOIN products p ON p.id=v.product_id AND (p.deleted_at IS NULL OR p.is_gift_box=1)
             CROSS JOIN accounting_warehouses w
             LEFT JOIN variant_option_values vov ON vov.variant_id=v.id
             LEFT JOIN product_option_values ov ON ov.id=vov.option_value_id
             LEFT JOIN product_options po ON po.id=ov.option_id
             WHERE w.is_active=1 AND {$scope}
             GROUP BY v.id,w.id ORDER BY p.name,variant_name,w.name"
        )->fetchAll();

        $movement = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN effective_date<? THEN quantity_in-quantity_out ELSE 0 END),0) opening_quantity,
                COALESCE(SUM(CASE WHEN effective_date BETWEEN ? AND ? THEN quantity_in ELSE 0 END),0) period_in,
                COALESCE(SUM(CASE WHEN effective_date BETWEEN ? AND ? THEN quantity_out ELSE 0 END),0) period_out,
                COALESCE(SUM(CASE WHEN effective_date<=? THEN quantity_in-quantity_out ELSE 0 END),0) closing_quantity,
                SUM(CASE WHEN effective_date BETWEEN ? AND ? THEN 1 ELSE 0 END) movement_count,
                MAX(CASE WHEN effective_date<=? THEN effective_date END) last_movement
             FROM accounting_stock_movements WHERE product_variant_id=? AND warehouse_id=?"
        );
        $valuation = $pdo->prepare(
            "SELECT av.balance_value_after,av.calculated_unit_cost
             FROM accounting_stock_movements m
             JOIN accounting_stock_balances b ON b.product_variant_id=m.product_variant_id AND b.warehouse_id=m.warehouse_id
             JOIN accounting_stock_valuations av ON av.movement_id=m.id AND av.valuation_run_id=b.projection_version
             WHERE m.product_variant_id=? AND m.warehouse_id=? AND m.effective_date<=?
             ORDER BY m.effective_date DESC,COALESCE(m.effective_time,'00:00:00') DESC,m.posted_at DESC,m.id DESC LIMIT 1"
        );
        $productValuation = $pdo->prepare(
            "SELECT COALESCE(SUM(COALESCE((
                    SELECT av.balance_value_after
                    FROM accounting_stock_movements m
                    JOIN accounting_stock_balances b ON b.product_variant_id=m.product_variant_id AND b.warehouse_id=m.warehouse_id
                    JOIN accounting_stock_valuations av ON av.movement_id=m.id AND av.valuation_run_id=b.projection_version
                    WHERE m.product_variant_id=pv.id AND m.warehouse_id=? AND m.effective_date<=?
                    ORDER BY m.effective_date DESC,COALESCE(m.effective_time,'00:00:00') DESC,m.posted_at DESC,m.id DESC LIMIT 1
                ),0)),0) balance_value_after
             FROM product_variants pv WHERE pv.product_id=?"
        );
        $productMovement = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN effective_date<? THEN quantity_in-quantity_out ELSE 0 END),0) opening_quantity,
                COALESCE(SUM(CASE WHEN effective_date BETWEEN ? AND ? THEN quantity_in ELSE 0 END),0) period_in,
                COALESCE(SUM(CASE WHEN effective_date BETWEEN ? AND ? THEN quantity_out ELSE 0 END),0) period_out,
                COALESCE(SUM(CASE WHEN effective_date<=? THEN quantity_in-quantity_out ELSE 0 END),0) closing_quantity,
                SUM(CASE WHEN effective_date BETWEEN ? AND ? THEN 1 ELSE 0 END) movement_count,
                MAX(CASE WHEN effective_date<=? THEN effective_date END) last_movement
             FROM accounting_stock_movements WHERE product_id=? AND warehouse_id=?"
        );

        $rows = [];
        $images = [];
        foreach ($items as $index => $item) {
            $productScope = (int) $item['raw_tracking_mode'] !== 1;
            $movementStatement = $productScope ? $productMovement : $movement;
            $movementStatement->execute([$from,$from,$to,$from,$to,$to,$from,$to,$to,$productScope ? $item['product_id'] : $item['variant_id'],$item['warehouse_id']]);
            $totals = $movementStatement->fetch() ?: [];
            if ($productScope) {
                $productValuation->execute([$item['warehouse_id'],$to,$item['product_id']]);
                $closingValue = (float) ($productValuation->fetchColumn() ?: 0);
                $closingQuantity = (float) ($totals['closing_quantity'] ?? 0);
                $value = ['balance_value_after'=>$closingValue,'calculated_unit_cost'=>$closingQuantity != 0.0 ? $closingValue / $closingQuantity : 0];
            } else {
                $valuation->execute([$item['variant_id'],$item['warehouse_id'],$to]);
                $value = $valuation->fetch() ?: ['balance_value_after'=>'0','calculated_unit_cost'=>'0'];
            }
            $rows[] = [
                '', $item['sku'], $item['product_name'], $item['variant_name'], $item['is_gift_box'] ? 'Cutie cadou' : 'Produs', $item['warehouse_name'],
                $totals['opening_quantity'] ?? 0, $totals['period_in'] ?? 0, $totals['period_out'] ?? 0, $totals['closing_quantity'] ?? 0,
                $value['calculated_unit_cost'] ?? 0, $value['balance_value_after'] ?? 0, (int) ($totals['movement_count'] ?? 0), $totals['last_movement'] ?? '',
            ];
            $imagePath = trim((string) $item['image_path']);
            if ($imagePath !== '') {
                $absolute = BASE_PATH . '/public/' . ltrim($imagePath, '/');
                if (is_file($absolute)) $images[$index] = $absolute;
            }
        }

        $binary = (new XlsxService())->export('Stocuri Conta', [
            'Imagine','SKU','Produs','Variantă','Tip articol','Gestiune','Sold inițial','Intrări în perioadă','Ieșiri în perioadă','Sold final','Cost unitar final (RON)','Valoare finală (RON)','Nr. mișcări','Ultima mișcare',
        ], $rows, [
            'Raport' => 'Situația stocurilor contabile pe perioadă',
            'Perioada' => $from . ' - ' . $to,
            'Metodă' => 'Sold inițial + intrări - ieșiri = sold final',
            'Generat la' => date('d.m.Y H:i'),
        ], ['text','text','text','text','text','text','integer','integer','integer','integer','','','integer','text'], $images);

        return ['binary' => $binary, 'filename' => 'stocuri-contabile-' . $from . '-' . $to . '.xlsx', 'count' => count($rows)];
    }
}
