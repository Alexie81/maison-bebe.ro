<?php
declare(strict_types=1);

use MaisonBebe\Controllers\Admin\AdminController;
use MaisonBebe\Controllers\Admin\CatalogController;
use MaisonBebe\Controllers\Admin\GiftBoxController;

/** @var MaisonBebe\Core\Router $router */
$router = require __DIR__ . '/app3.php';
$admin = ['admin'];

$router->get('/admin', [AdminController::class, 'dashboard'], $admin);
$router->get('/admin/comenzi', [AdminController::class, 'orders'], $admin);
$router->get('/admin/comenzi/{id}', [AdminController::class, 'order'], $admin);
$router->post('/admin/comenzi/{id}/status', [AdminController::class, 'updateOrder'], ['admin','csrf']);
$router->get('/admin/produse', [CatalogController::class, 'products'], $admin);
$router->get('/admin/produse/creare', [CatalogController::class, 'productForm'], $admin);
$router->post('/admin/produse', [CatalogController::class, 'saveProduct'], ['admin','csrf']);
$router->get('/admin/produse/{id}/edit', [CatalogController::class, 'productForm'], $admin);
$router->post('/admin/produse/{id}', [CatalogController::class, 'saveProduct'], ['admin','csrf']);
$router->post('/admin/produse/{id}/sterge', [CatalogController::class, 'deleteProduct'], ['admin','csrf']);
$router->get('/admin/categorii', [CatalogController::class, 'categories'], $admin);
$router->get('/admin/categorii/creare', [CatalogController::class, 'categoryForm'], $admin);
$router->post('/admin/categorii', [CatalogController::class, 'saveCategory'], ['admin','csrf']);
$router->get('/admin/categorii/{id}/edit', [CatalogController::class, 'categoryForm'], $admin);
$router->post('/admin/categorii/{id}', [CatalogController::class, 'saveCategory'], ['admin','csrf']);
$router->post('/admin/categorii/{id}/sterge', [CatalogController::class, 'deleteCategory'], ['admin','csrf']);
$router->get('/admin/gift-box', [GiftBoxController::class, 'index'], $admin);
$router->post('/admin/gift-box/setari', [GiftBoxController::class, 'saveSettings'], ['admin','csrf']);
$router->get('/admin/gift-box/cutii/creare', [GiftBoxController::class, 'form'], $admin);
$router->post('/admin/gift-box/cutii', [GiftBoxController::class, 'save'], ['admin','csrf']);
$router->get('/admin/gift-box/cutii/{id}/edit', [GiftBoxController::class, 'form'], $admin);
$router->post('/admin/gift-box/cutii/{id}', [GiftBoxController::class, 'save'], ['admin','csrf']);
$router->post('/admin/gift-box/cutii/{id}/sterge', [GiftBoxController::class, 'delete'], ['admin','csrf']);
$router->get('/admin/clienti', [AdminController::class, 'customers'], $admin);
$router->get('/admin/clienti/{id}', [AdminController::class, 'customer'], $admin);
$router->get('/admin/notificari', [AdminController::class, 'notifications'], $admin);
$router->get('/admin/api/notifications/unread', [AdminController::class, 'unread'], $admin);
$router->post('/admin/api/notifications/{id}/read', [AdminController::class, 'markRead'], ['admin','csrf']);
$router->post('/admin/api/notifications/read-all', [AdminController::class, 'markAllRead'], ['admin','csrf']);
$router->get('/admin/cupoane', [AdminController::class, 'coupons'], $admin);
$router->post('/admin/cupoane', [AdminController::class, 'saveCoupon'], ['admin','csrf']);
$router->get('/admin/cms', [AdminController::class, 'cms'], $admin);
$router->post('/admin/cms', [AdminController::class, 'saveCms'], ['admin','csrf']);
$router->get('/admin/expeditii', [AdminController::class, 'shipments'], $admin);

return $router;