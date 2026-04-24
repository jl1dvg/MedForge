<?php

use Core\Router;

return function (Router $router) {
    $redirectToV2 = static function (string $target, int $status = 302): void {
        $queryString = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
        if ($queryString !== '') {
            $target .= (str_contains($target, '?') ? '&' : '?') . $queryString;
        }

        header('Location: ' . $target, true, $status);
        exit;
    };

    $retiredJson = static function (): void {
        header('Content-Type: application/json; charset=utf-8', true, 410);
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint legacy de Solicitudes retirado. Usa /v2/solicitudes.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    };

    $router->get('/solicitudes', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes');
    });

    $router->get('/solicitudes/dashboard', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/dashboard');
    });

    $router->get('/solicitudes/turnero', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/turnero');
    });

    $router->get('/turneros/unificado', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/turnero');
    });

    $router->get('/solicitudes/kanban-data', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/kanban-data');
    });

    $router->post('/solicitudes/kanban-data', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/kanban-data', 307);
    });

    $router->get('/solicitudes/conciliacion-cirugias', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/conciliacion-cirugias');
    });

    $router->post('/solicitudes/{id}/conciliacion-cirugia/confirmar', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/conciliacion-cirugia/confirmar', 307);
    });

    $router->post('/solicitudes/reportes/pdf', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/reportes/pdf', 307);
    });

    $router->post('/solicitudes/reportes/excel', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/reportes/excel', 307);
    });

    $router->post('/solicitudes/actualizar-estado', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/actualizar-estado', 307);
    });

    $router->post('/solicitudes/re-scrape-derivacion', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/re-scrape-derivacion', 307);
    });

    $router->post('/solicitudes/derivacion-preseleccion', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/derivacion-preseleccion', 307);
    });

    $router->post('/solicitudes/derivacion-preseleccion/guardar', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/derivacion-preseleccion/guardar', 307);
    });

    $router->post('/solicitudes/cobertura-mail', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/cobertura-mail', 307);
    });

    $router->post('/solicitudes/notificaciones/recordatorios', function (\PDO $pdo) use ($retiredJson) {
        $retiredJson();
    });

    $router->get('/solicitudes/api/estado', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/api/estado');
    });

    $router->post('/solicitudes/api/estado', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/api/estado', 307);
    });

    $router->get('/solicitudes/prefactura', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/prefactura');
    });

    $router->get('/solicitudes/derivacion', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/derivacion');
    });

    $router->get('/solicitudes/turnero-data', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/turnero-data');
    });

    $router->post('/solicitudes/turnero-llamar', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/turnero-llamar', 307);
    });

    $router->post('/solicitudes/dashboard-data', function (\PDO $pdo) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/dashboard-data', 307);
    });

    $router->get('/solicitudes/{id}/crm', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/crm');
    });

    $router->post('/solicitudes/{id}/crm', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/crm', 307);
    });

    $router->post('/solicitudes/{id}/crm/bootstrap', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/crm/bootstrap', 307);
    });

    $router->get('/solicitudes/{id}/crm/checklist-state', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/crm/checklist-state');
    });

    $router->post('/solicitudes/{id}/crm/checklist', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/crm/checklist', 307);
    });

    $router->post('/solicitudes/{id}/crm/notas', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/crm/notas', 307);
    });

    $router->post('/solicitudes/{id}/crm/bloqueo', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/crm/bloqueo', 307);
    });

    $router->post('/solicitudes/{id}/crm/tareas', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/crm/tareas', 307);
    });

    $router->post('/solicitudes/{id}/crm/tareas/estado', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/crm/tareas/estado', 307);
    });

    $router->post('/solicitudes/{id}/crm/adjuntos', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/crm/adjuntos', 307);
    });

    $router->post('/solicitudes/{id}/cirugia', function (\PDO $pdo, $solicitudId) use ($redirectToV2) {
        $redirectToV2('/v2/solicitudes/' . rawurlencode((string) $solicitudId) . '/cirugia', 307);
    });
};
