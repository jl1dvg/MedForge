<?php

declare(strict_types=1);

namespace App\Modules\Consultas\Services;

use App\Modules\Examenes\Services\ConsultaExamenSyncService;
use PDO;
use RuntimeException;
use Throwable;

class ConsultasParityService
{
    private ConsultaExamenSyncService $examenSync;

    public function __construct(private readonly PDO $db)
    {
        $this->examenSync = new ConsultaExamenSyncService($this->db);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function guardar(array $data): array
    {
        if (!isset($data['hcNumber'], $data['form_id'], $data['motivoConsulta'])) {
            return ['success' => false, 'message' => 'Datos no válidos o incompletos'];
        }

        $hcNumber = trim((string) $data['hcNumber']);
        $formId = trim((string) $data['form_id']);
        $fechaActual = trim((string) ($data['fechaActual'] ?? date('Y-m-d')));
        if ($fechaActual === '') {
            $fechaActual = date('Y-m-d');
        }

        if ($hcNumber === '' || $formId === '') {
            return ['success' => false, 'message' => 'Datos no válidos o incompletos'];
        }

        try {
            $this->db->beginTransaction();

            $patientFieldMap = [
                'fecha_nacimiento' => $data['fechaNacimiento'] ?? null,
                'sexo' => $data['sexo'] ?? null,
                'celular' => $data['celular'] ?? null,
                'ciudad' => $data['ciudad'] ?? null,
            ];
            $patientUpdates = [];
            foreach ($patientFieldMap as $column => $value) {
                if ($value === null) {
                    continue;
                }

                $normalized = is_string($value) ? trim($value) : $value;
                if ($normalized === '') {
                    continue;
                }

                $patientUpdates[$column] = $normalized;
            }

            if ($patientUpdates !== []) {
                $auditType = PHP_SAPI === 'cli' ? 'cron' : 'api';
                $auditIdentifier = PHP_SAPI === 'cli'
                    ? 'cron:' . basename((string) ($_SERVER['argv'][0] ?? 'unknown_script'))
                    : 'api:' . trim((string) ($_SERVER['REQUEST_URI'] ?? '/v2/api/consultas/guardar'));

                $stmtExists = $this->db->prepare('SELECT 1 FROM patient_data WHERE hc_number = :hc LIMIT 1');
                $stmtExists->execute([':hc' => $hcNumber]);
                $patientExists = (bool) $stmtExists->fetchColumn();

                if ($patientExists) {
                    $sets = [];
                    $params = [
                        ':hc' => $hcNumber,
                        ':updated_by_type' => $auditType,
                        ':updated_by_identifier' => $auditIdentifier,
                    ];

                    foreach ($patientUpdates as $column => $value) {
                        $paramName = ':' . $column;
                        $sets[] = $column . ' = ' . $paramName;
                        $params[$paramName] = $value;
                    }

                    $sets[] = 'updated_at = CURRENT_TIMESTAMP';
                    $sets[] = 'updated_by_type = :updated_by_type';
                    $sets[] = 'updated_by_identifier = :updated_by_identifier';

                    $sqlPaciente = 'UPDATE patient_data SET ' . implode(', ', $sets) . ' WHERE hc_number = :hc';
                    $stmt = $this->db->prepare($sqlPaciente);
                    $stmt->execute($params);
                } else {
                    error_log(
                        'ConsultasParityService: Se omitió creación de patient_data para hc_number ' . $hcNumber
                        . ' por falta de datos de identidad.'
                    );
                }
            }

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
                ':form_id' => $formId,
                ':fecha' => $fechaActual,
                ':motivo' => $data['motivoConsulta'] ?? null,
                ':enfermedad' => $data['enfermedadActual'] ?? null,
                ':examen' => $data['examenFisico'] ?? null,
                ':plan' => $data['plan'] ?? null,
                ':diagnosticos' => json_encode($data['diagnosticos'] ?? []),
                ':examenes' => json_encode($data['examenes'] ?? []),
                ':estado_enfermedad' => isset($data['estadoEnfermedad'])
                    ? $this->normalizeOptionalId($data['estadoEnfermedad'])
                    : null,
                ':antecedente_alergico' => $data['antecedente_alergico'] ?? null,
                ':signos_alarma' => $data['signos_alarma'] ?? null,
                ':recomen_no_farmaco' => $data['recomen_no_farmaco'] ?? null,
                ':vigencia_receta' => $data['vigenciaReceta'] ?? null,
            ]);

            if (!$ok) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return ['success' => false, 'message' => 'Error al guardar en consulta_data'];
            }

            $examenes = $data['examenes'] ?? [];
            if (!is_array($examenes)) {
                $examenes = [];
            }

            $doctorEnviado = $data['doctor']
                ?? $data['doctorTratante']
                ?? $data['doctorConsulta']
                ?? $data['doctor_consulta']
                ?? $data['doctorConsultaNombre']
                ?? null;

            $solicitanteEnviado = $data['solicitanteExamen']
                ?? $data['solicitante']
                ?? $data['referidoPor']
                ?? $data['referido_por']
                ?? null;

            try {
                $this->examenSync->syncFromPayload(
                    $formId,
                    $hcNumber,
                    is_string($doctorEnviado) ? $doctorEnviado : null,
                    is_string($solicitanteEnviado) ? $solicitanteEnviado : null,
                    $fechaActual,
                    $examenes
                );
            } catch (Throwable $e) {
                error_log('No se pudo sincronizar exámenes normalizados: ' . $e->getMessage());
            }

            $this->db->prepare('INSERT IGNORE INTO procedimiento_proyectado (form_id, hc_number) VALUES (:form_id, :hc)')
                ->execute([':form_id' => $formId, ':hc' => $hcNumber]);

            $stmtExistentes = $this->db->prepare("SELECT dx_code FROM diagnosticos_asignados WHERE form_id = :form_id AND fuente = 'consulta'");
            $stmtExistentes->execute([':form_id' => $formId]);
            $existentes = $stmtExistentes->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!is_array($existentes)) {
                $existentes = [];
            }
            $existentes = array_values(array_map(static fn($value): string => (string) $value, $existentes));

            $nuevosDx = [];
            $dxCodigosNuevos = [];
            foreach (($data['diagnosticos'] ?? []) as $dx) {
                if (!is_array($dx)) {
                    continue;
                }

                if (!isset($dx['idDiagnostico']) || $dx['idDiagnostico'] === 'SELECCIONE') {
                    continue;
                }

                $parts = explode(' - ', (string) $dx['idDiagnostico'], 2);
                $codigo = trim((string) ($parts[0] ?? ''));
                $descripcion = trim((string) ($parts[1] ?? ''));

                if ($codigo === '') {
                    continue;
                }

                $dxCodigosNuevos[] = $codigo;

                $payloadDx = [
                    ':form_id' => $formId,
                    ':dx_code' => $codigo,
                    ':descripcion' => $descripcion,
                    ':definitivo' => isset($dx['evidencia']) && in_array(strtoupper((string) $dx['evidencia']), ['1', 'DEFINITIVO'], true) ? 1 : 0,
                    ':lateralidad' => $dx['ojo'] ?? null,
                    ':selector' => $dx['selector'] ?? null,
                ];

                if (in_array($codigo, $existentes, true)) {
                    $this->db->prepare(
                        "UPDATE diagnosticos_asignados
                     SET descripcion=:descripcion, definitivo=:definitivo, lateralidad=:lateralidad, selector=:selector
                     WHERE form_id=:form_id AND fuente='consulta' AND dx_code=:dx_code"
                    )->execute($payloadDx);
                } else {
                    $nuevosDx[] = $payloadDx;
                }
            }

            $codigosEliminar = array_values(array_diff($existentes, $dxCodigosNuevos));
            if ($codigosEliminar !== []) {
                $in = implode(',', array_fill(0, count($codigosEliminar), '?'));
                $stmtDelete = $this->db->prepare("DELETE FROM diagnosticos_asignados WHERE form_id = ? AND fuente = 'consulta' AND dx_code IN ($in)");
                $stmtDelete->execute(array_merge([$formId], $codigosEliminar));
            }

            if ($nuevosDx !== []) {
                $insertDxStmt = $this->db->prepare(
                    "INSERT INTO diagnosticos_asignados (form_id, fuente, dx_code, descripcion, definitivo, lateralidad, selector)
                 VALUES (:form_id, 'consulta', :dx_code, :descripcion, :definitivo, :lateralidad, :selector)"
                );
                foreach ($nuevosDx as $payloadDx) {
                    $insertDxStmt->execute($payloadDx);
                }
            }

            $this->db->prepare('DELETE FROM pio_mediciones WHERE form_id = :form_id')->execute([':form_id' => $formId]);

            if (!empty($data['pio']) && is_array($data['pio'])) {
                $stmtPio = $this->db->prepare(
                    'INSERT INTO pio_mediciones
                 (form_id, id_ui, tonometro, od, oi, patologico, hora, hora_fin, observacion)
                 VALUES (:form_id, :id_ui, :tonometro, :od, :oi, :patologico, :hora, :hora_fin, :observacion)'
                );

                foreach ($data['pio'] as $p) {
                    if (!is_array($p)) {
                        continue;
                    }

                    $stmtPio->execute([
                        ':form_id' => $formId,
                        ':id_ui' => $p['id'] ?? null,
                        ':tonometro' => $this->cleanText($p['po_tonometro_id'] ?? $p['tonometro'] ?? null),
                        ':od' => $this->normalizeOptionalFloat($p['od'] ?? null),
                        ':oi' => $this->normalizeOptionalFloat($p['oi'] ?? null),
                        ':patologico' => isset($p['po_patologico']) ? (int) ((bool) $p['po_patologico']) : 0,
                        ':hora' => $this->normalizeOptionalTime($p['po_hora'] ?? null),
                        ':hora_fin' => $this->normalizeOptionalTime($p['hora_fin'] ?? null),
                        ':observacion' => $p['po_observacion'] ?? null,
                    ]);
                }
            }

            $this->db->prepare('DELETE FROM recetas_items WHERE form_id = :form_id')->execute([':form_id' => $formId]);

            if (!empty($data['recetas']) && is_array($data['recetas'])) {
                $stmtRec = $this->db->prepare(
                    'INSERT INTO recetas_items
                 (form_id, id_ui, estado_receta, producto, vias, unidad, pauta,
                  dosis, cantidad, total_farmacia, observaciones)
                 VALUES
                 (:form_id, :id_ui, :estado_receta, :producto, :vias, :unidad, :pauta,
                  :dosis, :cantidad, :total_farmacia, :observaciones)'
                );

                foreach ($data['recetas'] as $r) {
                    if (!is_array($r)) {
                        continue;
                    }

                    $productoTxt = $this->cleanText($r['producto'] ?? $r['producto_text'] ?? $r['producto_id'] ?? null);
                    $viasTxt = $this->cleanText($r['vias'] ?? $r['vias_text'] ?? null);
                    $unidadTxt = $this->cleanText($r['unidad'] ?? $r['unidad_text'] ?? null);
                    $pautaTxt = $this->cleanText($r['pauta'] ?? $r['pauta_text'] ?? null);

                    if (!$productoTxt || !$viasTxt) {
                        continue;
                    }

                    $stmtRec->execute([
                        ':form_id' => $formId,
                        ':id_ui' => $r['idRecetas'] ?? null,
                        ':estado_receta' => $this->cleanText($r['estadoRecetaid'] ?? $r['estado_receta'] ?? null),
                        ':producto' => $productoTxt,
                        ':vias' => $viasTxt,
                        ':unidad' => $unidadTxt,
                        ':pauta' => $pautaTxt,
                        ':dosis' => isset($r['dosis']) ? trim((string) $r['dosis']) : null,
                        ':cantidad' => isset($r['cantidad']) && $r['cantidad'] !== '' ? (int) $r['cantidad'] : null,
                        ':total_farmacia' => isset($r['total_farmacia']) && $r['total_farmacia'] !== '' ? (int) $r['total_farmacia'] : null,
                        ':observaciones' => $this->cleanText($r['observaciones'] ?? null),
                    ]);
                }
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Datos de la consulta guardados correctamente'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function consultaAnterior(?string $hcNumber, ?string $formIdActual, ?string $procedimientoActual): array
    {
        $hcNumber = trim((string) ($hcNumber ?? ''));
        $formIdActual = trim((string) ($formIdActual ?? ''));
        $procedimientoActual = trim((string) ($procedimientoActual ?? ''));

        if ($hcNumber === '') {
            return [
                'success' => false,
                'message' => 'Parámetro hcNumber requerido',
            ];
        }

        $baseProcedimiento = null;
        if ($procedimientoActual !== '') {
            $partes = explode(' - ', $procedimientoActual);
            $baseProcedimiento = trim((string) ($partes[0] ?? ''));
            if ($baseProcedimiento === '') {
                $baseProcedimiento = null;
            }
        }

        try {
            $sql = 'SELECT cd.form_id,
                    cd.hc_number,
                    cd.examen_fisico,
                    cd.plan,
                    cd.fecha
             FROM procedimiento_proyectado AS pp
             LEFT JOIN consulta_data AS cd
                    ON pp.form_id = cd.form_id
             WHERE pp.hc_number = :hcNumber';

            $params = [':hcNumber' => $hcNumber];

            if ($baseProcedimiento !== null) {
                $sql .= ' AND pp.procedimiento_proyectado LIKE :baseProc';
                $params[':baseProc'] = $baseProcedimiento . '%';

                $sql .= ' AND pp.procedimiento_proyectado <> :procActual';
                $params[':procActual'] = $procedimientoActual;
            }

            if ($formIdActual !== '') {
                $sql .= ' AND pp.form_id < :formIdActual';
                $params[':formIdActual'] = $formIdActual;
            }

            $sql .= " AND cd.examen_fisico IS NOT NULL
              AND cd.examen_fisico <> ''";

            $sql .= ' ORDER BY cd.fecha DESC, cd.form_id DESC LIMIT 1';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                return [
                    'success' => true,
                    'data' => [
                        'form_id' => $row['form_id'],
                        'hc_number' => $row['hc_number'],
                        'examen_fisico' => $row['examen_fisico'],
                        'plan' => $row['plan'],
                        'fecha' => $row['fecha'],
                    ],
                ];
            }

            return [
                'success' => true,
                'data' => null,
                'message' => 'No se encontró consulta anterior con examen físico.',
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error al buscar consulta anterior',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerPlan(?string $formId, ?string $hcNumber): array
    {
        $formId = trim((string) ($formId ?? ''));
        $hcNumber = trim((string) ($hcNumber ?? ''));

        if ($formId === '' && $hcNumber === '') {
            throw new RuntimeException('Parámetros requeridos: form_id o hcNumber');
        }

        $sql = 'SELECT form_id, hc_number, plan, fecha
                FROM consulta_data
                WHERE 1=1';
        $params = [];

        if ($formId !== '') {
            $sql .= ' AND form_id = :form_id';
            $params[':form_id'] = $formId;
        }

        if ($hcNumber !== '') {
            $sql .= ' AND hc_number = :hc_number';
            $params[':hc_number'] = $hcNumber;
        }

        $sql .= ' ORDER BY fecha DESC LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return ['success' => false, 'message' => 'No se encontró un plan registrado para esos parámetros'];
        }

        return [
            'success' => true,
            'data' => [
                'form_id' => $row['form_id'],
                'hc_number' => $row['hc_number'],
                'plan' => $row['plan'] ?? '',
                'fecha' => $row['fecha'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function actualizarPlan(array $payload): array
    {
        $formId = trim((string) ($payload['form_id'] ?? $payload['formId'] ?? ''));
        $hcNumber = trim((string) ($payload['hcNumber'] ?? $payload['hc_number'] ?? ''));
        $plan = $this->normalizeString(isset($payload['plan']) ? (string) $payload['plan'] : '');

        if ($formId === '' || $hcNumber === '') {
            throw new RuntimeException('form_id y hcNumber son obligatorios');
        }

        $update = $this->db->prepare('UPDATE consulta_data SET plan = :plan WHERE form_id = :form_id AND hc_number = :hc_number');
        $update->execute([
            ':plan' => $plan,
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);

        if ($update->rowCount() === 0) {
            $insert = $this->db->prepare(
                'INSERT INTO consulta_data (form_id, hc_number, fecha, motivo_consulta, enfermedad_actual, examen_fisico, plan, diagnosticos, examenes)
                VALUES (:form_id, :hc_number, CURRENT_DATE, NULL, NULL, NULL, :plan, \'[]\', \'[]\')
                ON DUPLICATE KEY UPDATE plan = VALUES(plan)'
            );

            $insert->execute([
                ':form_id' => $formId,
                ':hc_number' => $hcNumber,
                ':plan' => $plan,
            ]);
        }

        return [
            'success' => true,
            'plan' => $plan,
            'message' => 'Plan actualizado correctamente en MedForge',
        ];
    }

    private function normalizeString(?string $value): string
    {
        return trim($value ?? '');
    }

    private function normalizeOptionalId(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $normalized = is_string($value) ? trim($value) : $value;
        if ($normalized === '' || (is_string($normalized) && strtoupper($normalized) === 'SELECCIONE')) {
            return null;
        }

        return $normalized;
    }

    private function normalizeOptionalTime(mixed $value): ?string
    {
        $string = is_string($value) ? trim($value) : '';
        return $string === '' ? null : $string;
    }

    private function normalizeOptionalFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = str_replace(',', '.', trim((string) $value));
        return is_numeric($string) ? (float) $string : null;
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = strip_tags((string) $value);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        $text = trim($text);

        if ($text === '' || strtoupper($text) === 'SELECCIONE') {
            return null;
        }

        return $text;
    }
}
