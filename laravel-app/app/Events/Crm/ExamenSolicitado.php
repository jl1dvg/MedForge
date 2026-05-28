<?php

namespace App\Events\Crm;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamenSolicitado
{
    use Dispatchable, SerializesModels;

    /**
     * Se dispara cuando un examen queda ordenado sin pago ni confirmación operativa.
     *
     * @param array<string, mixed> $examenData  id, paciente_nombre, paciente_cedula, paciente_telefono, descripcion_examen
     */
    public function __construct(
        public readonly int $examenId,
        public readonly array $examenData,
    ) {}
}
