<?php

use Core\Router;
use Modules\MailTemplates\Controllers\CoberturaMailTemplateController;

return static function (Router $router): void {
    $router->get('/mail-templates/cobertura', static function (\PDO $pdo): void {
        (new CoberturaMailTemplateController($pdo))->index();
    });

    $router->get('/mail-templates/cobertura/{key}', static function (\PDO $pdo, string $key): void {
        (new CoberturaMailTemplateController($pdo))->index($key);
    });

    $router->post('/mail-templates/cobertura/{key}', static function (\PDO $pdo, string $key): void {
        (new CoberturaMailTemplateController($pdo))->save($key);
    });

    $router->post('/mail-templates/cobertura/resolve', static function (\PDO $pdo): void {
        (new CoberturaMailTemplateController($pdo))->resolve();
    });
};
