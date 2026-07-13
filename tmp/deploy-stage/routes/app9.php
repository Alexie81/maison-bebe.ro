<?php

declare(strict_types=1);

use MaisonBebe\Controllers\Admin\CmsController;

/** @var MaisonBebe\Core\Router $router */
$router = require __DIR__ . '/app8.php';

$router->get('/admin/cms/homepage', [CmsController::class, 'homepage'], ['admin']);
$router->post('/admin/cms/homepage/announcement', [CmsController::class, 'saveAnnouncement'], ['admin', 'csrf']);
$router->post('/admin/cms/homepage/{key}', [CmsController::class, 'saveHomepage'], ['admin', 'csrf']);
$router->get('/admin/cms/pagini/{id}', [CmsController::class, 'page'], ['admin']);
$router->post('/admin/cms/pagini/{id}', [CmsController::class, 'savePage'], ['admin', 'csrf']);

return $router;
