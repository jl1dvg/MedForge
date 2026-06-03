<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamenEstadoCambiado
{
    use Dispatchable, SerializesModels;

    /**
     * Fired when a consulta_examenes record changes estado via the kanban.
     *
     * Known estados: recibido | revisión de cobertura | listo para agenda |
     *                turno_llamado | completado | archivado
     *                Legacy emitters may still send llamado.
     */
    public function __construct(
        public readonly int    $examenId,
        public readonly string $nuevoEstado,
        public readonly string $estadoAnterior,
        public readonly ?int   $actorUserId = null,
    ) {}
}
