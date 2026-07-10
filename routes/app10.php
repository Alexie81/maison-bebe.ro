<?php

declare(strict_types=1);

use MaisonBebe\Controllers\Admin\SecurityController;
use MaisonBebe\Controllers\StripeWebhookController;

/** @var MaisonBebe\Core\Router $router */
$router = require __DIR__ . '/app9.php';

$router->post('/webhooks/plati/stripe', [StripeWebhookController::class, 'handle'], []);

$router->get('/admin/setari/securitate', [SecurityController::class, 'page'], ['admin']);
$router->post('/admin/setari/securitate', [SecurityController::class, 'changePassword'], ['admin', 'csrf']);

return $router;
