<?php

use Controllers\ProjectController;
use Controllers\TaskController;
use Core\Router;

return static function (Router $router, ?\PDO $unusedPdo = null): void {
    $router->get('/projects', static function (\PDO $pdo): void {
        (new ProjectController($pdo))->listProjects();
    });

    $router->post('/projects/create', static function (\PDO $pdo): void {
        (new ProjectController($pdo))->createProject();
    });

    $router->post('/projects/link', static function (\PDO $pdo): void {
        (new ProjectController($pdo))->linkProject();
    });

    $router->post('/projects/status', static function (\PDO $pdo): void {
        (new ProjectController($pdo))->updateStatus();
    });

    $router->get('/projects/{id}', static function (\PDO $pdo, string $id): void {
        (new ProjectController($pdo))->showProject((int) $id);
    });

    $router->get('/projects/{id}/tasks', static function (\PDO $pdo, string $id): void {
        (new ProjectController($pdo))->listTasks((int) $id);
    });

    $router->post('/projects/{id}/tasks', static function (\PDO $pdo, string $id): void {
        (new ProjectController($pdo))->createTask((int) $id);
    });

    $router->get('/tasks', static function (\PDO $pdo): void {
        (new TaskController($pdo))->listTasks();
    });

    $router->get('/tasks/summary', static function (\PDO $pdo): void {
        (new TaskController($pdo))->summary();
    });

    $router->get('/tasks/{id}', static function (\PDO $pdo, string $id): void {
        (new TaskController($pdo))->showTask((int) $id);
    });

    $router->post('/tasks/create', static function (\PDO $pdo): void {
        (new TaskController($pdo))->createTask();
    });

    $router->post('/tasks/update', static function (\PDO $pdo): void {
        (new TaskController($pdo))->updateTask();
    });

    $router->post('/tasks/status', static function (\PDO $pdo): void {
        (new TaskController($pdo))->updateStatus();
    });

    $router->post('/tasks/reschedule', static function (\PDO $pdo): void {
        (new TaskController($pdo))->reschedule();
    });

    $router->post('/tasks/whatsapp/send-template', static function (\PDO $pdo): void {
        (new TaskController($pdo))->sendWhatsAppTemplate();
    });

    $router->post('/tasks/whatsapp/open-chat', static function (\PDO $pdo): void {
        (new TaskController($pdo))->openChat();
    });

    $router->get('/projects/{id}/notes', static function (\PDO $pdo, string $id): void {
        (new ProjectController($pdo))->listNotes((int) $id);
    });

    $router->get('/projects/{id}/milestones', static function (\PDO $pdo, string $id): void {
        (new ProjectController($pdo))->listMilestones((int) $id);
    });

    $router->get('/projects/{id}/files', static function (\PDO $pdo, string $id): void {
        (new ProjectController($pdo))->listFiles((int) $id);
    });
};
