<?php

declare(strict_types=1);

namespace MaisonBebe\Services;

use MaisonBebe\Core\Database;
use RuntimeException;
use Throwable;

final class GoogleAnalyticsMeasurementService
{
    public function queueRefund(int $orderId): array
    {
        $pdo=Database::connection();
        $orderStatement=$pdo->prepare('SELECT * FROM orders WHERE id=? LIMIT 1');
        $orderStatement->execute([$orderId]);
        $order=$orderStatement->fetch();
        if(!$order)throw new RuntimeException('Comanda pentru rambursarea Analytics nu există.');
        $itemsStatement=$pdo->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
        $itemsStatement->execute([$orderId]);
        $payload=(new GoogleAnalyticsService())->refund($order,$itemsStatement->fetchAll());
        if($payload===null)return ['status'=>'skipped','reason'=>'Comanda nu este eligibilă pentru refund GA4.'];
        $eventKey='refund:'.(string)$order['order_number'];
        $pdo->prepare("INSERT INTO analytics_server_events (event_key,event_name,order_id,payload_json,status) VALUES (?,'refund',?,?,'pending') ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json),status=IF(status='sent','sent','pending'),last_error=NULL")
            ->execute([$eventKey,$orderId,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        return $this->send($eventKey);
    }

    public function send(string $eventKey): array
    {
        $pdo=Database::connection();
        $statement=$pdo->prepare('SELECT ase.*,o.ga_client_id,o.ga_session_id FROM analytics_server_events ase LEFT JOIN orders o ON o.id=ase.order_id WHERE ase.event_key=? LIMIT 1');
        $statement->execute([$eventKey]);
        $event=$statement->fetch();
        if(!$event)throw new RuntimeException('Evenimentul Analytics nu există.');
        if((string)$event['status']==='sent')return ['status'=>'sent','duplicate'=>true];
        $measurementId=trim((string)env('GOOGLE_ANALYTICS_MEASUREMENT_ID','G-8302PGSE85'));
        $apiSecret=trim((string)env('GOOGLE_ANALYTICS_API_SECRET',''));
        $clientId=trim((string)($event['ga_client_id']??''));
        if($apiSecret===''||$clientId===''){
            $reason=$apiSecret===''?'Lipsește GOOGLE_ANALYTICS_API_SECRET.':'Comanda nu are client_id GA4.';
            $pdo->prepare("UPDATE analytics_server_events SET status='skipped',attempts=attempts+1,last_error=? WHERE id=?")->execute([$reason,$event['id']]);
            return ['status'=>'skipped','reason'=>$reason];
        }
        $params=json_decode((string)$event['payload_json'],true);
        if(!is_array($params))throw new RuntimeException('Payloadul Analytics este invalid.');
        if(!empty($event['ga_session_id'])){$params['session_id']=(int)$event['ga_session_id'];$params['engagement_time_msec']=1;}
        $body=['client_id'=>$clientId,'events'=>[['name'=>(string)$event['event_name'],'params'=>$params]]];
        $url='https://www.google-analytics.com/mp/collect?measurement_id='.rawurlencode($measurementId).'&api_secret='.rawurlencode($apiSecret);
        try{
            $curl=curl_init($url);
            if($curl===false)throw new RuntimeException('Nu s-a putut inițializa conexiunea Analytics.');
            curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Content-Type: application/json']]);
            $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
            if($response===false||$status<200||$status>=300)throw new RuntimeException('Google Analytics HTTP '.$status.($error!==''?': '.$error:''));
            $pdo->prepare("UPDATE analytics_server_events SET status='sent',attempts=attempts+1,last_error=NULL,sent_at=NOW() WHERE id=?")->execute([$event['id']]);
            return ['status'=>'sent'];
        }catch(Throwable $exception){
            $pdo->prepare("UPDATE analytics_server_events SET status='failed',attempts=attempts+1,last_error=? WHERE id=?")->execute([mb_substr($exception->getMessage(),0,1000),$event['id']]);
            throw $exception;
        }
    }
}
