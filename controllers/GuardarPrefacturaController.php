<?php

namespace Controllers;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class GuardarPrefacturaController
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;
    private const DEFAULT_SOURCE = 'extension_cive';

    private PDO $db;
    /** @var array<string, bool> */
    private array $tablaExisteCache = [];

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function guardar(array $data): array
    {
        $procedimientos = array_map(
            [$this, 'sanearProcedimiento'],
            $this->sanearColeccion($data['procedimientos'] ?? [])
        );
        $diagnosticos = $this->sanearColeccion($data['diagnosticos'] ?? []);

        $sql = "
            INSERT INTO prefactura_paciente (
                sede, area, afiliacion, parentesco,
                hc_number, tipo_afiliacion,
                numero_aprobacion, tipo_plan,
                fecha_registro, fecha_vigencia,
                cod_derivacion, num_secuencial_derivacion,
                num_historia, examen_fisico,
                observaciones, procedimientos, diagnosticos
            )
            VALUES (
                :sede, :area, :afiliacion, :parentesco,
                :hc_number, :tipo_afiliacion, :numero_aprobacion, :tipo_plan,
                :fecha_registro, :fecha_vigencia, :cod_derivacion, :num_secuencial_derivacion,
                :num_historia, :examen_fisico, :observaciones, :procedimientos, :diagnosticos
            )
            ON DUPLICATE KEY UPDATE
                sede = VALUES(sede),
                area = VALUES(area),
                afiliacion = VALUES(afiliacion),
                parentesco = VALUES(parentesco),
                tipo_afiliacion = VALUES(tipo_afiliacion),
                numero_aprobacion = VALUES(numero_aprobacion),
                tipo_plan = VALUES(tipo_plan),
                fecha_registro = VALUES(fecha_registro),
                fecha_vigencia = VALUES(fecha_vigencia),
                cod_derivacion = VALUES(cod_derivacion),
                num_secuencial_derivacion = VALUES(num_secuencial_derivacion),
                num_historia = VALUES(num_historia),
                examen_fisico = VALUES(examen_fisico),
                observaciones = VALUES(observaciones),
                procedimientos = VALUES(procedimientos),
                diagnosticos = VALUES(diagnosticos)
        ";

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':sede' => $data['sede'] ?? null,
                ':area' => $data['area'] ?? null,
                ':afiliacion' => $data['afiliacion'] ?? null,
                ':parentesco' => $data['parentesco'] ?? null,
                ':hc_number' => $data['hcNumber'] ?? null,
                ':tipo_afiliacion' => $data['tipoAfiliacion'] ?? null,
                ':numero_aprobacion' => $data['numeroAprobacion'] ?? null,
                ':tipo_plan' => $data['tipoPlan'] ?? null,
                ':fecha_registro' => $data['fechaRegistro'] ?? null,
                ':fecha_vigencia' => $data['fechaVigencia'] ?? null,
                ':cod_derivacion' => $data['codDerivacion'] ?? null,
                ':num_secuencial_derivacion' => $data['numSecuencialDerivacion'] ?? null,
                ':num_historia' => $data['numHistoria'] ?? null,
                ':examen_fisico' => $data['examenFisico'] ?? null,
                ':observaciones' => $data['observacion'] ?? null,
                ':procedimientos' => $this->encodeOrNull($procedimientos),
                ':diagnosticos' => $this->encodeOrNull($diagnosticos),
            ]);

            $prefacturaId = $this->resolverPrefacturaId($data);
            if (!$prefacturaId) {
                throw new RuntimeException('No se pudo determinar el prefactura_id generado.');
            }

            $this->sincronizarProcedimientos($prefacturaId, $procedimientos);
            $this->sincronizarDiagnosticos($prefacturaId, $diagnosticos);
            $this->registrarAuditoria($prefacturaId, $data);

            $this->db->commit();

            return ["success" => true, "message" => "Datos guardados correctamente."];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log('Error al guardar prefactura: ' . $e->getMessage());

            return [
                "success" => false,
                "message" => "Error al guardar los datos de la prefactura.",
            ];
        }
    }

    private function encodeOrNull(array $payload): ?string
    {
        if ($payload === []) {
            return null;
        }

        $json = json_encode($payload, self::JSON_FLAGS);

        return $json === false ? null : $json;
    }

    private function sanearColeccion(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            $value,
            static fn($item) => is_array($item)
        ));
    }

    private function sanearProcedimiento(array $procedimiento): array
    {
        if (isset($procedimiento['procPrecio'])) {
            $procedimiento['procPrecio'] = $this->normalizarDecimal($procedimiento['procPrecio']);
        }
        if (isset($procedimiento['precioBase'])) {
            $procedimiento['precioBase'] = $this->normalizarDecimal($procedimiento['precioBase']);
        }

        return $procedimiento;
    }

    private function normalizarDecimal(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        $valor = preg_replace('/[^0-9,.-]/', '', $valor) ?? '';
        if ($valor === '') {
            return null;
        }

        $ultimaComa = strrpos($valor, ',');
        $ultimoPunto = strrpos($valor, '.');

        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        } elseif ($ultimaComa !== false) {
            $valor = str_replace(',', '.', $valor);
        }

        return $valor;
    }

    private function resolverPrefacturaId(array $data): ?int
    {
        $lastInsertId = (int) $this->db->lastInsertId();
        if ($lastInsertId > 0) {
            return $lastInsertId;
        }

        $hcNumber = $data['hcNumber'] ?? null;
        if (!$hcNumber) {
            return null;
        }

        $sql = 'SELECT id FROM prefactura_paciente WHERE hc_number = :hc_number';
        $params = [':hc_number' => $hcNumber];
        $filters = [];

        if (!empty($data['numHistoria'])) {
            $filters[] = 'num_historia = :num_historia';
            $params[':num_historia'] = $data['numHistoria'];
        }
        if (!empty($data['codDerivacion'])) {
            $filters[] = 'cod_derivacion = :cod_derivacion';
            $params[':cod_derivacion'] = $data['codDerivacion'];
        }
        if (!empty($data['fechaRegistro'])) {
            $filters[] = 'fecha_registro = :fecha_registro';
            $params[':fecha_registro'] = $data['fechaRegistro'];
        }

        if ($filters) {
            $sql .= ' AND ' . implode(' AND ', $filters);
        }

        $sql .= ' ORDER BY fecha_creacion DESC, id DESC LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $prefacturaId = $stmt->fetchColumn();

        if ($prefacturaId) {
            return (int) $prefacturaId;
        }

        $fallback = $this->db->prepare(
            'SELECT id FROM prefactura_paciente WHERE hc_number = ? ORDER BY fecha_creacion DESC, id DESC LIMIT 1'
        );
        $fallback->execute([$hcNumber]);

        $prefacturaId = $fallback->fetchColumn();

        return $prefacturaId ? (int) $prefacturaId : null;
    }

    private function sincronizarProcedimientos(int $prefacturaId, array $procedimientos): void
    {
        if (!$this->tablaExiste('prefactura_detalle_procedimientos')) {
            return;
        }

        $delete = $this->db->prepare('DELETE FROM prefactura_detalle_procedimientos WHERE prefactura_id = ?');
        $delete->execute([$prefacturaId]);

        if ($procedimientos === []) {
            return;
        }

        $sql = "
            INSERT INTO prefactura_detalle_procedimientos (
                prefactura_id, posicion, external_id, proc_interno, codigo,
                descripcion, lateralidad, observaciones, precio_base, precio_tarifado, raw
            ) VALUES (
                :prefactura_id, :posicion, :external_id, :proc_interno, :codigo,
                :descripcion, :lateralidad, :observaciones, :precio_base, :precio_tarifado, :raw
            )
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($procedimientos as $index => $procedimiento) {
            $descripcion = $procedimiento['procDetalle'] ?? $procedimiento['procedimiento'] ?? $procedimiento['procInterno'] ?? null;
            $stmt->execute([
                ':prefactura_id' => $prefacturaId,
                ':posicion' => $index,
                ':external_id' => $procedimiento['id'] ?? null,
                ':proc_interno' => $procedimiento['procInterno'] ?? ($procedimiento['procedimiento'] ?? null),
                ':codigo' => $procedimiento['procCodigo'] ?? null,
                ':descripcion' => $descripcion,
                ':lateralidad' => $procedimiento['ojoId'] ?? null,
                ':observaciones' => $procedimiento['observaciones'] ?? null,
                ':precio_base' => $procedimiento['precioBase'] ?? null,
                ':precio_tarifado' => $procedimiento['procPrecio'] ?? null,
                ':raw' => json_encode($procedimiento, self::JSON_FLAGS) ?: null,
            ]);
        }
    }

    private function sincronizarDiagnosticos(int $prefacturaId, array $diagnosticos): void
    {
        if (!$this->tablaExiste('prefactura_detalle_diagnosticos')) {
            return;
        }

        $delete = $this->db->prepare('DELETE FROM prefactura_detalle_diagnosticos WHERE prefactura_id = ?');
        $delete->execute([$prefacturaId]);

        if ($diagnosticos === []) {
            return;
        }

        $sql = "
            INSERT INTO prefactura_detalle_diagnosticos (
                prefactura_id, posicion, diagnostico_codigo, descripcion,
                lateralidad, evidencia, observaciones, raw
            ) VALUES (
                :prefactura_id, :posicion, :codigo, :descripcion,
                :lateralidad, :evidencia, :observaciones, :raw
            )
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($diagnosticos as $index => $diagnostico) {
            $stmt->execute([
                ':prefactura_id' => $prefacturaId,
                ':posicion' => $index,
                ':codigo' => $diagnostico['idDiagnostico'] ?? ($diagnostico['codigo'] ?? null),
                ':descripcion' => $diagnostico['diagnostico'] ?? $diagnostico['descripcion'] ?? null,
                ':lateralidad' => $diagnostico['ojo'] ?? $diagnostico['ojoId'] ?? null,
                ':evidencia' => $diagnostico['evidencia'] ?? null,
                ':observaciones' => $diagnostico['observaciones'] ?? null,
                ':raw' => json_encode($diagnostico, self::JSON_FLAGS) ?: null,
            ]);
        }
    }

    private function registrarAuditoria(int $prefacturaId, array $data): void
    {
        if (!$this->tablaExiste('prefactura_payload_audit')) {
            return;
        }

        $json = json_encode($data, self::JSON_FLAGS);
        if ($json === false) {
            $json = '{}';
        }

        $hash = hash('sha256', $json);
        $source = $data['source'] ?? self::DEFAULT_SOURCE;
        $formId = $data['form_id'] ?? $data['formId'] ?? null;
        $hcNumber = $data['hcNumber'] ?? null;

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO prefactura_payload_audit (
                    prefactura_id, hc_number, form_id, source, payload_hash, payload_json
                ) VALUES (
                    :prefactura_id, :hc_number, :form_id, :source, :payload_hash, :payload_json
                )
                ON DUPLICATE KEY UPDATE
                    payload_json = VALUES(payload_json),
                    received_at = CURRENT_TIMESTAMP"
            );

            $stmt->execute([
                ':prefactura_id' => $prefacturaId,
                ':hc_number' => $hcNumber,
                ':form_id' => $formId,
                ':source' => $source,
                ':payload_hash' => $hash,
                ':payload_json' => $json,
            ]);
        } catch (PDOException $exception) {
            error_log('No se pudo registrar la auditoría de prefactura: ' . $exception->getMessage());
        }
    }

    private function tablaExiste(string $tabla): bool
    {
        if (isset($this->tablaExisteCache[$tabla])) {
            return $this->tablaExisteCache[$tabla];
        }

        try {
            $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $this->db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                $stmt->execute([$tabla]);
                $exists = (bool) $stmt->fetchColumn();
            } else {
                $stmt = $this->db->prepare(
                    'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
                );
                $stmt->execute([$tabla]);
                $exists = (bool) $stmt->fetchColumn();
            }
        } catch (PDOException) {
            $exists = false;
        }

        return $this->tablaExisteCache[$tabla] = $exists;
    }
}