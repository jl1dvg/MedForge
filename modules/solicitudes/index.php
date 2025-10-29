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
require_once SOLICITUDES_MODELS . '/SolicitudModel.php';
require_once SOLICITUDES_CONTROLLERS . '/SolicitudController.php';
require_once SOLICITUDES_HELPERS . '/SolicitudHelper.php';

// Registrar rutas del módulo
require_once $modulePath . '/routes.php';