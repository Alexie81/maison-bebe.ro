<?php
declare(strict_types=1);

use MaisonBebe\Controllers\ApiController;
use MaisonBebe\Controllers\StorefrontController;
use MaisonBebe\Core\Router;

$router = new Router();

$router->get('/', [StorefrontController::class, 'home']);
$router->get('/shop', [StorefrontController::class, 'shop']);
$router->get('/categorie/{slug}', [StorefrontController::class, 'category']);
$router->get('/colectie/{slug}', [StorefrontController::class, 'collection']);
$router->get('/produs/{slug}', [StorefrontController::class, 'product']);
$router->get('/gift-box', [StorefrontController::class, 'giftBox']);
$router->get('/despre-noi', [StorefrontController::class, 'about']);
$router->get('/politici/{slug}', [StorefrontController::class, 'legal']);
$router->get('/legal/{slug}', [StorefrontController::class, 'legal']);
$router->get('/atelier', [StorefrontController::class, 'atelier']);
$router->get('/atelier/{slug}', [StorefrontController::class, 'article']);
$router->get('/api/search', [ApiController::class, 'search']);

return $router;

