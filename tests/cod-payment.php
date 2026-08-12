<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\OrderPaymentService;

$pdo = Database::connection();
$suffix = strtolower(substr(bin2hex(random_bytes(8)), 0, 12));
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare(
        "INSERT INTO orders (order_number,public_token,idempotency_key,email,phone,customer_type,customer_snapshot_json,subtotal_minor,grand_total_minor,payment_method,payment_status,shipping_method) "
        . "VALUES (?,?,?,?,?,'individual','{}',10000,10000,?,'unpaid','courier')"
    );
    $insert->execute(['TEST-COD-' . $suffix, hash('sha256', 'token-' . $suffix), hash('sha256', 'order-' . $suffix), 'test@example.test', '0700000000', 'cod']);
    $orderId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO payments (order_id,provider,amount_minor,currency,status,idempotency_key) VALUES (?,'cod',10000,'RON','unpaid',?)")
        ->execute([$orderId, hash('sha256', 'payment:' . $orderId . ':cod')]);

    $service = new OrderPaymentService();
    $result = $service->setCodReceived($orderId, true, null, $pdo);
    $assert($result['changed'] === true && $result['new_status'] === 'paid', 'Plata ramburs nu a fost marcată ca plătită.');
    $assert((string) $pdo->query('SELECT payment_status FROM orders WHERE id=' . $orderId)->fetchColumn() === 'paid', 'Comanda nu are starea plătită.');
    $assert((string) $pdo->query("SELECT status FROM payments WHERE order_id={$orderId} AND provider='cod'")->fetchColumn() === 'succeeded', 'Înregistrarea plății nu este reușită.');

    $service->setCodReceived($orderId, false, null, $pdo);
    $assert((string) $pdo->query('SELECT payment_status FROM orders WHERE id=' . $orderId)->fetchColumn() === 'unpaid', 'Debifarea nu readuce comanda la neplătită.');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM order_notes WHERE order_id=' . $orderId)->fetchColumn() === 2, 'Schimbările plății nu sunt auditate în notele comenzii.');

    $insert->execute(['TEST-CARD-' . $suffix, hash('sha256', 'token-card-' . $suffix), hash('sha256', 'order-card-' . $suffix), 'test@example.test', '0700000000', 'stripe']);
    $cardOrderId = (int) $pdo->lastInsertId();
    try {
        $service->setCodReceived($cardOrderId, true, null, $pdo);
        throw new RuntimeException('Plata cu cardul a putut fi modificată manual.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'Stripe'), 'Plata cu cardul nu a fost respinsă din motivul corect.');
    }

    $pdo->rollBack();
    echo "COD payment workflow: OK\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "COD payment workflow: FAIL - {$exception->getMessage()}\n");
    exit(1);
}
