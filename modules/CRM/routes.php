<?php

use Core\Router;
use Modules\CRM\Controllers\CRMController;

return static function (Router $router, ?\PDO $unusedPdo = null): void {
    $router->get('/crm', static function (\PDO $pdo): void {
        (new CRMController($pdo))->index();
    });

    $router->get('/crm/leads', static function (\PDO $pdo): void {
        (new CRMController($pdo))->listLeads();
    });

    $router->post('/crm/leads', static function (\PDO $pdo): void {
        (new CRMController($pdo))->createLead();
    });

    $router->post('/crm/leads/update', static function (\PDO $pdo): void {
        (new CRMController($pdo))->updateLead();
    });

    $router->post('/crm/leads/convert', static function (\PDO $pdo): void {
        (new CRMController($pdo))->convertLead();
    });

    $router->get('/crm/projects', static function (\PDO $pdo): void {
        (new CRMController($pdo))->listProjects();
    });

    $router->post('/crm/projects', static function (\PDO $pdo): void {
        (new CRMController($pdo))->createProject();
    });

    $router->post('/crm/projects/status', static function (\PDO $pdo): void {
        (new CRMController($pdo))->updateProjectStatus();
    });

    $router->get('/crm/tasks', static function (\PDO $pdo): void {
        (new CRMController($pdo))->listTasks();
    });

    $router->post('/crm/tasks', static function (\PDO $pdo): void {
        (new CRMController($pdo))->createTask();
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

    $router->get('/crm/proposals', static function (\PDO $pdo): void {
        (new CRMController($pdo))->listProposals();
    });

    $router->post('/crm/proposals', static function (\PDO $pdo): void {
        (new CRMController($pdo))->createProposal();
    });

    $router->post('/crm/proposals/status', static function (\PDO $pdo): void {
        (new CRMController($pdo))->updateProposalStatus();
    });
};
