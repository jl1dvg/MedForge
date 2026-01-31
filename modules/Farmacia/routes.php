<?php

use Core\Router;
use Modules\Farmacia\Controllers\FarmaciaController;

return static function (Router $router): void {
    $router->get('/farmacia', static function (\PDO $pdo): void {
        (new FarmaciaController($pdo))->index();
    });
};
