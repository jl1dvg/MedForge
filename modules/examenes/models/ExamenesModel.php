<?php

namespace Modules\Examenes\Models;

require_once dirname(__DIR__, 2) . '/solicitudes/models/SolicitudModel.php';

use Models\SolicitudModel;

if (class_exists(__NAMESPACE__ . '\\ExamenesModel', false)) {
    return;
}

/**
 * @deprecated Mantener solo por compatibilidad histórica.
 *             Usar Models\SolicitudModel como flujo principal.
 */
class ExamenesModel extends SolicitudModel
{
}

