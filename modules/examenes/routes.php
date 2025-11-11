<?php

use Controllers\ExamenesController;
use Core\Router;

return function (Router $router) {
    $router->get('/solicitudes', function (\PDO $pdo) {
        (new ExamenesController($pdo))->index();
    });

    $router->get('/solicitudes/turnero', function (\PDO $pdo) {
        (new ExamenesController($pdo))->turnero();
    });

    $router->post('/solicitudes/kanban-data', function (\PDO $pdo) {
        (new ExamenesController($pdo))->kanbanData();
    });

    $router->post('/solicitudes/actualizar-estado', function (\PDO $pdo) {
        (new ExamenesController($pdo))->actualizarEstado();
    });

    $router->post('/solicitudes/notificaciones/recordatorios', function (\PDO $pdo) {
        (new ExamenesController($pdo))->enviarRecordatorios();
    });

    $router->get('/solicitudes/prefactura', function (\PDO $pdo) {
        (new ExamenesController($pdo))->prefactura();
    });

    $router->get('/solicitudes/turnero-data', function (\PDO $pdo) {
        (new ExamenesController($pdo))->turneroData();
    });

    $router->post('/solicitudes/turnero-llamar', function (\PDO $pdo) {
        (new ExamenesController($pdo))->turneroLlamar();
    });

    $router->get('/solicitudes/{id}/crm', function (\PDO $pdo, $solicitudId) {
        (new ExamenesController($pdo))->crmResumen((int) $solicitudId);
    });

    $router->post('/solicitudes/{id}/crm', function (\PDO $pdo, $solicitudId) {
        (new ExamenesController($pdo))->crmGuardarDetalles((int) $solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/notas', function (\PDO $pdo, $solicitudId) {
        (new ExamenesController($pdo))->crmAgregarNota((int) $solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/tareas', function (\PDO $pdo, $solicitudId) {
        (new ExamenesController($pdo))->crmGuardarTarea((int) $solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/tareas/estado', function (\PDO $pdo, $solicitudId) {
        (new ExamenesController($pdo))->crmActualizarTarea((int) $solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/adjuntos', function (\PDO $pdo, $solicitudId) {
        (new ExamenesController($pdo))->crmSubirAdjunto((int) $solicitudId);
    });
};
