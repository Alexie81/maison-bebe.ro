<?php
require dirname(__DIR__,2).'/bootstrap.php';
$orders=MaisonBebe\Core\Database::connection()->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 30')->fetchAll();
$pdf=(new MaisonBebe\Services\OrderExportService())->pdf($orders);
file_put_contents(__DIR__.'/order-export-test.pdf',$pdf);
echo strlen($pdf)." bytes\n";