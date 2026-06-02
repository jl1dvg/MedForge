<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . "/../../bootstrap.php";
require_once __DIR__ . '/../../controllers/PalabraClaveController.php';
require_once __DIR__ . '/../../controllers/DiagnosticoController.php';
require_once __DIR__ . '/../../models/PalabraClaveModel.php';
require_once __DIR__ . '/../../models/DiagnosticoModel.php';

// Habilitar errores para depuración
use Helpers\CorsHelper;

header('Content-Type: application/json; charset=UTF-8');

CorsHelper::prepare('EXTENSION_ALLOWED_ORIGINS', [
    'https://cive.consulmed.me',
    'https://asistentecive.consulmed.me',
    'https://cive.ddns.net:8085',
    'http://192.168.1.13:8085',
]);

use Controllers\SugerenciaController;
use Controllers\DiagnosticoController;
use Models\DiagnosticoModel;
use Models\PalabraClaveModel;

global $pdo;
$sugerenciaController = new SugerenciaController($pdo);

var_dump(class_exists('controllers\PalabraClaveController'));

try {
    // Obtener el JSON enviado
    $input = json_decode(file_get_contents("php://input"), true);

    // Validar que venga el campo esperado
    if (!isset($input['examen_fisico'])) {
        throw new Exception('Falta el campo examen_fisico');
    }

    $texto = trim($input['examen_fisico']);

    $sugerencias = $sugerenciaController->sugerirDiagnosticos($texto);

    echo json_encode([
        'success' => true,
        'sugerencias' => $sugerencias
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}