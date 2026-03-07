<?php

namespace App\Modules\Billing\Services;

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
    /** @var array<int, string>|null */
    private ?array $formIdsFacturadosCache = null;

    public function __construct(
        private readonly PDO $db,
        private readonly BillingInformePacienteService $pacienteService
    ) {
    }

    public function getPdo(): PDO
    {
        return $this->db;
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
            if (!$this->hasCodigoDerivacion($row)) {
                $legacy = $this->obtenerDerivacionLegacyPorFormId($formId);
                if ($legacy !== []) {
                    $row = $this->mergeDerivacionRows($row, $legacy);
                }
            }
            $this->derivacionCache[$formId] = $row;
            return $row;
        }

        $fallback = $this->obtenerDerivacionLegacyPorFormId($formId);

        $this->derivacionCache[$formId] = $fallback;
        return $fallback;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hasCodigoDerivacion(array $row): bool
    {
        $codigo = trim((string) ($row['cod_derivacion'] ?? $row['codigo_derivacion'] ?? ''));
        return $codigo !== '';
    }

    /**
     * @return array<string,mixed>
     */
    private function obtenerDerivacionLegacyPorFormId(string $formId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM derivaciones_form_id WHERE form_id = ? LIMIT 1');
            $stmt->execute([$formId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
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
            'referido',
            'fecha_registro',
            'fecha_vigencia',
            'diagnostico',
            'sede',
            'parentesco',
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

        if (trim((string) ($merged['cod_derivacion'] ?? '')) === '' && !empty($merged['codigo_derivacion'])) {
            $merged['cod_derivacion'] = $merged['codigo_derivacion'];
        }
        if (trim((string) ($merged['codigo_derivacion'] ?? '')) === '' && !empty($merged['cod_derivacion'])) {
            $merged['codigo_derivacion'] = $merged['cod_derivacion'];
        }

        return $merged;
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

        $insumosConIva = [];
        $medicamentosSinIva = [];
        foreach ($insumosRaw as $insumo) {
            $esMedicamento = $insumo['es_medicamento'] ?? null;
            if ($esMedicamento === null) {
                $esMedicamento = isset($insumo['iva']) && (int) $insumo['iva'] === 0 ? 1 : 0;
            } else {
                $esMedicamento = (int) $esMedicamento;
            }

            if ($esMedicamento === 1) {
                $medicamentosSinIva[] = $insumo;
            } else {
                $insumosConIva[] = $insumo;
            }
        }

        if ($medicamentosSinIva !== []) {
            $codigos = [];
            foreach ($medicamentosSinIva as $medicamento) {
                $codigo = trim((string) ($medicamento['codigo'] ?? ''));
                if ($codigo !== '') {
                    $codigos[] = $codigo;
                }
            }
            $codigos = array_values(array_unique($codigos));

            if ($codigos !== []) {
                $placeholders = implode(',', array_fill(0, count($codigos), '?'));
                $stmt = $this->db->prepare(
                    "SELECT codigo_isspol, codigo_issfa, codigo_msp, codigo_iess, nombre
                     FROM insumos
                     WHERE codigo_isspol IN ($placeholders)"
                );
                $stmt->execute($codigos);
                $referencias = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $referenciaMap = [];
                foreach ($referencias as $referencia) {
                    $codigoClave = (string) ($referencia['codigo_isspol'] ?? '');
                    if ($codigoClave !== '') {
                        $referenciaMap[$codigoClave] = $referencia;
                    }
                }

                $afiliacion = (string) ($pacienteInfo['afiliacion'] ?? '');
                foreach ($medicamentosSinIva as &$medicamento) {
                    $medicamento = $this->ajustarCodigoPorAfiliacion($medicamento, $afiliacion, $referenciaMap);
                }
                unset($medicamento);
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
                    i.es_medicamento
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
