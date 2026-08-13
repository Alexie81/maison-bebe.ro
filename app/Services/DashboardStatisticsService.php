<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use DateTimeImmutable;
use PDO;

final class DashboardStatisticsService
{
    /**
     * @return array{
     *     kpis: array{sales:int,orders:int,customers:int,low_stock:int},
     *     chart: list<array{bucket:string,total:int,label:string}>
     * }
     */
    public function forPeriod(PDO $pdo, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $startSql = $start->format('Y-m-d');
        $endSql = $end->format('Y-m-d');
        $orderRange = 'o.created_at >= ? AND o.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
        $netSales = $this->netSalesExpression();
        $refunds = $this->refundTotalsJoin();

        $salesStatement = $pdo->prepare(
            "SELECT COALESCE(SUM({$netSales}),0) "
            . "FROM orders o {$refunds} WHERE {$orderRange}"
        );
        $salesStatement->execute([$startSql, $endSql]);

        $ordersStatement = $pdo->prepare(
            "SELECT COUNT(*) FROM orders o WHERE {$orderRange} "
            . "AND o.order_status NOT IN ('cancelled','returned','refunded')"
        );
        $ordersStatement->execute([$startSql, $endSql]);

        $customersStatement = $pdo->prepare(
            'SELECT COUNT(DISTINCT u.id) FROM users u '
            . 'JOIN user_roles ur ON ur.user_id=u.id '
            . 'JOIN roles r ON r.id=ur.role_id AND r.name=\'customer\' '
            . 'WHERE u.deleted_at IS NULL '
            . 'AND u.created_at >= ? AND u.created_at < DATE_ADD(?, INTERVAL 1 DAY)'
        );
        $customersStatement->execute([$startSql, $endSql]);

        $lowStock = (int) $pdo->query(
            "SELECT COUNT(*) FROM product_variants v "
            . "JOIN products p ON p.id=v.product_id "
            . "WHERE v.is_active=1 AND v.track_inventory=1 "
            . "AND v.stock_qty<=v.low_stock_threshold "
            . "AND p.status='active' AND p.deleted_at IS NULL"
        )->fetchColumn();

        $days = max(1, (int) $start->diff($end)->days + 1);
        if ($days <= 93) {
            $bucketExpression = 'DATE(o.created_at)';
            $chartMode = 'day';
        } elseif ($days <= 550) {
            $bucketExpression = 'DATE_SUB(DATE(o.created_at), INTERVAL WEEKDAY(o.created_at) DAY)';
            $chartMode = 'week';
        } else {
            $bucketExpression = "DATE_FORMAT(o.created_at,'%Y-%m-01')";
            $chartMode = 'month';
        }

        $chartStatement = $pdo->prepare(
            "SELECT {$bucketExpression} bucket,COALESCE(SUM({$netSales}),0) total "
            . "FROM orders o {$refunds} WHERE {$orderRange} "
            . 'GROUP BY bucket ORDER BY bucket'
        );
        $chartStatement->execute([$startSql, $endSql]);
        $chart = $this->completeChart(
            $chartStatement->fetchAll(),
            $start,
            $end,
            $chartMode
        );

        return [
            'kpis' => [
                'sales' => (int) $salesStatement->fetchColumn(),
                'orders' => (int) $ordersStatement->fetchColumn(),
                'customers' => (int) $customersStatement->fetchColumn(),
                'low_stock' => $lowStock,
            ],
            'chart' => $chart,
        ];
    }

    private function netSalesExpression(): string
    {
        return "CASE "
            . "WHEN o.order_status IN ('cancelled','returned','refunded') THEN 0 "
            . "WHEN o.payment_status NOT IN ('paid','partially_refunded') THEN 0 "
            . 'ELSE GREATEST(o.grand_total_minor-COALESCE(refund_totals.amount_minor,0),0) END';
    }

    private function refundTotalsJoin(): string
    {
        return "LEFT JOIN ("
            . "SELECT p.order_id,SUM(r.amount_minor) amount_minor "
            . "FROM payments p JOIN refunds r ON r.payment_id=p.id "
            . "WHERE LOWER(r.status) IN ('succeeded','successful','success','completed','processed','refunded') "
            . 'GROUP BY p.order_id'
            . ') refund_totals ON refund_totals.order_id=o.id';
    }

    /**
     * @param array<int,array{bucket:string,total:int|string}> $rows
     * @return list<array{bucket:string,total:int,label:string}>
     */
    private function completeChart(
        array $rows,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $mode
    ): array {
        $totals = [];
        foreach ($rows as $row) {
            $totals[(string) $row['bucket']] = (int) $row['total'];
        }

        $cursor = match ($mode) {
            'week' => $start->modify('monday this week'),
            'month' => $start->modify('first day of this month'),
            default => $start,
        };
        $last = match ($mode) {
            'week' => $end->modify('monday this week'),
            'month' => $end->modify('first day of this month'),
            default => $end,
        };
        $step = match ($mode) {
            'week' => '+1 week',
            'month' => '+1 month',
            default => '+1 day',
        };

        $chart = [];
        while ($cursor <= $last) {
            $bucket = $cursor->format('Y-m-d');
            $chart[] = [
                'bucket' => $bucket,
                'total' => $totals[$bucket] ?? 0,
                'label' => match ($mode) {
                    'week' => 'Săpt. ' . $cursor->format('d.m'),
                    'month' => $cursor->format('m.Y'),
                    default => $cursor->format('d.m'),
                },
            ];
            $cursor = $cursor->modify($step);
        }

        return $chart;
    }
}
