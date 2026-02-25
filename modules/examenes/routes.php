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

    $router->get('/imagenes/examenes-realizados', function (\PDO $pdo) {
        (new ExamenController($pdo))->imagenesRealizadas();
    });

    $router->get('/imagenes/dashboard', function (\PDO $pdo) {
        (new ExamenController($pdo))->imagenesDashboard();
    });

    $router->get('/imagenes/examenes-realizados/nas/list', function (\PDO $pdo) {
        (new ExamenController($pdo))->imagenesNasList();
    });

    $router->post('/imagenes/examenes-realizados/nas/warm', function (\PDO $pdo) {
        (new ExamenController($pdo))->imagenesNasWarm();
    });

    $router->get('/imagenes/examenes-realizados/nas/file', function (\PDO $pdo) {
        (new ExamenController($pdo))->imagenesNasFile();
    });

    $router->post('/imagenes/examenes-realizados/actualizar', function (\PDO $pdo) {
        (new ExamenController($pdo))->actualizarImagenRealizada();
    });

    $router->post('/imagenes/examenes-realizados/eliminar', function (\PDO $pdo) {
        (new ExamenController($pdo))->eliminarImagenRealizada();
    });

    $router->get('/imagenes/informes/datos', function (\PDO $pdo) {
        (new ExamenController($pdo))->informeDatos();
    });

    $router->post('/imagenes/informes/guardar', function (\PDO $pdo) {
        (new ExamenController($pdo))->informeGuardar();
    });

    $router->get('/imagenes/informes/plantilla', function (\PDO $pdo) {
        (new ExamenController($pdo))->informePlantilla();
    });

    $router->get('/imagenes/informes/012b/pdf', function (\PDO $pdo) {
        (new ExamenController($pdo))->imprimirInforme012B();
    });
    $router->get('/imagenes/informes/012b/paquete', function (\PDO $pdo) {
        (new ExamenController($pdo))->imprimirInforme012BPaquete();
    });
    $router->post('/imagenes/informes/012b/paquete/seleccion', function (\PDO $pdo) {
        (new ExamenController($pdo))->imprimirInforme012BPaqueteSeleccion();
    });

    $router->post('/examenes/kanban-data', function (\PDO $pdo) {
        (new ExamenController($pdo))->kanbanData();
    });

    $router->post('/examenes/cobertura-mail', function (\PDO $pdo) {
        (new ExamenController($pdo))->enviarCoberturaMail();
    });

    $router->get('/examenes/cobertura-012a/pdf', function (\PDO $pdo) {
        (new ExamenController($pdo))->imprimirCobertura012A();
    });

    $router->post('/examenes/reportes/pdf', function (\PDO $pdo) {
        (new ExamenController($pdo))->reportePdf();
    });

    $router->post('/examenes/reportes/excel', function (\PDO $pdo) {
        (new ExamenController($pdo))->reporteExcel();
    });

    $router->post('/examenes/actualizar-estado', function (\PDO $pdo) {
        (new ExamenController($pdo))->actualizarEstado();
    });

    $router->post('/examenes/notificaciones/recordatorios', function (\PDO $pdo) {
        (new ExamenController($pdo))->enviarRecordatorios();
    });

    $router->post('/examenes/derivacion-preseleccion', function (\PDO $pdo) {
        (new ExamenController($pdo))->derivacionPreseleccion();
    });

    $router->post('/examenes/derivacion-preseleccion/guardar', function (\PDO $pdo) {
        (new ExamenController($pdo))->guardarDerivacionPreseleccion();
    });

    $router->get('/examenes/api/estado', function (\PDO $pdo) {
        (new ExamenController($pdo))->apiEstadoGet();
    });

    $router->post('/examenes/api/estado', function (\PDO $pdo) {
        (new ExamenController($pdo))->apiEstadoPost();
    });

    $router->get('/examenes/turnero-data', function (\PDO $pdo) {
        (new ExamenController($pdo))->turneroData();
    });

    $router->get('/examenes/derivacion', function (\PDO $pdo) {
        (new ExamenController($pdo))->derivacionDetalle();
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
    $router->post('/examenes/{id}/crm/bootstrap', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmBootstrap((int) $examenId);
    });
    $router->get('/examenes/{id}/crm/checklist-state', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmChecklistState((int) $examenId);
    });
    $router->post('/examenes/{id}/crm/checklist', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmActualizarChecklist((int) $examenId);
    });

    $router->post('/examenes/{id}/crm/notas', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmAgregarNota((int) $examenId);
    });

    $router->post('/examenes/{id}/crm/bloqueo', function (\PDO $pdo, $examenId) {
        (new ExamenController($pdo))->crmRegistrarBloqueo((int) $examenId);
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
