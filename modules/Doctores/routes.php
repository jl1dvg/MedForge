<?php

use Core\Router;
use Modules\Doctores\Controllers\DoctoresController;

return static function (Router $router): void {
    $router->get('/doctores', static function (\PDO $pdo): void {
        (new DoctoresController($pdo))->index();
    });
};
