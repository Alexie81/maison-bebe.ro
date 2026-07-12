<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

final class Request
{
    public readonly string $method;
    public readonly string $path;

    public function __construct()
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper((string) $_POST['_method']);
        }
        $this->method = $method;

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim((string) Env::get('APP_BASE_PATH', ''), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $this->path = '/' . trim(urldecode($path), '/');
        if ($this->path === '//') {
            $this->path = '/';
        }
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function json(): array
    {
        $payload = json_decode((string) file_get_contents('php://input'), true);
        return is_array($payload) ? $payload : [];
    }

    public function expectsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_starts_with($this->path, '/api/') || str_starts_with($this->path, '/admin/api/');
    }
}

