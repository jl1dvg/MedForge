<?php

use Core\Router;

return static function (Router $router, \PDO $pdo): void {
    $router->match(['GET', 'POST'], '/settings', function (): void {
        $query = $_SERVER['QUERY_STRING'] ?? '';
        $target = '/v2/settings' . ($query !== '' ? '?' . $query : '');

        header('Location: ' . $target, true, 302);
        exit;
    });
};
