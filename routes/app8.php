<?php

declare(strict_types=1);

use MaisonBebe\Controllers\Admin\FulfillmentController;

/** @var MaisonBebe\Core\Router $router */
$router = require __DIR__ . '/app7.php';

$router->get('/admin/comenzi/{id}/awb', [FulfillmentController::class, 'awb'], ['admin']);
$router->post('/admin/comenzi/{id}/awb', [FulfillmentController::class, 'createAwb'], ['admin', 'csrf']);

return $router;
