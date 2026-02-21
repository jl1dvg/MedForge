<?php

namespace App\Modules\Billing\Services;

use PDO;
use PDOException;
use Throwable;

class BillingWriteParityService
{
    /** @var array<string, array<int, string>> */
    private array $columnsCache = [];

    /** @var array<string, bool> */
    private array $tableExistsCache = [];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array{billing_id:int, created:bool}
     */
    public function crearDesdeNoFacturado(string $formId, string $hcNumber, ?int $userId): array
    {
        $existing = $this->findBillingByFormId($formId);
        if ($existing !== null) {
            return [
                'billing_id' => (int) ($existing['id'] ?? 0),
                'created' => false,
            ];
        }

        $this->db->beginTransaction();

        try {
            $billingId = $this->insertBillingMain($formId, $hcNumber, $userId);
            $this->syncBillingCreatedAt($billingId, $formId);
            $this->seedBillingDetailsFromPreview($billingId, $formId);
            $this->db->commit();

            return [
                'billing_id' => $billingId,
                'created' => true,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function eliminarFactura(string $formId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM billing_main WHERE form_id = ?');
        $stmt->execute([$formId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<int, string|int> $formIds
     * @return array{success:bool,existentes:array<int,string>,nuevos:array<int,string>,message?:string}
     */
    public function verificarFormIds(array $formIds): array
    {
        $normalized = [];
        foreach ($formIds as $formId) {
            $value = trim((string) $formId);
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        if ($normalized === []) {
            return [
                'success' => false,
                'message' => 'No se enviaron form_ids.',
                'existentes' => [],
                'nuevos' => [],
            ];
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        $stmt = $this->db->prepare("SELECT form_id FROM procedimiento_proyectado WHERE form_id IN ($placeholders)");
        $stmt->execute($normalized);
        $existentes = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $nuevos = array_values(array_diff($normalized, $existentes));

        return [
            'success' => true,
            'existentes' => array_values(array_unique($existentes)),
            'nuevos' => $nuevos,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $procedimientos
     * @return array{
     *   procedimiento_proyectado:array{creados:array<int,string>,ya_existian:array<int,string>},
     *   billing:array{nuevos:array<int,string>,existentes:array<int,string>,procedimientos_insertados:array<int,string>},
     *   errores:array<int,string>
     * }
     */
    public function registrarProcedimientoCompleto(array $procedimientos, ?int $userId): array
    {
        $resultadoProyectado = $this->crearFormIdsFaltantes($procedimientos);
        $resultadoBilling = $this->insertarBillingMainSiNoExiste($procedimientos, $userId);

        return [
            'procedimiento_proyectado' => [
                'creados' => $resultadoProyectado['creados'],
                'ya_existian' => $resultadoProyectado['ya_existian'],
            ],
            'billing' => [
                'nuevos' => $resultadoBilling['nuevos'],
                'existentes' => $resultadoBilling['existentes'],
                'procedimientos_insertados' => $resultadoBilling['procedimientos_insertados'],
            ],
            'errores' => array_merge($resultadoProyectado['errores'], $resultadoBilling['errores']),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $procedimientos
     * @return array{creados:array<int,string>,ya_existian:array<int,string>,errores:array<int,string>}
     */
    private function crearFormIdsFaltantes(array $procedimientos): array
    {
        $formIds = [];
        foreach ($procedimientos as $item) {
            $formId = trim((string) ($item['form_id'] ?? ''));
            if ($formId !== '') {
                $formIds[] = $formId;
            }
        }

        if ($formIds === []) {
            return ['creados' => [], 'ya_existian' => [], 'errores' => []];
        }

        $placeholders = implode(',', array_fill(0, count($formIds), '?'));
        $stmt = $this->db->prepare("SELECT form_id FROM procedimiento_proyectado WHERE form_id IN ($placeholders)");
        $stmt->execute($formIds);
        $existentes = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $creados = [];
        $errores = [];
        $columns = $this->tableColumns('procedimiento_proyectado');
        $now = date('Y-m-d H:i:s');

        foreach ($procedimientos as $item) {
            $formId = trim((string) ($item['form_id'] ?? ''));
            $hcNumber = trim((string) ($item['hc_number'] ?? ''));

            if ($formId === '' || $hcNumber === '') {
                continue;
            }

            if (in_array($formId, $existentes, true)) {
                continue;
            }

            if (!is_numeric($formId)) {
                $errores[] = "form_id inválido: {$formId}";
                continue;
            }

            $payload = [];
            if (in_array('form_id', $columns, true)) {
                $payload['form_id'] = (int) $formId;
            }
            if (in_array('hc_number', $columns, true)) {
                $payload['hc_number'] = $hcNumber;
            }
            if (in_array('procedimiento_proyectado', $columns, true)) {
                $payload['procedimiento_proyectado'] = (string) ($item['procedimiento_proyectado'] ?? $item['detalle'] ?? '');
            }
            if (in_array('doctor', $columns, true)) {
                $payload['doctor'] = $this->nullableString($item['doctor'] ?? null);
            }
            if (in_array('fecha', $columns, true)) {
                $payload['fecha'] = $this->normalizeDate($item['fecha'] ?? null);
            }
            if (in_array('hora', $columns, true)) {
                $payload['hora'] = $this->normalizeTime($item['hora'] ?? null);
            }
            if (in_array('sede_departamento', $columns, true)) {
                $payload['sede_departamento'] = $this->nullableString($item['sede_departamento'] ?? null);
            }
            if (in_array('id_sede', $columns, true)) {
                $payload['id_sede'] = is_numeric($item['id_sede'] ?? null) ? (int) $item['id_sede'] : null;
            }
            if (in_array('afiliacion', $columns, true)) {
                $payload['afiliacion'] = $this->nullableString($item['afiliacion'] ?? null);
            }
            if (in_array('estado_agenda', $columns, true)) {
                $payload['estado_agenda'] = $this->nullableString($item['estado_agenda'] ?? null);
            }
            if (in_array('visita_id', $columns, true)) {
                $payload['visita_id'] = is_numeric($item['visita_id'] ?? null) ? (int) $item['visita_id'] : null;
            }
            if (in_array('created_at', $columns, true)) {
                $payload['created_at'] = $now;
            }
            if (in_array('updated_at', $columns, true)) {
                $payload['updated_at'] = $now;
            }

            try {
                $this->insertRow('procedimiento_proyectado', $payload);
                $existentes[] = $formId;
                $creados[] = $formId;
            } catch (Throwable $e) {
                $errores[] = "Error insertando procedimiento_proyectado {$formId}: {$e->getMessage()}";
            }
        }

        return [
            'creados' => $creados,
            'ya_existian' => array_values(array_unique($existentes)),
            'errores' => $errores,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $procedimientos
     * @return array{
     *   nuevos:array<int,string>,
     *   existentes:array<int,string>,
     *   procedimientos_insertados:array<int,string>,
     *   errores:array<int,string>
     * }
     */
    private function insertarBillingMainSiNoExiste(array $procedimientos, ?int $userId): array
    {
        $formIds = [];
        foreach ($procedimientos as $item) {
            $formId = trim((string) ($item['form_id'] ?? ''));
            if ($formId !== '') {
                $formIds[] = $formId;
            }
        }

        if ($formIds === []) {
            return [
                'nuevos' => [],
                'existentes' => [],
                'procedimientos_insertados' => [],
                'errores' => [],
            ];
        }

        $placeholders = implode(',', array_fill(0, count($formIds), '?'));
        $stmt = $this->db->prepare("SELECT form_id, id FROM billing_main WHERE form_id IN ($placeholders)");
        $stmt->execute($formIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $existingByFormId = [];
        foreach ($rows as $row) {
            $existingByFormId[(string) $row['form_id']] = (int) $row['id'];
        }

        $nuevos = [];
        $procedimientosInsertados = [];
        $errores = [];

        foreach ($procedimientos as $item) {
            $formId = trim((string) ($item['form_id'] ?? ''));
            $hcNumber = trim((string) ($item['hc_number'] ?? ''));
            if ($formId === '' || $hcNumber === '') {
                continue;
            }

            $billingId = $existingByFormId[$formId] ?? null;
            if ($billingId === null) {
                try {
                    $billingId = $this->insertBillingMain($formId, $hcNumber, $userId);
                    $existingByFormId[$formId] = $billingId;
                    $nuevos[] = $formId;
                    $this->syncBillingCreatedAt($billingId, $formId);
                } catch (Throwable $e) {
                    $errores[] = "Error insertando billing_main {$formId}: {$e->getMessage()}";
                    continue;
                }
            }

            $codigoDerivacion = trim((string) ($item['codigo_derivacion'] ?? ''));
            if ($codigoDerivacion !== '') {
                try {
                    $this->upsertLegacyDerivacion($item);
                } catch (Throwable $e) {
                    $errores[] = "No se pudo registrar derivación {$formId}: {$e->getMessage()}";
                }
            }

            [$codigo, $detalle] = $this->extractCodigoDetalle($item);
            if ($billingId > 0 && $codigo !== '' && $detalle !== '') {
                try {
                    if (!$this->existsBillingProcedimiento($billingId, $codigo, $detalle)) {
                        $this->insertBillingProcedimiento($billingId, $codigo, $detalle, $this->lookupTarifa($codigo));
                    }
                    $procedimientosInsertados[] = $formId;
                } catch (Throwable $e) {
                    $errores[] = "Error insertando procedimiento billing {$formId}: {$e->getMessage()}";
                }
            }
        }

        return [
            'nuevos' => $nuevos,
            'existentes' => array_values(array_unique(array_keys($existingByFormId))),
            'procedimientos_insertados' => array_values(array_unique($procedimientosInsertados)),
            'errores' => $errores,
        ];
    }

    private function existsBillingProcedimiento(int $billingId, string $codigo, string $detalle): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM billing_procedimientos WHERE billing_id = ? AND proc_codigo = ? AND proc_detalle = ? LIMIT 1'
        );
        $stmt->execute([$billingId, $codigo, $detalle]);

        return $stmt->fetchColumn() !== false;
    }

    private function insertBillingProcedimiento(int $billingId, string $codigo, string $detalle, float $precio): void
    {
        $columns = $this->tableColumns('billing_procedimientos');
        $payload = [];

        if (in_array('billing_id', $columns, true)) {
            $payload['billing_id'] = $billingId;
        }
        if (in_array('procedimiento_id', $columns, true)) {
            $payload['procedimiento_id'] = null;
        }
        if (in_array('proc_codigo', $columns, true)) {
            $payload['proc_codigo'] = $codigo;
        }
        if (in_array('proc_detalle', $columns, true)) {
            $payload['proc_detalle'] = $detalle;
        }
        if (in_array('proc_precio', $columns, true)) {
            $payload['proc_precio'] = $precio;
        }

        $this->insertRow('billing_procedimientos', $payload);
    }

    private function upsertLegacyDerivacion(array $item): void
    {
        if (!$this->hasTable('derivaciones_form_id')) {
            return;
        }

        $columns = $this->tableColumns('derivaciones_form_id');
        if (!in_array('form_id', $columns, true)) {
            return;
        }

        $formId = trim((string) ($item['form_id'] ?? ''));
        if ($formId === '') {
            return;
        }

        $payload = [];
        if (in_array('cod_derivacion', $columns, true)) {
            $payload['cod_derivacion'] = trim((string) ($item['codigo_derivacion'] ?? ''));
        }
        if (in_array('form_id', $columns, true)) {
            $payload['form_id'] = $formId;
        }
        if (in_array('hc_number', $columns, true)) {
            $payload['hc_number'] = $this->nullableString($item['hc_number'] ?? null);
        }
        if (in_array('fecha_registro', $columns, true)) {
            $payload['fecha_registro'] = $this->normalizeDate($item['fecha_registro'] ?? null);
        }
        if (in_array('fecha_vigencia', $columns, true)) {
            $payload['fecha_vigencia'] = $this->normalizeDate($item['fecha_vigencia'] ?? null);
        }
        if (in_array('referido', $columns, true)) {
            $payload['referido'] = $this->nullableString($item['referido'] ?? null);
        }
        if (in_array('diagnostico', $columns, true)) {
            $payload['diagnostico'] = $this->nullableString($item['diagnostico'] ?? null);
        }
        if (in_array('sede', $columns, true)) {
            $payload['sede'] = $this->nullableString($item['sede'] ?? null);
        }
        if (in_array('parentesco', $columns, true)) {
            $payload['parentesco'] = $this->nullableString($item['parentesco'] ?? null);
        }
        if (in_array('archivo_derivacion_path', $columns, true)) {
            $payload['archivo_derivacion_path'] = $this->nullableString($item['archivo_derivacion_path'] ?? null);
        }

        $stmt = $this->db->prepare('SELECT id FROM derivaciones_form_id WHERE form_id = ? LIMIT 1');
        $stmt->execute([$formId]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            $this->insertRow('derivaciones_form_id', $payload);
            return;
        }

        $update = $payload;
        unset($update['form_id']);
        if ($update === []) {
            return;
        }

        $assignments = [];
        $params = [];
        foreach ($update as $column => $value) {
            $assignments[] = "`{$column}` = ?";
            $params[] = $value;
        }
        $params[] = (int) $id;

        $sql = 'UPDATE derivaciones_form_id SET ' . implode(', ', $assignments) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    private function findBillingByFormId(string $formId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM billing_main WHERE form_id = ? LIMIT 1');
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function insertBillingMain(string $formId, string $hcNumber, ?int $userId): int
    {
        $columns = $this->tableColumns('billing_main');
        $payload = [];

        if (in_array('hc_number', $columns, true)) {
            $payload['hc_number'] = $hcNumber;
        }
        if (in_array('form_id', $columns, true)) {
            $payload['form_id'] = $formId;
        }
        if (in_array('facturado_por', $columns, true) && $userId !== null && $userId > 0) {
            $payload['facturado_por'] = $userId;
        }
        if (in_array('created_at', $columns, true) && !array_key_exists('created_at', $payload)) {
            $payload['created_at'] = date('Y-m-d H:i:s');
        }
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }

        $this->insertRow('billing_main', $payload);

        return (int) $this->db->lastInsertId();
    }

    private function syncBillingCreatedAt(int $billingId, string $formId): void
    {
        $columns = $this->tableColumns('billing_main');
        if (!in_array('created_at', $columns, true)) {
            return;
        }

        $stmt = $this->db->prepare('SELECT fecha_inicio FROM protocolo_data WHERE form_id = ? LIMIT 1');
        $stmt->execute([$formId]);
        $fechaInicio = $stmt->fetchColumn();
        if (!is_string($fechaInicio) || trim($fechaInicio) === '') {
            return;
        }

        $stmt = $this->db->prepare('UPDATE billing_main SET created_at = ? WHERE id = ?');
        $stmt->execute([$fechaInicio, $billingId]);
    }

    private function seedBillingDetailsFromPreview(int $billingId, string $formId): void
    {
        if (!$this->hasTable('billing_procedimientos')) {
            return;
        }

        $procedimientos = $this->buildProcedimientosPreview($formId);
        foreach ($procedimientos as $procedimiento) {
            $codigo = trim((string) ($procedimiento['procCodigo'] ?? ''));
            $detalle = trim((string) ($procedimiento['procDetalle'] ?? ''));
            if ($codigo === '' || $detalle === '') {
                continue;
            }

            $this->insertBillingProcedimiento(
                $billingId,
                $codigo,
                $detalle,
                (float) ($procedimiento['procPrecio'] ?? 0)
            );
        }
    }

    /**
     * @return array<int, array{procCodigo:string,procDetalle:string,procPrecio:float}>
     */
    private function buildProcedimientosPreview(string $formId): array
    {
        $result = [];
        $seen = [];

        $stmt = $this->db->prepare('SELECT procedimientos FROM protocolo_data WHERE form_id = ? LIMIT 1');
        $stmt->execute([$formId]);
        $json = $stmt->fetchColumn();
        if (is_string($json) && trim($json) !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $proc) {
                    if (!is_array($proc)) {
                        continue;
                    }
                    $raw = (string) ($proc['procInterno'] ?? '');
                    [$codigo, $detalle] = $this->parseCodigoDetalle($raw);
                    if ($codigo === '' || $detalle === '') {
                        continue;
                    }
                    $key = $codigo . '|' . $detalle;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $result[] = [
                        'procCodigo' => $codigo,
                        'procDetalle' => $detalle,
                        'procPrecio' => $this->lookupTarifa($codigo),
                    ];
                }
            }
        }

        if ($result !== []) {
            return $result;
        }

        $stmt = $this->db->prepare('SELECT procedimiento_proyectado FROM procedimiento_proyectado WHERE form_id = ? LIMIT 1');
        $stmt->execute([$formId]);
        $raw = (string) ($stmt->fetchColumn() ?: '');
        [$codigo, $detalle] = $this->parseCodigoDetalle($raw);
        if ($codigo !== '' && $detalle !== '') {
            $result[] = [
                'procCodigo' => $codigo,
                'procDetalle' => $detalle,
                'procPrecio' => $this->lookupTarifa($codigo),
            ];
        }

        return $result;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function parseCodigoDetalle(string $raw): array
    {
        $text = trim($raw);
        if ($text === '') {
            return ['', ''];
        }

        if (preg_match('/-\s*(\d{5,6})\s*-\s*(.+)$/', $text, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        if (preg_match('/\b(\d{5,6})\b/', $text, $matches) === 1) {
            $codigo = trim($matches[1]);
            $detalle = trim(str_replace($codigo, '', $text));
            $detalle = trim(preg_replace('/\s+/', ' ', $detalle) ?? $detalle);
            return [$codigo, $detalle !== '' ? $detalle : $text];
        }

        return ['', ''];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{0:string,1:string}
     */
    private function extractCodigoDetalle(array $item): array
    {
        $codigo = trim((string) ($item['codigo'] ?? ''));
        $detalle = trim((string) ($item['detalle'] ?? ''));
        if ($codigo !== '' && $detalle !== '') {
            return [$codigo, $detalle];
        }

        $raw = (string) ($item['procedimiento_proyectado'] ?? '');
        return $this->parseCodigoDetalle($raw);
    }

    private function lookupTarifa(string $codigo): float
    {
        $stmt = $this->db->prepare(
            'SELECT valor_facturar_nivel3 FROM tarifario_2014 WHERE codigo = ? OR codigo = ? LIMIT 1'
        );
        $stmt->execute([$codigo, ltrim($codigo, '0')]);
        $value = $stmt->fetchColumn();

        return $value !== false ? (float) $value : 0.0;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertRow(string $table, array $payload): void
    {
        if ($payload === []) {
            throw new PDOException("No hay columnas para insertar en {$table}.");
        }

        $columns = array_keys($payload);
        $quoted = array_map(static fn (string $column): string => "`{$column}`", $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $quoted) . ') VALUES (' . $placeholders . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($payload));
    }

    /**
     * @return array<int, string>
     */
    private function tableColumns(string $table): array
    {
        if (isset($this->columnsCache[$table])) {
            return $this->columnsCache[$table];
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return $this->columnsCache[$table] = [];
        }

        try {
            $stmt = $this->db->query('SHOW COLUMNS FROM `' . $table . '`');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            $rows = [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $field = isset($row['Field']) ? (string) $row['Field'] : '';
            if ($field !== '') {
                $columns[] = $field;
            }
        }

        return $this->columnsCache[$table] = $columns;
    }

    private function hasTable(string $table): bool
    {
        if (isset($this->tableExistsCache[$table])) {
            return $this->tableExistsCache[$table];
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return $this->tableExistsCache[$table] = false;
        }

        try {
            $stmt = $this->db->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            $exists = $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            $exists = false;
        }

        return $this->tableExistsCache[$table] = $exists;
    }

    private function nullableString(mixed $value): ?string
    {
        $clean = trim((string) $value);
        return $clean !== '' ? $clean : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return null;
        }

        $timestamp = strtotime($clean);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeTime(mixed $value): ?string
    {
        $clean = trim((string) $value);
        if ($clean === '') {
            return null;
        }

        $timestamp = strtotime($clean);
        if ($timestamp === false) {
            return null;
        }

        return date('H:i:s', $timestamp);
    }
}

