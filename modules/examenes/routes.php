<?php

use Controllers\ExamenController;
use Core\Router;

return function (Router $router) {
    $router->get('/examenes', function (\PDO $pdo) {
        (new ExamenController($pdo))->index();
    });

    $router->get('/examenes/turnero', function (\PDO $pdo) {
        (new ExamenController($pdo))->turnero();
    });

    $router->get('/examenes/prefactura', function (\PDO $pdo) {
        (new ExamenController($pdo))->prefactura();
    });

    $router->post('/examenes/kanban-data', function (\PDO $pdo) {
        (new ExamenController($pdo))->kanbanData();
    });

    $router->post('/examenes/actualizar-estado', function (\PDO $pdo) {
        (new ExamenController($pdo))->actualizarEstado();
    });

    $router->post('/examenes/notificaciones/recordatorios', function (\PDO $pdo) {
        (new ExamenController($pdo))->enviarRecordatorios();
    });

    $router->get('/examenes/turnero-data', function (\PDO $pdo) {
        (new ExamenController($pdo))->turneroData();
    });

    $router->post('/examenes/turnero-llamar', function (\PDO $pdo) {
        (new ExamenController($pdo))->turneroLlamar();
    });

    $router->get('/examenes/{id}/crm', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmResumen((int) $examenId);
    });

    $router->post('/examenes/{id}/crm', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmGuardarDetalles((int) $examenId);
    });

    $router->post('/examenes/{id}/crm/notas', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmAgregarNota((int) $examenId);
    });

    $router->post('/examenes/{id}/crm/tareas', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmGuardarTarea((int) $examenId);
    });

    $router->post('/examenes/{id}/crm/tareas/estado', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmActualizarTarea((int) $examenId);
    });

    $router->post('/examenes/{id}/crm/adjuntos', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmSubirAdjunto((int) $examenId);
    });
};
