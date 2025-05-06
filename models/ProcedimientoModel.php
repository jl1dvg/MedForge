<?php

namespace Models;

use PDO;

class ProcedimientoModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerProcedimientosAgrupados(): array
    {
        $sql = "SELECT categoria, membrete, cirugia, imagen_link, id FROM procedimientos ORDER BY categoria, cirugia";
        $stmt = $this->db->query($sql);

        $procedimientosPorCategoria = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $procedimientosPorCategoria[$row['categoria']][] = $row;
        }

        return $procedimientosPorCategoria;
    }

    public function actualizarProcedimiento(array $datos): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE procedimientos p
                    JOIN evolucion005 e ON p.id = e.id
                    SET p.cirugia = ?, p.categoria = ?, p.membrete = ?, p.dieresis = ?, 
                        p.exposicion = ?, p.hallazgo = ?, p.horas = ?, p.imagen_link = ?, 
                        p.operatorio = ?, e.pre_evolucion = ?, e.pre_indicacion = ?, 
                        e.post_evolucion = ?, e.post_indicacion = ?, e.alta_evolucion = ?, 
                        e.alta_indicacion = ?
                    WHERE p.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $datos['cirugia'], $datos['categoriaQX'], $datos['membrete'], $datos['dieresis'],
                $datos['exposicion'], $datos['hallazgo'], $datos['horas'], $datos['imagen_link'],
                $datos['operatorio'], $datos['pre_evolucion'], $datos['pre_indicacion'],
                $datos['post_evolucion'], $datos['post_indicacion'], $datos['alta_evolucion'],
                $datos['alta_indicacion'], $datos['id']
            ]);

            // Insumos
            $insumos = !empty($datos['insumos']) ? $datos['insumos'] : json_encode(["equipos" => [], "quirurgicos" => [], "anestesia" => []]);
            $sqlCheckInsumos = "SELECT COUNT(*) FROM insumos_pack WHERE procedimiento_id = ?";
            $stmtCheck = $this->db->prepare($sqlCheckInsumos);
            $stmtCheck->execute([$datos['id']]);
            if ($stmtCheck->fetchColumn() > 0) {
                $sqlUpdateInsumos = "UPDATE insumos_pack SET insumos = ? WHERE procedimiento_id = ?";
                $stmtUpdate = $this->db->prepare($sqlUpdateInsumos);
                $stmtUpdate->execute([$insumos, $datos['id']]);
            } else {
                $sqlInsertInsumos = "INSERT INTO insumos_pack (procedimiento_id, insumos) VALUES (?, ?)";
                $stmtInsert = $this->db->prepare($sqlInsertInsumos);
                $stmtInsert->execute([$datos['id'], $insumos]);
            }

            // Medicamentos
            $medicamentos = !empty($datos['medicamentos']) ? $datos['medicamentos'] : json_encode([]);
            $sqlCheckMedicamentos = "SELECT COUNT(*) FROM kardex WHERE procedimiento_id = ?";
            $stmtCheck = $this->db->prepare($sqlCheckMedicamentos);
            $stmtCheck->execute([$datos['id']]);
            if ($stmtCheck->fetchColumn() > 0) {
                $sqlUpdateMed = "UPDATE kardex SET medicamentos = ? WHERE procedimiento_id = ?";
                $stmtUpdate = $this->db->prepare($sqlUpdateMed);
                $stmtUpdate->execute([$medicamentos, $datos['id']]);
            } else {
                $sqlInsertMed = "INSERT INTO kardex (procedimiento_id, medicamentos) VALUES (?, ?)";
                $stmtInsert = $this->db->prepare($sqlInsertMed);
                $stmtInsert->execute([$datos['id'], $medicamentos]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function obtenerProtocoloPorId(string $id): ?array
    {
        $sql = "SELECT p.*, e.pre_evolucion, e.pre_indicacion, e.post_evolucion, e.post_indicacion, e.alta_evolucion, e.alta_indicacion
            FROM procedimientos p
            JOIN evolucion005 e ON p.id = e.id
            WHERE p.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $protocolo = $stmt->fetch(PDO::FETCH_ASSOC);

        return $protocolo ?: null;
    }

    public function obtenerMedicamentosDeProtocolo(string $id): array
    {
        $sql = "SELECT medicamentos FROM kardex WHERE procedimiento_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['medicamentos']) ? json_decode($row['medicamentos'], true) : [];
    }

    public function obtenerOpcionesMedicamentos(): array
    {
        $sql = "SELECT id, medicamento FROM medicamentos ORDER BY medicamento";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCategoriasInsumos(): array
    {
        $sql = "SELECT DISTINCT categoria FROM insumos ORDER BY categoria";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function obtenerInsumosDisponibles(): array
    {
        $sql = "SELECT id, nombre, categoria FROM insumos ORDER BY nombre";
        $stmt = $this->db->query($sql);
        $insumos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $insumos[$row['categoria']][] = $row;
        }
        return $insumos;
    }

    public function obtenerInsumosDeProtocolo(string $id): array
    {
        $sql = "SELECT insumos FROM insumos_pack WHERE procedimiento_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($row['insumos']) ? json_decode($row['insumos'], true) : [];
    }
}

?>
