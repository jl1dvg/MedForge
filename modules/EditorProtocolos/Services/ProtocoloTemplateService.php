<?php

namespace Modules\EditorProtocolos\Services;

use Models\ProcedimientoModel;
use PDO;

class ProtocoloTemplateService
{
    private ProcedimientoModel $procedimientoModel;

    public function __construct(PDO $pdo)
    {
        $this->procedimientoModel = new ProcedimientoModel($pdo);
    }

    public function obtenerProcedimientosAgrupados(): array
    {
        return $this->procedimientoModel->obtenerProcedimientosAgrupados();
    }

    public function obtenerProtocoloPorId(string $id): ?array
    {
        return $this->procedimientoModel->obtenerProtocoloPorId($id);
    }

    public function obtenerMedicamentosDeProtocolo(string $id): array
    {
        return $this->procedimientoModel->obtenerMedicamentosDeProtocolo($id);
    }

    public function obtenerOpcionesMedicamentos(): array
    {
        return $this->procedimientoModel->obtenerOpcionesMedicamentos();
    }

    public function obtenerCategoriasInsumos(): array
    {
        return $this->procedimientoModel->obtenerCategoriasInsumos();
    }

    public function obtenerInsumosDisponibles(): array
    {
        return $this->procedimientoModel->obtenerInsumosDisponibles();
    }

    public function obtenerInsumosDeProtocolo(string $id): array
    {
        return $this->procedimientoModel->obtenerInsumosDeProtocolo($id);
    }

    public function obtenerCodigosDeProcedimiento(string $id): array
    {
        return $this->procedimientoModel->obtenerCodigosDeProcedimiento($id);
    }

    public function obtenerStaffDeProcedimiento(string $id): array
    {
        return $this->procedimientoModel->obtenerStaffDeProcedimiento($id);
    }

    public function generarIdUnicoDesdeCirugia(string $cirugia): string
    {
        $baseId = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $cirugia), '_'));
        if ($baseId === '') {
            $baseId = 'protocolo';
        }

        $nuevoId = $baseId;
        $contador = 1;
        while ($this->procedimientoModel->existeProtocoloConId($nuevoId)) {
            $nuevoId = $baseId . '_' . $contador;
            $contador++;
        }

        return $nuevoId;
    }

    public function actualizarProcedimiento(array $datos): bool
    {
        return $this->procedimientoModel->actualizarProcedimiento($datos);
    }

    public function eliminarProtocolo(string $id): bool
    {
        return $this->procedimientoModel->eliminarProtocolo($id);
    }

    public function crearProtocoloVacio(?string $categoria = null): array
    {
        return [
            'id' => '',
            'cirugia' => '',
            'membrete' => '',
            'categoria' => $categoria ?? '',
            'horas' => '',
            'imagen_link' => '',
            'operatorio' => '',
            'dieresis' => '',
            'exposicion' => '',
            'hallazgo' => '',
            'pre_evolucion' => '',
            'pre_indicacion' => '',
            'post_evolucion' => '',
            'post_indicacion' => '',
            'alta_evolucion' => '',
            'alta_indicacion' => '',
            'codigos' => [],
            'staff' => [],
            'insumos' => [
                'equipos' => [],
                'quirurgicos' => [],
                'anestesia' => [],
            ],
            'medicamentos' => [],
        ];
    }
}
