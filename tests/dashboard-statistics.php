<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\DashboardStatisticsService;

$pdo = Database::connection();
$suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$start = new DateTimeImmutable('2037-02-10');
$end = new DateTimeImmutable('2037-02-16');

$pdo->beginTransaction();
try {
    $baseline = (new DashboardStatisticsService())->forPeriod($pdo, $start, $end);

    $pdo->prepare(
        "INSERT INTO users (email,first_name,last_name,status,created_at) "
        . "VALUES (?,'Client','Test','active','2037-02-10 10:00:00')"
    )->execute(['dashboard-' . $suffix . '@example.test']);
    $userId = (int) $pdo->lastInsertId();
    $customerRoleId = (int) $pdo->query("SELECT id FROM roles WHERE name='customer'")->fetchColumn();
    $pdo->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$userId, $customerRoleId]);

    $pdo->prepare(
        "INSERT INTO products (name,slug,sku,status,published_at) VALUES (?,?,?,'active',NOW())"
    )->execute([
        'Dashboard stock ' . $suffix,
        'dashboard-stock-' . $suffix,
        'DASH-P-' . $suffix,
    ]);
    $productId = (int) $pdo->lastInsertId();
    $variantInsert = $pdo->prepare(
        'INSERT INTO product_variants '
        . '(product_id,sku,price_minor,stock_qty,track_inventory,low_stock_threshold,is_active) '
        . 'VALUES (?,?,1000,?,?,3,1)'
    );
    $variantInsert->execute([$productId, 'DASH-V-ZERO-' . $suffix, 0, 1]);
    $variantInsert->execute([$productId, 'DASH-V-LOW-' . $suffix, 2, 1]);
    $variantInsert->execute([$productId, 'DASH-V-UNLIMITED-' . $suffix, 0, 0]);

    $orderInsert = $pdo->prepare(
        'INSERT INTO orders '
        . '(order_number,public_token,idempotency_key,email,phone,customer_type,customer_snapshot_json,'
        . 'subtotal_minor,grand_total_minor,order_status,payment_status,payment_method,shipping_method,created_at) '
        . "VALUES (?,?,?,?,?,'individual','{}',?,?,?,?,?,'courier',?)"
    );
    $insertOrder = static function (
        string $label,
        int $total,
        string $orderStatus,
        string $paymentStatus,
        string $createdAt
    ) use ($orderInsert, $suffix, $pdo): int {
        $identity = $label . '-' . $suffix;
        $orderInsert->execute([
            'DASH-' . strtoupper($identity),
            hash('sha256', 'public-' . $identity),
            hash('sha256', 'idem-' . $identity),
            'dashboard-' . $suffix . '@example.test',
            '0700000000',
            $total,
            $total,
            $orderStatus,
            $paymentStatus,
            'cod',
            $createdAt,
        ]);
        return (int) $pdo->lastInsertId();
    };

    $insertOrder('paid', 10000, 'delivered', 'paid', '2037-02-10 12:00:00');
    $insertOrder('unpaid', 20000, 'new', 'unpaid', '2037-02-11 12:00:00');
    $insertOrder('refunded', 59200, 'refunded', 'paid', '2037-02-12 12:00:00');
    $partialOrderId = $insertOrder(
        'partial',
        50000,
        'partially_refunded',
        'partially_refunded',
        '2037-02-13 12:00:00'
    );
    $pdo->prepare(
        "INSERT INTO payments (order_id,provider,amount_minor,currency,status,idempotency_key) "
        . "VALUES (?,'cod',50000,'RON','succeeded',?)"
    )->execute([$partialOrderId, hash('sha256', 'dashboard-payment-' . $suffix)]);
    $paymentId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "INSERT INTO refunds (payment_id,amount_minor,status,idempotency_key) VALUES (?,15000,'succeeded',?)"
    )->execute([$paymentId, hash('sha256', 'dashboard-refund-' . $suffix)]);

    $statistics = (new DashboardStatisticsService())->forPeriod($pdo, $start, $end);
    $kpis = $statistics['kpis'];

    $assert($kpis['sales'] - $baseline['kpis']['sales'] === 45000, 'Vânzările nete nu exclud rambursarea integrală sau nu scad rambursarea parțială.');
    $assert($kpis['orders'] - $baseline['kpis']['orders'] === 3, 'Numărul comenzilor valide include o comandă rambursată integral.');
    $assert($kpis['customers'] - $baseline['kpis']['customers'] === 1, 'Conturile noi nu sunt limitate la rolul de client.');
    $assert($kpis['low_stock'] - $baseline['kpis']['low_stock'] === 2, 'Stocul redus nu include stocul zero sau include stocul nelimitat.');

    $chart = $statistics['chart'];
    $assert(count($chart) === 7, 'Graficul nu conține toate cele șapte zile ale intervalului.');
    $totals = array_column($chart, 'total', 'bucket');
    $baselineTotals = array_column($baseline['chart'], 'total', 'bucket');
    $assert(($totals['2037-02-10'] - ($baselineTotals['2037-02-10'] ?? 0)) === 10000, 'Graficul nu include plata încasată.');
    $assert(($totals['2037-02-11'] - ($baselineTotals['2037-02-11'] ?? 0)) === 0, 'Graficul include comanda neplătită.');
    $assert(($totals['2037-02-12'] - ($baselineTotals['2037-02-12'] ?? 0)) === 0, 'Graficul include comanda rambursată integral.');
    $assert(($totals['2037-02-13'] - ($baselineTotals['2037-02-13'] ?? 0)) === 35000, 'Graficul nu afișează corect rambursarea parțială.');

    $pdo->rollBack();
    echo "Dashboard statistics regression test: OK\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Dashboard statistics regression test: FAIL - {$exception->getMessage()}\n");
    exit(1);
}
