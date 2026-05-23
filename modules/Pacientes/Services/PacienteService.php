<?php
// Compatibility shim — Pacientes module migrated to Laravel in Onda 4.
// Legacy modules (Billing, Consulta, Flujo) still reference this class.
// This shim delegates to the Laravel implementation via inheritance.

namespace Modules\Pacientes\Services;

use App\Modules\Pacientes\Services\PacientesParityService;

/**
 * @deprecated Use App\Modules\Pacientes\Services\PacientesParityService directly.
 *             Remove when Billing/Consulta/Flujo migrate to Laravel.
 */
class PacienteService extends PacientesParityService
{
    // Inherits all methods from PacientesParityService.
}
