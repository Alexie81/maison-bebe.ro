<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use RuntimeException;

final class ShippingService
{
    public function create(int $orderId, int $providerId, int $weightGrams, int $parcels): int
    {
        $pdo = Database::connection();
        $existing = $pdo->prepare("SELECT id FROM shipments WHERE order_id=? AND status NOT IN ('cancelled','failed') ORDER BY id DESC LIMIT 1");
        $existing->execute([$orderId]);
        if ($id = (int) $existing->fetchColumn()) {
            return $id;
        }
        $order = $pdo->prepare('SELECT id,order_number,payment_method,grand_total_minor FROM orders WHERE id=?');
        $order->execute([$orderId]);
        $order = $order->fetch();
        $provider = $pdo->prepare('SELECT * FROM shipping_providers WHERE id=? AND is_enabled=1');
        $provider->execute([$providerId]);
        $provider = $provider->fetch();
        if (!$order || !$provider) {
            throw new RuntimeException('Comanda sau curierul activ nu a fost găsit.');
        }
        $service = $pdo->prepare('SELECT id FROM shipping_services WHERE provider_id=? AND is_enabled=1 ORDER BY id LIMIT 1');
        $service->execute([$providerId]);
        $serviceId = $service->fetchColumn() ?: null;
        $cod = $order['payment_method'] === 'cod' ? (int) $order['grand_total_minor'] : 0;
        if ($provider['code'] === 'manual') {
            $awb = 'MB-' . date('ymd') . '-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
            $statement = $pdo->prepare("INSERT INTO shipments (order_id,provider_id,service_id,awb,tracking_url,status,weight_grams,parcels,cod_amount_minor,provider_payload_json) VALUES (?,?,?,?,?,'ready',?,?,?,?)");
            $statement->execute([$orderId, $providerId, $serviceId, $awb, absolute_url('/urmarire-comanda'), max(1, $weightGrams), max(1, $parcels), $cod, json_encode(['mode' => 'manual'])]);
            $shipmentId = (int) $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO shipment_events (shipment_id,status,public_label,occurred_at,payload_json) VALUES (?,'ready','Expediția este pregătită',NOW(),?)")->execute([$shipmentId, json_encode(['awb' => $awb])]);
            return $shipmentId;
        }
        $pdo->prepare("INSERT INTO shipments (order_id,provider_id,service_id,status,weight_grams,parcels,cod_amount_minor,provider_payload_json) VALUES (?,?,?,'draft',?,?,?,?)")->execute([$orderId, $providerId, $serviceId, max(1, $weightGrams), max(1, $parcels), $cod, json_encode(['provider' => $provider['code']])]);
        $shipmentId = (int) $pdo->lastInsertId();
        $key = hash('sha256', 'awb:' . $orderId . ':' . $providerId);
        $payload = json_encode(['order_id' => $orderId, 'shipment_id' => $shipmentId, 'provider_id' => $providerId], JSON_THROW_ON_ERROR);
        $pdo->prepare("INSERT INTO awb_jobs (order_id,shipment_id,idempotency_key,status,payload_json,available_at) VALUES (?,?,?,'pending',?,NOW()) ON DUPLICATE KEY UPDATE shipment_id=VALUES(shipment_id)")->execute([$orderId, $shipmentId, $key, $payload]);
        return $shipmentId;
    }
}
