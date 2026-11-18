<?php

declare(strict_types=1);

namespace HuitSchedule\Core;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, regex: string, handler: array{0: class-string, 1: string}|callable, middlewares: string[]}> */
    private array $routes = [];

    /** @var array<string, callable> */
    private array $middlewares = [];

    public function registerMiddleware(string $name, callable $handler): void
    {
        $this->middlewares[$name] = $handler;
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param string[] $middlewares
     */
    public function get(string $pattern, array|callable $handler, array $middlewares = []): void
    {
        $this->add('GET', $pattern, $handler, $middlewares);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param string[] $middlewares
     */
    public function post(string $pattern, array|callable $handler, array $middlewares = []): void
    {
        $this->add('POST', $pattern, $handler, $middlewares);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param string[] $middlewares
     */
    public function delete(string $pattern, array|callable $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middlewares);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param string[] $middlewares
     */
    private function add(string $method, string $pattern, array|callable $handler, array $middlewares): void
    {
        $normalized = '/' . trim($pattern, '/');
        if ($normalized === '') {
            $normalized = '/';
        }

        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $normalized);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method'      => strtoupper($method),
            'pattern'     => $normalized,
            'regex'       => $regex,
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['DELETE', 'PUT', 'PATCH'], true)) {
                $method = $override;
            }
        }

        $path = current_path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                // Enforce CSRF automatically on state-changing requests
                if (in_array($method, ['POST', 'DELETE', 'PUT', 'PATCH'], true)) {
                    Csrf::enforce();
                }

                foreach ($route['middlewares'] as $mwName) {
                    if (isset($this->middlewares[$mwName])) {
                        ($this->middlewares[$mwName])();
                    }
                }

                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $controller->$action(...array_values($params));
                } else {
                    $handler(...array_values($params));
                }

                if ($method === 'GET' && !str_starts_with($path, '/api/')) {
                    Flash::clearOld();
                }
                return;
            }
        }

        if (str_starts_with($path, '/api/')) {
            Response::jsonError('API endpoint không tồn tại: ' . $path, 404);
            return;
        }

        throw new \RuntimeException('Không tìm thấy trang yêu cầu: ' . $path, 404);
    }
}
