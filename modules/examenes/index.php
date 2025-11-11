<?php
/**
 * Inicializador del módulo de Exámenes.
 */

$modulePath = __DIR__;

define('EXAMENES_PATH', $modulePath);
define('EXAMENES_VIEWS', $modulePath . '/views');
define('EXAMENES_CONTROLLERS', $modulePath . '/controllers');
define('EXAMENES_MODELS', $modulePath . '/models');
define('EXAMENES_HELPERS', $modulePath . '/helpers');

// Cargar dependencias base del módulo.
require_once EXAMENES_MODELS . '/ExamenModel.php';
require_once EXAMENES_CONTROLLERS . '/ExamenController.php';

// Registrar rutas del módulo.
require_once $modulePath . '/routes.php';
