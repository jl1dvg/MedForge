<?php

namespace Modules\Cirugias\Services;

use Modules\Cirugias\Models\Cirugia;
use PDO;

class CirugiaService
{
    private ?string $lastError = null;

    public function __construct(private PDO $db)
    {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private function resetError(): void
    {
        $this->lastError = null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeJson(mixed $value, bool $allowNull = false, string $fallback = '[]'): ?string
    {
        if ($value === null) {
            return $allowNull ? null : $fallback;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return $allowNull ? null : $fallback;
            }

            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }

            $this->lastError = 'Formato JSON inválido recibido.';
            return $allowNull ? null : $fallback;
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

            if ($encoded !== false) {
                return $encoded;
            }

            $this->lastError = 'No se pudo codificar la información en formato JSON: ' . json_last_error_msg();
            return $allowNull ? null : $fallback;
        }

        return $allowNull ? null : $fallback;
    }

    private function decodeJsonArray(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $json = trim($json);

        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    private function hasInsumosContenido(array $insumos): bool
    {
        foreach ($insumos as $categoria => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $id = $item['id'] ?? null;
                $cantidad = $item['cantidad'] ?? null;
                $nombre = $item['nombre'] ?? null;

                if (
                    ($id !== null && $id !== '') ||
                    ($cantidad !== null && (int) $cantidad > 0) ||
                    ($nombre !== null && trim((string) $nombre) !== '')
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasMedicamentosContenido(array $medicamentos): bool
    {
        foreach ($medicamentos as $medicamento) {
            if (!is_array($medicamento)) {
                continue;
            }

            $id = $medicamento['id'] ?? null;
            $nombre = $medicamento['medicamento'] ?? null;
            $dosis = $medicamento['dosis'] ?? null;
            $frecuencia = $medicamento['frecuencia'] ?? null;
            $via = $medicamento['via_administracion'] ?? null;
            $responsable = $medicamento['responsable'] ?? null;

            if (
                ($id !== null && $id !== '') ||
                ($nombre !== null && trim((string) $nombre) !== '') ||
                ($dosis !== null && trim((string) $dosis) !== '') ||
                ($frecuencia !== null && trim((string) $frecuencia) !== '') ||
                ($via !== null && trim((string) $via) !== '') ||
                ($responsable !== null && trim((string) $responsable) !== '')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Devuelve el listado completo de cirugías con los campos necesarios para el reporte.
     *
     * @return Cirugia[]
     */
    public function obtenerCirugias(): array
    {
        $sql = "SELECT p.hc_number, p.fname, p.mname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion,
                       pr.fecha_inicio, pr.id, pr.membrete, pr.form_id, pr.hora_inicio, pr.hora_fin, pr.printed,
                       pr.dieresis, pr.exposicion, pr.hallazgo, pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia,
                       pr.procedimientos, pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos, pr.diagnosticos_previos, pp.procedimiento_proyectado,
                       pr.cirujano_1, pr.instrumentista, pr.cirujano_2, pr.circulante, pr.primer_ayudante, pr.anestesiologo,
                       pr.segundo_ayudante, pr.ayudante_anestesia, pr.tercer_ayudante, pr.status,
                       CASE WHEN bm.id IS NOT NULL THEN 1 ELSE 0 END AS existeBilling
                FROM patient_data p
                INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                LEFT JOIN billing_main bm ON bm.form_id = pr.form_id
                ORDER BY pr.fecha_inicio DESC, pr.id DESC";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $row) => new Cirugia($row), $rows);
    }

    /**
     * Devuelve los campos mínimos requeridos para la tabla del reporte.
     *
     * @return Cirugia[]
     */
    public function obtenerListaCirugias(): array
    {
        $sql = "SELECT
                    p.hc_number,
                    p.fname,
                    p.lname,
                    p.lname2,
                    p.afiliacion,
                    pr.fecha_inicio,
                    pr.membrete,
                    pr.form_id,
                    pr.printed,
                    pr.status,
                    CASE WHEN bm.id IS NOT NULL THEN 1 ELSE 0 END AS existeBilling
                FROM protocolo_data pr
                INNER JOIN patient_data p ON p.hc_number = pr.hc_number
                LEFT JOIN billing_main bm ON bm.form_id = pr.form_id
                ORDER BY pr.fecha_inicio DESC, pr.id DESC";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $row) => new Cirugia($row), $rows);
    }

    public function obtenerCirugiaPorId(string $formId, string $hcNumber): ?Cirugia
    {
        $sql = "SELECT p.hc_number, p.fname, p.mname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion,
                   pr.fecha_inicio, pr.id, pr.membrete, pr.form_id, pr.procedimiento_id, pr.hora_inicio, pr.hora_fin, pr.printed,
                   pr.dieresis, pr.exposicion, pr.hallazgo, pr.operatorio, pr.complicaciones_operatorio, pr.datos_cirugia,
                   pr.procedimientos, pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos, pr.diagnosticos_previos, pp.procedimiento_proyectado,
                   pr.cirujano_1, pr.instrumentista, pr.cirujano_2, pr.circulante, pr.primer_ayudante, pr.anestesiologo,
                   pr.segundo_ayudante, pr.ayudante_anestesia, pr.tercer_ayudante, pr.status, pr.insumos, pr.medicamentos
            FROM patient_data p
            INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
            WHERE pr.form_id = ? AND p.hc_number = ?
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$formId, $hcNumber]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? new Cirugia($result) : null;
    }

    public function obtenerProtocoloIdPorFormulario(string $formId, ?string $hcNumber = null): ?int
    {
        $sql = 'SELECT id FROM protocolo_data WHERE form_id = :form_id';
        $params = [':form_id' => $formId];

        if ($hcNumber !== null && $hcNumber !== '') {
            $sql .= ' AND hc_number = :hc_number';
            $params[':hc_number'] = $hcNumber;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function obtenerInsumosDisponibles(string $afiliacion): array
    {
        $afiliacion = strtolower($afiliacion);

        $sql = "
        SELECT
            id, categoria,
            IF(:afiliacion LIKE '%issfa%' AND producto_issfa <> '', producto_issfa, nombre) AS nombre_final,
            codigo_isspol, codigo_issfa, codigo_iess, codigo_msp
        FROM insumos
        GROUP BY id
        ORDER BY nombre_final
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['afiliacion' => $afiliacion]);

        $insumosDisponibles = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categoria = $row['categoria'];
            $id = $row['id'];
            $insumosDisponibles[$categoria][$id] = [
                'id' => $id,
                'nombre' => trim($row['nombre_final']),
                'codigo_isspol' => $row['codigo_isspol'],
                'codigo_issfa' => $row['codigo_issfa'],
                'codigo_iess' => $row['codigo_iess'],
                'codigo_msp' => $row['codigo_msp'],
            ];
        }

        return $insumosDisponibles;
    }

    public function obtenerInsumosPorProtocolo(?string $procedimientoId, ?string $jsonInsumosProtocolo): array
    {
        $decoded = $this->decodeJsonArray($jsonInsumosProtocolo);

        if ($decoded !== null && $this->hasInsumosContenido($decoded)) {
            return $decoded;
        }

        if (!$procedimientoId) {
            return [];
        }

        $sql = "SELECT insumos FROM insumos_pack WHERE procedimiento_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$procedimientoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $decoded = json_decode($row['insumos'] ?? '[]', true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    }

    public function obtenerMedicamentosConfigurados(?string $jsonMedicamentos, ?string $procedimientoId): array
    {
        $jsonMedicamentos = trim($jsonMedicamentos ?? '');
        if ($jsonMedicamentos !== '') {
            $decoded = json_decode($jsonMedicamentos, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && $this->hasMedicamentosContenido($decoded)) {
                return $decoded;
            }
        }

        if (!$procedimientoId) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT medicamentos FROM kardex WHERE procedimiento_id = ?");
        $stmt->execute([$procedimientoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $decoded = json_decode($row['medicamentos'] ?? '[]', true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
    }

    public function obtenerOpcionesMedicamentos(): array
    {
        $stmt = $this->db->query("SELECT id, medicamento FROM medicamentos ORDER BY medicamento");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardar(array $data): bool
    {
        $this->resetError();

        try {
            $existeStmt = $this->db->prepare("SELECT procedimiento_id FROM protocolo_data WHERE form_id = :form_id");
            $existeStmt->execute([':form_id' => $data['form_id']]);
            $procedimientoIdExistente = $existeStmt->fetchColumn();

            if (isset($procedimientoIdExistente) && empty($data['procedimiento_id'])) {
                $data['procedimiento_id'] = $procedimientoIdExistente;
            }

            $procedimientos = $this->normalizeJson($data['procedimientos'] ?? null);
            $diagnosticos = $this->normalizeJson($data['diagnosticos'] ?? null);
            $diagnosticosPrevios = $this->normalizeJson($data['diagnosticos_previos'] ?? null, true);
            $insumos = $this->normalizeJson($data['insumos'] ?? null);
            $medicamentos = $this->normalizeJson($data['medicamentos'] ?? null);

            if ($this->lastError !== null) {
                return false;
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
                'procedimiento_id' => $this->normalizeNullableString($data['procedimiento_id'] ?? null),
                'membrete' => $this->normalizeNullableString($data['membrete'] ?? null),
                'dieresis' => $this->normalizeNullableString($data['dieresis'] ?? null),
                'exposicion' => $this->normalizeNullableString($data['exposicion'] ?? null),
                'hallazgo' => $this->normalizeNullableString($data['hallazgo'] ?? null),
                'operatorio' => $this->normalizeNullableString($data['operatorio'] ?? null),
                'complicaciones_operatorio' => $this->normalizeNullableString($data['complicaciones_operatorio'] ?? null),
                'datos_cirugia' => $this->normalizeNullableString($data['datos_cirugia'] ?? null),
                'procedimientos' => $procedimientos,
                'diagnosticos' => $diagnosticos,
                'diagnosticos_previos' => $diagnosticosPrevios,
                'lateralidad' => $this->normalizeNullableString($data['lateralidad'] ?? null),
                'tipo_anestesia' => $this->normalizeNullableString($data['tipo_anestesia'] ?? null),
                'hora_inicio' => $this->normalizeNullableString($data['hora_inicio'] ?? null),
                'hora_fin' => $this->normalizeNullableString($data['hora_fin'] ?? null),
                'fecha_inicio' => $this->normalizeNullableString($data['fecha_inicio'] ?? null),
                'fecha_fin' => $this->normalizeNullableString($data['fecha_fin'] ?? null),
                'cirujano_1' => $this->normalizeNullableString($data['cirujano_1'] ?? null),
                'cirujano_2' => $this->normalizeNullableString($data['cirujano_2'] ?? null),
                'primer_ayudante' => $this->normalizeNullableString($data['primer_ayudante'] ?? null),
                'segundo_ayudante' => $this->normalizeNullableString($data['segundo_ayudante'] ?? null),
                'tercer_ayudante' => $this->normalizeNullableString($data['tercer_ayudante'] ?? null),
                'ayudante_anestesia' => $this->normalizeNullableString($data['ayudanteAnestesia'] ?? null),
                'anestesiologo' => $this->normalizeNullableString($data['anestesiologo'] ?? null),
                'instrumentista' => $this->normalizeNullableString($data['instrumentista'] ?? null),
                'circulante' => $this->normalizeNullableString($data['circulante'] ?? null),
                'insumos' => $insumos,
                'medicamentos' => $medicamentos,
                'status' => !empty($data['status']) ? 1 : 0,
                'form_id' => $data['form_id'],
                'hc_number' => $data['hc_number'],
            ])) {
                $protocoloId = (int)$this->db->lastInsertId();

                if ($protocoloId === 0) {
                    $searchStmt = $this->db->prepare("SELECT id FROM protocolo_data WHERE form_id = :form_id");
                    $searchStmt->execute([':form_id' => $data['form_id']]);
                    $protocoloId = (int)$searchStmt->fetchColumn();
                }

                $deleteStmt = $this->db->prepare("DELETE FROM protocolo_insumos WHERE protocolo_id = :protocolo_id");
                $deleteStmt->execute([':protocolo_id' => $protocoloId]);

                $insertStmt = $this->db->prepare("
                    INSERT INTO protocolo_insumos (protocolo_id, insumo_id, nombre, cantidad, categoria)
                    VALUES (:protocolo_id, :insumo_id, :nombre, :cantidad, :categoria)
                ");

                $insumos = is_string($insumos) ? json_decode($insumos, true) : $data['insumos'];

                if (is_array($insumos)) {
                    foreach (['equipos', 'anestesia', 'quirurgicos'] as $categoria) {
                        if (isset($insumos[$categoria]) && is_array($insumos[$categoria])) {
                            foreach ($insumos[$categoria] as $insumo) {
                                $insertStmt->execute([
                                    ':protocolo_id' => $protocoloId,
                                    ':insumo_id' => $insumo['id'] ?? null,
                                    ':nombre' => $insumo['nombre'] ?? '',
                                    ':cantidad' => $insumo['cantidad'] ?? 1,
                                    ':categoria' => $categoria,
                                ]);
                            }
                        }
                    }
                }

                $this->db->prepare("INSERT IGNORE INTO procedimiento_proyectado (form_id, hc_number) VALUES (:form_id, :hc_number)")
                    ->execute([
                        ':form_id' => $data['form_id'],
                        ':hc_number' => $data['hc_number'],
                    ]);

                $stmtExistentes = $this->db->prepare("SELECT dx_code FROM diagnosticos_asignados WHERE form_id = :form_id AND fuente = 'protocolo'");
                $stmtExistentes->execute([':form_id' => $data['form_id']]);
                $existentes = $stmtExistentes->fetchAll(PDO::FETCH_COLUMN, 0);

                $nuevosDx = [];
                $dxCodigosNuevos = [];

                $diagnosticos = is_string($diagnosticos) ? json_decode($diagnosticos, true) : $data['diagnosticos'];
                foreach ($diagnosticos as $dx) {
                    if (!isset($dx['idDiagnostico']) || $dx['idDiagnostico'] === 'SELECCIONE') {
                        continue;
                    }

                    $parts = explode(' - ', $dx['idDiagnostico'], 2);
                    $codigo = trim($parts[0] ?? '');
                    $descripcion = trim($parts[1] ?? '');

                    $dxCodigosNuevos[] = $codigo;

                    if (in_array($codigo, $existentes, true)) {
                        $stmtUpdate = $this->db->prepare("UPDATE diagnosticos_asignados SET descripcion = :descripcion, definitivo = :definitivo, lateralidad = :lateralidad, selector = :selector
                                                          WHERE form_id = :form_id AND fuente = 'protocolo' AND dx_code = :dx_code");
                        $stmtUpdate->execute([
                            ':form_id' => $data['form_id'],
                            ':dx_code' => $codigo,
                            ':descripcion' => $descripcion,
                            ':definitivo' => isset($dx['evidencia']) && in_array(strtoupper($dx['evidencia']), ['1', 'DEFINITIVO'], true) ? 1 : 0,
                            ':lateralidad' => $dx['ojo'] ?? null,
                            ':selector' => $dx['selector'] ?? null,
                        ]);
                    } else {
                        $nuevosDx[] = [
                            'form_id' => $data['form_id'],
                            'dx_code' => $codigo,
                            'descripcion' => $descripcion,
                            'definitivo' => isset($dx['evidencia']) && in_array(strtoupper($dx['evidencia']), ['1', 'DEFINITIVO'], true) ? 1 : 0,
                            'lateralidad' => $dx['ojo'] ?? null,
                            'selector' => $dx['selector'] ?? null,
                        ];
                    }
                }

                $codigosEliminar = array_diff($existentes, $dxCodigosNuevos);
                if (!empty($codigosEliminar)) {
                    $in = implode(',', array_fill(0, count($codigosEliminar), '?'));
                    $stmtDelete = $this->db->prepare("DELETE FROM diagnosticos_asignados WHERE form_id = ? AND fuente = 'protocolo' AND dx_code IN ($in)");
                    $stmtDelete->execute(array_merge([$data['form_id']], $codigosEliminar));
                }

                if (!empty($nuevosDx)) {
                    $insertDxStmt = $this->db->prepare("INSERT INTO diagnosticos_asignados (form_id, fuente, dx_code, descripcion, definitivo, lateralidad, selector)
                                                    VALUES (:form_id, 'protocolo', :dx_code, :descripcion, :definitivo, :lateralidad, :selector)");
                    foreach ($nuevosDx as $dx) {
                        $insertDxStmt->execute([
                            ':form_id' => $dx['form_id'],
                            ':dx_code' => $dx['dx_code'],
                            ':descripcion' => $dx['descripcion'],
                            ':definitivo' => $dx['definitivo'],
                            ':lateralidad' => $dx['lateralidad'],
                            ':selector' => $dx['selector'],
                        ]);
                    }
                }

                return true;
            }

            $errorInfo = $stmt->errorInfo();
            $this->lastError = $errorInfo[2] ?? 'No se pudo guardar la información del protocolo.';

            return false;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('❌ Error al guardar protocolo: ' . $e->getMessage());
            return false;
        }
    }

    public function guardarDesdeApi(array $data): array
    {
        $data['hc_number'] = $data['hc_number'] ?? $data['hcNumber'] ?? null;
        $data['form_id'] = $data['form_id'] ?? $data['formId'] ?? null;
        $data['fecha_inicio'] = $data['fecha_inicio'] ?? $data['fechaInicio'] ?? null;
        $data['fecha_fin'] = $data['fecha_fin'] ?? $data['fechaFin'] ?? null;
        $data['hora_inicio'] = $data['hora_inicio'] ?? $data['horaInicio'] ?? null;
        $data['hora_fin'] = $data['hora_fin'] ?? $data['horaFin'] ?? null;
        $data['tipo_anestesia'] = $data['tipo_anestesia'] ?? $data['tipoAnestesia'] ?? null;

        if (empty($data['procedimiento_id'])) {
            return ['success' => false, 'message' => 'El campo procedimiento_id es obligatorio.'];
        }

        $data['insumos'] = $data['insumos'] ?? [];
        $data['medicamentos'] = $data['medicamentos'] ?? [];

        if (!$data['hc_number'] || !$data['form_id']) {
            return ['success' => false, 'message' => 'Datos no válidos'];
        }

        $ok = $this->guardar($data);

        if ($ok) {
            $stmt = $this->db->prepare("SELECT id FROM protocolo_data WHERE form_id = :form_id");
            $stmt->execute([':form_id' => $data['form_id']]);
            $protocoloId = (int)$stmt->fetchColumn();

            return ['success' => true, 'message' => 'Datos guardados correctamente', 'protocolo_id' => $protocoloId];
        }

        return ['success' => false, 'message' => $this->lastError ?? 'Error al guardar el protocolo'];
    }

    public function actualizarPrinted(string $formId, string $hcNumber, int $printed): bool
    {
        $stmt = $this->db->prepare('UPDATE protocolo_data SET printed = :printed WHERE form_id = :form_id AND hc_number = :hc_number');
        return $stmt->execute([
            ':printed' => $printed,
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);
    }

    public function actualizarStatus(string $formId, string $hcNumber, int $status): bool
    {
        $stmt = $this->db->prepare('UPDATE protocolo_data SET status = :status WHERE form_id = :form_id AND hc_number = :hc_number');
        return $stmt->execute([
            ':status' => $status,
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);
    }

    public function guardarAutosave(string $formId, string $hcNumber, ?string $insumos, ?string $medicamentos): bool
    {
        $this->resetError();

        $sets = [];
        $params = [
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ];

        if ($insumos !== null) {
            $decodedInsumos = $this->decodeJsonArray($insumos);
            if ($decodedInsumos !== null && $this->hasInsumosContenido($decodedInsumos)) {
                $sets[] = 'insumos = :insumos';
                $params[':insumos'] = json_encode($decodedInsumos, JSON_UNESCAPED_UNICODE);
            }
        }

        if ($medicamentos !== null) {
            $decodedMedicamentos = $this->decodeJsonArray($medicamentos);
            if ($decodedMedicamentos !== null && $this->hasMedicamentosContenido($decodedMedicamentos)) {
                $sets[] = 'medicamentos = :medicamentos';
                $params[':medicamentos'] = json_encode($decodedMedicamentos, JSON_UNESCAPED_UNICODE);
            }
        }

        if (empty($sets)) {
            return true;
        }

        $sql = 'UPDATE protocolo_data SET ' . implode(', ', $sets) . ' WHERE form_id = :form_id AND hc_number = :hc_number';
        $stmt = $this->db->prepare($sql);

        if ($stmt->execute($params)) {
            return true;
        }

        $errorInfo = $stmt->errorInfo();
        $this->lastError = $errorInfo[2] ?? 'No se pudo actualizar el autosave.';

        return false;
    }
}
