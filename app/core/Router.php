<?php
// app/core/Router.php

namespace App\Core;

class Router {
    private array $routes = [];
    private $notFoundHandler = null;

    public function get(string $path, $handler, ?string $moduleCode = null, string $permission = 'view'): void {
        $this->addRoute('GET', $path, $handler, $moduleCode, $permission);
    }

    public function post(string $path, $handler, ?string $moduleCode = null, string $permission = 'add'): void {
        $this->addRoute('POST', $path, $handler, $moduleCode, $permission);
    }

    private function addRoute(string $method, string $path, $handler, ?string $moduleCode, string $permission): void {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'pattern'    => $pattern,
            'handler'    => $handler,
            'module'     => $moduleCode,
            'permission' => $permission,
        ];
    }

    public function setNotFound($handler): void {
        $this->notFoundHandler = $handler;
    }

    public function getRoutes(): array {
        return $this->routes;
    }

    public function dispatch(string $uri, string $method): void {
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        // Strip query string
        if (false !== ($pos = strpos($uri, '?'))) {
            $uri = substr($uri, 0, $pos);
        }

        // Normalize base path
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = str_replace('\\', '/', $scriptDir);
        if ($scriptDir === '/' || $scriptDir === '.') {
            $scriptDir = '';
        }
        if (!empty($scriptDir)) {
            while (str_starts_with($uri, $scriptDir . '/') || $uri === $scriptDir) {
                $uri = substr($uri, strlen($scriptDir));
            }
        }

        $uri = '/' . trim($uri, '/');
        if ($uri === '//' || $uri === '/index.php') {
            $uri = '/';
        }

        // Central CSRF protection for all POST requests (excluding /api/*)
        if ($method === 'POST' && !str_starts_with($uri, '/api/')) {
            CSRF::validateRequest();
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = [];
                foreach ($matches as $key => $val) {
                    if (is_string($key)) {
                        $params[$key] = $val;
                    }
                }

                // Enforce module guard if route is guarded
                if ($route['module'] !== null) {
                    if ($route['module'] === 'super_admin') {
                        ModuleGuard::enforceSuperAdmin();
                    } else {
                        ModuleGuard::enforce($route['module'], $route['permission']);
                    }
                }

                $this->executeHandler($route['handler'], $params);
                return;
            }
        }

        // 404
        if ($this->notFoundHandler) {
            $this->executeHandler($this->notFoundHandler, []);
        } else {
            http_response_code(404);
            $viewPath = __DIR__ . '/../views/errors/404.php';
            if (file_exists($viewPath)) {
                require $viewPath;
            } else {
                echo "<h1>404 Not Found</h1>";
            }
        }
    }

    private function executeHandler($handler, array $params): void {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
        } elseif (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            call_user_func_array([$controller, $method], $params);
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler);
            $controller = new $class();
            call_user_func_array([$controller, $method], $params);
        }
    }
}
