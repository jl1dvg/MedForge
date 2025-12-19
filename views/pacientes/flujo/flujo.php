<?php
require_once __DIR__ . '/../../../bootstrap.php';

use Controllers\DashboardController;

$dashboardController = new DashboardController($pdo);
$username = $dashboardController->getAuthenticatedUser();

$pageTitle = 'Flujo de Pacientes';

if (!isset($styles) || !is_array($styles)) {
    $styles = [];
}

$styles[] = '/public/css/kanban-scroll.css';
$styles[] = 'https://cdn.jsdelivr.net/npm/pickadate@3.6.2/lib/themes/classic.css';
$styles[] = 'https://cdn.jsdelivr.net/npm/pickadate@3.6.2/lib/themes/classic.date.css';

if (!isset($scripts) || !is_array($scripts)) {
    $scripts = [];
}

array_push(
    $scripts,
    'https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js',
    'https://cdn.jsdelivr.net/npm/sweetalert2@11',
    'https://cdn.jsdelivr.net/npm/pickadate@3.6.2/lib/picker.js',
    'https://cdn.jsdelivr.net/npm/pickadate@3.6.2/lib/picker.date.js',
    '/modules/Flujo/js/kanban_base.js'
);

$viewPath = __DIR__ . '/partials/flujo_content.php';

include BASE_PATH . '/views/layout.php';
