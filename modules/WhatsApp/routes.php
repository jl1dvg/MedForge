<?php

use Core\Router;
use Modules\WhatsApp\Controllers\ChatController;
use Modules\WhatsApp\Controllers\InboxController;
use Modules\WhatsApp\Controllers\KpiController;
use Modules\WhatsApp\Controllers\TemplateController;
use Modules\WhatsApp\Controllers\WebhookController;

return static function (Router $router): void {
    $router->get('/whatsapp/templates', static function (\PDO $pdo): void {
        (new TemplateController($pdo))->index();
    });

    $router->get('/whatsapp/chat', static function (\PDO $pdo): void {
        (new ChatController($pdo))->index();
    });

    $router->get('/whatsapp/dashboard', static function (\PDO $pdo): void {
        (new KpiController($pdo))->dashboard();
    });

    $router->get('/whatsapp/api/conversations', static function (\PDO $pdo): void {
        (new ChatController($pdo))->listConversations();
    });

    $router->get('/whatsapp/api/conversations/{conversationId}', static function (\PDO $pdo, string $conversationId): void {
        (new ChatController($pdo))->showConversation((int) $conversationId);
    });

    $router->get('/whatsapp/api/agents', static function (\PDO $pdo): void {
        (new ChatController($pdo))->listAgents();
    });

    $router->get('/whatsapp/api/agent-presence', static function (\PDO $pdo): void {
        (new ChatController($pdo))->getAgentPresence();
    });

    $router->post('/whatsapp/api/agent-presence', static function (\PDO $pdo): void {
        (new ChatController($pdo))->updateAgentPresence();
    });

    $router->get('/whatsapp/api/inbox', static function (\PDO $pdo): void {
        (new InboxController($pdo))->index();
    });

    $router->get('/whatsapp/api/kpis', static function (\PDO $pdo): void {
        (new KpiController($pdo))->index();
    });

    $router->get('/whatsapp/api/kpis/drilldown', static function (\PDO $pdo): void {
        (new KpiController($pdo))->drilldown();
    });

    $router->post('/whatsapp/api/messages', static function (\PDO $pdo): void {
        (new ChatController($pdo))->sendMessage();
    });

    $router->get('/whatsapp/api/media/{mediaId}', static function (\PDO $pdo, string $mediaId): void {
        (new ChatController($pdo))->streamMedia($mediaId);
    });

    $router->post('/whatsapp/api/conversations/{conversationId}/assign', static function (\PDO $pdo, string $conversationId): void {
        (new ChatController($pdo))->assignConversation((int) $conversationId);
    });

    $router->post('/whatsapp/api/conversations/{conversationId}/transfer', static function (\PDO $pdo, string $conversationId): void {
        (new ChatController($pdo))->transferConversation((int) $conversationId);
    });

    $router->post('/whatsapp/api/conversations/{conversationId}/close', static function (\PDO $pdo, string $conversationId): void {
        (new ChatController($pdo))->closeConversation((int) $conversationId);
    });

    $router->post('/whatsapp/api/conversations/{conversationId}/delete', static function (\PDO $pdo, string $conversationId): void {
        (new ChatController($pdo))->deleteConversation((int) $conversationId);
    });

    $router->get('/whatsapp/api/templates', static function (\PDO $pdo): void {
        (new TemplateController($pdo))->listTemplates();
    });

    $router->get('/whatsapp/api/patients', static function (\PDO $pdo): void {
        (new ChatController($pdo))->searchPatients();
    });

    $router->get('/whatsapp/api/chat-templates', static function (\PDO $pdo): void {
        (new ChatController($pdo))->listAvailableTemplates();
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
