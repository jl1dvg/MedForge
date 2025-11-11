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

if ($resolvedClass === 'Models\\ExamenesModel' && !class_exists('ExamenesModel', false)) {
    class_alias($resolvedClass, 'ExamenesModel');
}

require_once SOLICITUDES_CONTROLLERS . '/SolicitudController.php';
require_once SOLICITUDES_HELPERS . '/SolicitudHelper.php';

// Registrar rutas del módulo
require_once $modulePath . '/routes.php';
