<?php

use Modules\Solicitudes\Controllers\SolicitudController;
use Core\Router;

return function (Router $router) {
    $router->get('/solicitudes', function (\PDO $pdo) {
        (new SolicitudController($pdo))->index();
    });

    $router->get('/solicitudes/dashboard', function (\PDO $pdo) {
        (new SolicitudController($pdo))->dashboard();
    });

    $router->get('/solicitudes/turnero', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turnero();
    });

    $router->get('/turneros/unificado', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turneroUnificado();
    });

    $router->get('/solicitudes/kanban-data', function (\PDO $pdo) {
        (new SolicitudController($pdo))->kanbanData();
    });

    $router->post('/solicitudes/kanban-data', function (\PDO $pdo) {
        (new SolicitudController($pdo))->kanbanData();
    });

    $router->post('/solicitudes/reportes/pdf', function (\PDO $pdo) {
        (new SolicitudController($pdo))->reportePdf();
    });

    $router->post('/solicitudes/reportes/excel', function (\PDO $pdo) {
        (new SolicitudController($pdo))->reporteExcel();
    });

    $router->post('/solicitudes/actualizar-estado', function (\PDO $pdo) {
        (new SolicitudController($pdo))->actualizarEstado();
    });

    $router->post('/solicitudes/re-scrape-derivacion', function (\PDO $pdo) {
        (new SolicitudController($pdo))->rescrapeDerivacion();
    });

    $router->post('/solicitudes/derivacion-preseleccion', function (\PDO $pdo) {
        (new SolicitudController($pdo))->derivacionPreseleccion();
    });

    $router->post('/solicitudes/derivacion-preseleccion/guardar', function (\PDO $pdo) {
        (new SolicitudController($pdo))->guardarDerivacionPreseleccion();
    });

    $router->post('/solicitudes/cobertura-mail', function (\PDO $pdo) {
        (new SolicitudController($pdo))->enviarCoberturaMail();
    });

    $router->post('/solicitudes/notificaciones/recordatorios', function (\PDO $pdo) {
        (new SolicitudController($pdo))->enviarRecordatorios();
    });

    $router->get('/solicitudes/api/estado', function (\PDO $pdo) {
        (new SolicitudController($pdo))->apiEstadoGet();
    });

    $router->post('/solicitudes/api/estado', function (\PDO $pdo) {
        (new SolicitudController($pdo))->apiEstadoPost();
    });

    $router->get('/solicitudes/prefactura', function (\PDO $pdo) {
        (new SolicitudController($pdo))->prefactura();
    });

    $router->get('/solicitudes/derivacion', function (\PDO $pdo) {
        (new SolicitudController($pdo))->derivacionDetalle();
    });

    $router->get('/solicitudes/turnero-data', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turneroData();
    });

    $router->post('/solicitudes/turnero-llamar', function (\PDO $pdo) {
        (new SolicitudController($pdo))->turneroLlamar();
    });

    $router->post('/solicitudes/dashboard-data', function (\PDO $pdo) {
        (new SolicitudController($pdo))->dashboardData();
    });

    $router->get('/solicitudes/{id}/crm', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmResumen((int)$solicitudId);
    });

    $router->post('/solicitudes/{id}/crm', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmGuardarDetalles((int)$solicitudId);
    });
    $router->post('/solicitudes/{id}/crm/bootstrap', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmBootstrap((int)$solicitudId);
    });
    $router->get('/solicitudes/{id}/crm/checklist-state', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmChecklistState((int)$solicitudId);
    });
    $router->post('/solicitudes/{id}/crm/checklist', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmActualizarChecklist((int)$solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/notas', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmAgregarNota((int)$solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/bloqueo', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmRegistrarBloqueo((int)$solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/tareas', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmGuardarTarea((int)$solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/tareas/estado', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmActualizarTarea((int)$solicitudId);
    });

    $router->post('/solicitudes/{id}/crm/adjuntos', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->crmSubirAdjunto((int)$solicitudId);
    });

    $router->post('/solicitudes/{id}/cirugia', function (\PDO $pdo, $solicitudId) {
        (new SolicitudController($pdo))->guardarDetallesCirugia((int)$solicitudId);
    });
};
