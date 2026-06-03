<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\ListarProcedimientosController;
use Helpers\CorsHelper;

header('Content-Type: application/json; charset=UTF-8');

CorsHelper::prepare('EXTENSION_ALLOWED_ORIGINS', [
    'https://cive.consulmed.me',
    'https://asistentecive.consulmed.me',
    'https://cive.ddns.net:8085',
    'http://192.168.1.13:8085',
]);

$controller = new ListarProcedimientosController($pdo);
$afiliacion = $_GET['afiliacion'] ?? '';
$response = $controller->listar($afiliacion);
$response['afiliacion_recibida'] = $afiliacion;

echo json_encode($response);