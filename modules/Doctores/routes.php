<?php

use Core\Router;
use Modules\Doctores\Controllers\DoctoresController;

return static function (Router $router): void {
    $router->get('/doctores', static function (\PDO $pdo): void {
        (new DoctoresController($pdo))->index();
    });

    $router->get('/doctores/{doctor}', static function (\PDO $pdo, string $doctorId): void {
        (new DoctoresController($pdo))->show((int) $doctorId);
    });
};
