<?php

use Core\Router;
use Modules\Search\Controllers\SearchController;

return static function (Router $router, ?\PDO $unused = null): void {
    $router->get('/search', static function (\PDO $pdo): void {
        (new SearchController($pdo))->index();
    });

    $router->post('/search/history/clear', static function (\PDO $pdo): void {
        (new SearchController($pdo))->clearHistory();
    });
};
