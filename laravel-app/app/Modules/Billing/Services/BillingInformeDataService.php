<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Support\InformesHelper;
use PDO;

class BillingInformeDataService
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $facturasCache = [];
    /** @var array<string, array<string, mixed>> */
    private array $datosCache = [];
    /** @var array<string, array<string, mixed>> */
    private array $derivacionCache = [];
    /** @var array<string, string> */
    private array $sedeCache = [];
    /** @var array<string, int|null> */
    private array $billingIdCache = [];
    /** @var array<string, float|null> */
    private array $valorAnestesiaCache = [];
    /** @var array<string, bool> */
    private array $cirugiaCache = [];
    /** @var array<string, array<string, mixed>|null> */
    private array $resumenConsolidadoCache = [];
    /** @var array<string, array<string, mixed>> */
    private array $derivacionLookupCache = [];
    /** @var array<int, string>|null */
    private ?array $formIdsFacturadosCache = null;
    /** @var array<string, bool>|null */
    private ?array $medicamentoNombreLookup = null;
    /** @var array<int, string>|null */
    private ?array $medicamentoNombreList = null;
    private ?string $sigcenterDbHost;
    private int $sigcenterDbPort;
    private ?string $sigcenterDbDatabase;
    private ?string $sigcenterDbUsername;
    private ?string $sigcenterDbPassword;
    private ?string $sigcenterBaseUrl;
    private ?string $sigcenterSshHost;
    private int $sigcenterSshPort;
    private ?string $sigcenterSshUser;
    private ?string $sigcenterSshPass;
    private ?\phpseclib3\Net\SSH2 $sigcenterSsh = null;

    public function __construct(
        private readonly PDO $db,
        private readonly BillingInformePacienteService $pacienteService,
        private readonly ?PDO $sigcenterDb = null
    ) {
        $this->sigcenterDbHost = $this->readEnv('SIGCENTER_DB_HOST') ?: '127.0.0.1';
        $this->sigcenterDbPort = (int) ($this->readEnv('SIGCENTER_DB_PORT') ?: 3306);
        $this->sigcenterDbDatabase = $this->readEnv('SIGCENTER_DB_DATABASE') ?: 'inmicrocsa';
        $this->sigcenterDbUsername = $this->readEnv('SIGCENTER_DB_USERNAME');
        $this->sigcenterDbPassword = $this->readEnv('SIGCENTER_DB_PASSWORD');
        $this->sigcenterBaseUrl = rtrim($this->readEnv('SIGCENTER_BASE_URL') ?: 'https://cive.ddns.net:8085', '/');
        $this->sigcenterSshHost = $this->readEnv('SIGCENTER_FILES_SSH_HOST');
        $this->sigcenterSshPort = (int) ($this->readEnv('SIGCENTER_FILES_SSH_PORT') ?: 22);
        $this->sigcenterSshUser = $this->readEnv('SIGCENTER_FILES_SSH_USER');
        $this->sigcenterSshPass = $this->readEnv('SIGCENTER_FILES_SSH_PASS');
    }

    public function getPdo(): PDO
    {
        return $this->db;
    }

    public function resetRemoteConnections(): void
    {
        $this->sigcenterSsh = null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function obtenerFacturasDisponibles(?string $mes = null): array
    {
        $cacheKey = $mes !== null ? trim($mes) : '';
        if (isset($this->facturasCache[$cacheKey])) {
            return $this->facturasCache[$cacheKey];
        }

        $query = <<<'SQL'
            SELECT
                bm.id,
                bm.form_id,
                bm.hc_number,
                COALESCE(pd.fecha_inicio, pp.fecha) AS fecha_ordenada
            FROM billing_main bm
            LEFT JOIN protocolo_data pd ON bm.form_id = pd.form_id
            LEFT JOIN procedimiento_proyectado pp ON bm.form_id = pp.form_id
        SQL;

        $params = [];
        $mes = trim((string) $mes);
        if ($mes !== '') {
            $startDate = $mes . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            $query .= ' WHERE COALESCE(pd.fecha_inicio, pp.fecha) BETWEEN :startDate AND :endDate';
            $params[':startDate'] = $startDate;
            $params[':endDate'] = $endDate;
        }

        $query .= ' ORDER BY fecha_ordenada DESC';

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->facturasCache[$cacheKey] = $rows;
        return $rows;
    }

    public function obtenerSedePorFormId(string $formId): string
    {
        $formId = trim($formId);
        if ($formId === '') {
            return '';
        }

        if (array_key_exists($formId, $this->sedeCache)) {
            return $this->sedeCache[$formId];
        }

        $stmt = $this->db->prepare(
            'SELECT sede_departamento, id_sede FROM procedimiento_proyectado WHERE form_id = ? ORDER BY fecha DESC LIMIT 1'
        );
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $raw = strtolower(trim((string) ($row['sede_departamento'] ?? $row['id_sede'] ?? '')));
        if ($raw === '') {
            $this->sedeCache[$formId] = '';
            return '';
        }
        if (str_contains($raw, 'ceib')) {
            $this->sedeCache[$formId] = 'CEIBOS';
            return 'CEIBOS';
        }
        if (str_contains($raw, 'matriz') || str_contains($raw, 'villa')) {
            $this->sedeCache[$formId] = 'MATRIZ';
            return 'MATRIZ';
        }

        $this->sedeCache[$formId] = '';
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerDerivacionPorFormId(string $formId): array
    {
        $formId = trim($formId);
        if ($formId === '') {
            return [];
        }

        if (isset($this->derivacionCache[$formId])) {
            return $this->derivacionCache[$formId];
        }

        $row = false;
        try {
            $stmt = $this->db->prepare(
                <<<'SQL'
                SELECT
                    df.iess_form_id   AS form_id,
                    df.hc_number      AS hc_number,
                    df.fecha_creacion AS fecha_creacion,
                    COALESCE(df.fecha_registro, dr.issued_at) AS fecha_registro,
                    COALESCE(df.fecha_vigencia, dr.valid_until) AS fecha_vigencia,
                    df.referido,
                    df.diagnostico,
                    df.sede,
                    df.parentesco,
                    df.archivo_derivacion_path,
                    df.payer,
                    df.afiliacion_raw,
                    dr.referral_code  AS cod_derivacion,
                    dr.referral_code  AS codigo_derivacion,
                    dr.status         AS estado_derivacion,
                    dr.issued_at      AS issued_at,
                    dr.valid_until    AS valid_until,
                    dr.source         AS source,
                    dr.priority       AS priority,
                    dr.service_type   AS service_type,
                    rf.status         AS link_status,
                    rf.linked_at      AS linked_at,
                    rf.form_id        AS derivacion_form_id
                FROM derivaciones_forms df
                LEFT JOIN derivaciones_referral_forms rf ON rf.form_id = df.id
                LEFT JOIN derivaciones_referrals dr ON dr.id = rf.referral_id
                WHERE df.iess_form_id = ?
                ORDER BY COALESCE(rf.linked_at, df.updated_at) DESC, df.id DESC
                LIMIT 1
                SQL
            );
            $stmt->execute([$formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $row = false;
        }

        if ($row !== false) {
            if (empty($row['fecha_registro']) && !empty($row['issued_at'])) {
                $row['fecha_registro'] = $row['issued_at'];
            }
            if (empty($row['fecha_vigencia']) && !empty($row['valid_until'])) {
                $row['fecha_vigencia'] = $row['valid_until'];
            }
            $legacy = $this->obtenerDerivacionLegacyPorFormId($formId);
            if ($legacy !== []) {
                $row = $this->mergeDerivacionRows($row, $legacy);
            }
            $row = $this->sanitizeDerivacionRow($row);
            $this->derivacionCache[$formId] = $row;
            return $row;
        }

        $fallback = $this->sanitizeDerivacionRow($this->obtenerDerivacionLegacyPorFormId($formId));

        $this->derivacionCache[$formId] = $fallback;
        return $fallback;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hasCodigoDerivacion(array $row): bool
    {
        return $this->extractCodigoDerivacion($row) !== '';
    }

    /**
     * @return array<string,mixed>
     */
    private function obtenerDerivacionLegacyPorFormId(string $formId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM derivaciones_form_id WHERE form_id = ? LIMIT 1');
            $stmt->execute([$formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $sanitized = $this->sanitizeDerivacionRow($row);
            $rawCode = trim((string) ($row['cod_derivacion'] ?? $row['codigo_derivacion'] ?? ''));
            if ($rawCode !== '' && ($sanitized['cod_derivacion'] ?? '') === '') {
                $this->clearLegacyDerivacionCode($formId);
            }

            return $sanitized;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $primary
     * @param array<string,mixed> $legacy
     * @return array<string,mixed>
     */
    private function mergeDerivacionRows(array $primary, array $legacy): array
    {
        $merged = $primary;

        foreach ([
            'cod_derivacion',
            'codigo_derivacion',
            'num_secuencial_derivacion',
            'referido',
            'procedencia',
            'fecha_registro',
            'fecha_vigencia',
            'diagnostico',
            'sede',
            'parentesco',
            'afiliacion',
            'pdf_totalizado_path',
            'pdf_totalizado_url',
            'archivo_derivacion_path',
            'payer',
            'afiliacion_raw',
        ] as $key) {
            $current = trim((string) ($merged[$key] ?? ''));
            if ($current !== '') {
                continue;
            }

            $legacyValue = $legacy[$key] ?? null;
            if ($legacyValue !== null && trim((string) $legacyValue) !== '') {
                $merged[$key] = $legacyValue;
            }
        }

        $primaryDiagnostico = trim((string) ($primary['diagnostico'] ?? ''));
        $legacyDiagnostico = trim((string) ($legacy['diagnostico'] ?? ''));
        if (
            $legacyDiagnostico !== ''
            && !$this->diagnosticoHasCie10Code($primaryDiagnostico)
            && $this->diagnosticoHasCie10Code($legacyDiagnostico)
        ) {
            $merged['diagnostico'] = $legacyDiagnostico;
        }

        if (trim((string) ($merged['cod_derivacion'] ?? '')) === '' && !empty($merged['codigo_derivacion'])) {
            $merged['cod_derivacion'] = $merged['codigo_derivacion'];
        }
        if (trim((string) ($merged['codigo_derivacion'] ?? '')) === '' && !empty($merged['cod_derivacion'])) {
            $merged['codigo_derivacion'] = $merged['cod_derivacion'];
        }

        return $this->sanitizeDerivacionRow($merged);
    }

    private function diagnosticoHasCie10Code(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $parts = preg_split('/\s*;\s*/', $value) ?: [];
        foreach ($parts as $part) {
            if (preg_match('/^\s*[A-Z][0-9]{2}[0-9A-Z]?(?:\.[0-9A-Z]+)?\s*-/u', trim((string) $part))) {
                return true;
            }
        }

        return false;
    }

    public function obtenerBillingIdPorFormId(string $formId): ?int
    {
        $formId = trim($formId);
        if ($formId === '') {
            return null;
        }

        if (array_key_exists($formId, $this->billingIdCache)) {
            return $this->billingIdCache[$formId];
        }

        $stmt = $this->db->prepare('SELECT id FROM billing_main WHERE form_id = ? LIMIT 1');
        $stmt->execute([$formId]);
        $id = $stmt->fetchColumn();
        $result = $id !== false ? (int) $id : null;

        $this->billingIdCache[$formId] = $result;
        return $result;
    }

    /**
     * @return array<int, string>
     */
    public function obtenerFormIdsFacturados(): array
    {
        if ($this->formIdsFacturadosCache !== null) {
            return $this->formIdsFacturadosCache;
        }

        try {
            $stmt = $this->db->query('SELECT form_id FROM billing_main');
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Throwable) {
            $rows = [];
        }

        $this->formIdsFacturadosCache = array_values(array_map('strval', $rows));
        return $this->formIdsFacturadosCache;
    }

    public function obtenerValorAnestesia(string $codigo): ?float
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        if (array_key_exists($codigo, $this->valorAnestesiaCache)) {
            return $this->valorAnestesiaCache[$codigo];
        }

        $stmt = $this->db->prepare(
            'SELECT anestesia_nivel3 FROM tarifario_2014 WHERE codigo = :codigo OR codigo = :codigo_sin_0 LIMIT 1'
        );
        $stmt->execute([
            'codigo' => $codigo,
            'codigo_sin_0' => ltrim($codigo, '0'),
        ]);
        $value = $stmt->fetchColumn();
        $result = $value !== false ? (float) $value : null;

        $this->valorAnestesiaCache[$codigo] = $result;
        return $result;
    }

    public function esCirugiaPorFormId(string $formId): bool
    {
        $formId = trim($formId);
        if ($formId === '') {
            return false;
        }

        if (array_key_exists($formId, $this->cirugiaCache)) {
            return $this->cirugiaCache[$formId];
        }

        $stmt = $this->db->prepare('SELECT 1 FROM protocolo_data WHERE form_id = ? LIMIT 1');
        $stmt->execute([$formId]);
        $result = $stmt->fetchColumn() !== false;

        $this->cirugiaCache[$formId] = $result;
        return $result;
    }

    public function preloadResumenesConsolidado(array $formIds): void
    {
        $normalizedFormIds = array_values(array_unique(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            $formIds
        ))));

        if ($normalizedFormIds === []) {
            return;
        }

        $missingFormIds = array_values(array_filter(
            $normalizedFormIds,
            fn(string $formId): bool => !array_key_exists($formId, $this->resumenConsolidadoCache)
        ));

        if ($missingFormIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($missingFormIds), '?'));

        $stmt = $this->db->prepare("SELECT * FROM billing_main WHERE form_id IN ($placeholders)");
        $stmt->execute($missingFormIds);
        $billingRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $billingByFormId = [];
        $billingIds = [];
        foreach ($billingRows as $billing) {
            $formId = trim((string) ($billing['form_id'] ?? ''));
            if ($formId === '') {
                continue;
            }

            $billingByFormId[$formId] = $billing;
            $billingId = (int) ($billing['id'] ?? 0);
            if ($billingId > 0) {
                $billingIds[] = $billingId;
                $this->billingIdCache[$formId] = $billingId;
            }
        }

        $procedimientosMap = $this->fetchGroupedByBillingIds('billing_procedimientos', $billingIds);
        $derechosMap = $this->fetchGroupedByBillingIds('billing_derechos', $billingIds);
        $oxigenoMap = $this->fetchGroupedByBillingIds('billing_oxigeno', $billingIds);
        $anestesiaMap = $this->fetchGroupedByBillingIds('billing_anestesia', $billingIds);
        $insumosMap = $this->obtenerInsumosPorBillingIds($billingIds);
        $protocoloMap = $this->obtenerProtocoloResumenPorFormIds($missingFormIds);

        foreach ($missingFormIds as $formId) {
            $billing = $billingByFormId[$formId] ?? null;
            if (!is_array($billing)) {
                $this->resumenConsolidadoCache[$formId] = null;
                continue;
            }

            $billingId = (int) ($billing['id'] ?? 0);
            $procedimientos = $billingId > 0 ? ($procedimientosMap[$billingId] ?? []) : [];
            $derechos = $billingId > 0 ? ($derechosMap[$billingId] ?? []) : [];
            $oxigeno = $billingId > 0 ? ($oxigenoMap[$billingId] ?? []) : [];
            $anestesia = $billingId > 0 ? ($anestesiaMap[$billingId] ?? []) : [];
            $insumosRaw = $billingId > 0 ? ($insumosMap[$billingId] ?? []) : [];
            $protocoloResumen = $protocoloMap[$formId] ?? [];

            $this->resumenConsolidadoCache[$formId] = $this->buildResumenConsolidadoFromSources(
                $billing,
                $procedimientos,
                $derechos,
                $oxigeno,
                $anestesia,
                $insumosRaw,
                $protocoloResumen
            );
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtenerResumenConsolidado(string $formId): ?array
    {
        $formId = trim($formId);
        if ($formId === '') {
            return null;
        }

        if (!array_key_exists($formId, $this->resumenConsolidadoCache)) {
            $this->preloadResumenesConsolidado([$formId]);
        }

        $summary = $this->resumenConsolidadoCache[$formId] ?? null;
        if (!is_array($summary)) {
            return null;
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDerivacionLookupPayload(string $formId, string $hcNumber): array
    {
        $formId = trim($formId);
        $hcNumber = trim($hcNumber);
        $cacheKey = $formId . '|' . $hcNumber;

        if (isset($this->derivacionLookupCache[$cacheKey])) {
            return $this->derivacionLookupCache[$cacheKey];
        }

        $debug = [];
        $derivacion = $this->fetchDerivacionLookupRow($formId, $hcNumber, $debug);
        $procedimientos = $this->fetchProcedimientosPorCobrar(
            $formId,
            $hcNumber,
            $this->extractCodigoDerivacion($derivacion)
        );

        $payload = [
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'oda_referencia' => (string) ($derivacion['oda_referencia'] ?? ''),
            'codigo_derivacion' => $this->extractCodigoDerivacion($derivacion),
            'num_secuencial_derivacion' => (string) ($derivacion['num_secuencial_derivacion'] ?? ''),
            'fecha_registro' => (string) ($derivacion['fecha_registro'] ?? ''),
            'fecha_vigencia' => (string) ($derivacion['fecha_vigencia'] ?? ''),
            'referido' => (string) ($derivacion['referido'] ?? ''),
            'procedencia' => (string) ($derivacion['procedencia'] ?? ''),
            'diagnostico' => (string) ($derivacion['diagnostico'] ?? ''),
            'sede' => (string) ($derivacion['sede'] ?? ''),
            'parentesco' => (string) ($derivacion['parentesco'] ?? ''),
            'afiliacion' => (string) ($derivacion['afiliacion'] ?? ''),
            'ruta_pdf_real' => (string) ($derivacion['ruta_pdf_real'] ?? ''),
            'pdf_totalizado_path' => (string) ($derivacion['pdf_totalizado_path'] ?? ''),
            'pdf_totalizado_url' => (string) ($derivacion['pdf_totalizado_url'] ?? ''),
            'archivo_derivacion_path' => (string) ($derivacion['archivo_derivacion_path'] ?? ''),
            'procedimientos' => $procedimientos,
            '_debug' => array_merge($debug, [
                'procedimientos_encontrados' => count($procedimientos),
            ]),
        ];

        $this->derivacionLookupCache[$cacheKey] = $payload;
        return $payload;
    }

    public function persistDerivacionLookupPayload(array $payload): bool
    {
        $formId = trim((string) ($payload['form_id'] ?? ''));
        $codigo = $this->sanitizeDerivacionCode((string) ($payload['codigo_derivacion'] ?? $payload['cod_derivacion'] ?? ''));

        if ($formId === '' || $codigo === '') {
            return false;
        }

        try {
            $stmt = $this->db->prepare('SELECT id FROM derivaciones_form_id WHERE form_id = ? LIMIT 1');
            $stmt->execute([$formId]);
            $existingId = $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }

        $record = [
            'cod_derivacion' => $codigo,
            'codigo_derivacion' => $codigo,
            'form_id' => $formId,
            'hc_number' => $this->nullableString($payload['hc_number'] ?? null),
            'fecha_registro' => $this->normalizeDateValue($payload['fecha_registro'] ?? null),
            'fecha_vigencia' => $this->normalizeDateValue($payload['fecha_vigencia'] ?? null),
            'referido' => $this->nullableString($payload['referido'] ?? null),
            'diagnostico' => $this->nullableString($payload['diagnostico'] ?? null),
            'sede' => $this->nullableString($payload['sede'] ?? null),
            'parentesco' => $this->nullableString($payload['parentesco'] ?? null),
            'archivo_derivacion_path' => $this->nullableString($payload['archivo_derivacion_path'] ?? $payload['ruta_pdf_real'] ?? $payload['pdf_totalizado_path'] ?? null),
        ];

        try {
            if ($existingId === false) {
                $columns = ['cod_derivacion', 'form_id', 'hc_number', 'fecha_registro', 'fecha_vigencia', 'referido', 'diagnostico', 'sede', 'parentesco', 'archivo_derivacion_path'];
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $stmt = $this->db->prepare(
                    'INSERT INTO derivaciones_form_id (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')'
                );
                $stmt->execute(array_map(static fn(string $column) => $record[$column] ?? null, $columns));
            } else {
                $stmt = $this->db->prepare(
                    'UPDATE derivaciones_form_id
                     SET cod_derivacion = ?, hc_number = ?, fecha_registro = ?, fecha_vigencia = ?, referido = ?, diagnostico = ?, sede = ?, parentesco = ?, archivo_derivacion_path = ?
                     WHERE id = ?'
                );
                $stmt->execute([
                    $record['cod_derivacion'],
                    $record['hc_number'],
                    $record['fecha_registro'],
                    $record['fecha_vigencia'],
                    $record['referido'],
                    $record['diagnostico'],
                    $record['sede'],
                    $record['parentesco'],
                    $record['archivo_derivacion_path'],
                    (int) $existingId,
                ]);
            }
        } catch (\Throwable) {
            return false;
        }

        $this->derivacionCache[$formId] = $record;
        $this->derivacionLookupCache[$formId . '|' . trim((string) ($payload['hc_number'] ?? ''))] = $payload;

        return true;
    }

    public function resolveDerivacionArchivoUrl(string $formId, string $basePath = '/v2/derivaciones/archivo-form'): ?string
    {
        $formId = trim($formId);
        if ($formId === '') {
            return null;
        }

        try {
            $stmt = $this->db->prepare('SELECT id, archivo_derivacion_path FROM derivaciones_form_id WHERE form_id = ? LIMIT 1');
            $stmt->execute([$formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return null;
        }

        if ($row === [] || trim((string) ($row['archivo_derivacion_path'] ?? '')) === '') {
            return null;
        }

        return rtrim($basePath, '/') . '?form_id=' . urlencode($formId);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchDerivacionLookupRow(string $formId, string $hcNumber, array &$debug = []): array
    {
        $base = $this->obtenerDerivacionPorFormId($formId);
        $sigcenterDebug = [];
        $sigcenter = $this->fetchDerivacionFromSigcenter($formId, $hcNumber, $sigcenterDebug);

        $debug = [
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'sigcenter' => $sigcenterDebug,
            'local' => [
                'encontrado' => $base !== [],
                'codigo_derivacion' => $this->extractCodigoDerivacion($base),
                'referido' => trim((string) ($base['referido'] ?? '')),
                'diagnostico' => trim((string) ($base['diagnostico'] ?? '')),
                'ruta_pdf_real' => trim((string) ($base['ruta_pdf_real'] ?? '')),
                'pdf_totalizado_path' => trim((string) ($base['pdf_totalizado_path'] ?? $base['archivo_derivacion_path'] ?? '')),
            ],
            'source' => 'none',
        ];

        if ($sigcenter === []) {
            $debug['source'] = $base !== [] ? 'local' : 'none';
            return $base;
        }

        if ($base === []) {
            $debug['source'] = 'sigcenter';
            return $sigcenter;
        }

        $debug['source'] = 'merged';
        return $this->mergeDerivacionRows($sigcenter, $base);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtenerDatos(string $formId): ?array
    {
        $formId = trim($formId);
        if ($formId === '') {
            return null;
        }

        if (isset($this->datosCache[$formId])) {
            return $this->datosCache[$formId];
        }

        $stmt = $this->db->prepare('SELECT * FROM billing_main WHERE form_id = ? LIMIT 1');
        $stmt->execute([$formId]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$billing) {
            return null;
        }

        $billingId = (int) ($billing['id'] ?? 0);
        if ($billingId <= 0) {
            return null;
        }

        $facturador = $this->resolverFacturador($billing);
        $billing['facturador_id'] = $facturador['id'] ?? null;
        $billing['facturador_nombre'] = $facturador['nombre'] ?? null;

        $hcNumber = (string) ($billing['hc_number'] ?? '');
        $pacienteInfo = $this->pacienteService->getPatientDetails($hcNumber);
        $formDetails = $this->pacienteService->getDetalleSolicitud($hcNumber, $formId);
        $visita = $this->obtenerDatosPacientePorFormId($formId);
        $protocoloExtendido = $this->obtenerProtocoloTiny($formId, $hcNumber);

        $procedimientos = $this->fetchAllByBillingId('billing_procedimientos', $billingId);
        $derechos = $this->fetchAllByBillingId('billing_derechos', $billingId);
        $oxigeno = $this->fetchAllByBillingId('billing_oxigeno', $billingId);
        $anestesia = $this->fetchAllByBillingId('billing_anestesia', $billingId);
        $insumosRaw = $this->obtenerInsumosPorBillingId($billingId);

        $afiliacion = (string) ($pacienteInfo['afiliacion'] ?? '');
        $splitInsumos = $this->splitInsumosPorTipo($insumosRaw);
        $insumosConIva = $splitInsumos['insumos'];
        $medicamentosSinIva = $this->ajustarMedicamentosPorAfiliacionLista($splitInsumos['medicamentos'], $afiliacion);

        if (
            $procedimientos === []
            || $derechos === []
            || $oxigeno === []
            || $anestesia === []
            || $insumosConIva === []
            || $medicamentosSinIva === []
        ) {
            $preview = $this->buildPreviewFallbackData($formId, $hcNumber, $protocoloExtendido);

            if ($procedimientos === []) {
                $procedimientos = $this->normalizePreviewProcedimientos($preview['procedimientos'] ?? []);
            }

            if ($derechos === []) {
                $derechos = $this->normalizePreviewDerechos($preview['derechos'] ?? []);
            }

            if ($oxigeno === []) {
                $oxigeno = $this->normalizePreviewCollection($preview['oxigeno'] ?? []);
            }

            if ($anestesia === []) {
                $anestesia = $this->normalizePreviewCollection($preview['anestesia'] ?? []);
            }

            if ($insumosConIva === [] || $medicamentosSinIva === []) {
                $previewSplit = $this->splitInsumosPorTipo($this->normalizePreviewCollection($preview['insumos'] ?? []));
                $previewMedicamentos = $this->ajustarMedicamentosPorAfiliacionLista($previewSplit['medicamentos'], $afiliacion);

                if ($insumosConIva === [] && $previewSplit['insumos'] !== []) {
                    $insumosConIva = $previewSplit['insumos'];
                }

                if ($medicamentosSinIva === [] && $previewMedicamentos !== []) {
                    $medicamentosSinIva = $previewMedicamentos;
                }
            }
        }

        $result = [
            'billing' => $billing,
            'procedimientos' => $procedimientos,
            'derechos' => $derechos,
            'insumos' => $insumosConIva,
            'medicamentos' => $medicamentosSinIva,
            'oxigeno' => $oxigeno,
            'anestesia' => $anestesia,
            'paciente' => $pacienteInfo,
            'visita' => $visita,
            'formulario' => $formDetails,
            'protocoloExtendido' => $protocoloExtendido,
        ];

        $this->datosCache[$formId] = $result;
        return $result;
    }

    /**
     * @param array<string, mixed> $billing
     * @return array{id:int|null,nombre:string|null}|null
     */
    private function resolverFacturador(array $billing): ?array
    {
        $userId = !empty($billing['facturado_por']) ? (int) $billing['facturado_por'] : null;
        $formId = trim((string) ($billing['form_id'] ?? ''));

        if (!$userId) {
            if ($formId !== '' && $this->esFacturacionImagenAutomatica($formId)) {
                return [
                    'id' => null,
                    'nombre' => 'Imagenes',
                ];
            }
            return null;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(NULLIF(nombre, ''), NULLIF(username, '')) AS nombre FROM users WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$userId]);
            $nombre = $stmt->fetchColumn();
        } catch (\Throwable) {
            $nombre = false;
        }

        if (!$nombre && $formId !== '' && $this->esFacturacionImagenAutomatica($formId)) {
            return [
                'id' => null,
                'nombre' => 'Imagenes',
            ];
        }

        return [
            'id' => $userId,
            'nombre' => $nombre ? (string) $nombre : null,
        ];
    }

    private function esFacturacionImagenAutomatica(string $formId): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT 1 FROM imagenes_informes WHERE form_id = ? LIMIT 1');
            $stmt->execute([$formId]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerDatosPacientePorFormId(string $formId): ?array
    {
        $stmt = $this->db->prepare(
            <<<'SQL'
            SELECT
                pp.procedimiento_proyectado AS procedimiento,
                pp.doctor AS doctor,
                pp.fecha AS fecha,
                pd.fname,
                pd.mname,
                pd.lname,
                pd.lname2
            FROM procedimiento_proyectado pp
            INNER JOIN patient_data pd ON pp.hc_number = pd.hc_number
            WHERE pp.form_id = ?
            LIMIT 1
            SQL
        );
        $stmt->execute([$formId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $nombreCompleto = trim(
            (string) ($row['fname'] ?? '') . ' '
            . (string) ($row['mname'] ?? '') . ' '
            . (string) ($row['lname'] ?? '') . ' '
            . (string) ($row['lname2'] ?? '')
        );

        return [
            'nombre' => $nombreCompleto,
            'procedimiento' => $row['procedimiento'] ?? null,
            'doctor' => $row['doctor'] ?? null,
            'fecha' => $row['fecha'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerProtocoloTiny(string $formId, string $hcNumber): ?array
    {
        if ($formId === '' || $hcNumber === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            <<<'SQL'
            SELECT
                pr.hc_number,
                pr.form_id,
                pr.fecha_inicio,
                pr.hora_inicio,
                pr.cirujano_1,
                pr.instrumentista,
                pr.cirujano_2,
                pr.primer_ayudante,
                pr.anestesiologo,
                pr.membrete,
                pr.procedimientos,
                pr.lateralidad,
                pr.tipo_anestesia,
                pr.diagnosticos,
                pp.procedimiento_proyectado,
                pr.procedimiento_id
            FROM protocolo_data pr
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
            WHERE pr.form_id = ? AND pr.hc_number = ?
            LIMIT 1
            SQL
        );
        $stmt->execute([$formId, $hcNumber]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    /**
     * @param array<int, int> $billingIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchGroupedByBillingIds(string $table, array $billingIds): array
    {
        if ($billingIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($billingIds), '?'));

        try {
            $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE billing_id IN ($placeholders)");
            $stmt->execute($billingIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $billingId = (int) ($row['billing_id'] ?? 0);
            if ($billingId <= 0) {
                continue;
            }

            $grouped[$billingId][] = $row;
        }

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAllByBillingId(string $table, int $billingId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE billing_id = ?");
            $stmt->execute([$billingId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function obtenerInsumosPorBillingId(int $billingId): array
    {
        try {
            $stmt = $this->db->prepare(
                <<<'SQL'
                SELECT
                    bi.id,
                    bi.insumo_id,
                    bi.codigo,
                    bi.nombre,
                    bi.cantidad,
                    bi.precio,
                    bi.iva,
                    CASE
                        WHEN COALESCE(i.es_medicamento, 0) = 1 THEN 1
                        ELSE COALESCE(
                            (
                                SELECT i2.es_medicamento
                                FROM insumos AS i2
                                WHERE i2.codigo_isspol = TRIM(bi.codigo)
                                   OR i2.codigo_issfa = TRIM(bi.codigo)
                                   OR i2.codigo_iess = TRIM(bi.codigo)
                                   OR i2.codigo_msp = TRIM(bi.codigo)
                                   OR i2.codigo_isspol = LPAD(TRIM(bi.codigo), 6, '0')
                                   OR i2.codigo_issfa = LPAD(TRIM(bi.codigo), 6, '0')
                                   OR i2.codigo_iess = LPAD(TRIM(bi.codigo), 6, '0')
                                   OR i2.codigo_msp = LPAD(TRIM(bi.codigo), 6, '0')
                                   OR i2.codigo_isspol = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                   OR i2.codigo_issfa = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                   OR i2.codigo_iess = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                   OR i2.codigo_msp = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                ORDER BY COALESCE(i2.es_medicamento, 0) DESC, i2.id ASC
                                LIMIT 1
                            ),
                            i.es_medicamento,
                            0
                        )
                    END AS es_medicamento,
                    COALESCE(
                        (
                            SELECT i2.categoria
                            FROM insumos AS i2
                            WHERE i2.codigo_isspol = TRIM(bi.codigo)
                               OR i2.codigo_issfa = TRIM(bi.codigo)
                               OR i2.codigo_iess = TRIM(bi.codigo)
                               OR i2.codigo_msp = TRIM(bi.codigo)
                               OR i2.codigo_isspol = LPAD(TRIM(bi.codigo), 6, '0')
                               OR i2.codigo_issfa = LPAD(TRIM(bi.codigo), 6, '0')
                               OR i2.codigo_iess = LPAD(TRIM(bi.codigo), 6, '0')
                               OR i2.codigo_msp = LPAD(TRIM(bi.codigo), 6, '0')
                               OR i2.codigo_isspol = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                               OR i2.codigo_issfa = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                               OR i2.codigo_iess = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                               OR i2.codigo_msp = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                            ORDER BY COALESCE(i2.es_medicamento, 0) DESC, i2.id ASC
                            LIMIT 1
                        ),
                        i.categoria
                    ) AS categoria
                FROM billing_insumos AS bi
                LEFT JOIN insumos AS i ON bi.insumo_id = i.id
                WHERE bi.billing_id = ?
                SQL
            );
            $stmt->execute([$billingId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<int, int> $billingIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function obtenerInsumosPorBillingIds(array $billingIds): array
    {
        if ($billingIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($billingIds), '?'));

        try {
            $stmt = $this->db->prepare(
                str_replace('WHERE bi.billing_id = ?', "WHERE bi.billing_id IN ($placeholders)", <<<'SQL'
                SELECT
                    bi.id,
                    bi.billing_id,
                    bi.insumo_id,
                    bi.codigo,
                    bi.nombre,
                    bi.cantidad,
                    bi.precio,
                    bi.iva,
                    CASE
                        WHEN COALESCE(i.es_medicamento, 0) = 1 THEN 1
                        ELSE COALESCE(
                            (
                                SELECT i2.es_medicamento
                                FROM insumos AS i2
                                WHERE i2.codigo_isspol = TRIM(bi.codigo)
                                   OR i2.codigo_issfa = TRIM(bi.codigo)
                                   OR i2.codigo_iess = TRIM(bi.codigo)
                                   OR i2.codigo_msp = TRIM(bi.codigo)
                                   OR i2.codigo_isspol = LPAD(TRIM(bi.codigo), 6, '0')
                                   OR i2.codigo_issfa = LPAD(TRIM(bi.codigo), 6, '0')
                                   OR i2.codigo_iess = LPAD(TRIM(bi.codigo), 6, '0')
                                   OR i2.codigo_msp = LPAD(TRIM(bi.codigo), 6, '0')
                                   OR i2.codigo_isspol = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                   OR i2.codigo_issfa = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                   OR i2.codigo_iess = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                   OR i2.codigo_msp = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                ORDER BY COALESCE(i2.es_medicamento, 0) DESC, i2.id ASC
                                LIMIT 1
                            ),
                            i.es_medicamento,
                            0
                        )
                    END AS es_medicamento,
                    COALESCE(
                        (
                            SELECT i2.categoria
                            FROM insumos AS i2
                            WHERE i2.codigo_isspol = TRIM(bi.codigo)
                               OR i2.codigo_issfa = TRIM(bi.codigo)
                               OR i2.codigo_iess = TRIM(bi.codigo)
                               OR i2.codigo_msp = TRIM(bi.codigo)
                               OR i2.codigo_isspol = LPAD(TRIM(bi.codigo), 6, '0')
                               OR i2.codigo_issfa = LPAD(TRIM(bi.codigo), 6, '0')
                               OR i2.codigo_iess = LPAD(TRIM(bi.codigo), 6, '0')
                               OR i2.codigo_msp = LPAD(TRIM(bi.codigo), 6, '0')
                               OR i2.codigo_isspol = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                               OR i2.codigo_issfa = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                               OR i2.codigo_iess = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                               OR i2.codigo_msp = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                            ORDER BY COALESCE(i2.es_medicamento, 0) DESC, i2.id ASC
                            LIMIT 1
                        ),
                        i.categoria
                    ) AS categoria
                FROM billing_insumos AS bi
                LEFT JOIN insumos AS i ON bi.insumo_id = i.id
                WHERE bi.billing_id = ?
                SQL)
            );
            $stmt->execute($billingIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $billingId = (int) ($row['billing_id'] ?? 0);
            if ($billingId <= 0) {
                continue;
            }

            $grouped[$billingId][] = $row;
        }

        return $grouped;
    }

    /**
     * @param array<int, string> $formIds
     * @return array<string, array<string, mixed>>
     */
    private function obtenerProtocoloResumenPorFormIds(array $formIds): array
    {
        if ($formIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($formIds), '?'));

        try {
            $sql = sprintf(
                <<<'SQL'
                SELECT
                    pd.form_id,
                    pd.cirujano_2,
                    pd.primer_ayudante,
                    pd.membrete,
                    pd.lateralidad,
                    pp.procedimiento_proyectado,
                    pp.tipo
                FROM protocolo_data pd
                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pd.form_id
                WHERE pd.form_id IN (%s)
                SQL,
                $placeholders
            );
            $stmt = $this->db->prepare($sql);
            $stmt->execute($formIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $formId = trim((string) ($row['form_id'] ?? ''));
            if ($formId === '' || isset($grouped[$formId])) {
                continue;
            }

            $grouped[$formId] = $row;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $billing
     * @param array<int, array<string, mixed>> $procedimientos
     * @param array<int, array<string, mixed>> $derechos
     * @param array<int, array<string, mixed>> $oxigeno
     * @param array<int, array<string, mixed>> $anestesia
     * @param array<int, array<string, mixed>> $insumosRaw
     * @param array<string, mixed> $protocoloResumen
     * @return array<string, mixed>
     */
    private function buildResumenConsolidadoFromSources(
        array $billing,
        array $procedimientos,
        array $derechos,
        array $oxigeno,
        array $anestesia,
        array $insumosRaw,
        array $protocoloResumen
    ): array {
        $splitInsumos = $this->splitInsumosPorTipo($insumosRaw);
        $pseudoDatos = [
            'procedimientos' => $procedimientos,
            'derechos' => $derechos,
            'insumos' => $splitInsumos['insumos'],
            'medicamentos' => $splitInsumos['medicamentos'],
            'oxigeno' => $oxigeno,
            'anestesia' => $anestesia,
            'protocoloExtendido' => $protocoloResumen,
            'formulario' => [
                'procedimiento' => $protocoloResumen['procedimiento_proyectado'] ?? '',
                'tipo' => $protocoloResumen['tipo'] ?? '',
            ],
            'visita' => [
                'procedimiento' => $protocoloResumen['procedimiento_proyectado'] ?? '',
            ],
        ];

        $facturador = $this->resolverFacturador($billing);
        $summary = [
            'billing_id' => (int) ($billing['id'] ?? 0),
            'form_id' => (string) ($billing['form_id'] ?? ''),
            'facturador_id' => $facturador['id'] ?? null,
            'facturador_nombre' => $facturador['nombre'] ?? null,
            'total' => InformesHelper::calcularTotalFactura($pseudoDatos, $this),
            'categoria' => InformesHelper::clasificarCategoriaFactura($pseudoDatos),
        ];

        $hasDetailRows = $procedimientos !== []
            || $derechos !== []
            || $oxigeno !== []
            || $anestesia !== []
            || $insumosRaw !== [];

        if ($hasDetailRows) {
            return $summary;
        }

        $fallbackFull = $this->obtenerDatos($summary['form_id']);
        if ($fallbackFull) {
            $summary['total'] = InformesHelper::calcularTotalFactura($fallbackFull, $this);
            $summary['categoria'] = InformesHelper::clasificarCategoriaFactura($fallbackFull);
            $summary['facturador_nombre'] = $fallbackFull['billing']['facturador_nombre']
                ?? $fallbackFull['billing']['facturador']
                ?? $summary['facturador_nombre'];
        }

        return $summary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProcedimientosPorCobrar(string $formId, string $hcNumber, string $codigoDerivacion = ''): array
    {
        $formId = trim($formId);
        $hcNumber = trim($hcNumber);
        $codigoDerivacion = trim($codigoDerivacion);

        if ($hcNumber === '' && $formId === '') {
            return [];
        }

        $pdo = $this->sigcenterDb ?? $this->db;
        $rows = $this->fetchProcedimientosViaPdo($pdo, $formId, $hcNumber, $codigoDerivacion);

        if ($rows === [] && $pdo !== $this->db) {
            $rows = $this->fetchProcedimientosViaPdo($this->db, $formId, $hcNumber, $codigoDerivacion);
            if ($rows !== []) {
                $pdo = $this->db;
            }
        }

        if ($rows === []) {
            return [];
        }

        $statusHistory = $this->fetchAgendaStatusHistory($pdo, array_column($rows, 'form_id'));
        $result = [];

        foreach ($rows as $row) {
            $formId = trim((string) ($row['form_id'] ?? ''));
            $procedimiento = trim((string) ($row['procedimiento'] ?? ''));
            if ($formId === '' || $procedimiento === '') {
                continue;
            }

            $fecha = trim((string) ($row['fecha'] ?? ''));
            $hora = trim((string) ($row['hora'] ?? ''));
            $fechaEjecucion = trim($fecha . ' ' . $hora);
            $estado = $this->normalizeAgendaAltaStatus(
                (string) ($row['estado_agenda'] ?? ''),
                $statusHistory[$formId] ?? []
            );

            $result[] = [
                'form_id' => $formId,
                'procedimiento_proyectado' => [
                    'id' => $formId,
                    'nombre' => $procedimiento,
                    'fecha_ejecucion' => $fechaEjecucion !== '' ? $fechaEjecucion : $fecha,
                    'doctor' => trim((string) ($row['doctor'] ?? '')),
                    'estado_alta' => $estado,
                ],
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchProcedimientosViaPdo(PDO $pdo, string $formId, string $hcNumber, string $codigoDerivacion): array
    {
        $queries = [];

        if ($formId !== '') {
            $queries[] = [
                'sql' => <<<'SQL'
                    SELECT
                        pp.form_id,
                        pp.procedimiento_proyectado AS procedimiento,
                        pp.doctor,
                        COALESCE(pp.fecha, v.fecha_visita) AS fecha,
                        COALESCE(pp.hora, v.hora_llegada) AS hora,
                        pp.estado_agenda
                    FROM procedimiento_proyectado pp
                    LEFT JOIN visitas v ON v.id = pp.visita_id
                    WHERE pp.form_id = :form_id
                    ORDER BY COALESCE(pp.fecha, v.fecha_visita) DESC,
                             COALESCE(pp.hora, v.hora_llegada) DESC,
                             pp.form_id DESC
                    SQL,
                'params' => [
                    ':form_id' => $formId,
                ],
            ];
        }

        if ($codigoDerivacion !== '' && $hcNumber !== '') {
            $queries[] = [
                'sql' => <<<'SQL'
                    SELECT
                        pp.form_id,
                        pp.procedimiento_proyectado AS procedimiento,
                        pp.doctor,
                        COALESCE(pp.fecha, v.fecha_visita) AS fecha,
                        COALESCE(pp.hora, v.hora_llegada) AS hora,
                        pp.estado_agenda
                    FROM procedimiento_proyectado pp
                    LEFT JOIN visitas v ON v.id = pp.visita_id
                    LEFT JOIN derivaciones_forms df ON df.iess_form_id = pp.form_id
                    LEFT JOIN derivaciones_referral_forms rf ON rf.form_id = df.id
                    LEFT JOIN derivaciones_referrals dr ON dr.id = rf.referral_id
                    WHERE pp.hc_number = :hc
                      AND dr.referral_code = :codigo
                    ORDER BY COALESCE(pp.fecha, v.fecha_visita) DESC,
                             COALESCE(pp.hora, v.hora_llegada) DESC,
                             pp.form_id DESC
                    SQL,
                'params' => [
                    ':hc' => $hcNumber,
                    ':codigo' => $codigoDerivacion,
                ],
            ];
        }

        if ($hcNumber !== '') {
            $queries[] = [
                'sql' => <<<'SQL'
                    SELECT
                        pp.form_id,
                        pp.procedimiento_proyectado AS procedimiento,
                        pp.doctor,
                        COALESCE(pp.fecha, v.fecha_visita) AS fecha,
                        COALESCE(pp.hora, v.hora_llegada) AS hora,
                        pp.estado_agenda
                    FROM procedimiento_proyectado pp
                    LEFT JOIN visitas v ON v.id = pp.visita_id
                    WHERE pp.hc_number = :hc
                    ORDER BY COALESCE(pp.fecha, v.fecha_visita) DESC,
                             COALESCE(pp.hora, v.hora_llegada) DESC,
                             pp.form_id DESC
                    SQL,
                'params' => [
                    ':hc' => $hcNumber,
                ],
            ];
        }

        foreach ($queries as $query) {
            try {
                $stmt = $pdo->prepare($query['sql']);
                $stmt->execute($query['params']);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if ($rows !== []) {
                    return $rows;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchDerivacionFromSigcenter(string $formId, string $hcNumber, array &$debug = []): array
    {
        if ($formId === '') {
            $debug = [
                'status' => 'missing_form_id',
                'error' => null,
                'codigo_derivacion' => '',
            ];
            return [];
        }

        if ($this->sigcenterDb === null) {
            $sshRow = $this->fetchDerivacionFromSigcenterViaSsh($formId, $hcNumber, $debug);
            if ($sshRow !== []) {
                return $sshRow;
            }

            if ($debug === []) {
                $debug = [
                    'status' => 'connection_missing',
                    'error' => null,
                    'codigo_derivacion' => '',
                ];
            }
            return [];
        }

        try {
            $stmt = $this->sigcenterDb->prepare(
                <<<'SQL'
                SELECT
                    base.pedido_id,
                    base.oda_id,
                    base.oda_referencia,
                    base.paciente_id,
                    base.cedula,
                    base.hc_number,
                    base.cod_derivacion,
                    base.cod_derivacion AS codigo_derivacion,
                    base.num_secuencial_derivacion,
                    base.fecha_registro,
                    base.fecha_vigencia,
                    base.referido,
                    base.procedencia,
                    base.parentesco,
                    base.sede,
                    base.afiliacion,
                    TRIM(COALESCE(dx.cie10_codes, '')) AS cie10_codes,
                    TRIM(COALESCE(dx.diagnostico_medforge, '')) AS cie10_display,
                    TRIM(COALESCE(dx.diagnostico_medforge, '')) AS diagnostico,
                    base.pdf_totalizado_path,
                    base.documento_id,
                    base.archivo_nombre,
                    base.titulo,
                    base.tipo_documento,
                    base.mimeType,
                    base.ruta_pdf_real,
                    base.form_id,
                    base.diagnostico_source_id
                FROM (
                    SELECT
                        dsp.id AS pedido_id,
                        dm.id AS oda_id,
                        TRIM(COALESCE(dm.nroOda, '')) AS oda_referencia,
                        p.ID_PACIENTE AS paciente_id,
                        TRIM(COALESCE(p.IDENTIFICACION, '')) AS cedula,
                        TRIM(COALESCE(p.numero_historia_clinica, '')) AS hc_number,
                        TRIM(COALESCE(dspac.cod_derivacion, '')) AS cod_derivacion,
                        TRIM(COALESCE(dspac.num_secuencial_derivacion, '')) AS num_secuencial_derivacion,
                        COALESCE(DATE_FORMAT(dspac.fecha_registro, '%Y-%m-%d'), '') AS fecha_registro,
                        COALESCE(DATE_FORMAT(dspac.fecha_vigencia, '%Y-%m-%d'), '') AS fecha_vigencia,
                        TRIM(COALESCE(ref.nombre, '')) AS referido,
                        TRIM(COALESCE(procd.NOMBRE, '')) AS procedencia,
                        TRIM(COALESCE(par.NOMBRE, '')) AS parentesco,
                        TRIM(COALESCE(s.NOMBRE, '')) AS sede,
                        TRIM(COALESCE(af.NOMBRE, '')) AS afiliacion,
                        CONCAT('/documentacion/doc-multiple-documentos/imprimir-totalizado?id=', dspac.pacienteId, '&idSolicitud=', dsp.id, '&check=18') AS pdf_totalizado_path,
                        dma.id AS documento_id,
                        TRIM(COALESCE(dma.nombre, '')) AS archivo_nombre,
                        TRIM(COALESCE(dma.titulo, '')) AS titulo,
                        TRIM(COALESCE(dma.tipo_documento, '')) AS tipo_documento,
                        TRIM(COALESCE(dma.mimeType, '')) AS mimeType,
                        CONCAT('/var/www/html/GOOGLE/frontend/web/data/empresa_113/paciente_', p.ID_PACIENTE, '/doc_afiliacion/', dma.nombre) AS ruta_pdf_real,
                        :form_id AS form_id,
                        (
                            SELECT d2.id
                            FROM doc_solicitud_procedimientos d2
                            INNER JOIN doc_solicitud_paciente dsp2
                                ON dsp2.id = d2.doc_solicitud_pacienteId
                            WHERE dsp2.pacienteId = dspac.pacienteId
                              AND COALESCE(TRIM(dsp2.cod_derivacion), '') = COALESCE(TRIM(dspac.cod_derivacion), '')
                              AND COALESCE(TRIM(dsp2.num_secuencial_derivacion), '') = COALESCE(TRIM(dspac.num_secuencial_derivacion), '')
                              AND COALESCE(DATE(dsp2.fecha_registro), '1900-01-01') = COALESCE(DATE(dspac.fecha_registro), '1900-01-01')
                              AND COALESCE(DATE(dsp2.fecha_vigencia), '1900-01-01') = COALESCE(DATE(dspac.fecha_vigencia), '1900-01-01')
                              AND COALESCE(dsp2.afiliacionId, 0) = COALESCE(dspac.afiliacionId, 0)
                              AND EXISTS (
                                  SELECT 1
                                  FROM hc_diagnostico_solicitud h2
                                  WHERE h2.docSolicitudProcedimiento_id = d2.id
                              )
                            ORDER BY d2.id DESC
                            LIMIT 1
                        ) AS diagnostico_source_id
                    FROM doc_solicitud_procedimientos dsp
                    INNER JOIN doc_solicitud_paciente dspac
                        ON dspac.id = dsp.doc_solicitud_pacienteId
                    INNER JOIN paciente p
                        ON p.ID_PACIENTE = dspac.pacienteId
                    LEFT JOIN doc_motivo dm
                        ON dm.id = dspac.motivo_id
                    LEFT JOIN afiliacion af
                        ON af.ID_AFILIACION = dspac.afiliacionId
                    LEFT JOIN referido ref
                        ON ref.id = dspac.referido_id
                    LEFT JOIN procedencia procd
                        ON procd.ID_PROCEDENCIA = dspac.procedencia_id
                    LEFT JOIN parentesco par
                        ON par.ID_PARENTESCO = dspac.parentescoId
                    LEFT JOIN sede s
                        ON s.ID_SEDE = dsp.sede_id
                    LEFT JOIN doc_solicitud_documento_afiliacion dsda
                        ON dsda.doc_solicitud_pacienteId = dsp.doc_solicitud_pacienteId
                    LEFT JOIN doc_multiple_documentos_afiliacion dma
                        ON dma.doc_solicitud_documento_afiliacionId = dsda.id
                    WHERE dsp.id = :form_id
                    ORDER BY dma.id DESC
                    LIMIT 1
                ) base
                LEFT JOIN (
                    SELECT
                        hds.docSolicitudProcedimiento_id,
                        GROUP_CONCAT(enf.codigo ORDER BY hds.id SEPARATOR '; ') AS cie10_codes,
                        GROUP_CONCAT(
                            CONCAT(enf.codigo, ' - ', enf.nombre)
                            ORDER BY hds.id
                            SEPARATOR '; '
                        ) AS diagnostico_medforge
                    FROM hc_diagnostico_solicitud hds
                    INNER JOIN enfermedades enf
                        ON enf.idEnfermedades = hds.diagnostico_id
                    GROUP BY hds.docSolicitudProcedimiento_id
                ) dx
                    ON dx.docSolicitudProcedimiento_id = base.diagnostico_source_id
                ORDER BY base.documento_id DESC
                LIMIT 1
                SQL
            );
            $stmt->execute([':form_id' => $formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $exception) {
            $debug = [
                'status' => 'query_error',
                'error' => $exception->getMessage(),
                'codigo_derivacion' => '',
            ];
            return [];
        }

        if ($row === []) {
            $debug = [
                'status' => 'not_found',
                'error' => null,
                'codigo_derivacion' => '',
            ];
            return [];
        }

        if (trim((string) ($row['hc_number'] ?? '')) === '' && $hcNumber !== '') {
            $row['hc_number'] = $hcNumber;
        }
        $row['pdf_totalizado_url'] = $this->buildSigcenterPdfUrl((string) ($row['pdf_totalizado_path'] ?? ''));
        $row['archivo_derivacion_path'] = (string) ($row['ruta_pdf_real'] ?? $row['pdf_totalizado_path'] ?? '');
        $row = $this->sanitizeDerivacionRow($row);

        $debug = [
            'status' => 'ok',
            'error' => null,
            'codigo_derivacion' => $this->extractCodigoDerivacion($row),
            'referido' => trim((string) ($row['referido'] ?? '')),
            'diagnostico' => trim((string) ($row['diagnostico'] ?? '')),
            'ruta_pdf_real' => (string) ($row['ruta_pdf_real'] ?? ''),
            'pdf_totalizado_url' => (string) ($row['pdf_totalizado_url'] ?? ''),
        ];

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchDerivacionFromSigcenterViaSsh(string $formId, string $hcNumber, array &$debug = []): array
    {
        $ssh = $this->sigcenterSsh();
        if ($ssh === null) {
            $debug = [
                'status' => 'connection_missing',
                'error' => 'SSH Sigcenter no configurado o autenticación fallida.',
                'codigo_derivacion' => '',
            ];
            return [];
        }

        $sql = sprintf(
            <<<'SQL'
SELECT
    base.pedido_id,
    base.oda_id,
    base.oda_referencia,
    base.paciente_id,
    base.cedula,
    base.hc_number,
    base.cod_derivacion,
    base.cod_derivacion AS codigo_derivacion,
    base.num_secuencial_derivacion,
    base.fecha_registro,
    base.fecha_vigencia,
    base.referido,
    base.procedencia,
    base.parentesco,
    base.sede,
    base.afiliacion,
    TRIM(COALESCE(dx.cie10_codes, '')) AS cie10_codes,
    TRIM(COALESCE(dx.diagnostico_medforge, '')) AS cie10_display,
    TRIM(COALESCE(dx.diagnostico_medforge, '')) AS diagnostico,
    base.pdf_totalizado_path,
    base.documento_id,
    base.archivo_nombre,
    base.titulo,
    base.tipo_documento,
    base.mimeType,
    base.ruta_pdf_real,
    base.form_id,
    base.diagnostico_source_id
FROM (
    SELECT
        dsp.id AS pedido_id,
        dm.id AS oda_id,
        TRIM(COALESCE(dm.nroOda, '')) AS oda_referencia,
        p.ID_PACIENTE AS paciente_id,
        TRIM(COALESCE(p.IDENTIFICACION, '')) AS cedula,
        TRIM(COALESCE(p.numero_historia_clinica, '')) AS hc_number,
        TRIM(COALESCE(dspac.cod_derivacion, '')) AS cod_derivacion,
        TRIM(COALESCE(dspac.num_secuencial_derivacion, '')) AS num_secuencial_derivacion,
        COALESCE(DATE_FORMAT(dspac.fecha_registro, '%%Y-%%m-%%d'), '') AS fecha_registro,
        COALESCE(DATE_FORMAT(dspac.fecha_vigencia, '%%Y-%%m-%%d'), '') AS fecha_vigencia,
        TRIM(COALESCE(ref.nombre, '')) AS referido,
        TRIM(COALESCE(procd.NOMBRE, '')) AS procedencia,
        TRIM(COALESCE(par.NOMBRE, '')) AS parentesco,
        TRIM(COALESCE(s.NOMBRE, '')) AS sede,
        TRIM(COALESCE(af.NOMBRE, '')) AS afiliacion,
        CONCAT('/documentacion/doc-multiple-documentos/imprimir-totalizado?id=', dspac.pacienteId, '&idSolicitud=', dsp.id, '&check=18') AS pdf_totalizado_path,
        dma.id AS documento_id,
        TRIM(COALESCE(dma.nombre, '')) AS archivo_nombre,
        TRIM(COALESCE(dma.titulo, '')) AS titulo,
        TRIM(COALESCE(dma.tipo_documento, '')) AS tipo_documento,
        TRIM(COALESCE(dma.mimeType, '')) AS mimeType,
        CONCAT('/var/www/html/GOOGLE/frontend/web/data/empresa_113/paciente_', p.ID_PACIENTE, '/doc_afiliacion/', dma.nombre) AS ruta_pdf_real,
        '%s' AS form_id,
        (
            SELECT d2.id
            FROM doc_solicitud_procedimientos d2
            INNER JOIN doc_solicitud_paciente dsp2
                ON dsp2.id = d2.doc_solicitud_pacienteId
            WHERE dsp2.pacienteId = dspac.pacienteId
              AND COALESCE(TRIM(dsp2.cod_derivacion), '') = COALESCE(TRIM(dspac.cod_derivacion), '')
              AND COALESCE(TRIM(dsp2.num_secuencial_derivacion), '') = COALESCE(TRIM(dspac.num_secuencial_derivacion), '')
              AND COALESCE(DATE(dsp2.fecha_registro), '1900-01-01') = COALESCE(DATE(dspac.fecha_registro), '1900-01-01')
              AND COALESCE(DATE(dsp2.fecha_vigencia), '1900-01-01') = COALESCE(DATE(dspac.fecha_vigencia), '1900-01-01')
              AND COALESCE(dsp2.afiliacionId, 0) = COALESCE(dspac.afiliacionId, 0)
              AND EXISTS (
                  SELECT 1
                  FROM hc_diagnostico_solicitud h2
                  WHERE h2.docSolicitudProcedimiento_id = d2.id
              )
            ORDER BY d2.id DESC
            LIMIT 1
        ) AS diagnostico_source_id
    FROM doc_solicitud_procedimientos dsp
    INNER JOIN doc_solicitud_paciente dspac
        ON dspac.id = dsp.doc_solicitud_pacienteId
    INNER JOIN paciente p
        ON p.ID_PACIENTE = dspac.pacienteId
    LEFT JOIN doc_motivo dm
        ON dm.id = dspac.motivo_id
    LEFT JOIN afiliacion af
        ON af.ID_AFILIACION = dspac.afiliacionId
    LEFT JOIN referido ref
        ON ref.id = dspac.referido_id
    LEFT JOIN procedencia procd
        ON procd.ID_PROCEDENCIA = dspac.procedencia_id
    LEFT JOIN parentesco par
        ON par.ID_PARENTESCO = dspac.parentescoId
    LEFT JOIN sede s
        ON s.ID_SEDE = dsp.sede_id
    LEFT JOIN doc_solicitud_documento_afiliacion dsda
        ON dsda.doc_solicitud_pacienteId = dsp.doc_solicitud_pacienteId
    LEFT JOIN doc_multiple_documentos_afiliacion dma
        ON dma.doc_solicitud_documento_afiliacionId = dsda.id
    WHERE dsp.id = '%s'
    ORDER BY dma.id DESC
    LIMIT 1
) base
LEFT JOIN (
    SELECT
        hds.docSolicitudProcedimiento_id,
        GROUP_CONCAT(enf.codigo ORDER BY hds.id SEPARATOR '; ') AS cie10_codes,
        GROUP_CONCAT(
            CONCAT(enf.codigo, ' - ', enf.nombre)
            ORDER BY hds.id
            SEPARATOR '; '
        ) AS diagnostico_medforge
    FROM hc_diagnostico_solicitud hds
    INNER JOIN enfermedades enf
        ON enf.idEnfermedades = hds.diagnostico_id
    GROUP BY hds.docSolicitudProcedimiento_id
) dx
    ON dx.docSolicitudProcedimiento_id = base.diagnostico_source_id
ORDER BY base.documento_id DESC
 LIMIT 1
SQL,
            $this->escapeMysqlLiteral($formId),
            $this->escapeMysqlLiteral($formId)
        );

        $output = $ssh->exec($this->buildMysqlCommand($sql));
        $exitStatus = $ssh->getExitStatus();

        if (($exitStatus ?? 0) !== 0 && trim((string) $output) === '') {
            $debug = [
                'status' => 'ssh_query_error',
                'error' => 'Consulta SSH a Sigcenter falló.',
                'codigo_derivacion' => '',
            ];
            return [];
        }

        $rawOutput = (string) $output;
        $lines = array_values(array_filter(
            preg_split('/\r?\n/', trim($rawOutput, "\r\n")) ?: [],
            static fn($candidate): bool => trim((string) $candidate) !== ''
        ));
        $line = '';
        foreach (array_reverse($lines) as $candidate) {
            if (substr_count((string) $candidate, "\t") >= 5) {
                $line = (string) $candidate;
                break;
            }
        }
        if ($line === '') {
            $line = (string) ($lines[count($lines) - 1] ?? '');
        }
        if ($line === '') {
            $debug = [
                'status' => 'not_found',
                'error' => null,
                'codigo_derivacion' => '',
            ];
            return [];
        }

        $parts = explode("\t", preg_split('/\r?\n/', $line)[0] ?? '');
        $row = [
            'pedido_id' => trim((string) ($parts[0] ?? $formId)),
            'oda_id' => trim((string) ($parts[1] ?? '')),
            'oda_referencia' => trim((string) ($parts[2] ?? '')),
            'paciente_id' => trim((string) ($parts[3] ?? '')),
            'cedula' => trim((string) ($parts[4] ?? '')),
            'hc_number' => trim((string) ($parts[5] ?? $hcNumber)),
            'cod_derivacion' => trim((string) ($parts[6] ?? '')),
            'codigo_derivacion' => trim((string) ($parts[7] ?? '')),
            'num_secuencial_derivacion' => trim((string) ($parts[8] ?? '')),
            'fecha_registro' => trim((string) ($parts[9] ?? '')),
            'fecha_vigencia' => trim((string) ($parts[10] ?? '')),
            'referido' => trim((string) ($parts[11] ?? '')),
            'procedencia' => trim((string) ($parts[12] ?? '')),
            'parentesco' => trim((string) ($parts[13] ?? '')),
            'sede' => trim((string) ($parts[14] ?? '')),
            'afiliacion' => trim((string) ($parts[15] ?? '')),
            'cie10_codes' => trim((string) ($parts[16] ?? '')),
            'cie10_display' => trim((string) ($parts[17] ?? '')),
            'diagnostico' => trim((string) ($parts[18] ?? '')),
            'pdf_totalizado_path' => trim((string) ($parts[19] ?? '')),
            'documento_id' => trim((string) ($parts[20] ?? '')),
            'archivo_nombre' => trim((string) ($parts[21] ?? '')),
            'titulo' => trim((string) ($parts[22] ?? '')),
            'tipo_documento' => trim((string) ($parts[23] ?? '')),
            'mimeType' => trim((string) ($parts[24] ?? '')),
            'ruta_pdf_real' => trim((string) ($parts[25] ?? '')),
            'form_id' => trim((string) ($parts[26] ?? $formId)),
            'diagnostico_source_id' => trim((string) ($parts[27] ?? '')),
        ];

        if ($row['hc_number'] === '' && $hcNumber !== '') {
            $row['hc_number'] = $hcNumber;
        }
        $row['pdf_totalizado_url'] = $this->buildSigcenterPdfUrl((string) ($row['pdf_totalizado_path'] ?? ''));
        $row['archivo_derivacion_path'] = (string) ($row['ruta_pdf_real'] ?? $row['pdf_totalizado_path'] ?? '');
        $row = $this->sanitizeDerivacionRow($row);

        $debug = [
            'status' => 'ssh_ok',
            'error' => null,
            'codigo_derivacion' => $this->extractCodigoDerivacion($row),
            'referido' => $row['referido'],
            'diagnostico' => $row['diagnostico'],
            'ruta_pdf_real' => (string) ($row['ruta_pdf_real'] ?? ''),
            'pdf_totalizado_url' => (string) ($row['pdf_totalizado_url'] ?? ''),
        ];

        return $row;
    }

    private function buildSigcenterPdfUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return $this->sigcenterBaseUrl . '/' . ltrim($path, '/');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function sanitizeDerivacionRow(array $row): array
    {
        $codigo = $this->sanitizeDerivacionCode((string) ($row['cod_derivacion'] ?? $row['codigo_derivacion'] ?? ''));
        $row['cod_derivacion'] = $codigo;
        $row['codigo_derivacion'] = $codigo;
        $row['referido'] = $this->sanitizeDerivacionReferido((string) ($row['referido'] ?? ''), $row);
        $row['diagnostico'] = $this->sanitizeDerivacionDiagnostico((string) ($row['diagnostico'] ?? ''), $row);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractCodigoDerivacion(array $row): string
    {
        return $this->sanitizeDerivacionCode((string) ($row['cod_derivacion'] ?? $row['codigo_derivacion'] ?? ''));
    }

    private function sanitizeDerivacionCode(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return '';
        }

        if (str_contains($value, ';')) {
            return '';
        }

        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        if ($value === '') {
            return '';
        }

        if (!preg_match('/\d/', $value)) {
            return '';
        }

        if (!preg_match('/[A-Z]/', $value)) {
            return '';
        }

        if (preg_match_all('/\d/', $value) < 6) {
            return '';
        }

        if (!preg_match('/^[A-Z0-9._\\/-]{8,}$/', $value)) {
            return '';
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function sanitizeDerivacionReferido(string $value, array $row): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $formId = trim((string) ($row['form_id'] ?? ''));
        $hcNumber = trim((string) ($row['hc_number'] ?? ''));
        if ($value === $formId || $value === $hcNumber) {
            return '';
        }

        if (preg_match('/^\d+$/', $value)) {
            return '';
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function sanitizeDerivacionDiagnostico(string $value, array $row): string
    {
        $preferred = trim((string) ($row['cie10_display'] ?? ''));
        if ($preferred !== '') {
            $value = $preferred;
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $hcNumber = trim((string) ($row['hc_number'] ?? ''));
        if ($value === $hcNumber) {
            return '';
        }

        $parts = preg_split('/\s*;\s*/', $value) ?: [];
        $normalized = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $part = preg_replace('/\s+/u', ' ', $part) ?? $part;
            if (preg_match('/^([A-Z][0-9][0-9A-Z.]+)\s*-\s*(.+)$/u', $part, $matches)) {
                $part = strtoupper(trim((string) $matches[1])) . ' - ' . trim((string) $matches[2]);
            }

            $normalized[$part] = true;
        }

        return implode('; ', array_keys($normalized));
    }

    private function clearLegacyDerivacionCode(string $formId): void
    {
        try {
            $stmt = $this->db->prepare('UPDATE derivaciones_form_id SET cod_derivacion = NULL WHERE form_id = ?');
            $stmt->execute([$formId]);
        } catch (\Throwable) {
            // Ignorar: la UI ya usa el valor saneado en memoria.
        }
    }

    private function buildMysqlCommand(string $sql): string
    {
        return sprintf(
            'mysql --batch --raw --skip-column-names -h %s -P %d -u %s -p%s %s -e %s',
            escapeshellarg((string) $this->sigcenterDbHost),
            $this->sigcenterDbPort,
            escapeshellarg((string) $this->sigcenterDbUsername),
            escapeshellarg((string) ($this->sigcenterDbPassword ?? '')),
            escapeshellarg((string) $this->sigcenterDbDatabase),
            escapeshellarg($sql)
        );
    }

    private function sigcenterSsh(): ?\phpseclib3\Net\SSH2
    {
        if ($this->sigcenterSsh instanceof \phpseclib3\Net\SSH2) {
            return $this->sigcenterSsh;
        }

        if (
            $this->sigcenterSshHost === null
            || $this->sigcenterSshUser === null
            || $this->sigcenterSshPass === null
            || $this->sigcenterDbDatabase === null
            || $this->sigcenterDbUsername === null
            || !class_exists('\\phpseclib3\\Net\\SSH2')
        ) {
            return null;
        }

        $ssh = new \phpseclib3\Net\SSH2((string) $this->sigcenterSshHost, $this->sigcenterSshPort, 20);
        if (!$ssh->login((string) $this->sigcenterSshUser, (string) $this->sigcenterSshPass)) {
            return null;
        }

        $this->sigcenterSsh = $ssh;
        return $this->sigcenterSsh;
    }

    private function readEnv(string $key): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === null || $value === '') {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function escapeMysqlLiteral(string $value): string
    {
        return str_replace(
            ["\\", "'"],
            ["\\\\", "\\'"],
            $value
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);
        return $string !== '' ? $string : null;
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        $timestamp = strtotime($string);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    /**
     * @param array<int, string> $formIds
     * @return array<string, array<int, array{estado:string,fecha_hora_cambio:string}>>
     */
    private function fetchAgendaStatusHistory(PDO $pdo, array $formIds): array
    {
        $normalizedFormIds = array_values(array_unique(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            $formIds
        ))));

        if ($normalizedFormIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalizedFormIds), '?'));

        try {
            $stmt = $pdo->prepare(
                "SELECT form_id, estado, fecha_hora_cambio
                 FROM procedimiento_proyectado_estado
                 WHERE form_id IN ($placeholders)
                 ORDER BY form_id ASC, fecha_hora_cambio ASC"
            );
            $stmt->execute($normalizedFormIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $formId = trim((string) ($row['form_id'] ?? ''));
            if ($formId === '') {
                continue;
            }

            $result[$formId][] = [
                'estado' => (string) ($row['estado'] ?? ''),
                'fecha_hora_cambio' => (string) ($row['fecha_hora_cambio'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array{estado:string,fecha_hora_cambio:string}> $history
     */
    private function normalizeAgendaAltaStatus(string $currentStatus, array $history): string
    {
        $statuses = [];
        $currentStatus = strtoupper(trim($currentStatus));
        if ($currentStatus !== '') {
            $statuses[] = $currentStatus;
        }

        foreach ($history as $row) {
            $status = strtoupper(trim((string) ($row['estado'] ?? '')));
            if ($status !== '') {
                $statuses[] = $status;
            }
        }

        foreach ($statuses as $status) {
            if (
                str_contains($status, 'DADO DE ALTA')
                || str_contains($status, 'YA FUE DADO DE ALTA')
                || $status === 'ALTA'
            ) {
                return '✅ Dado de Alta';
            }
        }

        return '❌ No dado de alta';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{insumos: array<int, array<string, mixed>>, medicamentos: array<int, array<string, mixed>>}
     */
    private function splitInsumosPorTipo(array $rows): array
    {
        $insumosConIva = [];
        $medicamentosSinIva = [];

        foreach ($rows as $insumo) {
            if (!is_array($insumo)) {
                continue;
            }

            $esMedicamento = $insumo['es_medicamento'] ?? null;
            $categoria = strtolower(trim((string) ($insumo['categoria'] ?? '')));
            if ($esMedicamento === null) {
                $esMedicamento = isset($insumo['iva']) && (int) $insumo['iva'] === 0 ? 1 : 0;
            } else {
                $esMedicamento = (int) $esMedicamento;
            }

            if ($esMedicamento !== 1 && $this->looksLikeMedicamento($insumo, $categoria)) {
                $esMedicamento = 1;
            }

            if ($esMedicamento === 1) {
                $medicamentosSinIva[] = $insumo;
                continue;
            }

            $insumosConIva[] = $insumo;
        }

        return [
            'insumos' => $insumosConIva,
            'medicamentos' => $medicamentosSinIva,
        ];
    }

    /**
     * @param array<string, mixed> $insumo
     */
    private function looksLikeMedicamento(array $insumo, string $categoria): bool
    {
        if ($categoria !== '' && (
            str_contains($categoria, 'medic') ||
            str_contains($categoria, 'farm')
        )) {
            return true;
        }

        $nombre = $this->normalizeInventoryName((string) ($insumo['nombre'] ?? ''));
        if ($nombre === '') {
            return false;
        }

        $lookup = $this->medicamentoNombreLookup();
        if (isset($lookup[$nombre])) {
            return true;
        }

        foreach ($this->medicamentoNombreList() as $medicamento) {
            if (strlen($medicamento) < 4) {
                continue;
            }
            if (
                str_starts_with($nombre, $medicamento . ' ') ||
                str_starts_with($nombre, $medicamento . '(') ||
                str_contains($nombre, $medicamento . ' liquido') ||
                str_contains($nombre, $medicamento . ' solido')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, bool>
     */
    private function medicamentoNombreLookup(): array
    {
        if ($this->medicamentoNombreLookup !== null) {
            return $this->medicamentoNombreLookup;
        }

        try {
            $stmt = $this->db->query('SELECT nombre FROM insumos WHERE es_medicamento = 1');
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Throwable) {
            $rows = [];
        }

        $lookup = [];
        foreach ($rows as $row) {
            $normalized = $this->normalizeInventoryName((string) $row);
            if ($normalized !== '') {
                $lookup[$normalized] = true;
            }
        }

        $this->medicamentoNombreLookup = $lookup;
        return $lookup;
    }

    /**
     * @return array<int, string>
     */
    private function medicamentoNombreList(): array
    {
        if ($this->medicamentoNombreList !== null) {
            return $this->medicamentoNombreList;
        }

        $list = array_keys($this->medicamentoNombreLookup());
        usort($list, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        $this->medicamentoNombreList = $list;
        return $list;
    }

    private function normalizeInventoryName(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return preg_replace('/[^a-z0-9 ]+/i', '', $value) ?? $value;
    }

    /**
     * @param array<int, array<string, mixed>> $medicamentos
     * @return array<int, array<string, mixed>>
     */
    private function ajustarMedicamentosPorAfiliacionLista(array $medicamentos, string $afiliacion): array
    {
        if ($medicamentos === []) {
            return [];
        }

        $codigos = [];
        foreach ($medicamentos as $medicamento) {
            $codigo = trim((string) ($medicamento['codigo'] ?? ''));
            if ($codigo !== '') {
                $codigos[] = $codigo;
            }
        }
        $codigos = array_values(array_unique($codigos));

        if ($codigos === []) {
            return $medicamentos;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($codigos), '?'));
            $stmt = $this->db->prepare(
                "SELECT codigo_isspol, codigo_issfa, codigo_msp, codigo_iess, nombre
                 FROM insumos
                 WHERE codigo_isspol IN ($placeholders)"
            );
            $stmt->execute($codigos);
            $referencias = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return $medicamentos;
        }

        $referenciaMap = [];
        foreach ($referencias as $referencia) {
            $codigoClave = (string) ($referencia['codigo_isspol'] ?? '');
            if ($codigoClave !== '') {
                $referenciaMap[$codigoClave] = $referencia;
            }
        }

        foreach ($medicamentos as &$medicamento) {
            $medicamento = $this->ajustarCodigoPorAfiliacion($medicamento, $afiliacion, $referenciaMap);
        }
        unset($medicamento);

        return $medicamentos;
    }

    /**
     * @param array<string, mixed>|null $protocoloExtendido
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildPreviewFallbackData(string $formId, string $hcNumber, ?array $protocoloExtendido): array
    {
        $preview = [
            'procedimientos' => [],
            'insumos' => [],
            'derechos' => [],
            'oxigeno' => [],
            'anestesia' => [],
            'reglas' => [],
        ];

        if ($formId !== '' && $hcNumber !== '') {
            try {
                $previewService = new BillingPreviewService($this->db);
                $previewData = $previewService->prepararPreviewFacturacion($formId, $hcNumber);
                if (is_array($previewData)) {
                    foreach (array_keys($preview) as $key) {
                        if (isset($previewData[$key]) && is_array($previewData[$key])) {
                            $preview[$key] = $previewData[$key];
                        }
                    }
                }
            } catch (\Throwable) {
                // El informe debe seguir abriendo aunque falle el preview remoto.
            }
        }

        if ($preview['procedimientos'] === []) {
            $preview['procedimientos'] = $this->buildProcedimientosPreviewRows($formId, $protocoloExtendido);
        }

        return $preview;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizePreviewCollection(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizePreviewProcedimientos(array $rows): array
    {
        $result = [];
        $seen = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $codigo = trim((string) ($row['proc_codigo'] ?? $row['procCodigo'] ?? ''));
            $detalle = trim((string) ($row['proc_detalle'] ?? $row['procDetalle'] ?? ''));
            if ($codigo === '' || $detalle === '') {
                continue;
            }

            $key = $codigo . '|' . $detalle;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $precio = $row['proc_precio'] ?? $row['procPrecio'] ?? null;
            $result[] = [
                'proc_codigo' => $codigo,
                'proc_detalle' => $detalle,
                'proc_precio' => $precio !== null ? (float) $precio : $this->lookupTarifa($codigo),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizePreviewDerechos(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $codigo = trim((string) ($row['codigo'] ?? ''));
            $detalle = trim((string) ($row['detalle'] ?? ''));
            if ($codigo === '' || $detalle === '') {
                continue;
            }

            $precioAfiliacion = $row['precio_afiliacion'] ?? $row['precioAfiliacion'] ?? 0;
            $result[] = [
                'codigo' => $codigo,
                'detalle' => $detalle,
                'cantidad' => (float) ($row['cantidad'] ?? 1),
                'iva' => (int) ($row['iva'] ?? 0),
                'precio_afiliacion' => (float) $precioAfiliacion,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $protocoloExtendido
     * @return array<int, array<string, mixed>>
     */
    private function buildProcedimientosPreviewRows(string $formId, ?array $protocoloExtendido): array
    {
        $rows = [];
        $seen = [];

        $json = is_array($protocoloExtendido) ? (string) ($protocoloExtendido['procedimientos'] ?? '') : '';
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $proc) {
                    if (!is_array($proc)) {
                        continue;
                    }

                    [$codigo, $detalle] = $this->parseCodigoDetalle((string) ($proc['procInterno'] ?? ''));
                    if ($codigo === '' || $detalle === '') {
                        continue;
                    }

                    $key = $codigo . '|' . $detalle;
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $rows[] = [
                        'proc_codigo' => $codigo,
                        'proc_detalle' => $detalle,
                        'proc_precio' => $this->lookupTarifa($codigo),
                    ];
                }
            }
        }

        if ($rows !== []) {
            return $rows;
        }

        $raw = is_array($protocoloExtendido) ? (string) ($protocoloExtendido['procedimiento_proyectado'] ?? '') : '';
        if ($raw === '') {
            try {
                $stmt = $this->db->prepare('SELECT procedimiento_proyectado FROM procedimiento_proyectado WHERE form_id = ? LIMIT 1');
                $stmt->execute([$formId]);
                $raw = (string) ($stmt->fetchColumn() ?: '');
            } catch (\Throwable) {
                $raw = '';
            }
        }

        [$codigo, $detalle] = $this->parseCodigoDetalle($raw);
        if ($codigo === '' || $detalle === '') {
            return $this->buildProcedimientosFromPrefactura($formId);
        }

        return [[
            'proc_codigo' => $codigo,
            'proc_detalle' => $detalle,
            'proc_precio' => $this->lookupTarifa($codigo),
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildProcedimientosFromPrefactura(string $formId): array
    {
        try {
            $stmt = $this->db->prepare(
                <<<'SQL'
                SELECT prefactura_id
                FROM prefactura_payload_audit
                WHERE form_id = ?
                  AND prefactura_id IS NOT NULL
                ORDER BY received_at DESC, id DESC
                LIMIT 1
                SQL
            );
            $stmt->execute([$formId]);
            $prefacturaId = (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Throwable) {
            return [];
        }

        if ($prefacturaId <= 0) {
            return [];
        }

        try {
            $stmt = $this->db->prepare(
                <<<'SQL'
                SELECT codigo, descripcion, proc_interno, precio_tarifado, precio_base
                FROM prefactura_detalle_procedimientos
                WHERE prefactura_id = ?
                ORDER BY posicion ASC, id ASC
                SQL
            );
            $stmt->execute([$prefacturaId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $codigo = trim((string) ($row['codigo'] ?? ''));
            $detalle = trim((string) ($row['descripcion'] ?? ''));

            if ($codigo === '' || $detalle === '') {
                [$codigo, $detalle] = $this->parseCodigoDetalle((string) ($row['proc_interno'] ?? ''));
            }

            if ($codigo === '' || $detalle === '') {
                continue;
            }

            $precio = $row['precio_tarifado'] ?? $row['precio_base'] ?? null;
            $result[] = [
                'proc_codigo' => $codigo,
                'proc_detalle' => $detalle,
                'proc_precio' => $precio !== null ? (float) $precio : $this->lookupTarifa($codigo),
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

    private function lookupTarifa(string $codigo): float
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT valor_facturar_nivel3 FROM tarifario_2014 WHERE codigo = ? OR codigo = ? LIMIT 1'
            );
            $stmt->execute([$codigo, ltrim($codigo, '0')]);
            $value = $stmt->fetchColumn();
        } catch (\Throwable) {
            $value = false;
        }

        return $value !== false ? (float) $value : 0.0;
    }

    /**
     * @param array<string, mixed> $medicamento
     * @param array<string, array<string, mixed>> $referenciaMap
     * @return array<string, mixed>
     */
    private function ajustarCodigoPorAfiliacion(array $medicamento, string $afiliacion, array $referenciaMap): array
    {
        $codigoClave = (string) ($medicamento['codigo'] ?? '');
        if ($codigoClave === '') {
            return $medicamento;
        }

        $referencia = $referenciaMap[$codigoClave] ?? null;
        if (!$referencia) {
            return $medicamento;
        }

        switch (strtoupper($afiliacion)) {
            case 'ISSFA':
                $medicamento['codigo'] = $referencia['codigo_issfa'] ?? $codigoClave;
                break;
            case 'MSP':
                $medicamento['codigo'] = $referencia['codigo_msp'] ?? $codigoClave;
                break;
            case 'IESS':
                $medicamento['codigo'] = $referencia['codigo_iess'] ?? $codigoClave;
                break;
            case 'ISSPOL':
                $medicamento['codigo'] = $referencia['codigo_isspol'] ?? $codigoClave;
                break;
            default:
                break;
        }

        $medicamento['nombre'] = $referencia['nombre'] ?? ($medicamento['nombre'] ?? null);
        return $medicamento;
    }
}
