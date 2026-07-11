<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

use Closure;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): self
    {
        return $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = ['csrf']): self
    {
        return $this->add('POST', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array $handler, array $middleware = ['csrf']): self
    {
        return $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = ['csrf']): self
    {
        return $this->add('DELETE', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, callable|array $handler, array $middleware = []): self
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
        return $this;
    }

    public function dispatch(?Request $request = null): void
    {
        $request ??= new Request();
        foreach ($this->routes as $route) {
            $requestMethod = $request->method === 'HEAD' ? 'GET' : $request->method;
            if ($route['method'] !== $requestMethod) {
                continue;
            }
            $params = $this->match($route['path'], $request->path);
            if ($params === null) {
                continue;
            }
            $this->middleware($route['middleware'], $request);
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $method] = $handler;
                $handler = [new $class(), $method];
            }
            $result = $handler($request, ...array_values($params));
            if (is_string($result)) {
                echo $result;
            }
            return;
        }
        throw new HttpException(404, 'Pagina nu a fost găsită.');
    }

    private function match(string $pattern, string $path): ?array
    {
        $keys = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $match) use (&$keys): string {
            $keys[] = $match[1];
            return '([^/]+)';
        }, rtrim($pattern, '/'));
        $regex = '#^' . ($regex === '' ? '/' : $regex) . '/?$#u';
        if (!preg_match($regex, $path, $matches)) {
            return null;
        }
        array_shift($matches);
        return $keys ? array_combine($keys, $matches) : [];
    }

    private function middleware(array $middleware, Request $request): void
    {
        foreach ($middleware as $item) {
            if ($item === 'csrf' && !Csrf::validate((string) ($request->input('_csrf') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')))) {
                throw new HttpException(419, 'Sesiunea formularului a expirat. Reîncarcă pagina.');
            }
            if ($item === 'auth' && !Auth::id()) {
                $this->rememberIntended($request);
                Response::redirect('/cont/autentificare');
            }
            if ($item === 'admin' && !Auth::id()) {
                $this->rememberIntended($request);
                Response::redirect('/cont/autentificare');
            }
            if ($item === 'admin' && !Auth::isAdmin()) {
                throw new HttpException(403, 'Nu ai acces la această zonă.');
            }
            if (str_starts_with($item, 'permission:') && !Auth::hasPermission(substr($item, 11))) {
                throw new HttpException(403, 'Permisiunea necesară lipsește.');
            }
        }
    }

    private function rememberIntended(Request $request): void
    {
        $path = $request->path;
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isApi = preg_match('#/(?:admin/)?api(?:/|$)#i', $path) === 1
            || preg_match('#/webhooks(?:/|$)#i', $path) === 1;
        if ($method !== 'GET' || $isApi || str_contains($accept, 'application/json')) {
            return;
        }
        Session::put('intended_url', $path);
    }
}
