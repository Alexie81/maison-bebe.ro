<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Database;
use PDO;

final class AccountingStockProjectionService
{
    public function rebuildVariants(
        array $variantIds,
        ?string $earliestEffectiveDate = null,
        string $reason = 'Recalculare proiecție',
        ?string $correlationId = null,
        ?PDO $pdo = null
    ): ?int {
        $pdo ??= Database::connection();
        $variantIds = array_values(array_unique(array_filter(array_map('intval', $variantIds))));
        if (!$variantIds) {
            return null;
        }
        $method = (string) (new AccountingSettingsService())->get()['valuation_method'];
        $correlationId ??= 'valuation:' . bin2hex(random_bytes(12));
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $skuStatement = $pdo->prepare("SELECT sku FROM product_variants WHERE id IN ({$placeholders}) ORDER BY sku");
        $skuStatement->execute($variantIds);
        $skus = $skuStatement->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare(
            'INSERT INTO accounting_valuation_runs '
            . '(valuation_method,reason,earliest_effective_date,affected_skus_json,created_by,correlation_id) VALUES (?,?,?,?,?,?)'
        )->execute([
            $method,
            $reason,
            $earliestEffectiveDate,
            json_encode($skus, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            Auth::id(),
            $correlationId,
        ]);
        $runId = (int) $pdo->lastInsertId();

        $pairs = $pdo->prepare(
            "SELECT DISTINCT product_variant_id,warehouse_id FROM accounting_stock_movements "
            . "WHERE product_variant_id IN ({$placeholders}) ORDER BY product_variant_id,warehouse_id"
        );
        $pairs->execute($variantIds);
        foreach ($pairs->fetchAll() as $pair) {
            $this->rebuildOne($pdo, (int) $pair['product_variant_id'], (int) $pair['warehouse_id'], $runId, $method);
        }

        (new AccountingAuditService())->record(
            'accounting.projection.rebuilt',
            'accounting_valuation_run',
            $runId,
            [],
            ['method' => $method, 'variants' => $variantIds, 'earliest_effective_date' => $earliestEffectiveDate],
            $reason,
            $correlationId,
            $pdo
        );
        return $runId;
    }

    public function rebuildAll(string $reason = 'Recalculare integrală'): ?int
    {
        $ids = Database::connection()->query('SELECT DISTINCT product_variant_id FROM accounting_stock_movements ORDER BY product_variant_id')
            ->fetchAll(PDO::FETCH_COLUMN);
        return $this->rebuildVariants($ids, null, $reason);
    }

    public function asOf(int $variantId, int $warehouseId, string $date): array
    {
        $pdo = Database::connection();
        $projection = $pdo->prepare('SELECT projection_version FROM accounting_stock_balances WHERE product_variant_id=? AND warehouse_id=?');
        $projection->execute([$variantId, $warehouseId]);
        $runId = (int) $projection->fetchColumn();
        if (!$runId) {
            return ['quantity' => '0.0000', 'value' => '0.00', 'unit_cost' => '0.000000'];
        }
        $statement = $pdo->prepare(
            'SELECT v.balance_quantity_after quantity,v.balance_value_after value,v.calculated_unit_cost unit_cost '
            . 'FROM accounting_stock_valuations v JOIN accounting_stock_movements m ON m.id=v.movement_id '
            . 'WHERE v.valuation_run_id=? AND m.product_variant_id=? AND m.warehouse_id=? AND m.effective_date<=? '
            . 'ORDER BY m.effective_date DESC,COALESCE(m.effective_time,\'00:00:00\') DESC,m.posted_at DESC,m.id DESC LIMIT 1'
        );
        $statement->execute([$runId, $variantId, $warehouseId, $date]);
        return $statement->fetch() ?: ['quantity' => '0.0000', 'value' => '0.00', 'unit_cost' => '0.000000'];
    }

    private function rebuildOne(PDO $pdo, int $variantId, int $warehouseId, int $runId, string $method): void
    {
        $statement = $pdo->prepare(
            'SELECT * FROM accounting_stock_movements WHERE product_variant_id=? AND warehouse_id=? '
            . 'ORDER BY effective_date,COALESCE(effective_time,\'00:00:00\'),posted_at,id'
        );
        $statement->execute([$variantId, $warehouseId]);
        $movements = $statement->fetchAll();
        if (!$movements) {
            return;
        }

        $quantity = '0.0000';
        $value = '0.00';
        $unitCost = '0.000000';
        $minimum = '0.0000';
        $historicalNegative = false;
        $layers = [];
        $insert = $pdo->prepare(
            'INSERT INTO accounting_stock_valuations '
            . '(movement_id,valuation_run_id,valuation_method,calculated_unit_cost,calculated_movement_value,balance_quantity_after,balance_value_after) '
            . 'VALUES (?,?,?,?,?,?,?)'
        );

        foreach ($movements as $movement) {
            $quantityIn = Decimal::normalize($movement['quantity_in'], 4);
            $quantityOut = Decimal::normalize($movement['quantity_out'], 4);
            $movementValue = '0.00';
            $movementCost = $unitCost;

            if (Decimal::cmp($quantityIn, '0', 4) > 0) {
                $movementCost = $movement['source_unit_cost'] !== null
                    ? Decimal::normalize($movement['source_unit_cost'], 6)
                    : $unitCost;
                $movementValue = Decimal::round(Decimal::mul($quantityIn, $movementCost, 10), 2);
                $quantity = Decimal::add($quantity, $quantityIn, 4);
                $value = Decimal::add($value, $movementValue, 2);
                if ($method === 'FIFO') {
                    $layers[] = ['quantity' => $quantityIn, 'cost' => $movementCost];
                }
            } else {
                if ($method === 'FIFO') {
                    [$movementValue, $movementCost, $layers] = $this->fifoOut($quantityOut, $layers, $unitCost, $movement);
                } else {
                    $movementCost = Decimal::cmp($quantity, '0', 4) !== 0
                        ? Decimal::div($value, $quantity, 6)
                        : ($movement['source_unit_cost'] !== null ? Decimal::normalize($movement['source_unit_cost'], 6) : $unitCost);
                    $movementValue = Decimal::round(Decimal::mul($quantityOut, $movementCost, 10), 2);
                }
                $quantity = Decimal::sub($quantity, $quantityOut, 4);
                $value = Decimal::sub($value, $movementValue, 2);
            }

            if (Decimal::cmp($quantity, '0', 4) === 0) {
                $value = '0.00';
            }
            if (Decimal::cmp($quantity, '0', 4) !== 0) {
                $unitCost = Decimal::div($value, $quantity, 6);
            } elseif (Decimal::cmp($movementCost, '0', 6) >= 0) {
                $unitCost = $movementCost;
            }
            if (Decimal::cmp($quantity, $minimum, 4) < 0) {
                $minimum = $quantity;
            }
            if (Decimal::cmp($quantity, '0', 4) < 0) {
                $historicalNegative = true;
            }
            $insert->execute([
                $movement['id'],
                $runId,
                $method,
                $movementCost,
                $movementValue,
                $quantity,
                $value,
            ]);
        }

        $last = $movements[array_key_last($movements)];
        $pdo->prepare(
            'INSERT INTO accounting_stock_balances '
            . '(product_id,product_variant_id,sku,warehouse_id,current_quantity,current_accounting_value,calculated_unit_cost,minimum_historical_quantity,has_current_negative_balance,has_historical_negative_balance,last_movement_date,projection_version,row_version) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1) '
            . 'ON DUPLICATE KEY UPDATE product_id=VALUES(product_id),sku=VALUES(sku),current_quantity=VALUES(current_quantity),'
            . 'current_accounting_value=VALUES(current_accounting_value),calculated_unit_cost=VALUES(calculated_unit_cost),'
            . 'minimum_historical_quantity=VALUES(minimum_historical_quantity),has_current_negative_balance=VALUES(has_current_negative_balance),'
            . 'has_historical_negative_balance=VALUES(has_historical_negative_balance),last_movement_date=VALUES(last_movement_date),'
            . 'projection_version=VALUES(projection_version),row_version=row_version+1'
        )->execute([
            $last['product_id'],
            $variantId,
            $last['sku_snapshot'],
            $warehouseId,
            $quantity,
            $value,
            $unitCost,
            $minimum,
            Decimal::cmp($quantity, '0', 4) < 0 ? 1 : 0,
            $historicalNegative ? 1 : 0,
            $last['effective_date'],
            $runId,
        ]);
    }

    private function fifoOut(string $required, array $layers, string $fallbackCost, array $movement): array
    {
        $remaining = $required;
        $value = '0.00';
        while (Decimal::cmp($remaining, '0', 4) > 0 && $layers) {
            $layer = array_shift($layers);
            $take = Decimal::cmp($layer['quantity'], $remaining, 4) <= 0 ? $layer['quantity'] : $remaining;
            $value = Decimal::add($value, Decimal::round(Decimal::mul($take, $layer['cost'], 10), 2), 2);
            $remaining = Decimal::sub($remaining, $take, 4);
            $left = Decimal::sub($layer['quantity'], $take, 4);
            if (Decimal::cmp($left, '0', 4) > 0) {
                array_unshift($layers, ['quantity' => $left, 'cost' => $layer['cost']]);
            }
            $fallbackCost = $layer['cost'];
        }
        if (Decimal::cmp($remaining, '0', 4) > 0) {
            $fallbackCost = $movement['source_unit_cost'] !== null
                ? Decimal::normalize($movement['source_unit_cost'], 6)
                : $fallbackCost;
            $value = Decimal::add($value, Decimal::round(Decimal::mul($remaining, $fallbackCost, 10), 2), 2);
        }
        $unitCost = Decimal::cmp($required, '0', 4) > 0 ? Decimal::div($value, $required, 6) : $fallbackCost;
        return [$value, $unitCost, $layers];
    }
}
