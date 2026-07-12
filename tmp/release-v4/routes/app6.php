<?php

declare(strict_types=1);

use MaisonBebe\Controllers\Admin\EditorialController;
use MaisonBebe\Controllers\Admin\SeoController;
use MaisonBebe\Controllers\SitemapController;

/** @var MaisonBebe\Core\Router $router */
$router = require __DIR__ . '/app5.php';

$router->get('/sitemap.xml', [SitemapController::class, 'index']);
$router->get('/sitemaps/{type}.xml', [SitemapController::class, 'map']);
$router->get('/robots.txt', [SitemapController::class, 'robots']);

$router->get('/admin/atelier', [EditorialController::class, 'index'], ['admin']);
$router->get('/admin/atelier/creare', [EditorialController::class, 'form'], ['admin']);
$router->post('/admin/atelier', [EditorialController::class, 'save'], ['admin', 'csrf']);
$router->get('/admin/atelier/taxonomii', [EditorialController::class, 'taxonomies'], ['admin']);
$router->post('/admin/atelier/taxonomii', [EditorialController::class, 'saveTaxonomy'], ['admin', 'csrf']);
$router->get('/admin/atelier/calendar', [EditorialController::class, 'calendar'], ['admin']);
$router->get('/admin/atelier/{id}/revisions', [EditorialController::class, 'revisions'], ['admin']);
$router->post('/admin/atelier/{id}/revisions/{revision}/restore', [EditorialController::class, 'restore'], ['admin', 'csrf']);
$router->get('/admin/atelier/{id}/edit', [EditorialController::class, 'form'], ['admin']);
$router->post('/admin/atelier/{id}', [EditorialController::class, 'save'], ['admin', 'csrf']);
$router->get('/admin/atelier/{id}/seo', [EditorialController::class, 'form'], ['admin']);
$router->get('/admin/atelier/{id}/social', [EditorialController::class, 'form'], ['admin']);

$router->get('/admin/seo/indexabilitate', [SeoController::class, 'indexability'], ['admin']);
$router->post('/admin/seo/indexabilitate/audit', [SeoController::class, 'audit'], ['admin', 'csrf']);
$router->get('/admin/seo/sitemap', [SeoController::class, 'sitemap'], ['admin']);
$router->post('/admin/seo/sitemap/rebuild', [SeoController::class, 'rebuildSitemap'], ['admin', 'csrf']);
$router->get('/admin/seo/redirecturi', [SeoController::class, 'redirects'], ['admin']);
$router->post('/admin/seo/redirecturi', [SeoController::class, 'saveRedirect'], ['admin', 'csrf']);
$router->get('/admin/seo/search-console', [SeoController::class, 'searchConsole'], ['admin']);
$router->post('/admin/seo/search-console', [SeoController::class, 'saveSearchConsole'], ['admin', 'csrf']);
$router->get('/admin/produse/{id}/seo', [SeoController::class, 'product'], ['admin']);
$router->post('/admin/produse/{id}/seo', [SeoController::class, 'saveProduct'], ['admin', 'csrf']);

return $router;
