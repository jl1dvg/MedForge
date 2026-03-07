<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

class CirugiasDerivacionService
{
    private ?string $lastError = null;
    /** @var array<string, bool> */
    private array $tableExistsCache = [];
    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(
        private readonly PDO $db,
        private readonly string $projectRoot,
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * @return array{
     *   payload:array<string,mixed>|null,
     *   raw_output:string,
     *   exit_code:int,
     *   diagnosticos_previos:array<int,array{cie10:string,descripcion:string,from_scraper:bool}>,
     *   derivacion_sync:bool
     * }
     */
    public function scrapearDerivacion(string $formId, string $hcNumber): array
    {
        $this->lastError = null;

        [$payload, $rawOutput, $exitCode] = $this->runScraper($formId, $hcNumber);

        if ($payload === null && $rawOutput !== '') {
            $payload = $this->extractPayloadFromRawOutput($rawOutput, $formId, $hcNumber);
        }

        $diagnosticoRaw = $this->resolveDiagnosticoRaw($payload, $rawOutput);
        $diagnosticosPrevios = $this->buildDiagnosticosPrevios($hcNumber, $diagnosticoRaw);

        $sync = false;
        if (is_array($payload)) {
            $sync = $this->syncDerivacion($formId, $hcNumber, $payload);
        }

        return [
            'payload' => $payload,
            'raw_output' => $rawOutput,
            'exit_code' => $exitCode,
            'diagnosticos_previos' => $diagnosticosPrevios,
            'derivacion_sync' => $sync,
        ];
    }

    /**
     * @return array{0:array<string,mixed>|null,1:string,2:int}
     */
    private function runScraper(string $formId, string $hcNumber): array
    {
        $scriptPath = $this->projectRoot . '/scrapping/scrape_log_admision.py';
        if (!is_file($scriptPath)) {
            throw new RuntimeException('No se encontró el script scrape_log_admision.py');
        }

        $python = trim((string) (env('PYTHON_BIN') ?: '/usr/bin/python3'));
        if ($python === '') {
            $python = '/usr/bin/python3';
        }

        if ($python !== 'python3' && !is_file($python)) {
            $python = 'python3';
        }

        $command = sprintf(
            '%s %s %s %s --quiet 2>&1',
            escapeshellcmd($python),
            escapeshellarg($scriptPath),
            escapeshellarg($formId),
            escapeshellarg($hcNumber)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        $rawOutput = trim(implode("\n", $output));
        $decoded = $this->parseJsonPayload($output, $rawOutput);
        if (!is_array($decoded)) {
            return [null, $rawOutput, $exitCode];
        }

        return [$this->extractDerivationPayload($decoded, $formId, $hcNumber), $rawOutput, $exitCode];
    }

    /**
     * @param array<int,string> $outputLines
     * @return array<string,mixed>|null
     */
    private function parseJsonPayload(array $outputLines, string $rawOutput): ?array
    {
        for ($i = count($outputLines) - 1; $i >= 0; $i--) {
            $line = trim((string) $outputLines[$i]);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if ($rawOutput === '') {
            return null;
        }

        $decoded = json_decode($rawOutput, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $decoded
     * @return array<string,mixed>
     */
    private function extractDerivationPayload(array $decoded, string $formId, string $hcNumber): array
    {
        $codigo = $this->sanitizeReferralCode((string) ($decoded['codigo_derivacion'] ?? $decoded['cod_derivacion'] ?? ''));

        return [
            'codigo_derivacion' => $codigo,
            'cod_derivacion' => $codigo,
            'form_id' => trim((string) ($decoded['form_id'] ?? $formId)),
            'hc_number' => trim((string) ($decoded['hc_number'] ?? $decoded['identificacion'] ?? $hcNumber)),
            'fecha_registro' => $decoded['fecha_registro'] ?? null,
            'fecha_vigencia' => $decoded['fecha_vigencia'] ?? null,
            'referido' => $decoded['referido'] ?? null,
            'diagnostico' => $decoded['diagnostico'] ?? null,
            'sede' => $decoded['sede'] ?? null,
            'parentesco' => $decoded['parentesco'] ?? null,
            'archivo_derivacion_path' => $decoded['archivo_derivacion_path'] ?? null,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function extractPayloadFromRawOutput(string $rawOutput, string $formId, string $hcNumber): ?array
    {
        if ($rawOutput === '') {
            return null;
        }

        $payload = [
            'codigo_derivacion' => '',
            'cod_derivacion' => '',
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'fecha_registro' => null,
            'fecha_vigencia' => null,
            'referido' => null,
            'diagnostico' => null,
            'sede' => null,
            'parentesco' => null,
            'archivo_derivacion_path' => null,
        ];

        if (preg_match('/Código Derivación:\s*([^\n]+)/u', $rawOutput, $matchCodigo)) {
            $payload['codigo_derivacion'] = $this->sanitizeReferralCode((string) $matchCodigo[1]);
            $payload['cod_derivacion'] = $payload['codigo_derivacion'];
        }

        if (preg_match('/Fecha de registro:\s*(\d{4}-\d{2}-\d{2})/u', $rawOutput, $matchRegistro)) {
            $payload['fecha_registro'] = $matchRegistro[1];
        }

        if (preg_match('/Fecha de Vigencia:\s*(\d{4}-\d{2}-\d{2})/u', $rawOutput, $matchVigencia)) {
            $payload['fecha_vigencia'] = $matchVigencia[1];
        }

        if (preg_match('/📌 Diagnostico:\s*(.+)/u', $rawOutput, $matchDiagnostico)) {
            $payload['diagnostico'] = trim((string) $matchDiagnostico[1]);
        } elseif (preg_match('/"diagnostico":\s*([^\n]+)/u', $rawOutput, $matchDiagnosticoJson)) {
            $payload['diagnostico'] = trim((string) $matchDiagnosticoJson[1], "\", ");
        }

        if (preg_match('/"hc_number":\s*"([^"]+)"/u', $rawOutput, $matchHc)) {
            $payload['hc_number'] = trim((string) $matchHc[1]);
        }

        return $payload;
    }

    private function resolveDiagnosticoRaw(?array $payload, string $rawOutput): string
    {
        $fromPayload = trim((string) ($payload['diagnostico'] ?? ''));
        if ($fromPayload !== '') {
            return $fromPayload;
        }

        if (preg_match('/"diagnostico":\s*([^\n]+)/u', $rawOutput, $matchDiagnostico)) {
            return trim((string) $matchDiagnostico[1], "\", ");
        }

        if (preg_match('/📌 Diagnostico:\s*(.+)/u', $rawOutput, $matchAlt)) {
            return trim((string) $matchAlt[1]);
        }

        return '';
    }

    private function sanitizeReferralCode(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }

        $codigo = preg_replace('/\s*SECUENCIAL.*$/iu', '', $codigo) ?? $codigo;
        return trim($codigo);
    }

    /**
     * @return array<int,array{cie10:string,descripcion:string,from_scraper:bool}>
     */
    private function buildDiagnosticosPrevios(string $hcNumber, string $diagnosticoRaw): array
    {
        $diagnosticosList = array_values(array_filter(array_map(
            static fn(string $value): string => trim($value),
            preg_split('/[;\r\n]+/', $diagnosticoRaw) ?: []
        ), static fn(string $value): bool => $value !== ''));

        $fromScraper = [];
        foreach ($diagnosticosList as $item) {
            $normalized = $this->normalizarCieDesc($item);
            if ($normalized['cie10'] === '') {
                continue;
            }

            $fromScraper[$normalized['cie10']] = [
                'cie10' => $normalized['cie10'],
                'descripcion' => $normalized['descripcion'],
                'from_scraper' => true,
            ];
        }

        $fromHistory = [];
        foreach ($this->getDiagnosticosPorPaciente($hcNumber) as $item) {
            $raw = trim((string) ($item['idDiagnostico'] ?? ''));
            if ($raw === '') {
                continue;
            }

            $normalized = $this->normalizarCieDesc($raw);
            if ($normalized['cie10'] === '') {
                continue;
            }

            $code = $normalized['cie10'];
            if (!isset($fromHistory[$code])) {
                $fromHistory[$code] = [
                    'cie10' => $code,
                    'descripcion' => $normalized['descripcion'],
                    'from_scraper' => false,
                ];
                continue;
            }

            if ($fromHistory[$code]['descripcion'] === '' && $normalized['descripcion'] !== '') {
                $fromHistory[$code]['descripcion'] = $normalized['descripcion'];
            }
        }

        $ordered = [];
        $seen = [];

        foreach ($diagnosticosList as $item) {
            $normalized = $this->normalizarCieDesc($item);
            $code = $normalized['cie10'];
            if ($code === '' || isset($seen[$code])) {
                continue;
            }

            if (isset($fromScraper[$code])) {
                $ordered[] = $fromScraper[$code];
                $seen[$code] = true;
            }
        }

        foreach ($fromHistory as $code => $diag) {
            if (isset($seen[$code])) {
                continue;
            }

            $ordered[] = $diag;
            $seen[$code] = true;
        }

        return $ordered;
    }

    /**
     * @return array{cie10:string,descripcion:string}
     */
    private function normalizarCieDesc(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['cie10' => '', 'descripcion' => ''];
        }

        if (str_contains($raw, '-')) {
            $parts = array_map(static fn(string $item): string => trim($item), explode('-', $raw, 2));
            $codePart = $parts[0] ?? '';
            $description = $parts[1] ?? '';
            $code = trim((string) (explode(' ', $codePart)[0] ?? ''));

            return [
                'cie10' => strtoupper($code),
                'descripcion' => $description,
            ];
        }

        $code = trim((string) (explode(' ', $raw)[0] ?? ''));

        return [
            'cie10' => strtoupper($code),
            'descripcion' => '',
        ];
    }

    /**
     * @return array<int,array{idDiagnostico:string,fecha:?string}>
     */
    private function getDiagnosticosPorPaciente(string $hcNumber): array
    {
        $hcNumber = trim($hcNumber);
        if ($hcNumber === '') {
            return [];
        }

        $unique = [];

        if ($this->tableExists('prefactura_detalle_diagnosticos') && $this->tableExists('prefactura_paciente')) {
            $stmtPref = $this->db->prepare(
                <<<'SQL'
                SELECT
                    d.diagnostico_codigo,
                    d.descripcion,
                    pp.fecha_creacion,
                    pp.fecha_registro
                FROM prefactura_detalle_diagnosticos d
                INNER JOIN prefactura_paciente pp ON pp.id = d.prefactura_id
                WHERE pp.hc_number = ?
                ORDER BY pp.fecha_creacion DESC, d.posicion ASC
                SQL
            );
            $stmtPref->execute([$hcNumber]);

            while ($row = $stmtPref->fetch(PDO::FETCH_ASSOC)) {
                $code = trim((string) ($row['diagnostico_codigo'] ?: ($row['descripcion'] ?? '')));
                if ($code === '' || isset($unique[$code])) {
                    continue;
                }

                $dateValue = $row['fecha_creacion'] ?? $row['fecha_registro'] ?? null;
                $timestamp = $dateValue ? strtotime((string) $dateValue) : false;

                $unique[$code] = [
                    'idDiagnostico' => trim((string) ($row['diagnostico_codigo'] ?: $code)),
                    'fecha' => $timestamp ? date('d M Y', $timestamp) : null,
                ];
            }
        }

        if ($this->tableExists('consulta_data') && $this->columnExists('consulta_data', 'diagnosticos')) {
            $stmt = $this->db->prepare('SELECT fecha, diagnosticos FROM consulta_data WHERE hc_number = ? ORDER BY fecha DESC');
            $stmt->execute([$hcNumber]);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $diagnosticos = json_decode((string) ($row['diagnosticos'] ?? ''), true);
                if (!is_array($diagnosticos)) {
                    continue;
                }

                $timestamp = strtotime((string) ($row['fecha'] ?? ''));
                $fecha = $timestamp ? date('d M Y', $timestamp) : null;

                foreach ($diagnosticos as $diagnostico) {
                    if (!is_array($diagnostico)) {
                        continue;
                    }

                    $id = trim((string) ($diagnostico['idDiagnostico'] ?? ''));
                    if ($id === '' || isset($unique[$id])) {
                        continue;
                    }

                    $unique[$id] = [
                        'idDiagnostico' => $id,
                        'fecha' => $fecha,
                    ];
                }
            }
        }

        return array_values($unique);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function syncDerivacion(string $formId, string $hcNumber, array $payload): bool
    {
        $codigo = $this->sanitizeReferralCode((string) ($payload['codigo_derivacion'] ?? $payload['cod_derivacion'] ?? ''));
        if ($codigo === '') {
            return false;
        }

        $formId = trim($formId);
        $hcNumber = trim($hcNumber);
        if ($formId === '' || $hcNumber === '') {
            return false;
        }

        $normalized = [
            'cod_derivacion' => $codigo,
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'fecha_registro' => $this->normalizeDate($payload['fecha_registro'] ?? null),
            'fecha_vigencia' => $this->normalizeDate($payload['fecha_vigencia'] ?? null),
            'referido' => $this->nullableString($payload['referido'] ?? null),
            'diagnostico' => $this->nullableString($payload['diagnostico'] ?? null),
            'sede' => $this->nullableString($payload['sede'] ?? null),
            'parentesco' => $this->nullableString($payload['parentesco'] ?? null),
            'archivo_derivacion_path' => $this->nullableString($payload['archivo_derivacion_path'] ?? null),
        ];

        try {
            $this->db->beginTransaction();

            $syncedAny = false;
            $referralId = $this->upsertDerivacionesReferral($normalized);
            $formRowId = $this->upsertDerivacionesForm($normalized);

            if ($referralId !== null && $formRowId !== null) {
                $this->upsertDerivacionesReferralForm($referralId, $formRowId, $normalized['fecha_registro']);
                $syncedAny = true;
            }

            if ($this->upsertLegacyDerivacionFormId($normalized)) {
                $syncedAny = true;
            }

            $this->db->commit();
            return $syncedAny;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->lastError = $exception->getMessage();
            return false;
        }
    }

    /**
     * @param array<string,mixed> $normalized
     */
    private function upsertDerivacionesReferral(array $normalized): ?int
    {
        if (!$this->tableExists('derivaciones_referrals')) {
            return null;
        }

        $referralCode = (string) $normalized['cod_derivacion'];
        if ($referralCode === '' || !$this->columnExists('derivaciones_referrals', 'referral_code')) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $existingId = $this->selectIdByColumn('derivaciones_referrals', 'referral_code', $referralCode);

        if ($existingId === null) {
            $insert = [
                'referral_code' => $referralCode,
                'source' => 'IESS',
                'valid_until' => $normalized['fecha_vigencia'],
                'source_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $insertedId = $this->insertRow('derivaciones_referrals', $insert);
            if ($insertedId !== null) {
                return $insertedId;
            }

            return $this->selectIdByColumn('derivaciones_referrals', 'referral_code', $referralCode);
        }

        $update = [
            'source' => 'IESS',
            'valid_until' => $normalized['fecha_vigencia'],
            'source_updated_at' => $now,
            'updated_at' => $now,
        ];

        $this->updateRowById('derivaciones_referrals', $existingId, $update);
        return $existingId;
    }

    /**
     * @param array<string,mixed> $normalized
     */
    private function upsertDerivacionesForm(array $normalized): ?int
    {
        if (!$this->tableExists('derivaciones_forms')) {
            return null;
        }

        $formId = (string) $normalized['form_id'];
        if ($formId === '' || !$this->columnExists('derivaciones_forms', 'iess_form_id')) {
            return null;
        }

        $now = date('Y-m-d H:i:s');
        $afiliacionRaw = $this->fetchPatientAffiliation((string) $normalized['hc_number']);
        $payer = $this->detectPayer($afiliacionRaw);

        $existingId = $this->selectIdByColumn('derivaciones_forms', 'iess_form_id', $formId);

        if ($existingId === null) {
            $insert = [
                'iess_form_id' => $formId,
                'hc_number' => $normalized['hc_number'],
                'payer' => $payer,
                'afiliacion_raw' => $afiliacionRaw,
                'fecha_registro' => $normalized['fecha_registro'],
                'fecha_vigencia' => $normalized['fecha_vigencia'],
                'referido' => $normalized['referido'],
                'diagnostico' => $normalized['diagnostico'],
                'sede' => $normalized['sede'],
                'parentesco' => $normalized['parentesco'],
                'archivo_derivacion_path' => $normalized['archivo_derivacion_path'],
                'source_updated_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $insertedId = $this->insertRow('derivaciones_forms', $insert);
            if ($insertedId !== null) {
                return $insertedId;
            }

            return $this->selectIdByColumn('derivaciones_forms', 'iess_form_id', $formId);
        }

        $update = [
            'hc_number' => $normalized['hc_number'],
            'payer' => $payer,
            'afiliacion_raw' => $afiliacionRaw,
            'fecha_registro' => $normalized['fecha_registro'],
            'fecha_vigencia' => $normalized['fecha_vigencia'],
            'referido' => $normalized['referido'],
            'diagnostico' => $normalized['diagnostico'],
            'sede' => $normalized['sede'],
            'parentesco' => $normalized['parentesco'],
            'archivo_derivacion_path' => $normalized['archivo_derivacion_path'],
            'source_updated_at' => $now,
            'updated_at' => $now,
        ];

        $this->updateRowById('derivaciones_forms', $existingId, $update);
        return $existingId;
    }

    private function upsertDerivacionesReferralForm(int $referralId, int $formRowId, ?string $fechaRegistro): void
    {
        if (!$this->tableExists('derivaciones_referral_forms')) {
            return;
        }

        if (!$this->columnExists('derivaciones_referral_forms', 'referral_id')
            || !$this->columnExists('derivaciones_referral_forms', 'form_id')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $linkedAt = $fechaRegistro !== null ? $fechaRegistro . ' 00:00:00' : $now;

        $stmt = $this->db->prepare(
            'SELECT id FROM derivaciones_referral_forms
             WHERE referral_id = :referral_id AND form_id = :form_id
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':referral_id' => $referralId,
            ':form_id' => $formRowId,
        ]);

        $existingId = $stmt->fetchColumn();

        if ($existingId === false) {
            $insert = [
                'referral_id' => $referralId,
                'form_id' => $formRowId,
                'status' => 'active',
                'linked_at' => $linkedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $this->insertRow('derivaciones_referral_forms', $insert);
            return;
        }

        $update = [
            'status' => 'active',
            'linked_at' => $linkedAt,
            'updated_at' => $now,
        ];

        $this->updateRowById('derivaciones_referral_forms', (int) $existingId, $update);
    }

    /**
     * @param array<string,mixed> $normalized
     */
    private function upsertLegacyDerivacionFormId(array $normalized): bool
    {
        if (!$this->tableExists('derivaciones_form_id')) {
            return false;
        }

        if (!$this->columnExists('derivaciones_form_id', 'form_id')) {
            return false;
        }

        $formId = (string) $normalized['form_id'];
        if ($formId === '') {
            return false;
        }

        $existingId = $this->selectIdByColumn('derivaciones_form_id', 'form_id', $formId);

        $payload = [
            'cod_derivacion' => $normalized['cod_derivacion'],
            'form_id' => $normalized['form_id'],
            'hc_number' => $normalized['hc_number'],
            'fecha_registro' => $normalized['fecha_registro'],
            'fecha_vigencia' => $normalized['fecha_vigencia'],
            'referido' => $normalized['referido'],
            'diagnostico' => $normalized['diagnostico'],
            'sede' => $normalized['sede'],
            'parentesco' => $normalized['parentesco'],
            'archivo_derivacion_path' => $normalized['archivo_derivacion_path'],
        ];

        if ($existingId === null) {
            $inserted = $this->insertRow('derivaciones_form_id', $payload);
            return $inserted !== null;
        }

        unset($payload['form_id']);
        $this->updateRowById('derivaciones_form_id', $existingId, $payload);
        return true;
    }

    /**
     * @param array<string,mixed> $values
     */
    private function insertRow(string $table, array $values): ?int
    {
        $filtered = $this->filterColumns($table, $values);
        if ($filtered === []) {
            return null;
        }

        $columns = array_keys($filtered);
        $columnSql = implode(', ', array_map(static fn(string $column): string => "`{$column}`", $columns));
        $placeholderSql = implode(', ', array_map(static fn(string $column): string => ':' . $column, $columns));

        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table, $columnSql, $placeholderSql);
        $stmt = $this->db->prepare($sql);

        foreach ($filtered as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }

        $stmt->execute();

        $lastId = (int) $this->db->lastInsertId();
        return $lastId > 0 ? $lastId : null;
    }

    /**
     * @param array<string,mixed> $values
     */
    private function updateRowById(string $table, int $id, array $values): void
    {
        if ($id <= 0 || !$this->columnExists($table, 'id')) {
            return;
        }

        $filtered = $this->filterColumns($table, $values);
        if ($filtered === []) {
            return;
        }

        $assignments = [];
        foreach (array_keys($filtered) as $column) {
            $assignments[] = sprintf('`%s` = :%s', $column, $column);
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE id = :id', $table, implode(', ', $assignments));
        $stmt = $this->db->prepare($sql);

        foreach ($filtered as $column => $value) {
            $stmt->bindValue(':' . $column, $value);
        }

        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private function filterColumns(string $table, array $values): array
    {
        $filtered = [];

        foreach ($values as $column => $value) {
            if ($this->columnExists($table, $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
    }

    private function selectIdByColumn(string $table, string $column, string|int $value): ?int
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column) || !$this->columnExists($table, 'id')) {
            return null;
        }

        $sql = sprintf('SELECT id FROM `%s` WHERE `%s` = :value ORDER BY id DESC LIMIT 1', $table, $column);
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':value', $value);
        $stmt->execute();

        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute([':table' => $table]);

        $exists = (int) $stmt->fetchColumn() > 0;
        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    private function columnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column'
        );

        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        $exists = (int) $stmt->fetchColumn() > 0;
        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    private function nullableString(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        return $raw !== '' ? $raw : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($raw))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function fetchPatientAffiliation(string $hcNumber): ?string
    {
        if (!$this->tableExists('patient_data') || !$this->columnExists('patient_data', 'afiliacion')) {
            return null;
        }

        $hcNumber = trim($hcNumber);
        if ($hcNumber === '') {
            return null;
        }

        $stmt = $this->db->prepare('SELECT afiliacion FROM patient_data WHERE hc_number = :hc LIMIT 1');
        $stmt->execute([':hc' => $hcNumber]);
        $value = $stmt->fetchColumn();

        if ($value === false) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    private function detectPayer(?string $afiliacionRaw): string
    {
        $normalized = $this->normalizeAffiliation($afiliacionRaw);
        if ($normalized === null) {
            return 'OTROS';
        }

        $iessAffiliations = [
            'contribuyente voluntario',
            'conyuge',
            'conyuge pensionista',
            'seguro campesino',
            'seguro campesino jubilado',
            'seguro general',
            'seguro general jubilado',
            'seguro general por montepio',
            'seguro general tiempo parcial',
            'iess',
            'hijos dependientes',
        ];

        if (in_array($normalized, $iessAffiliations, true)) {
            return 'IESS';
        }

        return match ($normalized) {
            'issfa' => 'ISSFA',
            'isspol' => 'ISSPOL',
            'msp' => 'MSP',
            default => 'OTROS',
        };
    }

    private function normalizeAffiliation(?string $affiliation): ?string
    {
        if ($affiliation === null) {
            return null;
        }

        $normalized = strtolower(trim($affiliation));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);

        return $normalized !== '' ? $normalized : null;
    }
}
