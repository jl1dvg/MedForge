<?php

use Core\Router;
use Modules\WhatsApp\Controllers\AutoresponderController;
use Modules\WhatsApp\Controllers\ChatController;
use Modules\WhatsApp\Controllers\InboxController;
use Modules\WhatsApp\Controllers\TemplateController;
use Modules\WhatsApp\Controllers\WebhookController;

return static function (Router $router): void {
    $router->get('/whatsapp/autoresponder', static function (\PDO $pdo): void {
        (new AutoresponderController($pdo))->index();
    });

    $router->post('/whatsapp/autoresponder', static function (\PDO $pdo): void {
        (new AutoresponderController($pdo))->update();
    });

    $router->get('/whatsapp/templates', static function (\PDO $pdo): void {
        (new TemplateController($pdo))->index();
    });

    $router->get('/whatsapp/chat', static function (\PDO $pdo): void {
        (new ChatController($pdo))->index();
    });

    $router->get('/whatsapp/api/conversations', static function (\PDO $pdo): void {
        (new ChatController($pdo))->listConversations();
    });

    $router->get('/whatsapp/api/conversations/{conversationId}', static function (\PDO $pdo, string $conversationId): void {
        (new ChatController($pdo))->showConversation((int) $conversationId);
    });

    $router->get('/whatsapp/api/inbox', static function (\PDO $pdo): void {
        (new InboxController($pdo))->index();
    });

    $router->post('/whatsapp/api/messages', static function (\PDO $pdo): void {
        (new ChatController($pdo))->sendMessage();
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

    $router->match(['GET', 'POST'], '/whatsapp/webhook', static function (\PDO $pdo): void {
        (new WebhookController($pdo))->handle();
    });
};
