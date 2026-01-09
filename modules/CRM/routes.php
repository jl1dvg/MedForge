<?php

use Core\Router;
use Modules\CRM\Controllers\CRMController;

return static function (Router $router, ?\PDO $unusedPdo = null): void {
    $router->get('/crm', static function (\PDO $pdo): void {
        (new CRMController($pdo))->index();
    });

    $router->get('/leads', static function (\PDO $pdo): void {
        (new CRMController($pdo))->index();
    });

    $router->get('/crm/leads', static function (\PDO $pdo): void {
        (new CRMController($pdo))->listLeads();
    });

    $router->get('/crm/leads/meta', static function (\PDO $pdo): void {
        (new CRMController($pdo))->leadMeta();
    });

    $router->get('/crm/leads/metrics', static function (\PDO $pdo): void {
        (new CRMController($pdo))->leadMetrics();
    });

    $router->post('/crm/leads', static function (\PDO $pdo): void {
        (new CRMController($pdo))->createLead();
    });

    $router->post('/crm/leads/update', static function (\PDO $pdo): void {
        (new CRMController($pdo))->updateLead();
    });

    $router->match(['PUT'], '/crm/leads/{id}', static function (\PDO $pdo, string $id): void {
        (new CRMController($pdo))->updateLeadRecordById((int) $id);
    });

    $router->get('/crm/leads/{id}', static function (\PDO $pdo, string $id): void {
        (new CRMController($pdo))->showLead((int) $id);
    });

    $router->get('/crm/leads/{id}/profile', static function (\PDO $pdo, string $id): void {
        (new CRMController($pdo))->leadProfile((int) $id);
    });

    $router->match(['PATCH'], '/crm/leads/{id}/status', static function (\PDO $pdo, string $id): void {
        (new CRMController($pdo))->updateLeadStatus((int) $id);
    });

    $router->get('/crm/leads/{id}/mail/compose', static function (\PDO $pdo, string $id): void {
        (new CRMController($pdo))->composeLeadMail((int) $id);
    });

    $router->post('/crm/leads/{id}/mail/send-template', static function (\PDO $pdo, string $id): void {
        (new CRMController($pdo))->sendLeadMailTemplate((int) $id);
    });

    $router->post('/crm/leads/convert', static function (\PDO $pdo): void {
        (new CRMController($pdo))->convertLead();
    });

    $router->get('/crm/projects', static function (\PDO $pdo): void {
        (new CRMController($pdo))->listProjects();
    });

    $router->get('/crm/projects/{id}', static function (\PDO $pdo, string $id): void {
        (new CRMController($pdo))->showProject((int) $id);
    });

    $router->post('/crm/projects', static function (\PDO $pdo): void {
        (new CRMController($pdo))->createProject();
    });

    $router->post('/crm/projects/status', static function (\PDO $pdo): void {
        (new CRMController($pdo))->updateProjectStatus();
    });

    $router->match(['PATCH'], '/crm/projects/{id}', static function (\PDO $pdo, string $id): void {
        (new CRMController($pdo))->updateProject((int) $id);
    });

    $router->get('/crm/tasks', static function (\PDO $pdo): void {
        (new CRMController($pdo))->listTasks();
    });

    $router->post('/crm/tasks', static function (\PDO $pdo): void {
        (new CRMController($pdo))->createTask();
    });

    $router->match(['PATCH'], '/crm/tasks/{id}', static function (\PDO $pdo, string $id): void {
        (new CRMController($pdo))->updateTask((int) $id);
    });

    $router->post('/crm/tasks/status', static function (\PDO $pdo): void {
        (new CRMController($pdo))->updateTaskStatus();
    });

    $router->get('/crm/tickets', static function (\PDO $pdo): void {
        (new CRMController($pdo))->listTickets();
    });

    $router->post('/crm/tickets', static function (\PDO $pdo): void {
        (new CRMController($pdo))->createTicket();
    });

    $router->post('/crm/tickets/reply', static function (\PDO $pdo): void {
        (new CRMController($pdo))->replyTicket();
    });

    $router->get('/crm/proposals/{id}', static function (\PDO $pdo, string $id): void {
        (new CRMController($pdo))->getProposal((int) $id);
    });

    $router->get('/crm/proposals', static function (\PDO $pdo): void {
        (new CRMController($pdo))->listProposals();
    });

    $router->post('/crm/proposals/perfex/parse', static function (\PDO $pdo): void {
        (new CRMController($pdo))->parsePerfexEstimates();
    });

    $router->post('/crm/proposals', static function (\PDO $pdo): void {
        (new CRMController($pdo))->createProposal();
    });

    $router->post('/crm/proposals/status', static function (\PDO $pdo): void {
        (new CRMController($pdo))->updateProposalStatus();
    });
};
