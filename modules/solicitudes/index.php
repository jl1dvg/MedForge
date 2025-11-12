<?php
/**
 * Inicializador del módulo de Solicitudes
 */

$moduleName = 'solicitudes';
$modulePath = __DIR__;

define('SOLICITUDES_PATH', $modulePath);
define('SOLICITUDES_VIEWS', $modulePath . '/views');
define('SOLICITUDES_CONTROLLERS', $modulePath . '/controllers');
define('SOLICITUDES_MODELS', $modulePath . '/models');
define('SOLICITUDES_HELPERS', $modulePath . '/helpers');

// Cargar archivos base
$examenesModelCandidates = [
    dirname(__DIR__) . '/examenes/models/ExamenesModel.php',
    dirname(__DIR__) . '/examenes/Models/ExamenesModel.php',
    SOLICITUDES_MODELS . '/ExamenesModel.php',
];

$examenesClassCandidates = [
    'ExamenesModel',
    'Modules\\Examenes\\Models\\ExamenesModel',
    'Models\\ExamenesModel',
];

$resolvedClass = null;

foreach ($examenesClassCandidates as $className) {
    if (class_exists($className, false)) {
        $resolvedClass = $className;
        break;
    }
}

foreach ($examenesModelCandidates as $modelPath) {
    if ($resolvedClass) {
        break;
    }

    if (!is_file($modelPath)) {
        continue;
    }

    require_once $modelPath;

    foreach ($examenesClassCandidates as $className) {
        if (class_exists($className, false)) {
            $resolvedClass = $className;
            break 2;
        }
    }
}

if (!$resolvedClass) {
    $pathsList = implode(", ", $examenesModelCandidates);
    throw new RuntimeException("No se pudo cargar ExamenesModel. Rutas probadas: {$pathsList}");
}

if (
    in_array($resolvedClass, ['Models\\ExamenesModel', 'Modules\\Examenes\\Models\\ExamenesModel'], true)
    && !class_exists('ExamenesModel', false)
) {
    class_alias($resolvedClass, 'ExamenesModel');
}

// Asegurarnos de que el controlador y servicios compartidos de Exámenes estén cargados.
$sharedDependencies = [
    dirname(__DIR__) . '/examenes/controllers/ExamenesController.php',
    __DIR__ . '/services/ExamenesCrmService.php',
    __DIR__ . '/services/ExamenesReminderService.php',
];

foreach ($sharedDependencies as $dependencyPath) {
    if (is_file($dependencyPath)) {
        require_once $dependencyPath;
    }
}

require_once SOLICITUDES_CONTROLLERS . '/SolicitudController.php';
require_once SOLICITUDES_HELPERS . '/SolicitudHelper.php';

// Registrar rutas del módulo
require_once $modulePath . '/routes.php';
