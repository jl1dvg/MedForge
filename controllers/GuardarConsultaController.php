<?php

namespace Controllers;

use Modules\Examenes\Services\ConsultaExamenSyncService;
use PDO;
use Throwable;

class GuardarConsultaController
{
    private $db;
    private ConsultaExamenSyncService $examenSync;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->examenSync = new ConsultaExamenSyncService($pdo);
    }

    public function guardar(array $data): array
    {
        if (!isset($data['hcNumber'], $data['form_id'], $data['motivoConsulta'])) {
            return ["success" => false, "message" => "Datos no válidos o incompletos"];
        }

        $hcNumber = $data['hcNumber'];
        $form_id = $data['form_id'];
        $fechaActual = $data['fechaActual'] ?? date('Y-m-d');

        // Helpers
        $normId = function ($v) {
            if ($v === null) return null;
            $s = is_string($v) ? trim($v) : $v;
            if ($s === '' || strtoupper($s) === 'SELECCIONE') return null;
            return $s;
        };
        $toTime = function ($v) {
            $s = is_string($v) ? trim($v) : '';
            return $s === '' ? null : $s; // espera formato 'HH:MM'
        };
        $toFloat = function ($v) {
            if ($v === null || $v === '') return null;
            $s = str_replace(',', '.', trim((string)$v));
            return is_numeric($s) ? (float)$s : null;
        };
        $cleanTxt = function ($v) {
            if ($v === null) return null;
            $txt = strip_tags((string)$v);
            $txt = html_entity_decode($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $txt = trim(preg_replace('/\s+/', ' ', $txt));
            if ($txt === '' || strtoupper($txt) === 'SELECCIONE') return null;
            return $txt;
        };

        try {
            $this->db->beginTransaction();

            // 1) Upsert patient_data
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

            // 2) Upsert consulta_data (ahora con campos nuevos)
            $sqlConsulta = "INSERT INTO consulta_data
            (hc_number, form_id, fecha, motivo_consulta, enfermedad_actual, examen_fisico, plan,
             diagnosticos, examenes,
             estado_enfermedad, antecedente_alergico, signos_alarma, recomen_no_farmaco, vigencia_receta)
         VALUES
            (:hc, :form_id, :fecha, :motivo, :enfermedad, :examen, :plan,
             :diagnosticos, :examenes,
             :estado_enfermedad, :antecedente_alergico, :signos_alarma, :recomen_no_farmaco, :vigencia_receta)
                        ON DUPLICATE KEY UPDATE
                            fecha = VALUES(fecha),
                            motivo_consulta = VALUES(motivo_consulta),
                            enfermedad_actual = VALUES(enfermedad_actual),
                            examen_fisico = VALUES(examen_fisico),
                            plan = VALUES(plan),
                            diagnosticos = VALUES(diagnosticos),
            examenes = VALUES(examenes),
            estado_enfermedad = VALUES(estado_enfermedad),
            antecedente_alergico = VALUES(antecedente_alergico),
            signos_alarma = VALUES(signos_alarma),
            recomen_no_farmaco = VALUES(recomen_no_farmaco),
            vigencia_receta = VALUES(vigencia_receta)";

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
                ':estado_enfermedad' => isset($data['estadoEnfermedad']) ? $normId($data['estadoEnfermedad']) : null,
                ':antecedente_alergico' => $data['antecedente_alergico'] ?? null,
                ':signos_alarma' => $data['signos_alarma'] ?? null,
                ':recomen_no_farmaco' => $data['recomen_no_farmaco'] ?? null,
                ':vigencia_receta' => $data['vigenciaReceta'] ?? null, // 'YYYY-MM-DD'
            ]);

            if (!$ok) {
                $this->db->rollBack();
                return ["success" => false, "message" => "Error al guardar en consulta_data"];
            }

            $examenes = $data['examenes'] ?? [];
            if (!is_array($examenes)) {
                $examenes = [];
            }

            try {
                $this->examenSync->syncFromPayload(
                    $form_id,
                    $hcNumber,
                    $data['doctor'] ?? $data['doctorTratante'] ?? null,
                    $data['solicitanteExamen'] ?? $data['referidoPor'] ?? null,
                    $fechaActual,
                    $examenes
                );
            } catch (Throwable $e) {
                // No interrumpimos la consulta si la normalización falla; el log ayuda a depurar.
                error_log('No se pudo sincronizar exámenes normalizados: ' . $e->getMessage());
            }

            // 3) Asegurar form_id en procedimiento_proyectado
            $this->db->prepare("INSERT IGNORE INTO procedimiento_proyectado (form_id, hc_number) VALUES (:form_id, :hc)")
                ->execute([':form_id' => $form_id, ':hc' => $hcNumber]);

            // 4) Sin cambios: sincroniza diagnosticos_asignados (tu lógica actual)
            //    (dejo tu bloque tal como estaba)
            $stmtExistentes = $this->db->prepare("SELECT dx_code FROM diagnosticos_asignados WHERE form_id = :form_id AND fuente = 'consulta'");
            $stmtExistentes->execute([':form_id' => $form_id]);
            $existentes = $stmtExistentes->fetchAll(PDO::FETCH_COLUMN, 0);

            $nuevosDx = [];
            $dxCodigosNuevos = [];
            foreach (($data['diagnosticos'] ?? []) as $dx) {
                if (!isset($dx['idDiagnostico']) || $dx['idDiagnostico'] === 'SELECCIONE') continue;

                $parts = explode(' - ', $dx['idDiagnostico'], 2);
                $codigo = trim($parts[0] ?? '');
                $descripcion = trim($parts[1] ?? '');

                if ($codigo === '') continue;
                $dxCodigosNuevos[] = $codigo;

                $payload = [
                    ':form_id' => $form_id,
                    ':dx_code' => $codigo,
                    ':descripcion' => $descripcion,
                    ':definitivo' => isset($dx['evidencia']) && in_array(strtoupper($dx['evidencia']), ['1', 'DEFINITIVO']) ? 1 : 0,
                    ':lateralidad' => $dx['ojo'] ?? null,
                    ':selector' => $dx['selector'] ?? null
                ];

                if (in_array($codigo, $existentes)) {
                    $this->db->prepare(
                        "UPDATE diagnosticos_asignados
                     SET descripcion=:descripcion, definitivo=:definitivo, lateralidad=:lateralidad, selector=:selector
                     WHERE form_id=:form_id AND fuente='consulta' AND dx_code=:dx_code"
                    )->execute($payload);
                } else {
                    $nuevosDx[] = $payload;
                }
            }

            $codigosEliminar = array_diff($existentes, $dxCodigosNuevos);
            if (!empty($codigosEliminar)) {
                $in = implode(',', array_fill(0, count($codigosEliminar), '?'));
                $stmtDelete = $this->db->prepare("DELETE FROM diagnosticos_asignados WHERE form_id = ? AND fuente = 'consulta' AND dx_code IN ($in)");
                $stmtDelete->execute(array_merge([$form_id], $codigosEliminar));
            }

            if (!empty($nuevosDx)) {
                $insertDxStmt = $this->db->prepare(
                    "INSERT INTO diagnosticos_asignados (form_id, fuente, dx_code, descripcion, definitivo, lateralidad, selector)
                 VALUES (:form_id, 'consulta', :dx_code, :descripcion, :definitivo, :lateralidad, :selector)"
                );
                foreach ($nuevosDx as $payload) $insertDxStmt->execute($payload);
            }

            // 5) Reemplazar PIO del form_id por lo nuevo
            $this->db->prepare("DELETE FROM pio_mediciones WHERE form_id = :form_id")->execute([':form_id' => $form_id]);

            if (!empty($data['pio']) && is_array($data['pio'])) {
                $stmtPio = $this->db->prepare(
                    "INSERT INTO pio_mediciones
                 (form_id, id_ui, tonometro, od, oi, patologico, hora, hora_fin, observacion)
                 VALUES (:form_id, :id_ui, :tonometro, :od, :oi, :patologico, :hora, :hora_fin, :observacion)"
                );
                foreach ($data['pio'] as $p) {
                    // convertir “SELECCIONE” a NULL en tonómetro
                    $stmtPio->execute([
                        ':form_id' => $form_id,
                        ':id_ui' => $p['id'] ?? null,
                        ':tonometro' => $cleanTxt($p['po_tonometro_id'] ?? $p['tonometro'] ?? null), // aceptar ID o TEXTO
                        ':od' => $toFloat($p['od'] ?? null),
                        ':oi' => $toFloat($p['oi'] ?? null),
                        ':patologico' => isset($p['po_patologico']) ? (int)!!$p['po_patologico'] : 0,
                        ':hora' => $toTime($p['po_hora'] ?? null),
                        ':hora_fin' => $toTime($p['hora_fin'] ?? null),
                        ':observacion' => $p['po_observacion'] ?? null,
                    ]);
                }
            }

            // 6) Reemplazar Recetas internas del form_id por lo nuevo (ignorar recetas_externas)
            $this->db->prepare("DELETE FROM recetas_items WHERE form_id = :form_id")->execute([':form_id' => $form_id]);

            if (!empty($data['recetas']) && is_array($data['recetas'])) {
                $stmtRec = $this->db->prepare(
                    "INSERT INTO recetas_items
                 (form_id, id_ui, estado_receta, producto, vias, unidad, pauta,
                  dosis, cantidad, total_farmacia, observaciones)
                 VALUES
                 (:form_id, :id_ui, :estado_receta, :producto, :vias, :unidad, :pauta,
                  :dosis, :cantidad, :total_farmacia, :observaciones)"
                );

                foreach ($data['recetas'] as $r) {
                    // Aceptar campos como TEXTO (se limpiará HTML) porque el cliente no maneja IDs
                    $productoTxt = $cleanTxt($r['producto'] ?? $r['producto_text'] ?? $r['producto_id'] ?? null);
                    $viasTxt = $cleanTxt($r['vias'] ?? $r['vias_text'] ?? null);
                    $unidadTxt = $cleanTxt($r['unidad'] ?? $r['unidad_text'] ?? null);
                    $pautaTxt = $cleanTxt($r['pauta'] ?? $r['pauta_text'] ?? null);

                    // Reglas mínimas: producto y vía son obligatorios
                    if (!$productoTxt || !$viasTxt) continue;

                    $stmtRec->execute([
                        ':form_id' => $form_id,
                        ':id_ui' => $r['idRecetas'] ?? null,
                        ':estado_receta' => $cleanTxt($r['estadoRecetaid'] ?? $r['estado_receta'] ?? null),
                        ':producto' => $productoTxt,
                        ':vias' => $viasTxt,
                        ':unidad' => $unidadTxt,
                        ':pauta' => $pautaTxt,
                        ':dosis' => isset($r['dosis']) ? trim((string)$r['dosis']) : null,
                        ':cantidad' => isset($r['cantidad']) && $r['cantidad'] !== '' ? (int)$r['cantidad'] : null,
                        ':total_farmacia' => isset($r['total_farmacia']) && $r['total_farmacia'] !== '' ? (int)$r['total_farmacia'] : null,
                        ':observaciones' => $cleanTxt($r['observaciones'] ?? null),
                    ]);
                }
            }

            $this->db->commit();
            return ["success" => true, "message" => "Datos de la consulta guardados correctamente"];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ["success" => false, "message" => "Error: " . $e->getMessage()];
        }
    }
}