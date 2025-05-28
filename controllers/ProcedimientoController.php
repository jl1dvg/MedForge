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

    public function existeProtocoloConId(string $id): bool
    {
        return $this->procedimientoModel->existeProtocoloConId($id);
    }

    public function generarIdUnicoDesdeCirugia(string $nombreCirugia): string
    {
        // Normaliza el nombre: minúsculas, reemplaza espacios y símbolos con guiones bajos
        $baseId = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $nombreCirugia), '_'));
        $nuevoId = $baseId;
        $contador = 1;

        while ($this->existeProtocoloConId($nuevoId)) {
            $nuevoId = $baseId . '_' . $contador;
            $contador++;
        }

        return $nuevoId;
    }

    public function eliminarProtocolo(string $id): bool
    {
        return $this->procedimientoModel->eliminarProtocolo($id);
    }
}