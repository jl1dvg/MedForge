<?php

use Core\Router;
use Modules\Agenda\Controllers\AgendaController;

return static function (Router $router): void {
    $router->get('/agenda', static function (\PDO $pdo): void {
        (new AgendaController($pdo))->index();
    });

    $router->get('/agenda/visitas/{visitaId}', static function (\PDO $pdo, string $visitaId): void {
        (new AgendaController($pdo))->mostrarVisita((int) $visitaId);
    });
};
