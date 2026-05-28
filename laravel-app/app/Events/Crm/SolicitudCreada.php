<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SolicitudCreada
{
    use Dispatchable, SerializesModels;

    /**
     * @param array<string, mixed> $solicitudData  Snapshot mínimo: id, paciente_nombre, paciente_cedula, paciente_telefono, servicio
     */
    public function __construct(
        public readonly int $solicitudId,
        public readonly array $solicitudData,
    ) {}
}
