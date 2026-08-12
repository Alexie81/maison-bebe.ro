<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\AccountingStockPostingService;
use MaisonBebe\Services\AccountingStockProjectionService;

$pdo=Database::connection();
if(in_array('--post-issued-invoices',$argv,true)){
    $ids=$pdo->query("SELECT id FROM invoices WHERE status='issued' AND accounting_posted_at IS NULL ORDER BY issue_date,id")->fetchAll(PDO::FETCH_COLUMN);
    $posted=0;$failed=0;
    foreach($ids as $id){
        $pdo->beginTransaction();
        try{(new AccountingStockPostingService())->postSalesInvoiceOutflow((int)$id,'historical-sales-invoice:'.$id,$pdo);$pdo->commit();$posted++;echo "[OK] Factura {$id}\n";}
        catch(Throwable $exception){if($pdo->inTransaction())$pdo->rollBack();$failed++;fwrite(STDERR,"[FAIL] Factura {$id}: {$exception->getMessage()}\n");}
    }
    echo "Facturi procesate: {$posted}; erori: {$failed}.\n";exit($failed?1:0);
}
$run=(new AccountingStockProjectionService())->rebuildAll('Reconstrucție solicitată din CLI');
echo $run?"Proiecția Stocuri Conta a fost reconstruită în rularea {$run}.\n":"Nu există mișcări de reconstruit.\n";
