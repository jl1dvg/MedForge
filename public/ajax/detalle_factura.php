<?php
require_once '../../bootstrap.php';
require_once '../../controllers/BillingController.php';

use Controllers\BillingController;

$formId = $_GET['form_id'] ?? null;

if (!$formId) {
    http_response_code(400);
    echo '❌ Formulario no especificado.';
    exit;
}

$controller = new BillingController($pdo);
$datos = $controller->obtenerDatos($formId);

if (!$datos) {
    echo "<div class='alert alert-warning'>⚠️ No se encontraron datos para el form_id {$formId}</div>";
    exit;
}

// Reutiliza la vista de detalle de factura
ob_start();
include '../../views/billing/components/detalle_factura.php';
$html = ob_get_clean();
echo $html;