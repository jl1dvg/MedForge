<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Services;

use PDO;

class ProtocolosTemplateReadService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function obtenerProcedimientosAgrupados(): array
    {
        $sql = 'SELECT categoria, membrete, cirugia, imagen_link, id FROM procedimientos ORDER BY categoria, cirugia';
        $stmt = $this->db->query($sql);

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $grouped[$row['categoria']][] = $row;
        }

        return $grouped;
    }

    public function obtenerProtocoloPorId(string $id): ?array
    {
        $sql = 'SELECT p.*, e.pre_evolucion, e.pre_indicacion, e.post_evolucion, e.post_indicacion, e.alta_evolucion, e.alta_indicacion
            FROM procedimientos p
            JOIN evolucion005 e ON p.id = e.id
            WHERE p.id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function obtenerMedicamentosDeProtocolo(string $id): array
    {
        $stmt = $this->db->prepare('SELECT medicamentos FROM kardex WHERE procedimiento_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row['medicamentos']) ? (json_decode((string) $row['medicamentos'], true) ?: []) : [];
    }

    public function obtenerOpcionesMedicamentos(): array
    {
        $stmt = $this->db->query('SELECT id, medicamento FROM medicamentos ORDER BY medicamento');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerInsumosDisponibles(): array
    {
        $stmt = $this->db->query('SELECT id, nombre, categoria FROM insumos ORDER BY nombre');
        $insumos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insumos[$row['categoria']][] = $row;
        }

        return $insumos;
    }

    public function obtenerInsumosDeProtocolo(string $id): array
    {
        $stmt = $this->db->prepare('SELECT insumos FROM insumos_pack WHERE procedimiento_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row['insumos']) ? (json_decode((string) $row['insumos'], true) ?: []) : [];
    }

    public function obtenerCodigosDeProcedimiento(string $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM procedimientos_codigos WHERE procedimiento_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerStaffDeProcedimiento(string $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM procedimientos_tecnicos WHERE procedimiento_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

