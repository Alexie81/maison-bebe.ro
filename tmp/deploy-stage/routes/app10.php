<?php

declare(strict_types=1);

use MaisonBebe\Controllers\Admin\SecurityController;
use MaisonBebe\Controllers\Admin\StaffController;
use MaisonBebe\Controllers\StripeWebhookController;

/** @var MaisonBebe\Core\Router $router */
$router = require __DIR__ . '/app9.php';

$router->post('/webhooks/plati/stripe', [StripeWebhookController::class, 'handle'], []);

$router->get('/admin/utilizatori', [StaffController::class, 'index'], ['admin', 'permission:settings.manage']);
$router->get('/admin/utilizatori/creare', [StaffController::class, 'form'], ['admin', 'permission:settings.manage']);
$router->post('/admin/utilizatori', [StaffController::class, 'save'], ['admin', 'permission:settings.manage', 'csrf']);
$router->get('/admin/utilizatori/{id}/edit', [StaffController::class, 'form'], ['admin', 'permission:settings.manage']);
$router->post('/admin/utilizatori/{id}', [StaffController::class, 'save'], ['admin', 'permission:settings.manage', 'csrf']);
$router->post('/admin/utilizatori/{id}/status', [StaffController::class, 'toggle'], ['admin', 'permission:settings.manage', 'csrf']);

$router->get('/admin/setari/securitate', [SecurityController::class, 'page'], ['admin']);
$router->post('/admin/setari/securitate', [SecurityController::class, 'changePassword'], ['admin', 'csrf']);

return $router;
