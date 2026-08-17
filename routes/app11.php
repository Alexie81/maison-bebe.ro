<?php

declare(strict_types=1);

use MaisonBebe\Controllers\Admin\AccountingStockController;
use MaisonBebe\Controllers\Admin\NirController;

/** @var MaisonBebe\Core\Router $router */
$router = require __DIR__ . '/app10.php';

$router->get('/admin/nir-uri', [NirController::class, 'index'], ['admin', 'permission:nir.view']);
$router->get('/admin/nir-uri/export', [NirController::class, 'exportList'], ['admin', 'permission:nir.view']);
$router->get('/admin/nir-uri/arhiva', [NirController::class, 'archive'], ['admin', 'permission:nir.view']);
$router->post('/admin/nir-uri/arhiva/email', [NirController::class, 'emailArchive'], ['admin', 'permission:nir.view', 'csrf']);
$router->get('/admin/nir-uri/curs-valutar', [NirController::class, 'exchangeRate'], ['admin', 'permission:nir.create']);
$router->get('/admin/nir-uri/import', [NirController::class, 'import'], ['admin', 'permission:nir.create']);
$router->post('/admin/nir-uri/import/preview', [NirController::class, 'importPreview'], ['admin', 'permission:nir.create', 'csrf']);
$router->post('/admin/nir-uri/import/map', [NirController::class, 'importMap'], ['admin', 'permission:nir.create', 'csrf']);
$router->get('/admin/nir-uri/nou', [NirController::class, 'form'], ['admin', 'permission:nir.create']);
$router->post('/admin/nir-uri', [NirController::class, 'create'], ['admin', 'permission:nir.create', 'csrf']);
$router->get('/admin/nir-uri/{id}/edit', [NirController::class, 'form'], ['admin', 'permission:nir.create']);
$router->post('/admin/nir-uri/{id}', [NirController::class, 'update'], ['admin', 'permission:nir.create', 'csrf']);
$router->post('/admin/nir-uri/{id}/confirmare', [NirController::class, 'confirm'], ['admin', 'permission:nir.confirm', 'csrf']);
$router->post('/admin/nir-uri/{id}/inversare', [NirController::class, 'reverse'], ['admin', 'permission:nir.reverse', 'csrf']);
$router->post('/admin/nir-uri/{id}/stergere-ciorna', [NirController::class, 'delete'], ['admin', 'permission:nir.create', 'csrf']);
$router->get('/admin/nir-uri/{id}/pdf', [NirController::class, 'pdf'], ['admin', 'permission:nir.view']);
$router->get('/admin/nir-uri/{id}/xlsx', [NirController::class, 'xlsx'], ['admin', 'permission:nir.view']);
$router->get('/admin/nir-uri/{id}/atasament/{artifact}', [NirController::class, 'attachment'], ['admin', 'permission:nir.view']);
$router->get('/admin/nir-uri/{id}', [NirController::class, 'show'], ['admin', 'permission:nir.view']);

$router->get('/admin/stocuri-conta', [AccountingStockController::class, 'index'], ['admin', 'permission:accounting_stock.view']);
$router->get('/admin/stocuri-conta/export', [AccountingStockController::class, 'export'], ['admin', 'permission:accounting_stock.export']);
$router->get('/admin/stocuri-conta/fisa/{variant}/xlsx', [AccountingStockController::class, 'cardXlsx'], ['admin', 'permission:accounting_stock.export']);
$router->get('/admin/stocuri-conta/fisa/{variant}/pdf', [AccountingStockController::class, 'cardPdf'], ['admin', 'permission:accounting_stock.export']);
$router->get('/admin/stocuri-conta/fisa/{variant}', [AccountingStockController::class, 'card'], ['admin', 'permission:accounting_stock.view']);
$router->post('/admin/stocuri-conta/setari', [AccountingStockController::class, 'saveSettings'], ['admin', 'permission:accounting_stock.settings', 'csrf']);
$router->post('/admin/stocuri-conta/perioade', [AccountingStockController::class, 'savePeriod'], ['admin', 'permission:accounting_periods.manage', 'csrf']);

return $router;
