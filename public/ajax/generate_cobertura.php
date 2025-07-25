<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../controllers/PdfController.php';
require_once __DIR__ . '/../../models/SolicitudModel.php';
require_once __DIR__ . '/../../helpers/SolicitudHelper.php';
require_once __DIR__ . '/../../helpers/PdfGenerator.php';

use Controllers\PdfController;

// Capturar parámetros
$form_id = $_GET['form_id'] ?? null;
$hc_number = $_GET['hc_number'] ?? null;
//$modo = $_GET['modo'] ?? 'completo'; // 'completo' o 'separado'

if ($form_id && $hc_number) {
    $pdfController = new PdfController($pdo);
    $pdfController->generateCobertura($form_id, $hc_number);
} else {
    echo "Faltan parámetros";
}