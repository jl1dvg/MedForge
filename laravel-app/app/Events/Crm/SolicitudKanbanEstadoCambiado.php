<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SolicitudKanbanEstadoCambiado
{
    use Dispatchable, SerializesModels;

    /**
     * Fired every time a solicitud moves to a new kanban stage.
     *
     * @param string $kanbanSlug  Normalized slug from SolicitudesStateMachineService:
     *                            recibida | llamado | en-atencion | revision-codigos |
     *                            espera-documentos | apto-oftalmologo | apto-anestesia |
     *                            listo-para-agenda | programada | completado
     */
    public function __construct(
        public readonly int    $solicitudId,
        public readonly string $kanbanSlug,
        public readonly string $estadoAnterior,
        public readonly ?int   $actorUserId = null,
    ) {}
}
