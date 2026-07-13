<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';
$pdo=MaisonBebe\Core\Database::connection();
$ids=$pdo->query("SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$service=new MaisonBebe\Services\StripeService();
$result=['synced'=>0,'errors'=>[]];
foreach($ids as $id){
    try{$service->syncProduct((int)$id);$result['synced']++;}
    catch(Throwable $e){$result['errors'][(string)$id]=mb_substr($e->getMessage(),0,180);}
}
$result['products']=$pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL")->fetchColumn();
$result['test_products']=$pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND stripe_test_product_id IS NOT NULL")->fetchColumn();
$result['test_prices']=$pdo->query("SELECT COUNT(*) FROM product_variants WHERE stripe_test_price_id IS NOT NULL")->fetchColumn();
$result['product_sync_errors']=$pdo->query("SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND stripe_test_sync_error IS NOT NULL")->fetchColumn();
$result['price_sync_errors']=$pdo->query("SELECT COUNT(*) FROM product_variants WHERE stripe_test_sync_error IS NOT NULL")->fetchColumn();
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);