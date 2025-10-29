<?php

namespace Core;

class Router
{
    private $routes = [];
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

    public function dispatch($method, $uri, $silent = false)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (isset($this->routes[$method][$path])) {
            $callback = $this->routes[$method][$path];
            call_user_func($callback, $this->pdo);
            return true;
        }

        if (!$silent) {
            http_response_code(404);
            echo "Ruta no encontrada: $path";
        }

        return false;
    }
}