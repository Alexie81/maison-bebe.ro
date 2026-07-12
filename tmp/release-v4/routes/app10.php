<?php

declare(strict_types=1);

use MaisonBebe\Controllers\Admin\SecurityController;

/** @var MaisonBebe\Core\Router $router */
$router = require __DIR__ . '/app9.php';

$router->get('/admin/setari/securitate', [SecurityController::class, 'page'], ['admin']);
$router->post('/admin/setari/securitate', [SecurityController::class, 'changePassword'], ['admin', 'csrf']);

return $router;
