<?php

use Core\Router;
use Modules\Autoresponder\Controllers\AutoresponderController;
use Modules\Autoresponder\Controllers\FlowmakerController;

return static function (Router $router): void {
    $router->get('/whatsapp/autoresponder', static function (\PDO $pdo): void {
        (new AutoresponderController($pdo))->index();
    });

    $router->post('/whatsapp/autoresponder', static function (\PDO $pdo): void {
        (new AutoresponderController($pdo))->update();
    });

    $router->get('/whatsapp/flowmaker', static function (\PDO $pdo): void {
        (new FlowmakerController($pdo))->index();
    });

    $router->get('/whatsapp/api/flowmaker/contract', static function (\PDO $pdo): void {
        (new FlowmakerController($pdo))->contract();
    });

    $router->post('/whatsapp/api/flowmaker/publish', static function (\PDO $pdo): void {
        (new FlowmakerController($pdo))->publish();
    });
};
