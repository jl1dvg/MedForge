<?php

use Core\Router;
use Modules\WhatsApp\Controllers\TemplateController;

return static function (Router $router): void {
    $router->get('/whatsapp/templates', static function (\PDO $pdo): void {
        (new TemplateController($pdo))->index();
    });

    $router->get('/whatsapp/api/templates', static function (\PDO $pdo): void {
        (new TemplateController($pdo))->listTemplates();
    });

    $router->post('/whatsapp/api/templates', static function (\PDO $pdo): void {
        (new TemplateController($pdo))->createTemplate();
    });

    $router->post('/whatsapp/api/templates/{templateId}', static function (\PDO $pdo, string $templateId): void {
        (new TemplateController($pdo))->updateTemplate($templateId);
    });

    $router->post('/whatsapp/api/templates/{templateId}/delete', static function (\PDO $pdo, string $templateId): void {
        (new TemplateController($pdo))->deleteTemplate($templateId);
    });
};
