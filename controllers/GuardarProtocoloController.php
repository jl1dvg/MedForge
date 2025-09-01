<?php

namespace Controllers;

use PDO;

class GuardarProtocoloController
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    public function guardar(array $data): bool
    {
        try {
            // Si form_id ya existe, y procedimiento_id viene vacío, conservar el existente
            $existeStmt = $this->db->prepare("SELECT procedimiento_id FROM protocolo_data WHERE form_id = :form_id");
            $existeStmt->execute([':form_id' => $data['form_id']]);
            $procedimientoIdExistente = $existeStmt->fetchColumn();

            if (isset($procedimientoIdExistente) && empty($data['procedimiento_id'])) {
                $data['procedimiento_id'] = $procedimientoIdExistente;
            }

            $sql = "INSERT INTO protocolo_data (
                form_id, hc_number, procedimiento_id, membrete, dieresis, exposicion, hallazgo, operatorio,
                complicaciones_operatorio, datos_cirugia, procedimientos, diagnosticos, diagnosticos_previos,
                lateralidad, tipo_anestesia, hora_inicio, hora_fin, fecha_inicio, fecha_fin,
                cirujano_1, cirujano_2, primer_ayudante, segundo_ayudante, tercer_ayudante,
                ayudante_anestesia, anestesiologo, instrumentista, circulante, insumos,
                medicamentos, status
            ) VALUES (
                :form_id, :hc_number, :procedimiento_id, :membrete, :dieresis, :exposicion, :hallazgo, :operatorio,
                :complicaciones_operatorio, :datos_cirugia, :procedimientos, :diagnosticos, :diagnosticos_previos,
                :lateralidad, :tipo_anestesia, :hora_inicio, :hora_fin, :fecha_inicio, :fecha_fin,
                :cirujano_1, :cirujano_2, :primer_ayudante, :segundo_ayudante, :tercer_ayudante,
                :ayudante_anestesia, :anestesiologo, :instrumentista, :circulante, :insumos,
                :medicamentos, :status
            )
            ON DUPLICATE KEY UPDATE
                procedimiento_id = VALUES(procedimiento_id),
                membrete = VALUES(membrete),
                dieresis = VALUES(dieresis),
                exposicion = VALUES(exposicion),
                hallazgo = VALUES(hallazgo),
                operatorio = VALUES(operatorio),
                complicaciones_operatorio = VALUES(complicaciones_operatorio),
                datos_cirugia = VALUES(datos_cirugia),
                procedimientos = VALUES(procedimientos),
                diagnosticos = VALUES(diagnosticos),
                diagnosticos_previos = VALUES(diagnosticos_previos),
                lateralidad = VALUES(lateralidad),
                tipo_anestesia = VALUES(tipo_anestesia),
                hora_inicio = VALUES(hora_inicio),
                hora_fin = VALUES(hora_fin),
                fecha_inicio = VALUES(fecha_inicio),
                fecha_fin = VALUES(fecha_fin),
                cirujano_1 = VALUES(cirujano_1),
                cirujano_2 = VALUES(cirujano_2),
                primer_ayudante = VALUES(primer_ayudante),
                segundo_ayudante = VALUES(segundo_ayudante),
                tercer_ayudante = VALUES(tercer_ayudante),
                ayudante_anestesia = VALUES(ayudante_anestesia),
                anestesiologo = VALUES(anestesiologo),
                instrumentista = VALUES(instrumentista),
                circulante = VALUES(circulante),
                insumos = VALUES(insumos),
                medicamentos = VALUES(medicamentos),
                status = VALUES(status)";

            $stmt = $this->db->prepare($sql);
            if ($stmt->execute([
                'procedimiento_id' => $data['procedimiento_id'] ?? '',
                'membrete' => $data['membrete'] ?? '',
                'dieresis' => $data['dieresis'] ?? '',
                'exposicion' => $data['exposicion'] ?? '',
                'hallazgo' => $data['hallazgo'] ?? '',
                'operatorio' => $data['operatorio'] ?? '',
                'complicaciones_operatorio' => $data['complicaciones_operatorio'] ?? '',
                'datos_cirugia' => $data['datos_cirugia'] ?? '',
                'procedimientos' => json_encode($data['procedimientos'] ?? '[]'),
                'diagnosticos' => json_encode($data['diagnosticos'] ?? '[]'),
                'diagnosticos_previos' => is_string($data['diagnosticos_previos'] ?? null) ? ($data['diagnosticos_previos'] ?? null) : json_encode($data['diagnosticos_previos'] ?? []),
                'lateralidad' => $data['lateralidad'] ?? '',
                'tipo_anestesia' => $data['tipo_anestesia'] ?? '',
                'hora_inicio' => $data['hora_inicio'] ?? '',
                'hora_fin' => $data['hora_fin'] ?? '',
                'fecha_inicio' => $data['fecha_inicio'] ?? '',
                'fecha_fin' => $data['fecha_fin'] ?? '',
                'cirujano_1' => $data['cirujano_1'] ?? '',
                'cirujano_2' => $data['cirujano_2'] ?? '',
                'primer_ayudante' => $data['primer_ayudante'] ?? '',
                'segundo_ayudante' => $data['segundo_ayudante'] ?? '',
                'tercer_ayudante' => $data['tercer_ayudante'] ?? '',
                'ayudante_anestesia' => $data['ayudanteAnestesia'] ?? '',
                'anestesiologo' => $data['anestesiologo'] ?? '',
                'instrumentista' => $data['instrumentista'] ?? '',
                'circulante' => $data['circulante'] ?? '',
                'insumos' => is_string($data['insumos']) ? $data['insumos'] : json_encode($data['insumos'] ?? []),
                'medicamentos' => is_string($data['medicamentos']) ? $data['medicamentos'] : json_encode($data['medicamentos'] ?? []),
                'status' => $data['status'] ?? 0,
                'form_id' => $data['form_id'],
                'hc_number' => $data['hc_number']
            ])) {
                $protocoloId = (int)$this->db->lastInsertId();

                if ($protocoloId === 0) {
                    $searchStmt = $this->db->prepare("SELECT id FROM protocolo_data WHERE form_id = :form_id");
                    $searchStmt->execute([':form_id' => $data['form_id']]);
                    $protocoloId = (int)$searchStmt->fetchColumn();
                }

                // Eliminar insumos anteriores
                $deleteStmt = $this->db->prepare("DELETE FROM protocolo_insumos WHERE protocolo_id = :protocolo_id");
                $deleteStmt->execute([':protocolo_id' => $protocoloId]);

                // Insertar nuevos insumos desnormalizados
                $insertStmt = $this->db->prepare("
                    INSERT INTO protocolo_insumos (protocolo_id, insumo_id, nombre, cantidad, categoria)
                    VALUES (:protocolo_id, :insumo_id, :nombre, :cantidad, :categoria)
                ");

                $insumos = is_string($data['insumos']) ? json_decode($data['insumos'], true) : $data['insumos'];

                if (is_array($insumos)) {
                    foreach (['equipos', 'anestesia', 'quirurgicos'] as $categoria) {
                        if (isset($insumos[$categoria]) && is_array($insumos[$categoria])) {
                            foreach ($insumos[$categoria] as $insumo) {
                                $insertStmt->execute([
                                    ':protocolo_id' => $protocoloId,
                                    ':insumo_id' => $insumo['id'] ?? null,
                                    ':nombre' => $insumo['nombre'] ?? '',
                                    ':cantidad' => $insumo['cantidad'] ?? 1,
                                    ':categoria' => $categoria
                                ]);
                            }
                        }
                    }
                }

                // Asegurar que el form_id exista en procedimiento_proyectado
                $this->db->prepare("INSERT IGNORE INTO procedimiento_proyectado (form_id, hc_number) VALUES (:form_id, :hc_number)")
                    ->execute([
                        ':form_id' => $data['form_id'],
                        ':hc_number' => $data['hc_number']
                    ]);

                // Sincronizar diagnosticos en diagnosticos_asignados
                $stmtExistentes = $this->db->prepare("SELECT dx_code FROM diagnosticos_asignados WHERE form_id = :form_id AND fuente = 'protocolo'");
                $stmtExistentes->execute([':form_id' => $data['form_id']]);
                $existentes = $stmtExistentes->fetchAll(PDO::FETCH_COLUMN, 0);

                $nuevosDx = [];
                $dxCodigosNuevos = [];

                $diagnosticos = is_string($data['diagnosticos']) ? json_decode($data['diagnosticos'], true) : $data['diagnosticos'];
                foreach ($diagnosticos as $dx) {
                    if (!isset($dx['idDiagnostico']) || $dx['idDiagnostico'] === 'SELECCIONE') {
                        continue;
                    }

                    $parts = explode(' - ', $dx['idDiagnostico'], 2);
                    $codigo = trim($parts[0] ?? '');
                    $descripcion = trim($parts[1] ?? '');

                    $dxCodigosNuevos[] = $codigo;

                    if (in_array($codigo, $existentes)) {
                        $stmtUpdate = $this->db->prepare("UPDATE diagnosticos_asignados SET descripcion = :descripcion, definitivo = :definitivo, lateralidad = :lateralidad, selector = :selector
                                                          WHERE form_id = :form_id AND fuente = 'protocolo' AND dx_code = :dx_code");
                        $stmtUpdate->execute([
                            ':form_id' => $data['form_id'],
                            ':dx_code' => $codigo,
                            ':descripcion' => $descripcion,
                            ':definitivo' => isset($dx['evidencia']) && in_array(strtoupper($dx['evidencia']), ['1', 'DEFINITIVO']) ? 1 : 0,
                            ':lateralidad' => $dx['ojo'] ?? null,
                            ':selector' => $dx['selector'] ?? null
                        ]);
                    } else {
                        $nuevosDx[] = [
                            'form_id' => $data['form_id'],
                            'dx_code' => $codigo,
                            'descripcion' => $descripcion,
                            'definitivo' => isset($dx['evidencia']) && in_array(strtoupper($dx['evidencia']), ['1', 'DEFINITIVO']) ? 1 : 0,
                            'lateralidad' => $dx['ojo'] ?? null,
                            'selector' => $dx['selector'] ?? null
                        ];
                    }
                }

                $codigosEliminar = array_diff($existentes, $dxCodigosNuevos);
                if (!empty($codigosEliminar)) {
                    $in = implode(',', array_fill(0, count($codigosEliminar), '?'));
                    $stmtDelete = $this->db->prepare("DELETE FROM diagnosticos_asignados WHERE form_id = ? AND fuente = 'protocolo' AND dx_code IN ($in)");
                    $stmtDelete->execute(array_merge([$data['form_id']], $codigosEliminar));
                }

                $insertDxStmt = $this->db->prepare("INSERT INTO diagnosticos_asignados (form_id, fuente, dx_code, descripcion, definitivo, lateralidad, selector)
                                                    VALUES (:form_id, 'protocolo', :dx_code, :descripcion, :definitivo, :lateralidad, :selector)");
                foreach ($nuevosDx as $dx) {
                    $insertDxStmt->execute([
                        ':form_id' => $dx['form_id'],
                        ':dx_code' => $dx['dx_code'],
                        ':descripcion' => $dx['descripcion'],
                        ':definitivo' => $dx['definitivo'],
                        ':lateralidad' => $dx['lateralidad'],
                        ':selector' => $dx['selector']
                    ]);
                }
                return true;
            }
            return false;
        } catch (\Exception $e) {
            error_log("❌ Error al guardar protocolo: " . $e->getMessage());
            return false;
        }
    }

    public function api($data)
    {
        $data['hc_number'] = $data['hc_number'] ?? $data['hcNumber'] ?? null;
        $data['form_id'] = $data['form_id'] ?? $data['formId'] ?? null;
        $data['fecha_inicio'] = $data['fecha_inicio'] ?? $data['fechaInicio'] ?? null;
        $data['fecha_fin'] = $data['fecha_fin'] ?? $data['fechaFin'] ?? null;
        $data['hora_inicio'] = $data['hora_inicio'] ?? $data['horaInicio'] ?? null;
        $data['hora_fin'] = $data['hora_fin'] ?? $data['horaFin'] ?? null;
        $data['tipo_anestesia'] = $data['tipo_anestesia'] ?? $data['tipoAnestesia'] ?? null;
        if (empty($data['procedimiento_id'])) {
            return ["success" => false, "message" => "El campo procedimiento_id es obligatorio."];
        }
        $data['procedimiento_id'] = $data['procedimiento_id'] ?? $data['procedimiento_id'] ?? null;
        $data['insumos'] = $data['insumos'] ?? [];
        $data['medicamentos'] = $data['medicamentos'] ?? [];

        if (!$data['hc_number'] || !$data['form_id']) {
            return ["success" => false, "message" => "Datos no válidos"];
        }

        $ok = $this->guardar($data);

        if ($ok) {
            $stmt = $this->db->prepare("SELECT id FROM protocolo_data WHERE form_id = :form_id");
            $stmt->execute([':form_id' => $data['form_id']]);
            $protocoloId = (int)$stmt->fetchColumn();

            return ["success" => true, "message" => "Datos guardados correctamente", "protocolo_id" => $protocoloId];
        }

        // return ["success" => true, "message" => "Datos guardados correctamente"];
        return ["success" => false, "message" => "Error al guardar el protocolo"];
    }
}