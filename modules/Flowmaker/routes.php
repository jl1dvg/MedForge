<?php

use Core\Router;
use Modules\Flowmaker\Controllers\BuilderController;
use Modules\Flowmaker\Controllers\FlowController;

return static function (Router $router): void {
    $router->get('/flowmaker/flows', static function (\PDO $pdo): void {
        (new FlowController($pdo))->index();
    });

    $router->post('/flowmaker/flows', static function (\PDO $pdo): void {
        (new FlowController($pdo))->store();
    });

    $router->post('/flowmaker/flows/{flowId}/update', static function (\PDO $pdo, string $flowId): void {
        (new FlowController($pdo))->update((int) $flowId);
    });

    $router->get('/flowmaker/flows/{flowId}/delete', static function (\PDO $pdo, string $flowId): void {
        (new FlowController($pdo))->delete((int) $flowId);
    });

    $router->get('/flowmaker/builder/{flowId}', static function (\PDO $pdo, string $flowId): void {
        (new BuilderController($pdo))->show((int) $flowId);
    });

    $router->post('/flowmaker/update/{flowId}', static function (\PDO $pdo, string $flowId): void {
        (new BuilderController($pdo))->update((int) $flowId);
    });

    $router->post('/flowmakermedia', static function (\PDO $pdo): void {
        (new BuilderController($pdo))->uploadMedia();
    });

    $router->get('/flowmaker/script', static function (\PDO $pdo): void {
        (new BuilderController($pdo))->script();
    });

    $router->get('/flowmaker/css', static function (\PDO $pdo): void {
        (new BuilderController($pdo))->css();
    });
};
