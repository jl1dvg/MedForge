<?php
// Legacy billing report view relocated under modules/Billing/views/informes.
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 5) . '/bootstrap.php';
}
require_once BASE_PATH . '/helpers/InformesHelper.php';

use Controllers\BillingController;

$formId = $_GET['form_id'] ?? null;
if (!$formId) {
    echo "❌ form_id no proporcionado.";
    exit;
}

$controller = new BillingController($pdo);
$datos = $controller->obtenerDatos($formId);

if (!$datos || !is_array($datos) || !isset($datos['protocoloExtendido'])) {
    echo "<div class='alert alert-danger'>❌ No se pudieron cargar los datos de la factura. Verifica si existe el protocolo para el form_id: <strong>" . htmlspecialchars($formId) . "</strong></div>";
    exit;
}

// Renderiza el detalle
include __DIR__ . '/../components/detalle_factura_iess.php';