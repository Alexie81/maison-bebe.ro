<?php

declare(strict_types=1);

require __DIR__ . '/app4.php';

use MaisonBebe\Controllers\Admin\SettingsController;

$admin = ['admin'];
$adminPost = ['admin', 'csrf'];

$router->get('/admin/setari/email', [SettingsController::class, 'email'], $admin);
$router->post('/admin/setari/email/destinatari', [SettingsController::class, 'saveRecipients'], $adminPost);
$router->post('/admin/setari/email/{purpose}/test', [SettingsController::class, 'testEmail'], $adminPost);
$router->post('/admin/setari/email/{purpose}', [SettingsController::class, 'saveEmail'], $adminPost);
$router->get('/admin/setari/plati', [SettingsController::class, 'payments'], $admin);
$router->get('/admin/setari/plati/{provider}', [SettingsController::class, 'payment'], $admin);
$router->post('/admin/setari/plati/stripe/wallets', [SettingsController::class, 'enableStripeWallets'], $adminPost);
$router->post('/admin/setari/plati/{provider}', [SettingsController::class, 'savePayment'], $adminPost);
$router->get('/admin/setari/autentificare', [SettingsController::class, 'authentication'], $admin);
$router->post('/admin/setari/autentificare', [SettingsController::class, 'saveAuthentication'], $adminPost);
$router->get('/admin/setari/livrare', [SettingsController::class, 'shipping'], $admin);
$router->post('/admin/setari/livrare/{provider}', [SettingsController::class, 'saveShipping'], $adminPost);

return $router;
