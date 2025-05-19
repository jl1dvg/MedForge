<?php

namespace Controllers;

use PDO;

class GuardarConsultaController
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function guardar(array $data): array
    {
        if (!isset($data['hcNumber'], $data['form_id'], $data['motivoConsulta'])) {
            return ["success" => false, "message" => "Datos no válidos o incompletos"];
        }

        $hcNumber = $data['hcNumber'];
        $form_id = $data['form_id'];
        $fechaActual = $data['fechaActual'] ?? date('Y-m-d');

        // Insertar/actualizar datos del paciente (si están presentes)
        if (!empty($data['fechaNacimiento']) || !empty($data['sexo']) || !empty($data['celular']) || !empty($data['ciudad'])) {
            $sqlPaciente = "INSERT INTO patient_data (hc_number, fecha_nacimiento, sexo, celular, ciudad)
                            VALUES (:hc, :nac, :sexo, :cel, :ciudad)
                            ON DUPLICATE KEY UPDATE
                                fecha_nacimiento = VALUES(fecha_nacimiento),
                                sexo = VALUES(sexo),
                                celular = VALUES(celular),
                                ciudad = VALUES(ciudad)";
            $stmt = $this->db->prepare($sqlPaciente);
            $stmt->execute([
                ':hc' => $hcNumber,
                ':nac' => $data['fechaNacimiento'] ?? null,
                ':sexo' => $data['sexo'] ?? null,
                ':cel' => $data['celular'] ?? null,
                ':ciudad' => $data['ciudad'] ?? null,
            ]);
        }

        // Datos de consulta
        $sqlConsulta = "INSERT INTO consulta_data (
                            hc_number, form_id, fecha, motivo_consulta, enfermedad_actual, examen_fisico, plan, diagnosticos, examenes
                        ) VALUES (
                            :hc, :form_id, :fecha, :motivo, :enfermedad, :examen, :plan, :diagnosticos, :examenes
                        )
                        ON DUPLICATE KEY UPDATE
                            fecha = VALUES(fecha),
                            motivo_consulta = VALUES(motivo_consulta),
                            enfermedad_actual = VALUES(enfermedad_actual),
                            examen_fisico = VALUES(examen_fisico),
                            plan = VALUES(plan),
                            diagnosticos = VALUES(diagnosticos),
                            examenes = VALUES(examenes)";

        $stmtConsulta = $this->db->prepare($sqlConsulta);
        $ok = $stmtConsulta->execute([
            ':hc' => $hcNumber,
            ':form_id' => $form_id,
            ':fecha' => $fechaActual,
            ':motivo' => $data['motivoConsulta'] ?? null,
            ':enfermedad' => $data['enfermedadActual'] ?? null,
            ':examen' => $data['examenFisico'] ?? null,
            ':plan' => $data['plan'] ?? null,
            ':diagnosticos' => json_encode($data['diagnosticos'] ?? []),
            ':examenes' => json_encode($data['examenes'] ?? []),
        ]);

        if ($ok) {
            // Asegurar que el form_id exista en procedimiento_proyectado
            $this->db->prepare("INSERT IGNORE INTO procedimiento_proyectado (form_id, hc_number) VALUES (:form_id, :hc_number)")
                ->execute([
                    ':form_id' => $form_id,
                    ':hc_number' => $hcNumber
                ]);
            // Obtener dx_code actuales en la base para este form_id
            $stmtExistentes = $this->db->prepare("SELECT dx_code FROM diagnosticos_asignados WHERE form_id = :form_id AND fuente = 'consulta'");
            $stmtExistentes->execute([':form_id' => $form_id]);
            $existentes = $stmtExistentes->fetchAll(PDO::FETCH_COLUMN, 0);

            $nuevosDx = [];
            $dxCodigosNuevos = [];

            $diagnosticos = $data['diagnosticos'] ?? [];
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
                                                      WHERE form_id = :form_id AND fuente = 'consulta' AND dx_code = :dx_code");
                    $stmtUpdate->execute([
                        ':form_id' => $form_id,
                        ':dx_code' => $codigo,
                        ':descripcion' => $descripcion,
                        ':definitivo' => isset($dx['evidencia']) && in_array(strtoupper($dx['evidencia']), ['1', 'DEFINITIVO']) ? 1 : 0,
                        ':lateralidad' => $dx['ojo'] ?? null,
                        ':selector' => $dx['selector'] ?? null
                    ]);
                } else {
                    $nuevosDx[] = [
                        'form_id' => $form_id,
                        'dx_code' => $codigo,
                        'descripcion' => $descripcion,
                        'definitivo' => isset($dx['evidencia']) && in_array(strtoupper($dx['evidencia']), ['1', 'DEFINITIVO']) ? 1 : 0,
                        'lateralidad' => $dx['ojo'] ?? null,
                        'selector' => $dx['selector'] ?? null
                    ];
                }
            }

            // Eliminar los diagnósticos que ya no están en el nuevo array
            $codigosEliminar = array_diff($existentes, $dxCodigosNuevos);
            if (!empty($codigosEliminar)) {
                $in = implode(',', array_fill(0, count($codigosEliminar), '?'));
                $stmtDelete = $this->db->prepare("DELETE FROM diagnosticos_asignados WHERE form_id = ? AND fuente = 'consulta' AND dx_code IN ($in)");
                $stmtDelete->execute(array_merge([$form_id], $codigosEliminar));
            }

            // Insertar nuevos diagnósticos
            $insertDxStmt = $this->db->prepare("INSERT INTO diagnosticos_asignados (form_id, fuente, dx_code, descripcion, definitivo, lateralidad, selector)
                                                VALUES (:form_id, 'consulta', :dx_code, :descripcion, :definitivo, :lateralidad, :selector)");
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

            return ["success" => true, "message" => "Datos de la consulta guardados correctamente"];
        } else {
            return ["success" => false, "message" => "Error al guardar en consulta_data"];
        }
    }
}