<?php
declare(strict_types=1);

use MaisonBebe\Core\HttpException;
use MaisonBebe\Core\Request;
use MaisonBebe\Services\RedirectService;

$router = require dirname(__DIR__) . '/bootstrap.php';
$request = new Request();

try {
    $router->dispatch($request);
} catch (HttpException $exception) {
    if ($exception->status() === 404 && (new RedirectService())->handle($request->path)) { exit; }
    http_response_code($exception->status());
    echo view('errors/http', [
        'status' => $exception->status(),
        'title' => $exception->getMessage(),
    ], $exception->status() >= 500 ? 'layouts/error' : 'layouts/storefront');
} catch (Throwable $exception) {
    error_log($exception->__toString());
    http_response_code(500);
    echo view('errors/http', [
        'status' => 500,
        'title' => env('APP_DEBUG', 'false') === 'true' ? $exception->getMessage() : 'Un detaliu neașteptat a întrerupt pagina.',
    ], 'layouts/error');
}

