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

            // Si no existe el protocolo, lo creamos (necesario al duplicar)
            $sqlCheckProc = "SELECT COUNT(*) FROM procedimientos WHERE id = ?";
            $stmtCheckProc = $this->db->prepare($sqlCheckProc);
            $stmtCheckProc->execute([$datos['id']]);

            if ($stmtCheckProc->fetchColumn() == 0) {
                $sqlInsertProc = "INSERT INTO procedimientos (id, cirugia, categoria, membrete) VALUES (?, '', '', '')";
                $stmtInsertProc = $this->db->prepare($sqlInsertProc);
                $stmtInsertProc->execute([$datos['id']]);

                $sqlInsertEvo = "INSERT INTO evolucion005 (id) VALUES (?)";
                $stmtInsertEvo = $this->db->prepare($sqlInsertEvo);
                $stmtInsertEvo->execute([$datos['id']]);
            }

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

            // Guardar códigos quirúrgicos
            if (isset($datos['codigos'], $datos['lateralidades'], $datos['selectores'])) {
                $codigos = $datos['codigos'];
                $lateralidades = $datos['lateralidades'];
                $selectores = $datos['selectores'];
                $formateados = [];

                foreach ($codigos as $index => $codigo) {
                    if (!empty($codigo)) {
                        $formateados[] = [
                            'nombre' => $codigo,
                            'lateralidad' => $lateralidades[$index] ?? '',
                            'selector' => $selectores[$index] ?? ''
                        ];
                    }
                }

                $this->guardarCodigosDeProcedimiento($datos['id'], $formateados);
            }

            // Guardar staff quirúrgico
            if (isset($datos['funciones'], $datos['trabajadores'], $datos['nombres_staff'], $datos['selectores'])) {
                $staff = [];
                foreach ($datos['funciones'] as $index => $funcion) {
                    if (!empty($funcion)) {
                        $staff[] = [
                            'funcion' => $funcion,
                            'trabajador' => $datos['trabajadores'][$index] ?? '',
                            'nombre' => $datos['nombres_staff'][$index] ?? '',
                            'selector' => "#select2-consultasubsecuente-trabajadorprotocolo-{$index}-funcion-container"
                        ];
                    }
                }

                try {
                    $this->guardarStaffDeProcedimiento($datos['id'], $staff);
                } catch (\Exception $e) {
                    error_log("❌ Error al guardar staff: " . $e->getMessage());
                    throw $e;
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("❌ Error en actualizarProcedimiento: " . $e->getMessage());
            throw $e; // 🔥 Esto es clave
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

    public function obtenerCodigosDeProcedimiento(string $procedimientoId): array
    {
        $sql = "SELECT * FROM procedimientos_codigos WHERE procedimiento_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$procedimientoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarCodigosDeProcedimiento(string $procedimientoId, array $codigos): bool
    {
        try {
            // Eliminar los códigos actuales
            $sqlDelete = "DELETE FROM procedimientos_codigos WHERE procedimiento_id = ?";
            $stmtDelete = $this->db->prepare($sqlDelete);
            $stmtDelete->execute([$procedimientoId]);

            // Insertar los nuevos códigos con lateralidad y selector
            $sqlInsert = "INSERT INTO procedimientos_codigos (procedimiento_id, nombre, lateralidad, selector) VALUES (?, ?, ?, ?)";
            $stmtInsert = $this->db->prepare($sqlInsert);
            foreach ($codigos as $codigo) {
                if (!empty($codigo['nombre'])) {
                    $stmtInsert->execute([
                        $procedimientoId,
                        $codigo['nombre'],
                        $codigo['lateralidad'],
                        $codigo['selector']
                    ]);
                }
            }

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function obtenerStaffDeProcedimiento(string $procedimientoId): array
    {
        $sql = "SELECT * FROM procedimientos_tecnicos WHERE procedimiento_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$procedimientoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Guarda el staff quirúrgico de un procedimiento, validando coherencia de índices y campos requeridos.
     * Cada miembro debe tener al menos 'funcion' y 'nombre' definidos.
     * Si algún índice de trabajador, selector o nombre no existe, se ignora ese registro.
     */
    public function guardarStaffDeProcedimiento(string $procedimientoId, array $staff): bool
    {
        try {
            if (
                count($staff) === 0 ||
                !is_array($staff)
            ) {
                throw new \Exception("Staff vacío o mal formado.");
            }

            foreach ($staff as $index => $miembro) {
                error_log("👨‍⚕️ Staff[{$index}]: " . json_encode($miembro));
            }

            // Eliminar el staff actual
            $sqlDelete = "DELETE FROM procedimientos_tecnicos WHERE procedimiento_id = ?";
            $stmtDelete = $this->db->prepare($sqlDelete);
            $stmtDelete->execute([$procedimientoId]);

            // Insertar el nuevo staff
            $sqlInsert = "INSERT INTO procedimientos_tecnicos (procedimiento_id, funcion, trabajador, nombre, selector) VALUES (?, ?, ?, ?, ?)";
            $stmtInsert = $this->db->prepare($sqlInsert);

            foreach ($staff as $index => $miembro) {
                // Validar que existan los índices requeridos en el array
                if (
                    !isset($miembro['funcion']) ||
                    !isset($miembro['nombre'])
                ) {
                    continue;
                }
                // Validar campos requeridos
                $funcion = $miembro['funcion'];
                $nombre = $miembro['nombre'];
                // Si faltan los datos mínimos, no guardar
                if (empty($funcion) || empty($nombre)) {
                    continue;
                }
                // Validar índices de trabajador, selector y nombre (aunque pueden ser vacíos, pero deben existir)
                if (
                    !array_key_exists('trabajador', $miembro) ||
                    !array_key_exists('selector', $miembro) ||
                    !array_key_exists('nombre', $miembro)
                ) {
                    continue;
                }
                $trabajador = $miembro['trabajador'];
                $selector = $miembro['selector'];
                $stmtInsert->execute([
                    $procedimientoId,
                    $funcion,
                    $trabajador,
                    $nombre,
                    $selector
                ]);
            }

            return true;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function existeProtocoloConId(string $id): bool
    {
        $sql = "SELECT COUNT(*) FROM procedimientos WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchColumn() > 0;
    }

    public function eliminarProtocolo(string $id): bool
    {
        try {
            $this->db->beginTransaction();

            // Eliminar datos relacionados primero
            $this->db->prepare("DELETE FROM procedimientos_codigos WHERE procedimiento_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM procedimientos_tecnicos WHERE procedimiento_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM kardex WHERE procedimiento_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM insumos_pack WHERE procedimiento_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM evolucion005 WHERE id = ?")->execute([$id]);

            // Finalmente, eliminar el protocolo principal
            $this->db->prepare("DELETE FROM procedimientos WHERE id = ?")->execute([$id]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("❌ Error al eliminar protocolo: " . $e->getMessage());
            return false;
        }
    }
}

?>
