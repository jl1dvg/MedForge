<?php

namespace App\Modules\Cirugias\Services;

use App\Modules\Cirugias\Models\Cirugia;
use PDO;

class CirugiaService
{
    private const IESS_AFFILIATIONS = [
        'contribuyente voluntario',
        'conyuge',
        'conyuge pensionista',
        'seguro campesino',
        'seguro campesino jubilado',
        'seguro general',
        'seguro general jubilado',
        'seguro general por montepio',
        'seguro general tiempo parcial',
        'hijos dependientes',
        'sin cobertura',
    ];

    private ?string $lastError = null;
    /** @var array<string, bool> */
    private array $tableExistsCache = [];
    /** @var array<string, bool> */
    private array $columnExistsCache = [];

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

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     recordsTotal:int,
     *     recordsFiltered:int,
     *     data:array<int, array<string, mixed>>
     * }
     */
    public function obtenerCirugiasPaginadas(
        int $start,
        int $length,
        string $search,
        string $orderColumn,
        string $orderDir,
        array $filters = []
    ): array {
        $start = max(0, $start);
        $length = $length > 0 ? min($length, 500) : 25;
        $search = trim($search);

        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionLabelExpr = $this->afiliacionLabelExpr('p');
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $sedeExpr = $this->sedeExpr('pp');

        $afiliacionFilter = $this->normalizeAfiliacionFilter((string)($filters['afiliacion'] ?? ''));
        $afiliacionCategoriaFilter = $this->normalizeAfiliacionCategoriaFilter((string)($filters['afiliacion_categoria'] ?? ''));
        $sedeFilter = $this->normalizeSedeFilter((string)($filters['sede'] ?? ''));
        $fechaInicio = $this->isValidDate((string)($filters['fecha_inicio'] ?? '')) ? (string)$filters['fecha_inicio'] : '';
        $fechaFin = $this->isValidDate((string)($filters['fecha_fin'] ?? '')) ? (string)$filters['fecha_fin'] : '';

        $baseFrom = "FROM protocolo_data pr
            INNER JOIN patient_data p
                ON p.hc_number = pr.hc_number
            LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
            {$categoriaContext['join']}";

        $buildWhere = function (bool $includeSearch, array &$params) use (
            $fechaInicio,
            $fechaFin,
            $afiliacionFilter,
            $afiliacionCategoriaFilter,
            $sedeFilter,
            $afiliacionKeyExpr,
            $afiliacionLabelExpr,
            $categoriaContext,
            $sedeExpr,
            $search
        ): string {
            $clauses = ['1 = 1'];

            if ($fechaInicio !== '') {
                $clauses[] = "DATE(pr.fecha_inicio) >= :fecha_inicio";
                $params[':fecha_inicio'] = $fechaInicio;
            }

            if ($fechaFin !== '') {
                $clauses[] = "DATE(pr.fecha_inicio) <= :fecha_fin";
                $params[':fecha_fin'] = $fechaFin;
            }

            if ($afiliacionFilter !== '') {
                $clauses[] = "{$afiliacionKeyExpr} = :afiliacion_filter";
                $params[':afiliacion_filter'] = $afiliacionFilter;
            }

            if ($afiliacionCategoriaFilter !== '') {
                $clauses[] = "{$categoriaContext['expr']} = :afiliacion_categoria_filter";
                $params[':afiliacion_categoria_filter'] = $afiliacionCategoriaFilter;
            }

            if ($sedeFilter !== '') {
                $clauses[] = "{$sedeExpr} = :sede_filter";
                $params[':sede_filter'] = $sedeFilter;
            }

            if ($includeSearch && $search !== '') {
                $searchValue = '%' . $search . '%';
                $clauses[] = "(
                    pr.form_id LIKE :search_form_id
                    OR p.hc_number LIKE :search_hc_number
                    OR CONCAT_WS(' ', COALESCE(p.fname, ''), COALESCE(p.lname, ''), COALESCE(p.lname2, '')) LIKE :search_full_name
                    OR COALESCE(pr.membrete, '') LIKE :search_membrete
                    OR {$afiliacionLabelExpr} LIKE :search_afiliacion
                )";
                $params[':search_form_id'] = $searchValue;
                $params[':search_hc_number'] = $searchValue;
                $params[':search_full_name'] = $searchValue;
                $params[':search_membrete'] = $searchValue;
                $params[':search_afiliacion'] = $searchValue;
            }

            return 'WHERE ' . implode(' AND ', $clauses);
        };

        $paramsTotal = [];
        $whereTotal = $buildWhere(false, $paramsTotal);
        $stmtTotal = $this->db->prepare("SELECT COUNT(*) {$baseFrom} {$whereTotal}");
        $stmtTotal->execute($paramsTotal);
        $recordsTotal = (int)$stmtTotal->fetchColumn();

        $paramsFiltered = [];
        $whereFiltered = $buildWhere(true, $paramsFiltered);
        $stmtFiltered = $this->db->prepare("SELECT COUNT(*) {$baseFrom} {$whereFiltered}");
        $stmtFiltered->execute($paramsFiltered);
        $recordsFiltered = (int)$stmtFiltered->fetchColumn();

        $orderColumns = [
            'form_id' => 'pr.form_id',
            'hc_number' => 'p.hc_number',
            'full_name' => "CONCAT_WS(' ', COALESCE(p.fname, ''), COALESCE(p.lname, ''), COALESCE(p.lname2, ''))",
            'afiliacion' => $afiliacionLabelExpr,
            'fecha_inicio' => 'pr.fecha_inicio',
            'membrete' => 'pr.membrete',
        ];
        $orderExpr = $orderColumns[$orderColumn] ?? 'pr.fecha_inicio';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $sqlData = "SELECT
                pr.form_id,
                p.hc_number,
                p.fname,
                p.lname,
                p.lname2,
                p.afiliacion,
                {$afiliacionLabelExpr} AS afiliacion_label,
                {$categoriaContext['expr']} AS afiliacion_categoria,
                {$sedeExpr} AS sede,
                pr.fecha_inicio,
                pr.membrete,
                pr.printed,
                pr.status
            {$baseFrom}
            {$whereFiltered}
            ORDER BY {$orderExpr} {$orderDir}, pr.id DESC
            LIMIT :limit OFFSET :offset";

        $stmtData = $this->db->prepare($sqlData);
        foreach ($paramsFiltered as $key => $value) {
            $stmtData->bindValue($key, $value);
        }
        $stmtData->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmtData->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmtData->execute();

        $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        return [
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public function obtenerAfiliacionOptions(): array
    {
        $afiliacionKeyExpr = $this->afiliacionGroupKeyExpr('p');
        $afiliacionLabelExpr = $this->afiliacionLabelExpr('p');

        $sql = "SELECT
                    x.value_key,
                    MAX(x.value_label) AS value_label
                FROM (
                    SELECT
                        {$afiliacionKeyExpr} AS value_key,
                        {$afiliacionLabelExpr} AS value_label
                    FROM protocolo_data pr
                    INNER JOIN patient_data p ON p.hc_number = pr.hc_number
                ) x
                GROUP BY x.value_key
                ORDER BY value_label ASC";

        $stmt = $this->db->query($sql);

        $options = [
            ['value' => '', 'label' => 'Todas'],
            ['value' => 'iess', 'label' => 'IESS'],
        ];
        $seen = [
            '' => true,
            'iess' => true,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = trim((string)($row['value_key'] ?? ''));
            $label = trim((string)($row['value_label'] ?? ''));
            if ($value === '' || $label === '' || isset($seen[$value])) {
                continue;
            }

            $options[] = ['value' => $value, 'label' => $label];
            $seen[$value] = true;
        }

        return $options;
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public function obtenerAfiliacionCategoriaOptions(): array
    {
        $categoriaContext = $this->resolveAfiliacionCategoriaContext("COALESCE(p.afiliacion, '')", 'acm');
        $sql = "SELECT
                    {$categoriaContext['expr']} AS categoria,
                    COUNT(*) AS total
                FROM protocolo_data pr
                INNER JOIN patient_data p ON p.hc_number = pr.hc_number
                {$categoriaContext['join']}
                GROUP BY {$categoriaContext['expr']}
                ORDER BY total DESC";

        $stmt = $this->db->query($sql);

        $options = [
            ['value' => '', 'label' => 'Todas las categorias'],
            ['value' => 'publico', 'label' => 'Publica'],
            ['value' => 'privado', 'label' => 'Privada'],
        ];
        $seen = [
            '' => true,
            'publico' => true,
            'privado' => true,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = trim((string)($row['categoria'] ?? ''));
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => $this->formatCategoriaLabel($value),
            ];
            $seen[$value] = true;
        }

        return $options;
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public function obtenerSedeOptions(): array
    {
        $sedeExpr = $this->sedeExpr('pp');

        $sql = "SELECT DISTINCT {$sedeExpr} AS sede
                FROM protocolo_data pr
                LEFT JOIN procedimiento_proyectado pp
                    ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
                ORDER BY sede ASC";

        $stmt = $this->db->query($sql);
        $options = [
            ['value' => '', 'label' => 'Todas las sedes'],
            ['value' => 'MATRIZ', 'label' => 'MATRIZ'],
            ['value' => 'CEIBOS', 'label' => 'CEIBOS'],
        ];
        $seen = [
            '' => true,
            'MATRIZ' => true,
            'CEIBOS' => true,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = trim((string)($row['sede'] ?? ''));
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $options[] = ['value' => $value, 'label' => $value];
            $seen[$value] = true;
        }

        return $options;
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

    /**
     * @return array<string, mixed>
     */
    public function obtenerAuditoriaProtocolo(Cirugia $cirugia): array
    {
        $checks = [];

        $projected = $this->obtenerProcedimientoProyectadoAuditoria(
            (string) ($cirugia->form_id ?? ''),
            (string) ($cirugia->hc_number ?? '')
        );

        $projectedDoctor = trim((string) ($projected['doctor'] ?? ''));
        $surgeon = trim((string) ($cirugia->cirujano_1 ?? ''));
        if ($projectedDoctor === '') {
            $checks[] = $this->buildAuditCheck(
                'doctor_proyectado',
                'Doctor proyectado vs cirujano principal',
                'warning',
                'No se encontró doctor en procedimiento_proyectado para validar la concordancia.'
            );
        } else {
            $matches = $surgeon !== '' && $this->comparePersonNames($projectedDoctor, $surgeon);
            $checks[] = $this->buildAuditCheck(
                'doctor_proyectado',
                'Doctor proyectado vs cirujano principal',
                $matches ? 'ok' : 'error',
                $matches
                    ? 'El doctor proyectado coincide con el cirujano principal registrado.'
                    : 'El doctor proyectado no coincide con el cirujano principal registrado.',
                [
                    'proyectado' => $projectedDoctor,
                    'registrado' => $surgeon !== '' ? $surgeon : 'Sin registrar',
                ]
            );
        }

        $projectedProcedure = trim((string) ($projected['procedimiento_proyectado'] ?? ''));
        $projectedEye = $this->extractLateralidadFromProjectedProcedure($projectedProcedure);
        $protocolEye = trim((string) ($cirugia->lateralidad ?? ''));
        if ($projectedEye === '') {
            $checks[] = $this->buildAuditCheck(
                'ojo_proyectado',
                'Ojo proyectado vs lateralidad de cirugía',
                'warning',
                'No se encontró lateralidad proyectada en procedimiento_proyectado para validar la concordancia.'
            );
        } else {
            $projectedSides = $this->normalizeLateralidad($projectedEye);
            $protocolSides = $this->normalizeLateralidad($protocolEye);
            $matches = $this->lateralidadCompatible($projectedSides, $protocolSides);
            $checks[] = $this->buildAuditCheck(
                'ojo_proyectado',
                'Ojo proyectado vs lateralidad de cirugía',
                $matches ? 'ok' : 'error',
                $matches
                    ? 'La lateralidad proyectada coincide con la cirugía registrada.'
                    : 'La lateralidad proyectada no coincide con la cirugía registrada.',
                [
                    'proyectado' => $projectedEye,
                    'registrado' => $protocolEye !== '' ? $protocolEye : 'Sin registrar',
                ]
            );
        }

        $projectedLensType = $this->extractProjectedLensType($projectedProcedure);
        $operatorio = trim((string) ($cirugia->operatorio ?? ''));
        if ($projectedLensType !== null) {
            $operatorioLensType = $this->extractProjectedLensType($operatorio);
            $matches = $operatorioLensType === $projectedLensType;
            $checks[] = $this->buildAuditCheck(
                'lente_implantado',
                'Tipo de lente proyectado vs implantado',
                $matches ? 'ok' : 'error',
                $matches
                    ? 'El operatorio confirma el mismo tipo de lente proyectado.'
                    : 'El operatorio no confirma el mismo tipo de lente proyectado.',
                [
                    'proyectado' => strtoupper($projectedLensType),
                    'registrado' => $operatorioLensType !== null ? strtoupper($operatorioLensType) : 'No identificado en operatorio',
                ]
            );
        }

        $template = $this->obtenerPlantillaAuditoria((string) ($cirugia->procedimiento_id ?? ''));
        if ($template === null) {
            $checks[] = $this->buildAuditCheck(
                'plantilla',
                'Plantilla quirúrgica asociada',
                'warning',
                'No se encontró una plantilla quirúrgica asociada al protocolo para validar staff y códigos.'
            );
        } else {
            $filledRoles = $this->getFilledProtocolRoleKeys($cirugia);
            $expectedRoles = $template['roles'] ?? [];
            $missingExpectedRoles = array_values(array_diff($expectedRoles, $filledRoles));

            $checks[] = $this->buildAuditCheck(
                'staff_plantilla',
                'Staff requerido por plantilla',
                $missingExpectedRoles === [] ? 'ok' : 'error',
                $missingExpectedRoles === []
                    ? 'Se cumple el staff esperado por la plantilla.'
                    : 'Faltan roles del staff definidos en la plantilla.',
                [
                    'esperado' => count($expectedRoles),
                    'registrado' => count($filledRoles),
                    'faltantes' => array_map([$this, 'roleLabelFromKey'], $missingExpectedRoles),
                ]
            );

            $actualCodeCount = $this->countFilledProcedimientos($cirugia->procedimientos ?? null);
            $expectedCodeCount = count($template['codigos'] ?? []);
            $checks[] = $this->buildAuditCheck(
                'codigos_plantilla',
                'Códigos requeridos por plantilla',
                $expectedCodeCount === $actualCodeCount ? 'ok' : 'error',
                $expectedCodeCount === $actualCodeCount
                    ? 'La cantidad de códigos registrados coincide con la plantilla.'
                    : 'La cantidad de códigos registrados no coincide con la plantilla.',
                [
                    'esperado' => $expectedCodeCount,
                    'registrado' => $actualCodeCount,
                ]
            );
        }

        $missingFields = $this->getMissingProtocolFields($cirugia);
        $checks[] = $this->buildAuditCheck(
            'campos_obligatorios',
            'Campos obligatorios completos',
            $missingFields === [] ? 'ok' : 'error',
            $missingFields === []
                ? 'Todos los campos obligatorios del protocolo están completos.'
                : 'Faltan campos obligatorios por completar en el protocolo.',
            [
                'faltantes' => $missingFields,
            ]
        );

        $statusWeight = ['ok' => 0, 'warning' => 1, 'error' => 2];
        $overallStatus = 'ok';
        $summary = ['ok' => 0, 'warning' => 0, 'error' => 0];
        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'warning');
            $summary[$status] = ($summary[$status] ?? 0) + 1;
            if (($statusWeight[$status] ?? 0) > ($statusWeight[$overallStatus] ?? 0)) {
                $overallStatus = $status;
            }
        }

        return [
            'status' => $overallStatus,
            'summary' => $summary,
            'checks' => $checks,
            'plantilla' => $template,
            'proyectado' => [
                'doctor' => $projectedDoctor,
                'ojo' => $projectedEye,
                'procedimiento' => $projectedProcedure,
            ],
        ];
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

    public function actualizarStatus(string $formId, string $hcNumber, int $status, ?int $userId = null): bool
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'SELECT id, status, version, protocolo_firmado_por, fecha_firma
                 FROM protocolo_data
                 WHERE form_id = :form_id AND hc_number = :hc_number
                 LIMIT 1'
            );
            $stmt->execute([
                ':form_id' => $formId,
                ':hc_number' => $hcNumber,
            ]);
            $protocolo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$protocolo) {
                $this->db->rollBack();
                return false;
            }

            $protocoloId = (int) ($protocolo['id'] ?? 0);
            $currentVersion = isset($protocolo['version']) ? (int) $protocolo['version'] : 0;
            $currentStatus = isset($protocolo['status']) ? (int) $protocolo['status'] : 0;
            $needsSignature = $status === 1
                && ($currentStatus !== 1 || empty($protocolo['fecha_firma']));

            if ($status === $currentStatus && !$needsSignature) {
                $this->db->rollBack();
                return true;
            }

            $newVersion = $currentVersion;

            if ($needsSignature) {
                $newVersion = $currentVersion + 1;
                $updateSql = 'UPDATE protocolo_data
                    SET status = :status,
                        protocolo_firmado_por = :user_id,
                        fecha_firma = COALESCE(fecha_firma, NOW()),
                        version = :version
                    WHERE form_id = :form_id AND hc_number = :hc_number';
                $updateParams = [
                    ':status' => $status,
                    ':user_id' => $userId,
                    ':version' => $newVersion,
                    ':form_id' => $formId,
                    ':hc_number' => $hcNumber,
                ];
            } else {
                $updateSql = 'UPDATE protocolo_data
                    SET status = :status
                    WHERE form_id = :form_id AND hc_number = :hc_number';
                $updateParams = [
                    ':status' => $status,
                    ':form_id' => $formId,
                    ':hc_number' => $hcNumber,
                ];
            }

            $updateStmt = $this->db->prepare($updateSql);
            $ok = $updateStmt->execute($updateParams);

            if ($ok) {
                $evento = $status === 1 ? 'revisado' : 'pendiente';
                $auditStmt = $this->db->prepare(
                    'INSERT INTO protocolo_auditoria
                        (protocolo_id, form_id, hc_number, evento, status, version, usuario_id, creado_en)
                     VALUES
                        (:protocolo_id, :form_id, :hc_number, :evento, :status, :version, :usuario_id, NOW())'
                );
                $auditStmt->execute([
                    ':protocolo_id' => $protocoloId ?: null,
                    ':form_id' => $formId,
                    ':hc_number' => $hcNumber,
                    ':evento' => $evento,
                    ':status' => $status,
                    ':version' => $newVersion,
                    ':usuario_id' => $userId,
                ]);
            }

            $this->db->commit();
            return $ok;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            error_log('❌ Error al actualizar status: ' . $exception->getMessage());
            return false;
        }
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

    private function normalizeAfiliacionFilter(string $afiliacionFilter): string
    {
        $value = strtolower(trim($afiliacionFilter));
        if ($value === 'sin convenio') {
            return 'sin_convenio';
        }

        return $value;
    }

    private function normalizeAfiliacionCategoriaFilter(string $categoryFilter): string
    {
        $value = strtolower(trim($categoryFilter));
        if ($value === 'publica') {
            return 'publico';
        }
        if ($value === 'privada') {
            return 'privado';
        }

        return $value;
    }

    private function normalizeSedeFilter(string $sedeFilter): string
    {
        $value = strtolower(trim($sedeFilter));
        if ($value === '') {
            return '';
        }

        if (str_contains($value, 'ceib')) {
            return 'CEIBOS';
        }
        if (str_contains($value, 'matriz')) {
            return 'MATRIZ';
        }

        return '';
    }

    private function formatCategoriaLabel(string $key): string
    {
        return match ($key) {
            'publico' => 'Publica',
            'privado' => 'Privada',
            'particular' => 'Particular',
            'fundacional' => 'Fundacional',
            'otros' => 'Otros',
            default => ucwords(str_replace('_', ' ', $key)),
        };
    }

    private function afiliacionGroupKeyExpr(string $alias): string
    {
        $col = "LOWER(TRIM(COALESCE({$alias}.afiliacion, '')))";
        return "CASE
            WHEN {$col} IN (" . $this->iessAffiliationsSqlList() . ") THEN 'iess'
            WHEN {$col} = '' THEN 'sin_convenio'
            ELSE {$col}
        END";
    }

    private function afiliacionLabelExpr(string $alias): string
    {
        $col = "LOWER(TRIM(COALESCE({$alias}.afiliacion, '')))";
        return "CASE
            WHEN {$col} IN (" . $this->iessAffiliationsSqlList() . ") THEN 'IESS'
            WHEN {$col} = '' THEN 'Sin convenio'
            ELSE TRIM({$alias}.afiliacion)
        END";
    }

    private function iessAffiliationsSqlList(): string
    {
        return "'" . implode("','", self::IESS_AFFILIATIONS) . "'";
    }

    private function sedeExpr(string $alias): string
    {
        $rawExpr = "LOWER(TRIM(COALESCE(NULLIF({$alias}.sede_departamento, ''), NULLIF({$alias}.id_sede, ''), '')))";

        return "CASE
            WHEN {$rawExpr} LIKE '%ceib%' THEN 'CEIBOS'
            WHEN {$rawExpr} LIKE '%matriz%' THEN 'MATRIZ'
            ELSE ''
        END";
    }

    /**
     * @return array{join:string,expr:string}
     */
    private function resolveAfiliacionCategoriaContext(string $rawAffiliationExpr, string $mapAlias = 'acm'): array
    {
        $afiliacionNormExpr = $this->normalizeSqlKey($rawAffiliationExpr);
        $fallbackExpr = "CASE
            WHEN {$afiliacionNormExpr} = '' THEN 'otros'
            WHEN {$afiliacionNormExpr} LIKE '%particular%' THEN 'particular'
            WHEN {$afiliacionNormExpr} LIKE '%fundacion%' OR {$afiliacionNormExpr} LIKE '%fundacional%' THEN 'fundacional'
            WHEN {$afiliacionNormExpr} REGEXP 'iess|issfa|isspol|seguro_general|seguro_campesino|jubilado|montepio|contribuyente|voluntario|publico' THEN 'publico'
            ELSE 'privado'
        END";
        return ['join' => '', 'expr' => $fallbackExpr];
    }

    private function normalizeSqlText(string $expr): string
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$expr}, 'Á', 'A'), 'É', 'E'), 'Í', 'I'), 'Ó', 'O'), 'Ú', 'U'), 'Ñ', 'N'), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ñ', 'n'))";
    }

    private function normalizeSqlKey(string $expr): string
    {
        $normalized = $this->normalizeSqlText($expr);

        return "REPLACE(REPLACE({$normalized}, ' ', '_'), '-', '_')";
    }

    /**
     * @return array<string, mixed>
     */
    private function obtenerProcedimientoProyectadoAuditoria(string $formId, string $hcNumber): array
    {
        if (
            $formId === ''
            || !$this->tableExists('procedimiento_proyectado')
            || !$this->columnExists('procedimiento_proyectado', 'form_id')
        ) {
            return [];
        }

        $columns = ['procedimiento_proyectado'];
        foreach (['doctor', 'ojo', 'hc_number'] as $column) {
            if ($this->columnExists('procedimiento_proyectado', $column)) {
                $columns[] = $column;
            }
        }

        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM procedimiento_proyectado WHERE form_id = :form_id';
        $params = [':form_id' => $formId];

        if ($hcNumber !== '' && $this->columnExists('procedimiento_proyectado', 'hc_number')) {
            $sql .= ' AND hc_number = :hc_number';
            $params[':hc_number'] = $hcNumber;
        }

        $sql .= ' ORDER BY form_id DESC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerPlantillaAuditoria(string $procedimientoId): ?array
    {
        $procedimientoId = trim($procedimientoId);
        if (
            $procedimientoId === ''
            || !$this->tableExists('procedimientos')
            || !$this->columnExists('procedimientos', 'id')
        ) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT id, cirugia, membrete FROM procedimientos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $procedimientoId]);
        $procedimiento = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$procedimiento) {
            return null;
        }

        $codigos = [];
        if ($this->tableExists('procedimientos_codigos')) {
            $stmt = $this->db->prepare(
                'SELECT nombre FROM procedimientos_codigos WHERE procedimiento_id = :id AND TRIM(COALESCE(nombre, "")) <> ""'
            );
            $stmt->execute([':id' => $procedimientoId]);
            $codigos = array_values(array_filter(array_map(
                static fn($value): string => trim((string) $value),
                $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
            )));
        }

        $roles = [];
        if ($this->tableExists('procedimientos_tecnicos')) {
            $stmt = $this->db->prepare(
                'SELECT funcion FROM procedimientos_tecnicos WHERE procedimiento_id = :id AND TRIM(COALESCE(funcion, "")) <> ""'
            );
            $stmt->execute([':id' => $procedimientoId]);
            $roles = array_values(array_filter(array_map(
                fn($value): string => $this->normalizeRoleNameToKey((string) $value),
                $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
            )));
        }

        return [
            'id' => (string) ($procedimiento['id'] ?? ''),
            'cirugia' => trim((string) ($procedimiento['cirugia'] ?? '')),
            'membrete' => trim((string) ($procedimiento['membrete'] ?? '')),
            'codigos' => $codigos,
            'roles' => array_values(array_unique($roles)),
        ];
    }

    /**
     * @return array<string>
     */
    private function getFilledProtocolRoleKeys(Cirugia $cirugia): array
    {
        $roleMap = [
            'cirujano_1' => $cirugia->cirujano_1,
            'cirujano_2' => $cirugia->cirujano_2,
            'instrumentista' => $cirugia->instrumentista,
            'circulante' => $cirugia->circulante,
            'primer_ayudante' => $cirugia->primer_ayudante,
            'segundo_ayudante' => $cirugia->segundo_ayudante,
            'tercer_ayudante' => $cirugia->tercer_ayudante,
            'anestesiologo' => $cirugia->anestesiologo,
            'ayudante_anestesia' => $cirugia->ayudante_anestesia,
        ];

        $filled = [];
        foreach ($roleMap as $key => $value) {
            if ($this->hasMeaningfulValue($value)) {
                $filled[] = $key;
            }
        }

        return $filled;
    }

    private function countFilledProcedimientos(?string $procedimientosJson): int
    {
        $decoded = $this->decodeJsonArray($procedimientosJson);
        if ($decoded === null) {
            return 0;
        }

        $count = 0;
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $value = trim((string) ($item['codigo'] ?? $item['procInterno'] ?? $item['nombre'] ?? ''));
            if ($value !== '' && $this->normalizeComparableText($value) !== 'SELECCIONE') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<int, string>
     */
    private function getMissingProtocolFields(Cirugia $cirugia): array
    {
        $fieldMap = [
            'membrete' => 'Plantilla / membrete',
            'fecha_inicio' => 'Fecha de inicio',
            'hora_inicio' => 'Hora de inicio',
            'hora_fin' => 'Hora de fin',
            'lateralidad' => 'Lateralidad',
            'tipo_anestesia' => 'Tipo de anestesia',
            'cirujano_1' => 'Cirujano principal',
            'dieresis' => 'Diéresis',
            'exposicion' => 'Exposición',
            'hallazgo' => 'Hallazgo',
            'operatorio' => 'Operatorio',
            'complicaciones_operatorio' => 'Comentario / complicaciones',
            'datos_cirugia' => 'Datos de cirugía',
            'diagnosticos' => 'Diagnósticos',
            'procedimientos' => 'Procedimientos',
        ];

        $missing = [];
        foreach ($fieldMap as $field => $label) {
            $value = $cirugia->{$field};
            if (!$this->hasMeaningfulValue($value)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function buildAuditCheck(string $code, string $title, string $status, string $message, array $details = []): array
    {
        return [
            'code' => $code,
            'title' => $title,
            'status' => $status,
            'message' => $message,
            'details' => $details,
        ];
    }

    private function hasMeaningfulValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return false;
        }

        $normalized = strtoupper($this->normalizeComparableText($trimmed));
        if (in_array($normalized, ['UNDEFINED', 'NULL', 'CENTER', 'SELECCIONE', '[]', '{}'], true)) {
            return false;
        }

        if (($trimmed[0] ?? '') === '[' || ($trimmed[0] ?? '') === '{') {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return is_array($decoded) ? $decoded !== [] : true;
            }
        }

        return true;
    }

    private function normalizeComparableText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }

        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function comparePersonNames(string $left, string $right): bool
    {
        $leftNormalized = $this->normalizeComparableText($left);
        $rightNormalized = $this->normalizeComparableText($right);

        if ($leftNormalized === '' || $rightNormalized === '') {
            return false;
        }

        if ($leftNormalized === $rightNormalized) {
            return true;
        }

        return $this->nameTokens($leftNormalized) === $this->nameTokens($rightNormalized);
    }

    /**
     * @return array<int, string>
     */
    private function nameTokens(string $normalizedName): array
    {
        $tokens = array_values(array_filter(explode(' ', $normalizedName), static fn(string $token): bool => $token !== ''));
        sort($tokens);

        return $tokens;
    }

    private function extractLateralidadFromProjectedProcedure(string $procedimiento): string
    {
        $normalized = $this->normalizeComparableText($procedimiento);
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/(^|\s)AMBOS($|\s)|(^|\s)BILATERAL($|\s)/', $normalized)) {
            return 'AMBOS';
        }

        if (preg_match('/(^|\s)DERECHO($|\s)/', $normalized)) {
            return 'DERECHO';
        }

        if (preg_match('/(^|\s)IZQUIERDO($|\s)/', $normalized)) {
            return 'IZQUIERDO';
        }

        return '';
    }

    private function extractProjectedLensType(string $text): ?string
    {
        $normalized = $this->normalizeComparableText($text);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/(^|\s)MULTIFOCAL($|\s)/', $normalized)) {
            return 'multifocal';
        }

        if (preg_match('/(^|\s)TORICO($|\s)/', $normalized)) {
            return 'torico';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeLateralidad(string $raw): array
    {
        $normalized = $this->normalizeComparableText($raw);
        if ($normalized === '') {
            return [];
        }

        $result = [];
        $map = [
            'OD' => ['OD', 'DERECHO', 'D'],
            'OI' => ['OI', 'IZQUIERDO', 'I'],
            'AO' => ['AO', 'AMBOS', 'BILATERAL', 'OU'],
        ];

        foreach ($map as $canonical => $variants) {
            foreach ($variants as $variant) {
                if (preg_match('/(^|\s)' . preg_quote($variant, '/') . '($|\s)/', $normalized)) {
                    if ($canonical === 'AO') {
                        $result['OD'] = true;
                        $result['OI'] = true;
                    } else {
                        $result[$canonical] = true;
                    }
                }
            }
        }

        return array_keys($result);
    }

    /**
     * @param array<int, string> $projectedSides
     * @param array<int, string> $protocolSides
     */
    private function lateralidadCompatible(array $projectedSides, array $protocolSides): bool
    {
        if ($projectedSides === [] || $protocolSides === []) {
            return false;
        }

        return array_intersect($projectedSides, $protocolSides) !== [];
    }

    private function normalizeRoleNameToKey(string $role): string
    {
        $role = $this->normalizeComparableText($role);

        return match ($role) {
            'CIRUJANO 1' => 'cirujano_1',
            'CIRUJANO 2' => 'cirujano_2',
            'INSTRUMENTISTA' => 'instrumentista',
            'CIRCULANTE' => 'circulante',
            'PRIMER AYUDANTE' => 'primer_ayudante',
            'SEGUNDO AYUDANTE' => 'segundo_ayudante',
            'TERCER AYUDANTE' => 'tercer_ayudante',
            'ANESTESIOLOGO' => 'anestesiologo',
            'AYUDANTE ANESTESIOLOGO', 'AYUDANTE ANESTESIA' => 'ayudante_anestesia',
            default => strtolower(str_replace(' ', '_', $role)),
        };
    }

    private function roleLabelFromKey(string $key): string
    {
        return match ($key) {
            'cirujano_1' => 'Cirujano 1',
            'cirujano_2' => 'Cirujano 2',
            'instrumentista' => 'Instrumentista',
            'circulante' => 'Circulante',
            'primer_ayudante' => 'Primer ayudante',
            'segundo_ayudante' => 'Segundo ayudante',
            'tercer_ayudante' => 'Tercer ayudante',
            'anestesiologo' => 'Anestesiólogo',
            'ayudante_anestesia' => 'Ayudante anestesia',
            default => ucwords(str_replace('_', ' ', $key)),
        };
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table"
        );
        $stmt->execute([':table' => $table]);
        $exists = (int)$stmt->fetchColumn() > 0;
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
            "SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table
               AND COLUMN_NAME = :column"
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);
        $exists = (int)$stmt->fetchColumn() > 0;
        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    private function isValidDate(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year);
    }
}
