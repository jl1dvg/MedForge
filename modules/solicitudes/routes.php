<?php

use Controllers\SolicitudController;
use Core\Router;

return function (Router $router) {
    $router->get('/solicitudes', function (\PDO $pdo) {
        (new SolicitudController($pdo))->index();
    });

    $router->get('/solicitudes/turnero', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turnero();
    });

    $router->post('/solicitudes/kanban-data', function (\PDO $pdo) {
        (new SolicitudController($pdo))->kanbanData();
    });

    $router->post('/solicitudes/actualizar-estado', function (\PDO $pdo) {
        (new SolicitudController($pdo))->actualizarEstado();
    });

    $router->post('/solicitudes/notificaciones/recordatorios', function (\PDO $pdo) {
        (new SolicitudController($pdo))->enviarRecordatorios();
    });

    $router->get('/solicitudes/prefactura', function (\PDO $pdo) {
        (new SolicitudController($pdo))->prefactura();
    });

    $router->get('/solicitudes/turnero-data', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turneroData();
    });

    $router->post('/solicitudes/turnero-llamar', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turneroLlamar();
    });

    $router->get('/solicitudes/{id}/crm', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmResumen((int) $solicitudId);
    });

    $router->post('/solicitudes/{id}/crm', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmGuardarDetalles((int) $solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/notas', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmAgregarNota((int) $solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/tareas', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmGuardarTarea((int) $solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/tareas/estado', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmActualizarTarea((int) $solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/adjuntos', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmSubirAdjunto((int) $solicitudId);
    });
};
