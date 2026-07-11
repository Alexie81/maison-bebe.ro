<?php
declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use PDO;

final class NotificationService
{
    public function newOrder(PDO $pdo, array $order): void
    {
        $eventKey = 'order.created:' . $order['id'];
        $statement = $pdo->prepare("INSERT IGNORE INTO notifications (target_role,event_key,type,title,body,url,entity_type,entity_id) VALUES ('order_operator',?,'new_order',?,?,?,'order',?)");
        $statement->execute([$eventKey, 'Comandă nouă ' . $order['order_number'], $order['email'] . ' · ' . money($order['grand_total_minor']), '/admin/comenzi/' . $order['id'], $order['id']]);

        $payload = json_encode(['order_id'=>$order['id'],'order_number'=>$order['order_number'],'email'=>$order['email'],'first_name'=>$order['first_name']??'','last_name'=>$order['last_name']??'','total_minor'=>$order['grand_total_minor']], JSON_UNESCAPED_UNICODE);
        $recipients = $pdo->query('SELECT email FROM order_email_recipients WHERE is_active=1 AND receive_new_orders=1')->fetchAll(PDO::FETCH_COLUMN);
        if (!$recipients) {
            $fallback = array_filter(array_map('trim', explode(',', (string) env('ADMIN_ORDER_EMAILS', ''))));
            $recipients = $fallback;
        }
        $emailStatement = $pdo->prepare("INSERT INTO email_queue (template_key,recipient,subject,payload_json,status,next_attempt_at,correlation_id) VALUES ('new_order_admin',?,?,?,'pending',NOW(),?)");
        foreach ($recipients as $recipient) {
            $emailStatement->execute([$recipient, 'Comandă nouă ' . $order['order_number'] . ' - ' . money($order['grand_total_minor']), $payload, $eventKey]);
        }
        $customerSubject=trim((string)($order['first_name']??''));$customerSubject=($customerSubject!==''?$customerSubject.', îți mulțumim pentru comanda ':'Îți mulțumim pentru comanda ').$order['order_number'];$pdo->prepare("INSERT INTO email_queue (template_key,recipient,subject,payload_json,status,next_attempt_at,correlation_id) VALUES ('order_confirmation_customer',?,?,?,'pending',NOW(),?)")->execute([$order['email'], $customerSubject, $payload, $eventKey . ':customer']);
    }
}

