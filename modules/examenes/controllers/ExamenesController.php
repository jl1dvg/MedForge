<?php

namespace Controllers;

require_once dirname(__DIR__, 2) . '/solicitudes/controllers/SolicitudController.php';

use Modules\Solicitudes\Controllers\SolicitudController as SolicitudesModuleController;
use PDO;

if (class_exists(__NAMESPACE__ . '\\ExamenesController', false)) {
    return;
}

/**
 * @deprecated Mantener solo por compatibilidad con integraciones legacy.
 *             El flujo activo de solicitudes vive en Modules\Solicitudes\Controllers\SolicitudController.
 */
class ExamenesController extends SolicitudesModuleController
{
    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }
}

