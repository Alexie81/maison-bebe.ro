<?php

declare(strict_types=1);

use MaisonBebe\Controllers\Admin\BillingController;
use MaisonBebe\Controllers\InvoiceController;

/** @var MaisonBebe\Core\Router $router */
$router = require __DIR__ . '/app6.php';

$router->get('/factura/{hash}', [InvoiceController::class, 'download']);
$router->get('/admin/facturare', [BillingController::class, 'overview'], ['admin']);
$router->get('/admin/facturare/firma', [BillingController::class, 'company'], ['admin']);
$router->post('/admin/facturare/firma', [BillingController::class, 'saveCompany'], ['admin', 'csrf']);
$router->get('/admin/facturare/conectori', [BillingController::class, 'connectors'], ['admin']);
$router->post('/admin/facturare/conectori/{code}', [BillingController::class, 'saveConnector'], ['admin', 'csrf']);
$router->get('/admin/facturare/sabloane', [BillingController::class, 'templates'], ['admin']);
$router->get('/admin/facturare/sabloane/{id}/model', [BillingController::class, 'previewTemplate'], ['admin']);
$router->get('/admin/facturare/sabloane/mapper', [BillingController::class, 'mapper'], ['admin']);
$router->post('/admin/facturare/sabloane/mapper', [BillingController::class, 'saveMapper'], ['admin', 'csrf']);
$router->post('/admin/facturare/sabloane/{id}/default', [BillingController::class, 'chooseTemplate'], ['admin', 'csrf']);
$router->get('/admin/facturare/efactura', [BillingController::class, 'efactura'], ['admin']);
$router->post('/admin/facturare/efactura', [BillingController::class, 'saveEfactura'], ['admin', 'csrf']);
$router->get('/admin/facturi', [BillingController::class, 'invoices'], ['admin']);
$router->get('/admin/facturi/{id}', [BillingController::class, 'invoice'], ['admin']);
$router->get('/admin/facturi/{id}/ubl', [BillingController::class, 'downloadUbl'], ['admin']);
$router->post('/admin/comenzi/{id}/factura', [BillingController::class, 'issueOrder'], ['admin', 'csrf']);

return $router;
