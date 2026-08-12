<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

final class OrderPaymentService
{
    /**
     * Marchează exclusiv o plată ramburs ca încasată sau neîncasată.
     * Plățile cu cardul rămân controlate de confirmările Stripe.
     *
     * @return array{changed:bool,old_status:string,new_status:string}
     */
    public function setCodReceived(int $orderId, bool $received, ?int $userId = null, ?PDO $pdo = null): array
    {
        $pdo ??= Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $statement = $pdo->prepare(
                'SELECT id,payment_method,payment_status,grand_total_minor,currency '
                . 'FROM orders WHERE id=? FOR UPDATE'
            );
            $statement->execute([$orderId]);
            $order = $statement->fetch();
            if (!$order) {
                throw new RuntimeException('Comanda nu a fost găsită.');
            }
            if ((string) $order['payment_method'] !== 'cod') {
                throw new RuntimeException('Doar plata ramburs poate fi confirmată manual. Plata cu cardul este confirmată automat de Stripe.');
            }
            if (in_array((string) $order['payment_status'], ['refunded', 'partially_refunded'], true)) {
                throw new RuntimeException('O plată rambursată nu poate fi modificată din acest control.');
            }

            $oldStatus = (string) $order['payment_status'];
            $newStatus = $received ? 'paid' : 'unpaid';
            $changed = $oldStatus !== $newStatus;

            $payment = $pdo->prepare(
                "SELECT id,status FROM payments WHERE order_id=? AND provider='cod' ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
            $payment->execute([$orderId]);
            $paymentRow = $payment->fetch();
            $paymentStatus = $received ? 'succeeded' : 'unpaid';

            if ($paymentRow) {
                if ((string) $paymentRow['status'] !== $paymentStatus) {
                    $pdo->prepare('UPDATE payments SET status=?,updated_at=NOW() WHERE id=?')
                        ->execute([$paymentStatus, (int) $paymentRow['id']]);
                }
            } else {
                $pdo->prepare(
                    "INSERT INTO payments (order_id,provider,amount_minor,currency,status,idempotency_key) VALUES (?,'cod',?,?,?,?)"
                )->execute([
                    $orderId,
                    (int) $order['grand_total_minor'],
                    (string) $order['currency'],
                    $paymentStatus,
                    hash('sha256', 'payment:' . $orderId . ':cod'),
                ]);
            }

            if ($changed) {
                $pdo->prepare('UPDATE orders SET payment_status=?,updated_at=NOW() WHERE id=?')
                    ->execute([$newStatus, $orderId]);
                $note = $received
                    ? 'Plata ramburs a fost marcată ca încasată.'
                    : 'Plata ramburs a fost marcată ca neîncasată.';
                $pdo->prepare('INSERT INTO order_notes (order_id,user_id,note,is_customer_visible) VALUES (?,?,?,0)')
                    ->execute([$orderId, $userId, $note]);
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return ['changed' => $changed, 'old_status' => $oldStatus, 'new_status' => $newStatus];
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}
