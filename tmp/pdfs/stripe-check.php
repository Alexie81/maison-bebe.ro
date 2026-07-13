<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
$service=new MaisonBebe\Services\StripeService();
try {
    $d=$service->diagnostics();
    unset($d['account_id']);
    echo json_encode($d,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch(Throwable $e) {
    echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);
}