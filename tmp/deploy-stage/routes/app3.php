<?php
declare(strict_types=1);

use MaisonBebe\Controllers\AccountController;
use MaisonBebe\Controllers\ApiController;
use MaisonBebe\Controllers\AuthController;
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
$router->post('/produs/{slug}/recenzie', [StorefrontController::class, 'saveReview'], ['auth','csrf']);
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
$router->get('/plata/stripe/{token}', [CommerceController::class, 'resumeStripe']);
$router->get('/urmarire-comanda', [CommerceController::class, 'tracking']);
$router->post('/urmarire-comanda', [CommerceController::class, 'tracking']);
$router->get('/contact', [CommerceController::class, 'contact']);
$router->post('/contact', [CommerceController::class, 'sendContact']);
$router->post('/newsletter/abonare', [CommerceController::class, 'subscribeNewsletter'], ['csrf']);
$router->get('/newsletter/dezabonare/{token}', [CommerceController::class, 'unsubscribeNewsletter']);

$router->get('/cont/autentificare', [AuthController::class, 'login']);
$router->post('/cont/autentificare', [AuthController::class, 'authenticate']);
$router->get('/cont/inregistrare', [AuthController::class, 'register']);
$router->post('/cont/inregistrare', [AuthController::class, 'store']);
$router->post('/cont/deconectare', [AuthController::class, 'logout']);
$router->get('/cont/resetare-parola', [AuthController::class, 'reset']);
$router->post('/cont/resetare-parola', [AuthController::class, 'sendReset']);
$router->get('/cont/parola-noua/{token}', [AuthController::class, 'newPassword']);
$router->post('/cont/parola-noua/{token}', [AuthController::class, 'updatePassword']);
$router->get('/auth/google', [AuthController::class, 'google']);
$router->get('/auth/google/callback', [AuthController::class, 'googleCallback']);
$router->get('/cont', [AccountController::class, 'dashboard'], ['auth']);
$router->get('/cont/comenzi', [AccountController::class, 'orders'], ['auth']);
$router->get('/cont/comenzi/{number}', [AccountController::class, 'order'], ['auth']);
$router->get('/cont/date-personale', [AccountController::class, 'personalPage'], ['auth']);
$router->post('/cont/date-personale', [AccountController::class, 'savePersonal'], ['auth','csrf']);
$router->post('/cont/preferinte-email', [AccountController::class, 'saveEmailPreferences'], ['auth','csrf']);
$router->get('/cont/cupoane', [AccountController::class, 'couponsPage'], ['auth']);
$router->get('/cont/adrese', [AccountController::class, 'addressesPage'], ['auth']);
$router->post('/cont/adrese', [AccountController::class, 'saveAddress'], ['auth','csrf']);
$router->post('/cont/adrese/{id}', [AccountController::class, 'saveAddress'], ['auth','csrf']);

$router->get('/api/search', [ApiController::class, 'search']);
$router->get('/api/cart', [CommerceApiController::class, 'cart']);
$router->post('/api/cart/items', [CommerceApiController::class, 'addCartItem']);
$router->post('/api/cart/toggle-product', [CommerceApiController::class, 'toggleCartProduct']);
$router->post('/api/gift-box', [CommerceApiController::class, 'giftBox']);
$router->patch('/api/cart/items/{id}', [CommerceApiController::class, 'updateCartItem']);
$router->delete('/api/cart/items/{id}', [CommerceApiController::class, 'removeCartItem']);
$router->post('/api/cart/coupon', [CommerceApiController::class, 'coupon']);
$router->post('/api/wishlist/toggle', [CommerceApiController::class, 'wishlistToggle']);

return $router;

