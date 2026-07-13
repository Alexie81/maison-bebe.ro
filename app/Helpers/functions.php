<?php
declare(strict_types=1);

use MaisonBebe\Core\Auth;
use MaisonBebe\Core\Csrf;
use MaisonBebe\Core\Env;
use MaisonBebe\Core\Session;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    $base = rtrim((string) Env::get('APP_BASE_PATH', ''), '/');
    return ($base ?: '') . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    return rtrim((string) Env::get('APP_URL', ''), '/') . '/' . ltrim($path, '/');
}

/** Public URL for customer emails and third-party integrations. */
function public_url(string $path = ''): string
{
    $configured = rtrim((string) Env::get('PUBLIC_SITE_URL', ''), '/');
    if ($configured === '') {
        $candidate = rtrim((string) Env::get('APP_URL', ''), '/');
        $host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
        if ($candidate !== '' && !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $configured = $candidate;
        }
    }
    if ($configured === '') {
        $configured = 'https://maison-bebe.ro';
    }
    return $configured . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function money(int|float|string|null $minor, string $currency = 'RON'): string
{
    return number_format(((int) $minor) / 100, 2, ',', '.') . ' ' . ($currency === 'RON' ? 'lei' : e($currency));
}

function view(string $name, array $data = [], string $layout = 'layouts/storefront'): string
{
    $viewFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $name) . '.php';
    if (!is_file($viewFile)) {
        throw new RuntimeException('View inexistent: ' . $name);
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $content = (string) ob_get_clean();

    if ($layout === '') {
        return $content;
    }
    $layoutFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $layout) . '.php';
    ob_start();
    require $layoutFile;
    return (string) ob_get_clean();
}

function old(string $key, mixed $default = ''): mixed
{
    return Session::get('_old')[$key] ?? $default;
}

function auth_user(): ?array
{
    return Auth::user();
}

