<?php

namespace Core;

class Router
{
    private array $routes = [];
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function get($path, $callback)
    {
        $this->routes['GET'][$path] = $callback;
    }

    public function post($path, $callback)
    {
        $this->routes['POST'][$path] = $callback;
    }

    public function match($methods, $path, $callback): void
    {
        $methods = (array) $methods;
        foreach ($methods as $method) {
            $method = strtoupper(ltrim($method, '/'));
            $this->routes[$method][$path] = $callback;
        }
    }

    public function dispatch($method, $uri, $silent = false)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (isset($this->routes[$method][$path])) {
            $callback = $this->routes[$method][$path];
            call_user_func($callback, $this->pdo);
            return true;
        }

        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $routePath => $callback) {
                if (strpos($routePath, '{') === false) {
                    continue;
                }

                $pattern = preg_replace('#\{[^/]+\}#', '([^/]+)', $routePath);
                $pattern = '#^' . $pattern . '$#';

                if (preg_match($pattern, $path, $matches)) {
                    array_shift($matches);
                    call_user_func_array($callback, array_merge([$this->pdo], $matches));
                    return true;
                }
            }
        }

        if (!$silent) {
            http_response_code(404);
            echo "Ruta no encontrada: $path";
        }

        return false;
    }
}
