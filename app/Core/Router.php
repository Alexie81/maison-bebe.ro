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
                throw new HttpException(403, 'Sesiunea formularului a expirat. Reîncarcă pagina.');
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
            if ($item === 'admin' && Auth::isAdmin()) {
                $this->enforceAdminPathPermission($request);
            }
            if (str_starts_with($item, 'permission:') && !Auth::hasPermission(substr($item, 11))) {
                throw new HttpException(403, 'Permisiunea necesară lipsește.');
            }
        }
    }

    private function enforceAdminPathPermission(Request $request): void
    {
        $path = rtrim($request->path, '/') ?: '/';
        $method = strtoupper($request->method);
        $permission = match (true) {
            $path === '/admin' => 'dashboard.view',
            $path === '/admin/setari/securitate' => null,
            str_starts_with($path, '/admin/expeditii') || str_contains($path, '/awb') => 'shipping.manage',
            str_starts_with($path, '/admin/comenzi') && str_ends_with($path, '/factura') => 'billing.issue',
            str_starts_with($path, '/admin/comenzi') => $method === 'GET' ? 'orders.view' : 'orders.update',
            str_starts_with($path, '/admin/produse') => $method === 'GET' ? 'products.view' : (str_ends_with($path, '/sterge') ? 'products.delete' : (preg_match('#^/admin/produse/?$#', $path) ? 'products.create' : 'products.update')),
            str_starts_with($path, '/admin/categorii') || str_starts_with($path, '/admin/colectii') || str_starts_with($path, '/admin/gift-box') => 'categories.manage',
            str_starts_with($path, '/admin/clienti') => 'customers.view',
            str_starts_with($path, '/admin/cupoane') => 'products.update',
            str_starts_with($path, '/admin/notificari') || str_starts_with($path, '/admin/api/notifications') => 'dashboard.view',
            str_starts_with($path, '/admin/cms') => 'cms.manage',
            str_starts_with($path, '/admin/atelier') => 'atelier.manage',
            str_starts_with($path, '/admin/facturi') => $method === 'GET' ? 'billing.view' : 'billing.issue',
            str_starts_with($path, '/admin/facturare') => $method === 'GET' ? 'billing.view' : 'billing.manage',
            str_starts_with($path, '/admin/seo') => 'seo.manage',
            str_starts_with($path, '/admin/setari') || str_starts_with($path, '/admin/utilizatori') => 'settings.manage',
            default => null,
        };
        if ($permission !== null && !Auth::hasPermission($permission)) {
            throw new HttpException(403, 'Nu ai permisiunea necesară pentru această secțiune. Cere administratorului să îți activeze accesul.');
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
