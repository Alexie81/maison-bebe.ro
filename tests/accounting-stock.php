<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MaisonBebe\Core\Database;
use MaisonBebe\Services\AccountingStockReportService;
use MaisonBebe\Services\AccountingStockPostingService;
use MaisonBebe\Services\NirPdfService;
use MaisonBebe\Services\NirService;
use MaisonBebe\Services\AccountingPeriodService;
use MaisonBebe\Services\ProductMappingService;
use MaisonBebe\Services\XlsxService;

$pdo=Database::connection();
$suffix=strtoupper(substr(bin2hex(random_bytes(7)),0,12));
$productId=$variantId=$nirId=$reversalId=$invoiceId=$supplierId=$orderId=$orderItemId=$salesInvoiceId=$stornoInvoiceId=$periodId=0;$artifactPaths=[];$runIds=[];
$assert=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);};
try{
    $pdo->prepare("INSERT INTO products (name,slug,sku,status) VALUES (?,?,?,'active')")->execute(['Produs test contabil '.$suffix,'test-contabil-'.strtolower($suffix),'ACC-P-'.$suffix]);
    $productId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO product_variants (product_id,sku,ean,price_minor,cost_minor,stock_qty,track_inventory,track_accounting_stock,is_active) VALUES (?,?,?,?,?,37,0,1,1)')->execute([$productId,'ACC-V-'.$suffix,'594'.substr(preg_replace('/\D/','',(string)hexdec(substr($suffix,0,7))),0,10),2500,800]);
    $variantId=(int)$pdo->lastInsertId();
    $warehouseId=(int)$pdo->query('SELECT id FROM accounting_warehouses WHERE is_default=1 ORDER BY id LIMIT 1')->fetchColumn();
    $companyId=(int)$pdo->query('SELECT id FROM company_profiles ORDER BY id LIMIT 1')->fetchColumn();
    $pdo->prepare("INSERT INTO orders (order_number,public_token,idempotency_key,email,phone,customer_snapshot_json,subtotal_minor,grand_total_minor,payment_method,shipping_method) VALUES (?,?,?,?,?,'{}',5000,5000,'bank','test')")
        ->execute(['QA-ACC-'.$suffix,hash('sha256','public-'.$suffix),hash('sha256','idem-'.$suffix),'qa-'.$suffix.'@example.test','0700000000']);
    $orderId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO order_items (order_id,product_id,variant_id,name_snapshot,sku_snapshot,unit_price_minor,quantity,total_minor,customization_json) VALUES (?,?,?,?,?,2500,2,5000,'{}')")
        ->execute([$orderId,$productId,$variantId,'Produs test contabil','ACC-V-'.$suffix]);
    $orderItemId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO invoices (order_id,company_profile_id,document_type,customer_type,number,status,currency,issue_date,issuer_snapshot_json,customer_snapshot_json,subtotal_minor,grand_total_minor) VALUES (?,?,'invoice','individual',?,'draft','RON','2001-02-02','{}','{\"first_name\":\"Client\",\"last_name\":\"Test\"}',5000,5000)")
        ->execute([$orderId,$companyId,'QA-OUT-'.$suffix]);
    $salesInvoiceId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO invoice_items (invoice_id,order_item_id,name,sku,quantity,unit_price_minor,total_minor,sort_order) VALUES (?,?,?,?,2,2500,5000,0)')->execute([$salesInvoiceId,$orderItemId,'Produs test contabil','ACC-V-'.$suffix]);
    $pdo->prepare("INSERT INTO invoice_items (invoice_id,order_item_id,name,sku,quantity,unit_price_minor,total_minor,sort_order) VALUES (?,NULL,'Serviciu test','SERVICIU',1,100,100,1)")->execute([$salesInvoiceId]);
    $posting=new AccountingStockPostingService();$posting->postSalesInvoiceOutflow($salesInvoiceId,'qa-draft-'.$suffix);
    $count=$pdo->prepare('SELECT COUNT(*) FROM accounting_stock_movements WHERE source_document_type=\'SALES_INVOICE\' AND source_document_id=?');$count->execute([$salesInvoiceId]);$assert((int)$count->fetchColumn()===0,'Factura în ciornă a creat o ieșire contabilă.');
    $pdo->prepare("UPDATE invoices SET status='issued',issued_at=NOW() WHERE id=?")->execute([$salesInvoiceId]);$posting->postSalesInvoiceOutflow($salesInvoiceId,'qa-issued-'.$suffix);$posting->postSalesInvoiceOutflow($salesInvoiceId,'qa-issued-repeat-'.$suffix);
    $balance=$pdo->prepare('SELECT * FROM accounting_stock_balances WHERE product_variant_id=? AND warehouse_id=?');$balance->execute([$variantId,$warehouseId]);$negative=$balance->fetch();
    $assert($negative && abs((float)$negative['current_quantity']+2.0)<0.0001,'Factura finală nu a creat ieșirea contabilă -2.');
    $commercial=$pdo->prepare('SELECT stock_qty,track_inventory FROM product_variants WHERE id=?');$commercial->execute([$variantId]);$commercial=$commercial->fetch();$assert((int)$commercial['stock_qty']===37 && (int)$commercial['track_inventory']===0,'Factura a modificat stocul comercial sau modul nelimitat.');
    $header=['supplier_name'=>'Furnizor test '.$suffix,'supplier_tax_id'=>'RO'.$suffix,'supplier_address'=>'Strada Test 1','invoice_series'=>'','invoice_number'=>$suffix,'invoice_date'=>'2001-02-01','receipt_date'=>'2001-02-03','invoice_received_date'=>'2001-02-05','due_date'=>'2001-03-01','warehouse_id'=>$warehouseId,'currency'=>'EUR','exchange_rate'=>'5.000000','exchange_rate_date'=>'2001-02-01','exchange_rate_source'=>'Test BNR','is_late_entered'=>'1','late_entry_reason'=>'Test automat document introdus ulterior','notes'=>'Test automat NIR'];
    $service=new NirService();$nirId=$service->createDraft($header);$document=$service->document($nirId);$invoiceId=(int)$document['supplier_invoice_id'];$supplierId=(int)$document['supplier_id'];
    $balance->execute([$variantId,$warehouseId]);$assert(abs((float)$balance->fetch()['current_quantity']+2.0)<0.0001,'Ciorna NIR a modificat soldul contabil.');
    $automatic=(new ProductMappingService())->automatic($supplierId,'ACC-V-'.$suffix,null,null);$assert((int)($automatic['product_variant_id']??0)===$variantId,'Asocierea automată după SKU exact a eșuat.');
    $input=$header+['row_version'=>1,'line_name'=>['Produs furnizor test'],'line_supplier_code'=>[''],'line_imported_sku'=>['ACC-V-'.$suffix],'line_imported_ean'=>[''],'line_type'=>['stockable'],'line_variant_id'=>[$variantId],'line_unit'=>['buc'],'line_invoiced_quantity'=>['10'],'line_received_quantity'=>['10'],'line_accepted_quantity'=>['10'],'line_damaged_quantity'=>['0'],'line_unit_price'=>['12.50'],'line_discount'=>['0'],'line_allocated_cost'=>['0'],'line_vat_rate'=>['19'],'line_value_without_vat'=>['125.00'],'line_vat_value'=>['23.75'],'line_total'=>['148.75'],'line_difference_type'=>['none'],'line_difference_reason'=>[''],'line_observations'=>[''],'line_ignore_reason'=>[''],'line_original_json'=>[''],'line_remember_mapping'=>[0=>'1']];
    $service->updateDraft($nirId,$input);
    $automaticByName=(new ProductMappingService())->automatic($supplierId,null,null,null,$pdo,'Produs furnizor test');
    $assert((int)($automaticByName['product_variant_id']??0)===$variantId && ($automaticByName['association_source']??'')==='supplier_name','Asocierea memorată fără cod furnizor nu a fost reutilizată după denumire.');
    $candidateRows=(new ProductMappingService())->candidates('ACC-V-'.$suffix);$candidate=$candidateRows[0]??[];
    $rememberedNames=array_column((array)($candidate['supplier_mappings']??[]),'supplier_product_name');
    $assert(in_array('Produs furnizor test',$rememberedNames,true),'Denumirea memorată nu este disponibilă pentru autocompletarea din selector.');
    $balance->execute([$variantId,$warehouseId]);$assert(abs((float)$balance->fetch()['current_quantity']+2.0)<0.0001,'Salvarea recepției în ciornă a modificat soldul contabil.');$confirmed=$service->confirm($nirId,['row_version'=>2]);
    $assert(in_array($confirmed['status'],['Confirmed','PartiallyReceived'],true),'NIR-ul nu a fost confirmat.');
    $assert(str_starts_with((string)$confirmed['formatted_number'],'NIR-MB-2001-'),'Numărul final NIR nu respectă seria/anul.');
    $pdo->prepare('DELETE FROM product_supplier_mappings WHERE supplier_id=? AND product_variant_id=?')->execute([$supplierId,$variantId]);
    $historicalAutomatic=(new ProductMappingService())->automatic($supplierId,null,null,null,$pdo,'Produs furnizor test');
    $assert((int)($historicalAutomatic['product_variant_id']??0)===$variantId && ($historicalAutomatic['association_source']??'')==='confirmed_nir_history','Istoricul NIR confirmat nu a recuperat asocierea veche lipsă.');
    $historicalCandidates=(new ProductMappingService())->candidates('ACC-V-'.$suffix);$historicalMappings=(array)($historicalCandidates[0]['supplier_mappings']??[]);
    $historicalMatch=array_values(array_filter($historicalMappings,static fn(array $mapping):bool=>($mapping['supplier_product_name']??'')==='Produs furnizor test'));
    $assert(($historicalMatch[0]['source']??'')==='confirmed_nir_history','Denumirea din NIR-ul confirmat nu este oferită selectorului pentru recuperare.');
    $movement=$pdo->prepare("SELECT * FROM accounting_stock_movements WHERE source_document_type='NIR' AND source_document_id=?");$movement->execute([$nirId]);$rows=$movement->fetchAll();
    $assert(count($rows)===1 && (float)$rows[0]['quantity_in']===10.0,'Confirmarea trebuie să creeze exact o intrare de 10 unități.');
    $assert(abs((float)$rows[0]['source_unit_cost']-62.5)<0.000001,'Costul unitar EUR nu a fost convertit în RON la cursul NIR.');
    $pdo->prepare('UPDATE accounting_stock_movements SET source_unit_cost=12.500000 WHERE id=?')->execute([(int)$rows[0]['id']]);
    $repair=$posting->repairForeignCurrencyNirCosts($pdo);
    $movement->execute([$nirId]);$repairedRows=$movement->fetchAll();
    $assert((int)$repair['movements_updated']===1 && abs((float)$repairedRows[0]['source_unit_cost']-62.5)<0.000001,'Repararea NIR-urilor valutare existente nu a recalculat costul în RON.');
    $balance->execute([$variantId,$warehouseId]);$balanceRow=$balance->fetch();
    $assert($balanceRow && abs((float)$balanceRow['current_quantity']-8.0)<0.0001 && abs((float)$balanceRow['minimum_historical_quantity']+2.0)<0.0001,'NIR-ul ulterior nu a recalculat corect soldul și istoricul negativ.');
    $commercial=$pdo->prepare('SELECT stock_qty,track_inventory FROM product_variants WHERE id=?');$commercial->execute([$variantId]);$commercial=$commercial->fetch();
    $assert((int)$commercial['stock_qty']===37 && (int)$commercial['track_inventory']===0,'NIR-ul a modificat stocul comercial sau modul nelimitat.');
    $service->confirm($nirId);$movement->execute([$nirId]);$assert(count($movement->fetchAll())===1,'Confirmarea idempotentă a dublat mișcarea.');
    foreach(['update','delete'] as $operation){$blocked=false;try{if($operation==='update')$service->updateDraft($nirId,$input+['row_version'=>3]);else $service->deleteDraft($nirId);}catch(Throwable){$blocked=true;}$assert($blocked,'Un NIR confirmat a permis operațiunea '.$operation.'.');}
    $snapshot=$pdo->prepare('SELECT product_name_snapshot FROM nir_lines WHERE nir_document_id=? LIMIT 1');$snapshot->execute([$nirId]);$snapshotName=(string)$snapshot->fetchColumn();$pdo->prepare('UPDATE products SET name=? WHERE id=?')->execute(['Produs redenumit '.$suffix,$productId]);$snapshot->execute([$nirId]);$assert((string)$snapshot->fetchColumn()===$snapshotName,'Redenumirea produsului a schimbat snapshot-ul NIR.');
    $pdo->prepare('UPDATE products SET sku=? WHERE id=?')->execute(['ACC-V-'.$suffix,$productId]);$duplicateBlocked=false;try{(new ProductMappingService())->assertCatalogSkuIntegrity();}catch(Throwable){$duplicateBlocked=true;}$assert($duplicateBlocked,'SKU-ul duplicat între produs și variantă nu a fost detectat.');$pdo->prepare('UPDATE products SET sku=? WHERE id=?')->execute(['ACC-P-'.$suffix,$productId]);
    $pdo->prepare('INSERT INTO accounting_periods (start_date,end_date,is_locked,locked_at) VALUES (\'2001-02-06\',\'2001-02-28\',1,NOW())')->execute();$periodId=(int)$pdo->lastInsertId();$periodBlocked=false;try{(new AccountingPeriodService())->assertPostingAllowed('2001-02-10');}catch(Throwable){$periodBlocked=true;}$assert($periodBlocked,'Perioada contabilă blocată a permis postarea neautorizată.');
    $pdf=(new NirPdfService())->generate($nirId);$assert(is_file(BASE_PATH.'/storage'.$pdf['path']),'PDF-ul NIR nu a fost generat.');
    $reversalId=$service->reverse($nirId,'2001-02-04','Test automat inversare');$balance->execute([$variantId,$warehouseId]);$assert(abs((float)$balance->fetch()['current_quantity']+2.0)<0.0001,'Inversarea NIR nu a păstrat ieșirea facturii.');
    $pdo->prepare("INSERT INTO invoices (order_id,company_profile_id,parent_invoice_id,document_type,customer_type,number,status,currency,issue_date,issuer_snapshot_json,customer_snapshot_json,subtotal_minor,grand_total_minor) VALUES (?, ?, ?, 'storno','individual',?,'issued','RON','2001-02-05','{}','{}',-5000,-5000)")
        ->execute([$orderId,$companyId,$salesInvoiceId,'QA-STORNO-'.$suffix]);$stornoInvoiceId=(int)$pdo->lastInsertId();
    $posting->reverseSalesInvoiceOutflow($salesInvoiceId,$stornoInvoiceId,'2001-02-05',true,'qa-storno-'.$suffix);$balance->execute([$variantId,$warehouseId]);$assert(abs((float)$balance->fetch()['current_quantity'])<0.0001,'Stornarea cu retur fizic nu a creat intrarea inversă.');
    $movementCount=$pdo->prepare('SELECT COUNT(*) FROM accounting_stock_movements WHERE product_variant_id=?');$movementCount->execute([$variantId]);$assert((int)$movementCount->fetchColumn()===4,'Registrul append-only trebuie să păstreze toate cele patru mișcări.');
    $report=(new AccountingStockReportService())->pdf($variantId,$warehouseId);$assert(str_starts_with($report,'%PDF-1.4'),'PDF-ul fișei de stoc este invalid.');
    $xlsx=(new XlsxService())->export('Test',['SKU','Cantitate'],[['ACC-V-'.$suffix,'10.0000']]);$temp=tempnam(sys_get_temp_dir(),'mb-xlsx-');file_put_contents($temp,$xlsx);$import=(new XlsxService())->import($temp);@unlink($temp);$assert(($import['headers'][0]??'')==='SKU' && ($import['rows'][0][0]??'')==='ACC-V-'.$suffix,'Exportul XLSX nu poate fi reimportat corect.');
    echo "Accounting stock regression test: OK\n";
}catch(Throwable $exception){fwrite(STDERR,"Accounting stock regression test: FAIL - {$exception->getMessage()}\n");$failed=true;}
finally{
    if($variantId){$statement=$pdo->prepare('SELECT DISTINCT valuation_run_id FROM accounting_stock_valuations av JOIN accounting_stock_movements m ON m.id=av.movement_id WHERE m.product_variant_id=?');$statement->execute([$variantId]);$runIds=array_map('intval',$statement->fetchAll(PDO::FETCH_COLUMN));}
    if($nirId||$reversalId){$ids=array_values(array_filter([$nirId,$reversalId]));$placeholders=implode(',',array_fill(0,count($ids),'?'));$statement=$pdo->prepare("SELECT path FROM nir_artifacts WHERE nir_document_id IN ($placeholders)");$statement->execute($ids);$artifactPaths=$statement->fetchAll(PDO::FETCH_COLUMN);$pdo->prepare("DELETE FROM nir_artifacts WHERE nir_document_id IN ($placeholders)")->execute($ids);}
    foreach($artifactPaths as $relative){$path=BASE_PATH.'/storage'.$relative;if(is_file($path))@unlink($path);}
    if($variantId){$statement=$pdo->prepare('DELETE av FROM accounting_stock_valuations av JOIN accounting_stock_movements m ON m.id=av.movement_id WHERE m.product_variant_id=?');$statement->execute([$variantId]);$pdo->prepare('DELETE FROM accounting_stock_balances WHERE product_variant_id=?')->execute([$variantId]);$pdo->prepare('DELETE FROM accounting_stock_movements WHERE product_variant_id=? ORDER BY reversal_of_movement_id IS NULL ASC,id DESC')->execute([$variantId]);}
    if($reversalId){$pdo->prepare('DELETE FROM nir_lines WHERE nir_document_id=?')->execute([$reversalId]);$pdo->prepare('DELETE FROM nir_documents WHERE id=?')->execute([$reversalId]);}
    if($nirId){$pdo->prepare('DELETE FROM nir_lines WHERE nir_document_id=?')->execute([$nirId]);$pdo->prepare('DELETE FROM nir_documents WHERE id=?')->execute([$nirId]);}
    if($invoiceId){$pdo->prepare('DELETE FROM supplier_invoice_lines WHERE supplier_invoice_id=?')->execute([$invoiceId]);$pdo->prepare('DELETE FROM supplier_invoices WHERE id=?')->execute([$invoiceId]);}
    if($supplierId)$pdo->prepare('DELETE FROM product_supplier_mappings WHERE supplier_id=?')->execute([$supplierId]);
    if($supplierId)$pdo->prepare('DELETE FROM accounting_suppliers WHERE id=?')->execute([$supplierId]);
    if($salesInvoiceId)$pdo->prepare('DELETE FROM invoice_items WHERE invoice_id=?')->execute([$salesInvoiceId]);
    if($stornoInvoiceId)$pdo->prepare('DELETE FROM invoices WHERE id=?')->execute([$stornoInvoiceId]);
    if($salesInvoiceId)$pdo->prepare('DELETE FROM invoices WHERE id=?')->execute([$salesInvoiceId]);
    if($orderItemId)$pdo->prepare('DELETE FROM order_items WHERE id=?')->execute([$orderItemId]);
    if($orderId)$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$orderId]);
    if($runIds){$placeholders=implode(',',array_fill(0,count($runIds),'?'));$pdo->prepare("DELETE FROM accounting_valuation_runs WHERE id IN ($placeholders)")->execute($runIds);}
    if($periodId)$pdo->prepare('DELETE FROM accounting_periods WHERE id=?')->execute([$periodId]);
    $pdo->exec("DELETE FROM accounting_document_sequences WHERE fiscal_year=2001 AND document_type IN ('NIR','NIR_REVERSAL')");
    if($variantId)$pdo->prepare('DELETE FROM product_variants WHERE id=?')->execute([$variantId]);
    if($productId)$pdo->prepare('DELETE FROM products WHERE id=?')->execute([$productId]);
}
exit(!empty($failed)?1:0);
