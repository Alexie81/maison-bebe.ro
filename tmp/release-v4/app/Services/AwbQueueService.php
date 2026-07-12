<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use MaisonBebe\Core\Encryptor;
use RuntimeException;
use Throwable;

final class AwbQueueService
{
    public function process(int $limit = 10): array
    {
        $pdo = Database::connection();
        $metrics = ['completed' => 0, 'retry' => 0, 'attention' => 0];
        for ($i = 0; $i < $limit; $i++) {
            $pdo->beginTransaction();
            $job = $pdo->query("SELECT * FROM awb_jobs WHERE status IN ('pending','retry') AND available_at<=NOW() ORDER BY id LIMIT 1 FOR UPDATE")->fetch();
            if (!$job) {
                $pdo->commit();
                break;
            }
            $pdo->prepare("UPDATE awb_jobs SET status='processing',attempts=attempts+1 WHERE id=?")->execute([$job['id']]);
            $pdo->commit();
            try {
                $data = $this->payload((int) $job['shipment_id']);
                $response = $this->request($data, (string) $job['idempotency_key']);
                if (empty($response['awb'])) {
                    throw new RuntimeException('Curierul nu a returnat un număr AWB.');
                }
                $pdo->prepare("UPDATE shipments SET awb=?,tracking_url=?,status='ready',provider_payload_json=? WHERE id=?")->execute([(string) $response['awb'], $response['tracking_url'] ?? null, json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $job['shipment_id']]);
                $pdo->prepare("INSERT INTO shipment_events (shipment_id,provider_event_id,status,public_label,occurred_at,payload_json) VALUES (?,?,'ready','Expediția este pregătită',NOW(),?)")->execute([$job['shipment_id'], $response['event_id'] ?? null, json_encode($response)]);
                $pdo->prepare("UPDATE awb_jobs SET status='completed',last_error=NULL WHERE id=?")->execute([$job['id']]);
                $metrics['completed']++;
            } catch (Throwable $exception) {
                $attempts = (int) $job['attempts'] + 1;
                $missing = str_contains($exception->getMessage(), 'configurat') || str_contains($exception->getMessage(), 'credențiale');
                $status = ($attempts >= 5 || $missing) ? 'requires_attention' : 'retry';
                $pdo->prepare('UPDATE awb_jobs SET status=?,available_at=DATE_ADD(NOW(),INTERVAL ? SECOND),last_error=? WHERE id=?')->execute([$status, min(3600, 60 * (2 ** max(0, $attempts - 1))), mb_substr($exception->getMessage(), 0, 1000), $job['id']]);
                $pdo->prepare("UPDATE shipments SET status=? WHERE id=?")->execute([$status, $job['shipment_id']]);
                $metrics[$status === 'retry' ? 'retry' : 'attention']++;
            }
        }
        return $metrics;
    }

    private function payload(int $shipmentId): array
    {
        $pdo = Database::connection();
        $statement = $pdo->prepare("SELECT s.*,p.code,p.config_json,o.order_number,o.email,o.phone,o.customer_snapshot_json,a.snapshot_json address_json,c.encrypted_payload FROM shipments s JOIN shipping_providers p ON p.id=s.provider_id JOIN orders o ON o.id=s.order_id LEFT JOIN order_addresses a ON a.order_id=o.id AND a.type='shipping' LEFT JOIN shipping_provider_credentials c ON c.provider_id=p.id WHERE s.id=? LIMIT 1");
        $statement->execute([$shipmentId]);
        $row = $statement->fetch();
        if (!$row) {
            throw new RuntimeException('Expediția nu a fost găsită.');
        }
        $config = json_decode((string) $row['config_json'], true) ?: [];
        if (empty($config['api_url'])) {
            throw new RuntimeException('API-ul curierului nu este configurat.');
        }
        $credentials = !empty($row['encrypted_payload']) ? json_decode(Encryptor::decrypt((string) $row['encrypted_payload']), true) : [];
        if (!$credentials) {
            throw new RuntimeException('Lipsesc credențialele curierului.');
        }
        return ['api_url' => $config['api_url'], 'credentials' => $credentials, 'shipment' => ['order_number' => $row['order_number'], 'recipient' => json_decode((string) $row['customer_snapshot_json'], true) ?: [], 'address' => json_decode((string) $row['address_json'], true) ?: [], 'email' => $row['email'], 'phone' => $row['phone'], 'weight_grams' => (int) $row['weight_grams'], 'parcels' => (int) $row['parcels'], 'cod_amount_minor' => (int) $row['cod_amount_minor'], 'currency' => 'RON']];
    }

    private function request(array $data, string $idempotency): array
    {
        $ch = curl_init((string) $data['api_url']);
        $credentials = $data['credentials'];
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Idempotency-Key: ' . $idempotency];
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($data['shipment'], JSON_THROW_ON_ERROR), CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25, CURLOPT_HTTPHEADER => $headers]);
        if (!empty($credentials['username'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $credentials['username'] . ':' . ($credentials['password'] ?? ''));
        }
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $decoded = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new RuntimeException('Curier API ' . $status . ': ' . ($error ?: 'răspuns invalid'));
        }
        return $decoded;
    }
}
