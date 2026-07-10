<?php

namespace App\Core;

/**
 * موجّه بسيط وخفيف يدعم متغيرات المسار {id} وميدلوير اختياري.
 */
class Router
{
    private array $routes = [];

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $params = $this->match($route['path'], $path);
            if ($params === null) {
                continue;
            }

            foreach ($route['middleware'] as $middleware) {
                $result = call_user_func($middleware);
                if ($result === false) {
                    return;
                }
            }

            $this->call($route['handler'], $params);
            return;
        }

        http_response_code(404);
        View::render('errors/404', [], '');
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        $routeParts = explode('/', trim($routePath, '/'));
        $requestParts = explode('/', trim($requestPath, '/'));

        if (count($routeParts) !== count($requestParts)) {
            return null;
        }

        $params = [];
        foreach ($routeParts as $i => $part) {
            if (preg_match('/^\{(\w+)\}$/', $part, $m)) {
                $params[$m[1]] = $requestParts[$i];
            } elseif ($part !== $requestParts[$i]) {
                return null;
            }
        }

        return $params;
    }

    private function call($handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $params);
            return;
        }

        [$class, $method] = $handler;
        $controller = new $class();
        $controller->$method($params);
    }
}
