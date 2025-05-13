<?php

namespace Controllers;

use Models\ProcedimientoModel;
use PDO;

class ProcedimientoController
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

    public function actualizarProcedimiento(array $datos): bool
    {
        return $this->procedimientoModel->actualizarProcedimiento($datos);
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

    public function obtenerCodigosDeProcedimiento(string $procedimientoId): array
    {
        return $this->procedimientoModel->obtenerCodigosDeProcedimiento($procedimientoId);
    }

    public function obtenerStaffDeProcedimiento(string $procedimientoId): array
    {
        return $this->procedimientoModel->obtenerStaffDeProcedimiento($procedimientoId);
    }
}