<?php

use Core\Router;
use Modules\CRM\Controllers\CRMController;
use PDO;

return function (Router $router) {
    $router->get('/crm/leads', function (PDO $pdo) {
        (new CRMController($pdo))->listLeads();
    });

    $router->post('/crm/leads', function (PDO $pdo) {
        (new CRMController($pdo))->createLead();
    });

    $router->post('/crm/leads/update', function (PDO $pdo) {
        (new CRMController($pdo))->updateLead();
    });

    $router->post('/crm/leads/convert', function (PDO $pdo) {
        (new CRMController($pdo))->convertLead();
    });

    $router->get('/crm/projects', function (PDO $pdo) {
        (new CRMController($pdo))->listProjects();
    });

    $router->post('/crm/projects', function (PDO $pdo) {
        (new CRMController($pdo))->createProject();
    });

    $router->get('/crm/tasks', function (PDO $pdo) {
        (new CRMController($pdo))->listTasks();
    });

    $router->post('/crm/tasks', function (PDO $pdo) {
        (new CRMController($pdo))->createTask();
    });

    $router->post('/crm/tasks/status', function (PDO $pdo) {
        (new CRMController($pdo))->updateTaskStatus();
    });

    $router->get('/crm/tickets', function (PDO $pdo) {
        (new CRMController($pdo))->listTickets();
    });

    $router->post('/crm/tickets', function (PDO $pdo) {
        (new CRMController($pdo))->createTicket();
    });

    $router->post('/crm/tickets/reply', function (PDO $pdo) {
        (new CRMController($pdo))->replyTicket();
    });
};
