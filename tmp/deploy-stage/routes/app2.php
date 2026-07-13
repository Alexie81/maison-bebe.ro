<?php
declare(strict_types=1);

use MaisonBebe\Controllers\ApiController;
use MaisonBebe\Controllers\CommerceApiController;
use MaisonBebe\Controllers\CommerceController;
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

$router->get('/cos', [CommerceController::class, 'cart']);
$router->get('/favorite', [CommerceController::class, 'wishlist']);
$router->get('/checkout', [CommerceController::class, 'checkout']);
$router->post('/checkout/create', [CommerceController::class, 'createOrder']);
$router->get('/comanda-confirmata/{token}', [CommerceController::class, 'confirmation']);
$router->get('/urmarire-comanda', [CommerceController::class, 'tracking']);
$router->post('/urmarire-comanda', [CommerceController::class, 'tracking']);
$router->get('/contact', [CommerceController::class, 'contact']);
$router->post('/contact', [CommerceController::class, 'sendContact']);

$router->get('/api/search', [ApiController::class, 'search']);
$router->get('/api/cart', [CommerceApiController::class, 'cart']);
$router->post('/api/cart/items', [CommerceApiController::class, 'addCartItem']);
$router->patch('/api/cart/items/{id}', [CommerceApiController::class, 'updateCartItem']);
$router->delete('/api/cart/items/{id}', [CommerceApiController::class, 'removeCartItem']);
$router->post('/api/cart/coupon', [CommerceApiController::class, 'coupon']);
$router->post('/api/wishlist/toggle', [CommerceApiController::class, 'wishlistToggle']);

return $router;

