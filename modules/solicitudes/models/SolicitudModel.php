<?php

namespace Models;

use PDO;
use DateTime;
use DateTimeImmutable;
use Modules\CRM\Services\LeadConfigurationService;

if (class_exists(__NAMESPACE__ . '\\SolicitudModel', false)) {
    return;
}

class SolicitudModel
{
    protected $db;
    /** @var array<string, bool>|null */
    private ?array $solicitudColumns = null;
    /** @var array<string, bool> */
    private array $tableExistsCache = [];
    /** @var array<string, array<string, bool>> */
    private array $tableColumnsCache = [];
    private const META_CIRUGIA_CONFIRMADA_KEYS = [
        'cirugia_confirmada_form_id',
        'cirugia_confirmada_hc_number',
        'cirugia_confirmada_fecha_inicio',
        'cirugia_confirmada_lateralidad',
        'cirugia_confirmada_membrete',
        'cirugia_confirmada_by',
        'cirugia_confirmada_at',
    ];

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function obtenerEstadosPorHc(string $hcNumber): array
    {
        $pedidoOrigenIdExpr = $this->selectSolicitudColumn('pedido_origen_id');
        $pedidoOrigenExpr = $this->selectSolicitudColumn('pedido_origen');
        $derivacionPedidoIdExpr = $this->selectSolicitudColumn('derivacion_pedido_id');
        $derivacionLateralidadExpr = $this->selectSolicitudColumn('derivacion_lateralidad');
        $derivacionPrefacturaExpr = $this->selectSolicitudColumn('derivacion_prefactura');

        $sql = "
            SELECT 
                sp.id,
                sp.hc_number,
                sp.form_id,
                sp.tipo,
                sp.afiliacion,
                sp.procedimiento,
                sp.doctor,
                sp.fecha,
                sp.duracion,
                sp.ojo,
                {$pedidoOrigenIdExpr},
                {$pedidoOrigenExpr},
                sp.prioridad,
                sp.producto,
                sp.observacion,
                sp.lente_id,
                sp.lente_nombre,
                sp.lente_poder,
                sp.lente_observacion,
                sp.incision,
                sp.estado,
                {$derivacionPedidoIdExpr},
                {$derivacionLateralidadExpr},
                {$derivacionPrefacturaExpr},
                sp.created_at
            FROM solicitud_procedimiento sp
            WHERE sp.hc_number = :hcNumber
            ORDER BY sp.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':hcNumber', $hcNumber);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Actualiza parcialmente una solicitud para reflejar cambios de estado o datos de agenda.
     */
    public function actualizarSolicitudParcial(int $id, array $campos): array
    {
        $limpiar = function ($v) {
            if (is_string($v)) {
                $v = trim($v);
                if ($v === '' || strtoupper($v) === 'SELECCIONE') {
                    return null;
                }
                return $v;
            }
            return $v === '' ? null : $v;
        };

        $normFecha = function ($v) {
            $v = is_string($v) ? trim($v) : $v;
            if (!$v) return null;
            if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v)) {
                return $v;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $v)) {
                $format = strlen($v) === 19 ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i';
                $dt = \DateTime::createFromFormat($format, $v);
                if ($dt instanceof \DateTime) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }
            $fmt = ['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'm/d/Y H:i', 'm-d-Y H:i'];
            foreach ($fmt as $f) {
                $dt = \DateTime::createFromFormat($f, $v);
                if ($dt instanceof \DateTime) {
                    return $dt->format(strlen($f) >= 10 ? 'Y-m-d H:i:s' : 'Y-m-d');
                }
            }
            return null;
        };

        $permitidos = [
            'estado', 'doctor', 'fecha', 'prioridad', 'observacion',
            'procedimiento', 'producto', 'ojo', 'afiliacion', 'duracion',
            'lente_id', 'lente_nombre', 'lente_poder', 'lente_observacion',
            'incision',
        ];

        $set = [];
        $params = [':id' => $id];

        foreach ($permitidos as $campo) {
            if (!array_key_exists($campo, $campos)) {
                continue;
            }

            $valor = $campos[$campo];
            if ($campo === 'fecha') {
                $valor = $normFecha($valor);
            } elseif ($campo === 'prioridad') {
                $valor = is_string($valor) ? strtoupper(trim($valor)) : $valor;
            } elseif ($campo === 'ojo' && is_array($valor)) {
                $valor = implode(',', array_filter(array_map($limpiar, $valor)));
            } else {
                $valor = $limpiar($valor);
            }

            $set[] = "{$campo} = :{$campo}";
            $params[":{$campo}"] = $valor;
        }

        if (empty($set)) {
            return ['success' => false, 'message' => 'No se enviaron campos para actualizar'];
        }

        $sql = 'UPDATE solicitud_procedimiento SET ' . implode(', ', $set) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->rowCount();
        error_log("Solicitud update rows affected: {$rows}");

        $stmtDatos = $this->db->prepare("
            SELECT sp.*, COALESCE(cd.fecha, sp.fecha) AS fecha_programada
            FROM solicitud_procedimiento sp
            LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
            WHERE sp.id = :id
        ");
        $stmtDatos->execute([':id' => $id]);
        $row = $stmtDatos->fetch(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'message' => 'Solicitud actualizada correctamente',
            'rows_affected' => $rows,
            'data' => $row ?: null,
        ];
    }

    public function fetchSolicitudesConDetallesFiltrado(array $filtros = []): array
    {
        $pedidoOrigenIdExpr = $this->selectSolicitudColumn('pedido_origen_id');
        $pedidoOrigenExpr = $this->selectSolicitudColumn('pedido_origen');
        $secuenciaExpr = $this->selectSolicitudColumn('secuencia');
        $turnoExpr = $this->selectSolicitudColumn('turno');
        $derivacionPedidoIdExpr = $this->selectSolicitudColumn('derivacion_pedido_id');
        $derivacionLateralidadExpr = $this->selectSolicitudColumn('derivacion_lateralidad');
        $hasDerivacionPedidoId = $this->hasSolicitudColumn('derivacion_pedido_id');
        $hasDerivacionVigenciaSel = $this->hasSolicitudColumn('derivacion_fecha_vigencia_sel');
        $toUnicodeExpr = static fn(string $expr): string => "CAST({$expr} AS CHAR) COLLATE utf8mb4_unicode_ci";
        $nonEmptyExpr = static fn(string $expr): string => "NULLIF(TRIM(" . $toUnicodeExpr($expr) . "), '' COLLATE utf8mb4_unicode_ci)";
        $normalizedDateExpr = static fn(string $expr): string => "NULLIF(NULLIF(NULLIF(TRIM(" . $toUnicodeExpr($expr) . "), '' COLLATE utf8mb4_unicode_ci), '0000-00-00' COLLATE utf8mb4_unicode_ci), '0000-00-00 00:00:00' COLLATE utf8mb4_unicode_ci)";
        $normalizedVigenciaRawExpr = $normalizedDateExpr('fecha_vigencia');

        $derivacionSelectionJoin = '';
        $derivacionSelectionPedidoExpr = 'NULL';
        $derivacionSelectionVigenciaExpr = 'NULL';

        if ($hasDerivacionPedidoId || $hasDerivacionVigenciaSel) {
            $whereParts = [];
            if ($hasDerivacionPedidoId) {
                $whereParts[] = '(' . $nonEmptyExpr('s2.`derivacion_pedido_id`') . ' IS NOT NULL)';
            }
            if ($hasDerivacionVigenciaSel) {
                $whereParts[] = '(' . $normalizedDateExpr('s2.`derivacion_fecha_vigencia_sel`') . ' IS NOT NULL)';
            }

            $whereLatestSelection = implode(' OR ', $whereParts);
            $pedidoSelect = $hasDerivacionPedidoId ? 's1.`derivacion_pedido_id`' : 'NULL';
            $vigenciaSelect = $hasDerivacionVigenciaSel ? 's1.`derivacion_fecha_vigencia_sel`' : 'NULL';

            $derivacionSelectionJoin = "
            LEFT JOIN (
                SELECT
                    s1.form_id,
                    s1.hc_number,
                    {$pedidoSelect} AS pedido_id_sel,
                    {$vigenciaSelect} AS vigencia_sel
                FROM solicitud_procedimiento s1
                INNER JOIN (
                    SELECT form_id, hc_number, MAX(id) AS max_id
                    FROM solicitud_procedimiento s2
                    WHERE {$whereLatestSelection}
                    GROUP BY form_id, hc_number
                ) latest_sel ON latest_sel.max_id = s1.id
            ) derivacion_sel ON " . $toUnicodeExpr('derivacion_sel.form_id') . " = " . $toUnicodeExpr('sp.form_id') . " AND " . $toUnicodeExpr('derivacion_sel.hc_number') . " = " . $toUnicodeExpr('sp.hc_number');

            if ($hasDerivacionPedidoId) {
                $derivacionSelectionPedidoExpr = $nonEmptyExpr('derivacion_sel.pedido_id_sel');
            }

            if ($hasDerivacionVigenciaSel) {
                $derivacionSelectionVigenciaExpr = $normalizedDateExpr('derivacion_sel.vigencia_sel');
            }
        }

        $lookupParts = ['sp.form_id'];
        if ($hasDerivacionPedidoId) {
            $lookupParts = [
                $nonEmptyExpr('sp.`derivacion_pedido_id`'),
            ];
            if ($derivacionSelectionPedidoExpr !== 'NULL') {
                $lookupParts[] = $derivacionSelectionPedidoExpr;
            }
            $lookupParts[] = 'sp.form_id';
        }
        $derivacionLookupFormExpr = $toUnicodeExpr('COALESCE(' . implode(', ', $lookupParts) . ')');

        $joinedVigenciaMaxExpr = "NULLIF(
            GREATEST(
                COALESCE(" . $normalizedDateExpr('derivacion_nueva.fecha_vigencia') . ", '1000-01-01' COLLATE utf8mb4_unicode_ci),
                COALESCE(" . $normalizedDateExpr('derivacion_legacy.fecha_vigencia') . ", '1000-01-01' COLLATE utf8mb4_unicode_ci)
            ),
            '1000-01-01' COLLATE utf8mb4_unicode_ci
        )";

        $vigenciaParts = [$joinedVigenciaMaxExpr];
        if ($hasDerivacionVigenciaSel) {
            array_unshift($vigenciaParts, $normalizedDateExpr('sp.`derivacion_fecha_vigencia_sel`'));
        }
        if ($derivacionSelectionVigenciaExpr !== 'NULL') {
            array_splice($vigenciaParts, 1, 0, [$derivacionSelectionVigenciaExpr]);
        }
        $derivacionVigenciaExpr = 'COALESCE(' . implode(', ', $vigenciaParts) . ')';
        $doctorAvatarMatchCondition = $this->buildDoctorNameMatchCondition('sp.doctor', 'u.nombre');

        $sql = "SELECT
                sp.id,
                sp.hc_number,
                sp.form_id,
                TRIM(CONCAT_WS(' ',
                  NULLIF(TRIM(pd.fname), ''),
                  NULLIF(TRIM(pd.mname), ''),
                  NULLIF(TRIM(pd.lname), ''),
                  NULLIF(TRIM(pd.lname2), '')
                )) AS full_name, 
                sp.tipo,
                pd.afiliacion,
                pd.celular AS paciente_celular,
                sp.procedimiento,
                sp.doctor,
                sp.estado,
                cd.fecha,
                COALESCE(cd.fecha, sp.fecha, sp.created_at) AS fecha_programada,
                sp.duracion,
                sp.ojo,
                {$pedidoOrigenIdExpr},
                {$pedidoOrigenExpr},
                sp.prioridad,
                sp.producto,
                sp.observacion,
                {$secuenciaExpr},
                sp.created_at,
                pd.fecha_caducidad,
                cd.diagnosticos,
                {$turnoExpr},
                {$derivacionPedidoIdExpr},
                {$derivacionLateralidadExpr},
                detalles.pipeline_stage AS crm_pipeline_stage,
                detalles.fuente AS crm_fuente,
                detalles.contacto_email AS crm_contacto_email,
                detalles.contacto_telefono AS crm_contacto_telefono,
                detalles.responsable_id AS crm_responsable_id,
                responsable.nombre AS crm_responsable_nombre,
                responsable.profile_photo AS crm_responsable_avatar,
                (
                    SELECT u.profile_photo
                    FROM users u
                    WHERE u.profile_photo IS NOT NULL
                      AND u.profile_photo <> ''
                      AND {$doctorAvatarMatchCondition}
                    ORDER BY u.id ASC
                    LIMIT 1
                ) AS doctor_avatar,
                COALESCE(notas.total_notas, 0) AS crm_total_notas,
                COALESCE(adjuntos.total_adjuntos, 0) AS crm_total_adjuntos,
                COALESCE(tareas.tareas_pendientes, 0) AS crm_tareas_pendientes,
                COALESCE(tareas.tareas_total, 0) AS crm_tareas_total,
                tareas.proximo_vencimiento AS crm_proximo_vencimiento,
                {$derivacionVigenciaExpr} AS derivacion_fecha_vigencia
            FROM solicitud_procedimiento sp
            INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
            LEFT JOIN consulta_data cd ON sp.hc_number = cd.hc_number AND sp.form_id = cd.form_id
            {$derivacionSelectionJoin}
            LEFT JOIN solicitud_crm_detalles detalles ON detalles.solicitud_id = sp.id
            LEFT JOIN users responsable ON detalles.responsable_id = responsable.id
            LEFT JOIN (
                SELECT
                    iess_form_id,
                    MAX({$normalizedVigenciaRawExpr}) AS fecha_vigencia
                FROM derivaciones_forms
                GROUP BY iess_form_id
            ) derivacion_nueva ON {$toUnicodeExpr('derivacion_nueva.iess_form_id')} = {$derivacionLookupFormExpr}
            LEFT JOIN (
                SELECT
                    form_id,
                    hc_number,
                    MAX({$normalizedVigenciaRawExpr}) AS fecha_vigencia
                FROM derivaciones_form_id
                GROUP BY form_id, hc_number
            ) derivacion_legacy ON {$toUnicodeExpr('derivacion_legacy.form_id')} = {$derivacionLookupFormExpr} AND {$toUnicodeExpr('derivacion_legacy.hc_number')} = {$toUnicodeExpr('sp.hc_number')}
            LEFT JOIN (
                SELECT solicitud_id, COUNT(*) AS total_notas
                FROM solicitud_crm_notas
                GROUP BY solicitud_id
            ) notas ON notas.solicitud_id = sp.id
            LEFT JOIN (
                SELECT solicitud_id, COUNT(*) AS total_adjuntos
                FROM solicitud_crm_adjuntos
                GROUP BY solicitud_id
            ) adjuntos ON adjuntos.solicitud_id = sp.id
            LEFT JOIN (
                SELECT solicitud_id,
                       COUNT(*) AS tareas_total,
                       SUM(CASE WHEN estado IN ('pendiente','en_progreso') THEN 1 ELSE 0 END) AS tareas_pendientes,
                       MIN(CASE WHEN estado IN ('pendiente','en_progreso') THEN due_date END) AS proximo_vencimiento
                FROM solicitud_crm_tareas
                GROUP BY solicitud_id
            ) tareas ON tareas.solicitud_id = sp.id
            WHERE sp.procedimiento IS NOT NULL
              AND sp.procedimiento <> ''
              AND sp.procedimiento != 'SELECCIONE' 
              AND sp.doctor != 'SELECCIONE'";

        [$filterSql, $params] = $this->buildSolicitudesFilterClause($filtros);
        $sql .= $filterSql;

        $sql .= " ORDER BY COALESCE(cd.fecha, sp.fecha, sp.created_at) DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildSolicitudesFilterClause(array $filtros): array
    {
        $sql = '';
        $params = [];

        if (!empty($filtros['afiliacion'])) {
            $sql .= " AND pd.afiliacion COLLATE utf8mb4_unicode_ci LIKE ?";
            $params[] = '%' . trim((string)$filtros['afiliacion']) . '%';
        }

        if (!empty($filtros['doctor'])) {
            $sql .= " AND sp.doctor COLLATE utf8mb4_unicode_ci LIKE ?";
            $params[] = '%' . trim((string)$filtros['doctor']) . '%';
        }

        if (!empty($filtros['prioridad'])) {
            $sql .= " AND sp.prioridad COLLATE utf8mb4_unicode_ci = ?";
            $params[] = trim((string)$filtros['prioridad']);
        }

        $dateRange = $this->resolveDateRange($filtros);
        if ($dateRange['from'] && $dateRange['to']) {
            $sql .= " AND DATE(COALESCE(cd.fecha, sp.fecha, sp.created_at)) BETWEEN ? AND ?";
            $params[] = $dateRange['from'];
            $params[] = $dateRange['to'];
        } elseif ($dateRange['from']) {
            $sql .= " AND DATE(COALESCE(cd.fecha, sp.fecha, sp.created_at)) >= ?";
            $params[] = $dateRange['from'];
        } elseif ($dateRange['to']) {
            $sql .= " AND DATE(COALESCE(cd.fecha, sp.fecha, sp.created_at)) <= ?";
            $params[] = $dateRange['to'];
        }

        return [$sql, $params];
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array{from: string|null, to: string|null}
     */
    private function resolveDateRange(array $filtros): array
    {
        $from = $this->normalizeDateValue($filtros['date_from'] ?? null);
        $to = $this->normalizeDateValue($filtros['date_to'] ?? null);

        if (!$from && !$to && !empty($filtros['fechaTexto']) && str_contains((string)$filtros['fechaTexto'], ' - ')) {
            [$inicio, $fin] = explode(' - ', (string)$filtros['fechaTexto']);
            $from = $this->normalizeDateValue($inicio);
            $to = $this->normalizeDateValue($fin);
        }

        return [
            'from' => $from,
            'to' => $to,
        ];
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $date = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
        } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
            $date = DateTime::createFromFormat('d-m-Y', $value);
        } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            $date = DateTime::createFromFormat('d/m/Y', $value);
        } else {
            try {
                $date = new DateTime($value);
            } catch (\Exception $e) {
                $date = null;
            }
        }

        return $date ? $date->format('Y-m-d') : null;
    }

    public function fetchTurneroSolicitudes(array $estados = []): array
    {
        $estados = array_values(array_filter(array_map('trim', $estados)));
        if (empty($estados)) {
            $estados = ['Llamado', 'En atención'];
        }

        $placeholders = implode(', ', array_fill(0, count($estados), '?'));

        $sql = "SELECT
                sp.id,
                sp.hc_number,
                sp.form_id,
                TRIM(CONCAT_WS(' ',
                  NULLIF(TRIM(pd.fname), ''),
                  NULLIF(TRIM(pd.mname), ''),
                  NULLIF(TRIM(pd.lname), ''),
                  NULLIF(TRIM(pd.lname2), '')
                )) AS full_name,
                sp.estado,
                sp.prioridad,
                sp.procedimiento,
                sp.created_at,
                sp.turno
            FROM solicitud_procedimiento sp
            INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
            WHERE sp.estado IN ($placeholders)
            ORDER BY CASE WHEN sp.turno IS NULL THEN 1 ELSE 0 END,
                     sp.turno DESC,
                     sp.created_at DESC,
                     sp.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($estados);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerDerivacionPorFormId($form_id)
    {
        $stmt = $this->db->prepare(
            "SELECT
                rf.id AS derivacion_id,
                r.referral_code AS cod_derivacion,
                r.referral_code AS codigo_derivacion,
                f.iess_form_id AS form_id,
                f.hc_number,
                f.fecha_creacion,
                f.fecha_registro,
                COALESCE(r.valid_until, f.fecha_vigencia) AS fecha_vigencia,
                f.referido,
                f.diagnostico,
                f.sede,
                f.parentesco,
                f.archivo_derivacion_path
             FROM derivaciones_forms f
             LEFT JOIN derivaciones_referral_forms rf ON rf.form_id = f.id
             LEFT JOIN derivaciones_referrals r ON r.id = rf.referral_id
             WHERE f.iess_form_id = ?
             ORDER BY COALESCE(rf.linked_at, f.updated_at) DESC, f.id DESC
             LIMIT 1"
        );
        $stmt->execute([$form_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $row['id'] = $row['derivacion_id'] ?? null;
            return $row;
        }

        $stmtLegacy = $this->db->prepare("SELECT * FROM derivaciones_form_id WHERE form_id = ?");
        $stmtLegacy->execute([$form_id]);
        return $stmtLegacy->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerDerivacionPreseleccion(int $solicitudId): ?array
    {
        $codigoExpr = $this->selectSolicitudColumn('derivacion_codigo');
        $pedidoExpr = $this->selectSolicitudColumn('derivacion_pedido_id');
        $lateralidadExpr = $this->selectSolicitudColumn('derivacion_lateralidad');
        $vigenciaExpr = $this->selectSolicitudColumn('derivacion_fecha_vigencia_sel');
        $prefacturaExpr = $this->selectSolicitudColumn('derivacion_prefactura');

        $stmt = $this->db->prepare(
            "SELECT
                {$codigoExpr},
                {$pedidoExpr},
                {$lateralidadExpr},
                {$vigenciaExpr},
                {$prefacturaExpr}
             FROM solicitud_procedimiento sp
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $solicitudId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $row;
    }

    public function obtenerDerivacionPreseleccionPorFormHc(string $formId, string $hcNumber): ?array
    {
        $codigoExpr = $this->selectSolicitudColumn('derivacion_codigo');
        $pedidoExpr = $this->selectSolicitudColumn('derivacion_pedido_id');
        $lateralidadExpr = $this->selectSolicitudColumn('derivacion_lateralidad');
        $vigenciaExpr = $this->selectSolicitudColumn('derivacion_fecha_vigencia_sel');
        $prefacturaExpr = $this->selectSolicitudColumn('derivacion_prefactura');

        $stmt = $this->db->prepare(
            "SELECT
                id,
                {$codigoExpr},
                {$pedidoExpr},
                {$lateralidadExpr},
                {$vigenciaExpr},
                {$prefacturaExpr}
             FROM solicitud_procedimiento sp
             WHERE form_id = :form_id
               AND hc_number = :hc
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':hc' => $hcNumber,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return $row;
    }

    public function guardarDerivacionPreseleccion(int $solicitudId, array $data): bool
    {
        $set = [];
        $params = [':id' => $solicitudId];

        if ($this->hasSolicitudColumn('derivacion_codigo')) {
            $set[] = 'derivacion_codigo = :codigo';
            $params[':codigo'] = $data['derivacion_codigo'] ?? null;
        }
        if ($this->hasSolicitudColumn('derivacion_pedido_id')) {
            $set[] = 'derivacion_pedido_id = :pedido_id';
            $params[':pedido_id'] = $data['derivacion_pedido_id'] ?? null;
        }
        if ($this->hasSolicitudColumn('derivacion_lateralidad')) {
            $set[] = 'derivacion_lateralidad = :lateralidad';
            $params[':lateralidad'] = $data['derivacion_lateralidad'] ?? null;
        }
        if ($this->hasSolicitudColumn('derivacion_fecha_vigencia_sel')) {
            $set[] = 'derivacion_fecha_vigencia_sel = :vigencia';
            $params[':vigencia'] = $data['derivacion_fecha_vigencia_sel'] ?? null;
        }
        if ($this->hasSolicitudColumn('derivacion_prefactura')) {
            $set[] = 'derivacion_prefactura = :prefactura';
            $params[':prefactura'] = $data['derivacion_prefactura'] ?? null;
        }

        if ($set === []) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE solicitud_procedimiento SET ' . implode(', ', $set) . ' WHERE id = :id'
        );
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function obtenerSolicitudIdPorFormHc(string $formId, string $hcNumber): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM solicitud_procedimiento
             WHERE form_id = :form_id
               AND hc_number = :hc
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([
            ':form_id' => $formId,
            ':hc' => $hcNumber,
        ]);
        $row = $stmt->fetchColumn();
        if ($row === false) {
            return null;
        }
        return (int) $row;
    }

    public function guardarSolicitudesBatchUpsert(array $data): array
    {
        $hcNumber = $data['hcNumber'] ?? $data['hc_number'] ?? null;

        if (!$hcNumber || !isset($data['form_id'], $data['solicitudes']) || !is_array($data['solicitudes'])) {
            return ['success' => false, 'message' => 'Datos no válidos o incompletos'];
        }

        // Helper closures for limpieza/normalización
        $clean = function ($v) {
            if (is_string($v)) {
                $v = trim($v);
                if ($v === '' || in_array(mb_strtoupper($v), ['SELECCIONE', 'NINGUNO'], true)) {
                    return null;
                }
                return $v;
            }
            return $v === '' ? null : $v;
        };

        $normPrioridad = function ($v) {
            $v = is_string($v) ? mb_strtoupper(trim($v)) : $v;
            return ($v === 'SI' || $v === 1 || $v === '1' || $v === true) ? 'SI' : 'NO';
        };

        $normFecha = function ($v) {
            $v = is_string($v) ? trim($v) : $v;
            if (!$v) return null;

            // ISO
            if (preg_match('/^\\d{4}-\\d{2}-\\d{2}( \\d{2}:\\d{2}(:\\d{2})?)?$/', $v)) {
                return $v;
            }

            // ISO con T
            if (preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}(:\\d{2})?$/', $v)) {
                $format = strlen($v) === 19 ? 'Y-m-d\\TH:i:s' : 'Y-m-d\\TH:i';
                $dt = DateTime::createFromFormat($format, $v);
                if ($dt instanceof DateTime) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }

            $fmt = ['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'm/d/Y H:i', 'm-d-Y H:i'];
            foreach ($fmt as $f) {
                $dt = DateTime::createFromFormat($f, $v);
                if ($dt instanceof DateTime) {
                    return $dt->format(strlen($f) >= 10 ? 'Y-m-d H:i:s' : 'Y-m-d');
                }
            }

            return null;
        };

        // Validar procedimiento obligatorio
        $missingProcedimientos = [];
        foreach ($data['solicitudes'] as $idx => $solicitud) {
            $procedimientoVal = $clean($solicitud['procedimiento'] ?? null);
            if ($procedimientoVal === null) {
                $missingProcedimientos[] = $solicitud['secuencia'] ?? ($idx + 1);
            }
        }
        if ($missingProcedimientos) {
            return [
                'success' => false,
                'message' => 'El procedimiento es obligatorio en todas las solicitudes (faltante en: ' . implode(', ', $missingProcedimientos) . ')',
            ];
        }

        $sql = "INSERT INTO solicitud_procedimiento
        (hc_number, form_id, secuencia, tipo, afiliacion, procedimiento, doctor, fecha, duracion, ojo, prioridad, producto, observacion, sesiones, lente_id, lente_nombre, lente_poder, lente_observacion, incision)
        VALUES (:hc, :form_id, :secuencia, :tipo, :afiliacion, :procedimiento, :doctor, :fecha, :duracion, :ojo, :prioridad, :producto, :observacion, :sesiones, :lente_id, :lente_nombre, :lente_poder, :lente_observacion, :incision)
        ON DUPLICATE KEY UPDATE
            tipo = VALUES(tipo),
            afiliacion = VALUES(afiliacion),
            procedimiento = VALUES(procedimiento),
            doctor = VALUES(doctor),
            fecha = VALUES(fecha),
            duracion = VALUES(duracion),
            ojo = VALUES(ojo),
            prioridad = VALUES(prioridad),
            producto = VALUES(producto),
            observacion = VALUES(observacion),
            sesiones = VALUES(sesiones),
            lente_id = VALUES(lente_id),
            lente_nombre = VALUES(lente_nombre),
            lente_poder = VALUES(lente_poder),
            lente_observacion = VALUES(lente_observacion),
            incision = VALUES(incision)";

        $stmt = $this->db->prepare($sql);

        foreach ($data['solicitudes'] as $solicitud) {
            $secuencia = $solicitud['secuencia'] ?? null;
            $tipo = $clean($solicitud['tipo'] ?? null);
            $afiliacion = $clean($solicitud['afiliacion'] ?? null);
            $procedimiento = $clean($solicitud['procedimiento'] ?? null);
            $doctor = $clean($solicitud['doctor'] ?? null);
            $fecha = $normFecha($solicitud['fecha'] ?? null);
            $duracion = $clean($solicitud['duracion'] ?? null);
            $prioridad = $normPrioridad($solicitud['prioridad'] ?? 'NO');
            $producto = $clean($solicitud['producto'] ?? null);
            $observacion = $clean($solicitud['observacion'] ?? null);
            $sesiones = $clean($solicitud['sesiones'] ?? null);

            // ojo: string o array
            $ojoVal = $solicitud['ojo'] ?? null;
            if (is_array($ojoVal)) {
                $ojoVal = implode(',', array_values(array_filter(array_map($clean, $ojoVal))));
            } else {
                $ojoVal = $clean($ojoVal);
            }

            // LIO/Incisión
            $lenteId = $clean($solicitud['lente_id'] ?? null);
            $lenteNombre = $clean($solicitud['lente_nombre'] ?? null);
            $lentePoder = $clean($solicitud['lente_poder'] ?? null);
            $lenteObs = $clean($solicitud['lente_observacion'] ?? null);
            $incision = $clean($solicitud['incision'] ?? null);

            $detalles = $solicitud['detalles'] ?? [];
            if (is_array($detalles)) {
                $detallePlano = null;
                foreach ($detalles as $d) {
                    if (!is_array($d)) continue;
                    $detallePlano = $d;
                    if (!empty($d['principal']) || !empty($d['tipo'])) {
                        break;
                    }
                }
                if ($detallePlano) {
                    $lenteId = $lenteId ?: $clean($detallePlano['id_lente_intraocular'] ?? $detallePlano['lente_id'] ?? null);
                    $lenteNombre = $lenteNombre ?: $clean($detallePlano['lente'] ?? $detallePlano['lente_nombre'] ?? null);
                    $lentePoder = $lentePoder ?: $clean($detallePlano['poder'] ?? $detallePlano['lente_poder'] ?? null);
                    $lenteObs = $lenteObs ?: $clean($detallePlano['observaciones'] ?? $detallePlano['lente_observacion'] ?? null);
                    $incision = $incision ?: $clean($detallePlano['incision'] ?? null);
                    if (!$ojoVal) {
                        $ojoVal = $clean($detallePlano['lateralidad'] ?? null);
                    }
                }
            }

            $stmt->execute([
                ':hc' => $hcNumber,
                ':form_id' => $data['form_id'],
                ':secuencia' => $secuencia,
                ':tipo' => $tipo,
                ':afiliacion' => $afiliacion,
                ':procedimiento' => $procedimiento,
                ':doctor' => $doctor,
                ':fecha' => $fecha,
                ':duracion' => $duracion,
                ':ojo' => $ojoVal,
                ':prioridad' => $prioridad,
                ':producto' => $producto,
                ':observacion' => $observacion,
                ':sesiones' => $sesiones,
                ':lente_id' => $lenteId,
                ':lente_nombre' => $lenteNombre,
                ':lente_poder' => $lentePoder,
                ':lente_observacion' => $lenteObs,
                ':incision' => $incision,
            ]);
        }

        return [
            'success' => true,
            'message' => 'Solicitudes guardadas o actualizadas correctamente',
            'total' => count($data['solicitudes']),
        ];
    }

    public function obtenerFechaCreacionSolicitud($form_id, $hc)
    {
        $sql = "SELECT * FROM solicitud_procedimiento
                WHERE form_id = ? AND hc_number = ? ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id, $hc]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function obtenerDxDeSolicitud($form_id)
    {
        $sql = "SELECT * FROM diagnosticos_asignados
                WHERE form_id = ? ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerConsultaDeSolicitud($form_id)
    {
        $doctorJoinCondition = $this->buildDoctorNameMatchCondition('pp.doctor', 'u.nombre');

        $sql = "SELECT
                cd.*,
                pp.doctor AS procedimiento_doctor,
                u.id AS doctor_user_id,
                u.first_name AS matched_doctor_fname,
                u.middle_name AS matched_doctor_mname,
                u.last_name AS matched_doctor_lname,
                u.second_last_name AS matched_doctor_lname2,
                u.cedula AS matched_doctor_cedula,
                u.signature_path AS matched_doctor_signature_path,
                u.firma AS matched_doctor_firma,
                u.full_name AS matched_doctor_full_name
            FROM consulta_data cd
            LEFT JOIN procedimiento_proyectado pp
                ON pp.id = (
                    SELECT pp2.id
                    FROM procedimiento_proyectado pp2
                    WHERE pp2.form_id = cd.form_id
                      AND pp2.hc_number = cd.hc_number
                      AND pp2.doctor IS NOT NULL
                      AND TRIM(pp2.doctor) <> ''
                    ORDER BY pp2.id DESC
                    LIMIT 1
                )
            LEFT JOIN users u
                ON {$doctorJoinCondition}
            WHERE cd.form_id = ?
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id]);
        $consulta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$consulta) {
            return [];
        }

        $fallbackMap = [
            'matched_doctor_fname' => 'doctor_fname',
            'matched_doctor_mname' => 'doctor_mname',
            'matched_doctor_lname' => 'doctor_lname',
            'matched_doctor_lname2' => 'doctor_lname2',
            'matched_doctor_cedula' => 'doctor_cedula',
            'matched_doctor_signature_path' => 'doctor_signature_path',
            'matched_doctor_firma' => 'doctor_firma',
            'matched_doctor_full_name' => 'doctor_full_name',
        ];

        foreach ($fallbackMap as $sourceField => $targetField) {
            $sourceValue = trim((string)($consulta[$sourceField] ?? ''));

            // Prioriza el dato de users cuando existe para mantener nombre/firma canónicos.
            if ($sourceValue !== '') {
                $consulta[$targetField] = $sourceValue;
            }

            unset($consulta[$sourceField]);
        }

        if (trim((string)($consulta['doctor'] ?? '')) === '') {
            $fromProcedimiento = trim((string)($consulta['procedimiento_doctor'] ?? ''));
            $fromFullName = trim((string)($consulta['doctor_full_name'] ?? ''));
            $consulta['doctor'] = $fromFullName !== '' ? $fromFullName : $fromProcedimiento;
        }

        return $consulta;
    }

    public function obtenerDatosYCirujanoSolicitud($form_id, $hc)
    {
        $doctorJoinCondition = $this->buildDoctorNameMatchCondition('sp.doctor', 'u.nombre');

        // Alias de ids para no confundir id de solicitud con id de usuario (sp.id vs u.id)
        $sql = "SELECT 
                sp.*,
                sp.id AS solicitud_id,
                sp.id AS id,
                u.id AS user_id,
                u.nombre AS user_nombre,
                u.email AS user_email,
                u.first_name AS doctor_first_name,
                u.middle_name AS doctor_middle_name,
                u.last_name AS doctor_last_name,
                u.second_last_name AS doctor_second_last_name,
                u.cedula AS doctor_cedula,
                u.signature_path AS doctor_signature_path,
                u.firma AS doctor_firma,
                u.full_name AS doctor_full_name
            FROM solicitud_procedimiento sp
            LEFT JOIN users u
                ON {$doctorJoinCondition}
            WHERE sp.form_id = ? AND sp.hc_number = ?
            ORDER BY sp.created_at DESC
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id, $hc]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findIdByFormId(int $formId): ?int
    {
        if ($formId <= 0) {
            return null;
        }

        $sql = 'SELECT id FROM solicitud_procedimiento WHERE form_id = ? ORDER BY created_at DESC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$formId]);
        $result = $stmt->fetchColumn();

        return $result !== false ? (int)$result : null;
    }

    public function actualizarEstado(int $id, string $estado): array
    {
        $this->db->beginTransaction();

        try {
            $datosPreviosStmt = $this->db->prepare("SELECT
                    sp.id,
                    sp.form_id,
                    sp.estado,
                    sp.turno,
                    sp.hc_number,
                    sp.procedimiento,
                    sp.prioridad,
                    sp.doctor,
                    sp.tipo,
                    sp.afiliacion,
                    COALESCE(cd.fecha, sp.fecha) AS fecha_programada,
                    TRIM(CONCAT_WS(' ',
                      NULLIF(TRIM(pd.fname), ''),
                      NULLIF(TRIM(pd.mname), ''),
                      NULLIF(TRIM(pd.lname), ''),
                      NULLIF(TRIM(pd.lname2), '')
                    )) AS full_name
                FROM solicitud_procedimiento sp
                LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
                LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
                WHERE sp.id = :id
                FOR UPDATE");
            $datosPreviosStmt->bindParam(':id', $id, \PDO::PARAM_INT);
            $datosPreviosStmt->execute();
            $datosPrevios = $datosPreviosStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $sql = "UPDATE solicitud_procedimiento SET estado = :estado WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            if (!$stmt) {
                throw new \RuntimeException('Error al preparar la consulta');
            }

            $stmt->bindParam(':estado', $estado, \PDO::PARAM_STR);
            $stmt->bindParam(':id', $id, \PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new \RuntimeException('No se pudo actualizar el estado');
            }

            $turno = null;
            if (strcasecmp($estado, 'Recibido') === 0) {
                $turno = $this->asignarTurnoSiNecesario($id);
            }

            $datosStmt = $this->db->prepare("SELECT
                    sp.id,
                    sp.form_id,
                    sp.estado,
                    sp.turno,
                    sp.hc_number,
                    sp.procedimiento,
                    sp.prioridad,
                    sp.doctor,
                    sp.tipo,
                    sp.afiliacion,
                    COALESCE(cd.fecha, sp.fecha) AS fecha_programada,
                    TRIM(CONCAT_WS(' ',
                      NULLIF(TRIM(pd.fname), ''),
                      NULLIF(TRIM(pd.mname), ''),
                      NULLIF(TRIM(pd.lname), ''),
                      NULLIF(TRIM(pd.lname2), '')
                    )) AS full_name
                FROM solicitud_procedimiento sp
                LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
                LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
                WHERE sp.id = :id");
            $datosStmt->bindParam(':id', $id, \PDO::PARAM_INT);
            $datosStmt->execute();
            $datos = $datosStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $this->db->commit();

            return [
                'id' => $id,
                'form_id' => $datos['form_id'] ?? null,
                'hc_number' => $datos['hc_number'] ?? null,
                'estado' => $datos['estado'] ?? $estado,
                'turno' => $datos['turno'] ?? $turno,
                'full_name' => isset($datos['full_name']) && trim((string)$datos['full_name']) !== ''
                    ? trim((string)$datos['full_name'])
                    : null,
                'procedimiento' => $datos['procedimiento'] ?? null,
                'prioridad' => $datos['prioridad'] ?? null,
                'doctor' => $datos['doctor'] ?? null,
                'tipo' => $datos['tipo'] ?? null,
                'afiliacion' => $datos['afiliacion'] ?? null,
                'fecha_programada' => $datos['fecha_programada'] ?? null,
                'estado_anterior' => $datosPrevios['estado'] ?? null,
                'turno_anterior' => $datosPrevios['turno'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function obtenerSolicitudBasicaPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT
                sp.id,
                sp.form_id,
                sp.hc_number,
                sp.estado,
                sp.prioridad,
                sp.doctor,
                sp.tipo,
                sp.procedimiento,
                sp.afiliacion,
                sp.turno,
                COALESCE(cd.fecha, sp.fecha) AS fecha_programada,
                TRIM(CONCAT_WS(' ',
                  NULLIF(TRIM(pd.fname), ''),
                  NULLIF(TRIM(pd.mname), ''),
                  NULLIF(TRIM(pd.lname), ''),
                  NULLIF(TRIM(pd.lname2), '')
                )) AS full_name
            FROM solicitud_procedimiento sp
            LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
            LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
            WHERE sp.id = :id
            LIMIT 1");
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            return null;
        }

        if (isset($row['full_name'])) {
            $row['full_name'] = trim((string)$row['full_name']) !== ''
                ? trim((string)$row['full_name'])
                : null;
        }

        return $row;
    }

    public function buscarSolicitudesProgramadas(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $columnas = [
            'sp.id',
            'sp.form_id',
            'sp.hc_number',
            'sp.estado',
            'sp.prioridad',
            'sp.procedimiento',
            'sp.doctor',
            'sp.tipo',
            'sp.afiliacion',
            'sp.turno',
            'COALESCE(cd.fecha, sp.fecha) AS fecha_programada',
            'pd.fecha_caducidad',
        ];

        if ($this->consultaDataTieneColumna('quirofano')) {
            $columnas[] = 'cd.quirofano';
        } else {
            $columnas[] = 'NULL AS quirofano';
        }

        $columnas[] = "TRIM(CONCAT_WS(' ',
          NULLIF(TRIM(pd.fname), ''),
          NULLIF(TRIM(pd.mname), ''),
          NULLIF(TRIM(pd.lname), ''),
          NULLIF(TRIM(pd.lname2), '')
        )) AS full_name";

        $sql = sprintf(
            "SELECT\n                %s\n            FROM solicitud_procedimiento sp\n            INNER JOIN patient_data pd ON pd.hc_number = sp.hc_number\n            LEFT JOIN consulta_data cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id\n            WHERE COALESCE(cd.fecha, sp.fecha) BETWEEN :desde AND :hasta\n            ORDER BY COALESCE(cd.fecha, sp.fecha) ASC, sp.id ASC",
            implode(",\n                ", $columnas)
        );

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':desde', $desde->format('Y-m-d H:i:s'));
        $stmt->bindValue(':hasta', $hasta->format('Y-m-d H:i:s'));
        $stmt->execute();

        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($resultados as &$row) {
            if (isset($row['full_name'])) {
                $row['full_name'] = trim((string)$row['full_name']) !== ''
                    ? trim((string)$row['full_name'])
                    : null;
            }
        }
        unset($row);

        return $resultados;
    }

    public function listarConciliacionCirugiasMes(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $derivacionLateralidadExpr = $this->selectSolicitudColumn('derivacion_lateralidad');
        $hasMetaTable = $this->hasTable('solicitud_crm_meta');
        $metaPlaceholders = implode(', ', array_fill(0, count(self::META_CIRUGIA_CONFIRMADA_KEYS), '?'));

        $metaSelect = implode(
            ",\n                ",
            [
                $hasMetaTable ? "meta.protocolo_confirmado_form_id" : "NULL AS protocolo_confirmado_form_id",
                $hasMetaTable ? "meta.protocolo_confirmado_hc_number" : "NULL AS protocolo_confirmado_hc_number",
                $hasMetaTable ? "meta.protocolo_confirmado_fecha_inicio" : "NULL AS protocolo_confirmado_fecha_inicio",
                $hasMetaTable ? "meta.protocolo_confirmado_lateralidad" : "NULL AS protocolo_confirmado_lateralidad",
                $hasMetaTable ? "meta.protocolo_confirmado_membrete" : "NULL AS protocolo_confirmado_membrete",
                $hasMetaTable ? "meta.protocolo_confirmado_by" : "NULL AS protocolo_confirmado_by",
                $hasMetaTable ? "meta.protocolo_confirmado_at" : "NULL AS protocolo_confirmado_at",
            ]
        );

        $metaJoin = '';
        if ($hasMetaTable) {
            $metaJoin = sprintf(
                "LEFT JOIN (
                    SELECT
                        solicitud_id,
                        MAX(CASE WHEN meta_key = 'cirugia_confirmada_form_id' THEN meta_value END) AS protocolo_confirmado_form_id,
                        MAX(CASE WHEN meta_key = 'cirugia_confirmada_hc_number' THEN meta_value END) AS protocolo_confirmado_hc_number,
                        MAX(CASE WHEN meta_key = 'cirugia_confirmada_fecha_inicio' THEN meta_value END) AS protocolo_confirmado_fecha_inicio,
                        MAX(CASE WHEN meta_key = 'cirugia_confirmada_lateralidad' THEN meta_value END) AS protocolo_confirmado_lateralidad,
                        MAX(CASE WHEN meta_key = 'cirugia_confirmada_membrete' THEN meta_value END) AS protocolo_confirmado_membrete,
                        MAX(CASE WHEN meta_key = 'cirugia_confirmada_by' THEN meta_value END) AS protocolo_confirmado_by,
                        MAX(CASE WHEN meta_key = 'cirugia_confirmada_at' THEN meta_value END) AS protocolo_confirmado_at
                    FROM solicitud_crm_meta
                    WHERE meta_key IN (%s)
                    GROUP BY solicitud_id
                ) meta ON meta.solicitud_id = sp.id",
                $metaPlaceholders
            );
        }

        $sql = sprintf(
            "SELECT
                sp.id,
                sp.form_id,
                sp.hc_number,
                sp.procedimiento,
                sp.ojo,
                %s,
                sp.estado,
                COALESCE(sp.created_at, sp.fecha, cd.fecha) AS fecha_solicitud,
                TRIM(CONCAT_WS(' ',
                    NULLIF(TRIM(pd.fname), ''),
                    NULLIF(TRIM(pd.mname), ''),
                    NULLIF(TRIM(pd.lname), ''),
                    NULLIF(TRIM(pd.lname2), '')
                )) AS full_name,
                %s
            FROM solicitud_procedimiento sp
            LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
            LEFT JOIN (
                SELECT hc_number, form_id, MAX(fecha) AS fecha
                FROM consulta_data
                GROUP BY hc_number, form_id
            ) cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
            %s
            WHERE COALESCE(sp.created_at, sp.fecha, cd.fecha) BETWEEN ? AND ?
              AND sp.procedimiento IS NOT NULL
              AND TRIM(sp.procedimiento) <> ''
              AND UPPER(TRIM(sp.procedimiento)) <> 'SELECCIONE'
            ORDER BY fecha_solicitud DESC, sp.id DESC",
            $derivacionLateralidadExpr,
            $metaSelect,
            $metaJoin
        );

        $params = array_merge(
            $hasMetaTable ? self::META_CIRUGIA_CONFIRMADA_KEYS : [],
            [
                $desde->format('Y-m-d H:i:s'),
                $hasta->format('Y-m-d H:i:s'),
            ]
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows === []) {
            return [];
        }

        $hcNumbers = [];
        $usuarioIds = [];
        $rawHcByKey = [];

        foreach ($rows as $row) {
            $hc = trim((string)($row['hc_number'] ?? ''));
            if ($hc !== '') {
                $hcNumbers[$hc] = true;
                $hcKey = $this->normalizarHcClave($hc);
                if ($hcKey !== '') {
                    $rawHcByKey[$hcKey][$hc] = true;
                }
            }

            if ($hasMetaTable) {
                $confirmadoBy = (int)($row['protocolo_confirmado_by'] ?? 0);
                if ($confirmadoBy > 0) {
                    $usuarioIds[$confirmadoBy] = true;
                }
            }
        }

        $usuariosById = $this->cargarUsuariosPorId(array_keys($usuarioIds));
        $protocolosByHc = $this->cargarProtocolosPorHc(array_keys($hcNumbers), '1900-01-01 00:00:00');
        $protocolosByHcKey = [];
        $protocolosByFormId = [];

        foreach ($protocolosByHc as $hc => $protocolos) {
            $hcKey = $this->normalizarHcClave($hc);
            foreach ($protocolos as $protocolo) {
                $formId = trim((string)($protocolo['form_id'] ?? ''));
                if ($formId !== '') {
                    $protocolosByFormId[$formId] = $protocolo;
                }

                $protocoloHcKey = $hcKey !== ''
                    ? $hcKey
                    : $this->normalizarHcClave((string)($protocolo['hc_number'] ?? ''));
                if ($protocoloHcKey !== '') {
                    $protocolosByHcKey[$protocoloHcKey][] = $protocolo;
                }
            }
        }

        $rowsByHc = [];
        foreach ($rows as $index => $row) {
            $hc = $this->normalizarHcClave((string)($row['hc_number'] ?? ''));
            if ($hc === '') {
                continue;
            }
            $rowsByHc[$hc][] = $index;
        }

        foreach ($rowsByHc as $hc => $indexes) {
            usort($indexes, function (int $a, int $b) use ($rows): int {
                $tsA = $this->toTimestamp((string)($rows[$a]['fecha_solicitud'] ?? ''));
                $tsB = $this->toTimestamp((string)($rows[$b]['fecha_solicitud'] ?? ''));

                if ($tsA === $tsB) {
                    return ((int)($rows[$a]['id'] ?? 0)) <=> ((int)($rows[$b]['id'] ?? 0));
                }

                return $tsA <=> $tsB;
            });

            foreach ($indexes as $index) {
                $row = &$rows[$index];
                $confirmado = $this->resolverProtocoloConfirmado(
                    $row,
                    $protocolosByFormId,
                    $usuariosById
                );

                if ($confirmado !== null) {
                    $row['protocolo_confirmado'] = $confirmado;
                    $row['protocolo_posterior_compatible'] = $confirmado;
                    continue;
                }

                $row['protocolo_confirmado'] = null;
                $row['protocolo_posterior_compatible'] = null;

                $protocolosPaciente = $protocolosByHcKey[$hc] ?? [];
                if ($protocolosPaciente === [] && !empty($rawHcByKey[$hc])) {
                    foreach (array_keys($rawHcByKey[$hc]) as $rawHc) {
                        if (!empty($protocolosByHc[$rawHc])) {
                            $protocolosPaciente = array_merge($protocolosPaciente, $protocolosByHc[$rawHc]);
                        }
                    }
                }
                if ($protocolosPaciente === []) {
                    continue;
                }

                $solicitudLateralidad = $this->resolverLateralidadSolicitud($row);
                $fechaSolicitudTs = $this->toTimestamp((string)($row['fecha_solicitud'] ?? ''));

                foreach ($protocolosPaciente as $protocolo) {
                    $formId = trim((string)($protocolo['form_id'] ?? ''));
                    if ($formId === '') {
                        continue;
                    }

                    $fechaProtocoloTs = $this->toTimestamp((string)($protocolo['fecha_inicio'] ?? ''));
                    $isPosterior = !($fechaSolicitudTs > 0 && $fechaProtocoloTs > 0 && $fechaProtocoloTs < $fechaSolicitudTs);

                    $lateralidadProtocolo = trim((string)($protocolo['lateralidad'] ?? ''));
                    if (!$this->lateralidadesCompatibles($solicitudLateralidad, $lateralidadProtocolo)) {
                        continue;
                    }

                    if (!$isPosterior) {
                        continue;
                    }

                    $row['protocolo_posterior_compatible'] = $this->formatearProtocolo($protocolo);
                    break;
                }
            }
            unset($row);
        }

        foreach ($rows as &$row) {
            $nombre = trim((string)($row['full_name'] ?? ''));
            $row['full_name'] = $nombre !== '' ? $nombre : null;
            $row['ojo_resuelto'] = $this->resolverLateralidadSolicitud($row);
            $estado = strtolower(trim((string)($row['estado'] ?? '')));
            if ($row['protocolo_confirmado'] === null && $estado === 'completado' && $row['protocolo_posterior_compatible'] !== null) {
                $row['protocolo_confirmado'] = $row['protocolo_posterior_compatible'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array{hc_number?:string,solicitud_id?:int,form_id?:string,limit?:int} $options
     * @return array<string, mixed>
     */
    public function diagnosticarConciliacion(array $rows, array $options = []): array
    {
        $hcFilterRaw = trim((string)($options['hc_number'] ?? ''));
        $hcFilterKey = $this->normalizarHcClave($hcFilterRaw);
        $solicitudIdFilter = (int)($options['solicitud_id'] ?? 0);
        $formIdFilter = trim((string)($options['form_id'] ?? ''));
        $limit = (int)($options['limit'] ?? 25);
        $limit = max(1, min(200, $limit));

        $selected = [];
        foreach ($rows as $row) {
            if ($solicitudIdFilter > 0 && (int)($row['id'] ?? 0) !== $solicitudIdFilter) {
                continue;
            }

            if ($formIdFilter !== '' && trim((string)($row['form_id'] ?? '')) !== $formIdFilter) {
                continue;
            }

            if ($hcFilterKey !== '') {
                $rowHcKey = $this->normalizarHcClave((string)($row['hc_number'] ?? ''));
                if ($rowHcKey !== $hcFilterKey) {
                    continue;
                }
            }

            $selected[] = $row;
            if (count($selected) >= $limit) {
                break;
            }
        }

        if ($selected === [] && $hcFilterKey === '' && $solicitudIdFilter <= 0 && $formIdFilter === '') {
            $selected = array_slice($rows, 0, $limit);
        }

        $items = [];
        foreach ($selected as $row) {
            $items[] = $this->diagnosticarFilaConciliacion($row);
        }

        return [
            'filtros' => [
                'hc_number' => $hcFilterRaw !== '' ? $hcFilterRaw : null,
                'solicitud_id' => $solicitudIdFilter > 0 ? $solicitudIdFilter : null,
                'form_id' => $formIdFilter !== '' ? $formIdFilter : null,
                'limit' => $limit,
            ],
            'total_rows' => count($rows),
            'rows_debugged' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function diagnosticarFilaConciliacion(array $row): array
    {
        $hcRaw = trim((string)($row['hc_number'] ?? ''));
        $hcKey = $this->normalizarHcClave($hcRaw);
        $fechaSolicitud = (string)($row['fecha_solicitud'] ?? '');
        $fechaSolicitudTs = $this->toTimestamp($fechaSolicitud);
        $solicitudLateralidad = $this->resolverLateralidadSolicitud($row);
        $solicitudLateralidadNormalizada = $this->normalizarLateralidades($solicitudLateralidad);

        $protocolos = $this->cargarProtocolosDebugPorHc($hcRaw, $hcKey);
        $totalProtocolos = count($protocolos);

        $firstPosterior = null;
        $firstCompatible = null;
        $firstCompatiblePosterior = null;
        $posteriores = 0;
        $compatibles = 0;
        $compatiblesPosteriores = 0;
        $muestra = [];

        foreach ($protocolos as $protocolo) {
            $protocoloFechaTs = $this->toTimestamp((string)($protocolo['fecha_inicio'] ?? ''));
            $isPosterior = !($fechaSolicitudTs > 0 && $protocoloFechaTs > 0 && $protocoloFechaTs < $fechaSolicitudTs);

            $lateralidadProtocolo = trim((string)($protocolo['lateralidad'] ?? ''));
            $isCompatible = $this->lateralidadesCompatibles($solicitudLateralidad, $lateralidadProtocolo);

            if ($isPosterior) {
                $posteriores++;
                if ($firstPosterior === null) {
                    $firstPosterior = $protocolo;
                }
            }

            if ($isCompatible) {
                $compatibles++;
                if ($firstCompatible === null) {
                    $firstCompatible = $protocolo;
                }
            }

            if ($isPosterior && $isCompatible) {
                $compatiblesPosteriores++;
                if ($firstCompatiblePosterior === null) {
                    $firstCompatiblePosterior = $protocolo;
                }
            }

            if (count($muestra) < 10) {
                $muestra[] = [
                    'form_id' => trim((string)($protocolo['form_id'] ?? '')),
                    'fecha_inicio' => $protocolo['fecha_inicio'] ?? null,
                    'lateralidad' => $lateralidadProtocolo,
                    'es_posterior' => $isPosterior,
                    'compatible_lateralidad' => $isCompatible,
                ];
            }
        }

        $esperado = $firstCompatiblePosterior;
        $actual = is_array($row['protocolo_posterior_compatible'] ?? null)
            ? $row['protocolo_posterior_compatible']
            : null;

        $motivo = 'con_match';
        if ($actual === null) {
            if ($totalProtocolos === 0) {
                $motivo = 'sin_protocolos_para_hc';
            } elseif ($posteriores === 0) {
                $motivo = 'sin_protocolos_posteriores';
            } elseif ($compatiblesPosteriores === 0 && $compatibles === 0) {
                $motivo = 'sin_compatibilidad_lateralidad';
            } elseif ($compatiblesPosteriores === 0 && $compatibles > 0) {
                $motivo = 'compatibles_solo_anteriores';
            } else {
                $motivo = 'sin_match';
            }
        }

        return [
            'solicitud' => [
                'id' => (int)($row['id'] ?? 0),
                'form_id' => trim((string)($row['form_id'] ?? '')),
                'hc_number' => $hcRaw,
                'hc_key' => $hcKey,
                'fecha_solicitud' => $fechaSolicitud !== '' ? $fechaSolicitud : null,
                'lateralidad' => $solicitudLateralidad,
                'lateralidad_normalizada' => $solicitudLateralidadNormalizada,
                'estado' => trim((string)($row['estado'] ?? '')),
            ],
            'resumen' => [
                'total_protocolos' => $totalProtocolos,
                'posteriores' => $posteriores,
                'compatibles_lateralidad' => $compatibles,
                'compatibles_posteriores' => $compatiblesPosteriores,
                'motivo' => $motivo,
            ],
            'match_actual' => $actual ? [
                'form_id' => trim((string)($actual['form_id'] ?? '')),
                'fecha_inicio' => $actual['fecha_inicio'] ?? null,
                'lateralidad' => trim((string)($actual['lateralidad'] ?? '')),
            ] : null,
            'match_esperado' => $esperado ? [
                'form_id' => trim((string)($esperado['form_id'] ?? '')),
                'fecha_inicio' => $esperado['fecha_inicio'] ?? null,
                'lateralidad' => trim((string)($esperado['lateralidad'] ?? '')),
            ] : null,
            'protocolos_muestra' => $muestra,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cargarProtocolosDebugPorHc(string $hcRaw, string $hcKey): array
    {
        $collected = [];
        $grouped = $this->cargarProtocolosPorHc($hcRaw !== '' ? [$hcRaw] : [], '1900-01-01 00:00:00');

        foreach ($grouped as $hcValue => $protocolos) {
            $groupKey = $this->normalizarHcClave((string)$hcValue);
            if ($hcKey !== '' && $groupKey !== '' && $groupKey !== $hcKey) {
                continue;
            }
            foreach ($protocolos as $protocolo) {
                $formId = trim((string)($protocolo['form_id'] ?? ''));
                if ($formId !== '') {
                    $collected[$formId] = $protocolo;
                }
            }
        }

        if ($collected !== []) {
            usort($collected, function (array $a, array $b): int {
                $tsA = $this->toTimestamp((string)($a['fecha_inicio'] ?? ''));
                $tsB = $this->toTimestamp((string)($b['fecha_inicio'] ?? ''));
                if ($tsA === $tsB) {
                    return strcmp((string)($a['form_id'] ?? ''), (string)($b['form_id'] ?? ''));
                }
                return $tsA <=> $tsB;
            });
            return array_values($collected);
        }

        if (
            !$this->hasTable('protocolo_data')
            || !$this->hasColumnInTable('protocolo_data', 'form_id')
            || !$this->hasColumnInTable('protocolo_data', 'hc_number')
            || !$this->hasColumnInTable('protocolo_data', 'fecha_inicio')
        ) {
            return [];
        }

        $lateralidadExpr = $this->hasColumnInTable('protocolo_data', 'lateralidad')
            ? 'lateralidad'
            : 'NULL AS lateralidad';
        $membreteExpr = $this->hasColumnInTable('protocolo_data', 'membrete')
            ? 'membrete'
            : 'NULL AS membrete';
        $statusExpr = $this->hasColumnInTable('protocolo_data', 'status')
            ? 'status'
            : 'NULL AS status';

        $compactRaw = preg_replace('/\s+/', '', $hcRaw) ?? '';
        $conditions = [];
        $params = [];

        if ($compactRaw !== '') {
            $conditions[] = "REPLACE(TRIM(hc_number), ' ', '') = ?";
            $params[] = $compactRaw;
        }

        if ($hcKey !== '') {
            if (ctype_digit($hcKey)) {
                $conditions[] = "TRIM(LEADING '0' FROM REPLACE(TRIM(hc_number), ' ', '')) = ?";
                $params[] = $hcKey;
            } else {
                $conditions[] = "UPPER(REPLACE(TRIM(hc_number), ' ', '')) = ?";
                $params[] = strtoupper($hcKey);
            }
        }

        if ($conditions === []) {
            return [];
        }

        $where = implode(' OR ', $conditions);
        $sql = "SELECT
                form_id,
                hc_number,
                fecha_inicio,
                {$lateralidadExpr},
                {$membreteExpr},
                {$statusExpr}
            FROM protocolo_data
            WHERE ({$where})
              AND fecha_inicio IS NOT NULL
            ORDER BY fecha_inicio ASC, form_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_values(array_filter($rows, static function (array $row): bool {
            return trim((string)($row['form_id'] ?? '')) !== '';
        }));
    }

    public function obtenerSolicitudConciliacionPorId(int $solicitudId): ?array
    {
        $derivacionLateralidadExpr = $this->selectSolicitudColumn('derivacion_lateralidad');

        $sql = sprintf(
            "SELECT
                sp.id,
                sp.form_id,
                sp.hc_number,
                sp.procedimiento,
                sp.ojo,
                %s,
                sp.estado,
                COALESCE(sp.created_at, sp.fecha, cd.fecha) AS fecha_solicitud
            FROM solicitud_procedimiento sp
            LEFT JOIN (
                SELECT hc_number, form_id, MAX(fecha) AS fecha
                FROM consulta_data
                GROUP BY hc_number, form_id
            ) cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
            WHERE sp.id = :id
            LIMIT 1",
            $derivacionLateralidadExpr
        );

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $solicitudId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            return null;
        }

        $row['ojo_resuelto'] = $this->resolverLateralidadSolicitud($row);

        return $row;
    }

    public function obtenerProtocoloConciliacionPorFormId(string $formId): ?array
    {
        $formId = trim($formId);
        if ($formId === '') {
            return null;
        }

        if (
            !$this->hasTable('protocolo_data')
            || !$this->hasColumnInTable('protocolo_data', 'form_id')
            || !$this->hasColumnInTable('protocolo_data', 'hc_number')
        ) {
            return null;
        }

        $lateralidadExpr = $this->hasColumnInTable('protocolo_data', 'lateralidad')
            ? 'lateralidad'
            : 'NULL AS lateralidad';
        $membreteExpr = $this->hasColumnInTable('protocolo_data', 'membrete')
            ? 'membrete'
            : 'NULL AS membrete';
        $statusExpr = $this->hasColumnInTable('protocolo_data', 'status')
            ? 'status'
            : 'NULL AS status';
        $fechaExpr = $this->hasColumnInTable('protocolo_data', 'fecha_inicio')
            ? 'fecha_inicio'
            : 'NULL AS fecha_inicio';

        $stmt = $this->db->prepare(
            "SELECT
                form_id,
                hc_number,
                {$fechaExpr},
                {$lateralidadExpr},
                {$membreteExpr},
                {$statusExpr}
            FROM protocolo_data
            WHERE form_id = :form_id
            ORDER BY fecha_inicio DESC
            LIMIT 1"
        );
        $stmt->bindValue(':form_id', $formId, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            return null;
        }

        return $row;
    }

    public function registrarConfirmacionCirugia(int $solicitudId, array $protocolo, ?int $usuarioId = null): void
    {
        $formId = trim((string)($protocolo['form_id'] ?? ''));
        if ($formId === '') {
            throw new \RuntimeException('No se puede guardar confirmación sin form_id de protocolo.');
        }

        if (!$this->hasTable('solicitud_crm_meta')) {
            return;
        }

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $metaValues = [
            'cirugia_confirmada_form_id' => $formId,
            'cirugia_confirmada_hc_number' => trim((string)($protocolo['hc_number'] ?? '')),
            'cirugia_confirmada_fecha_inicio' => trim((string)($protocolo['fecha_inicio'] ?? '')),
            'cirugia_confirmada_lateralidad' => trim((string)($protocolo['lateralidad'] ?? '')),
            'cirugia_confirmada_membrete' => trim((string)($protocolo['membrete'] ?? '')),
            'cirugia_confirmada_by' => $usuarioId ? (string)$usuarioId : '',
            'cirugia_confirmada_at' => $now,
        ];

        $metaTypes = [
            'cirugia_confirmada_form_id' => 'texto',
            'cirugia_confirmada_hc_number' => 'texto',
            'cirugia_confirmada_fecha_inicio' => 'fecha',
            'cirugia_confirmada_lateralidad' => 'texto',
            'cirugia_confirmada_membrete' => 'texto',
            'cirugia_confirmada_by' => 'numero',
            'cirugia_confirmada_at' => 'fecha',
        ];

        $placeholders = implode(', ', array_fill(0, count(self::META_CIRUGIA_CONFIRMADA_KEYS), '?'));

        $this->db->beginTransaction();

        try {
            $deleteStmt = $this->db->prepare(
                "DELETE FROM solicitud_crm_meta
                 WHERE solicitud_id = ?
                   AND meta_key IN ($placeholders)"
            );
            $deleteParams = array_merge([$solicitudId], self::META_CIRUGIA_CONFIRMADA_KEYS);
            $deleteStmt->execute($deleteParams);

            $insertStmt = $this->db->prepare(
                'INSERT INTO solicitud_crm_meta (solicitud_id, meta_key, meta_value, meta_type)
                 VALUES (:solicitud_id, :meta_key, :meta_value, :meta_type)'
            );

            foreach (self::META_CIRUGIA_CONFIRMADA_KEYS as $metaKey) {
                $value = $metaValues[$metaKey] ?? '';
                if ($value === '') {
                    continue;
                }

                $insertStmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
                $insertStmt->bindValue(':meta_key', $metaKey, PDO::PARAM_STR);
                $insertStmt->bindValue(':meta_value', $value, PDO::PARAM_STR);
                $insertStmt->bindValue(':meta_type', $metaTypes[$metaKey] ?? 'texto', PDO::PARAM_STR);
                $insertStmt->execute();
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function lateralidadesCompatibles(?string $solicitudLateralidad, ?string $protocoloLateralidad): bool
    {
        $solicitud = $this->normalizarLateralidades($solicitudLateralidad);
        $protocolo = $this->normalizarLateralidades($protocoloLateralidad);

        // Si falta lateralidad en alguno de los dos lados, se permite para conciliación manual.
        if ($solicitud === [] || $protocolo === []) {
            return true;
        }

        return array_intersect($solicitud, $protocolo) !== [];
    }

    /**
     * @param array<int, int|string> $ids
     * @return array<int, string>
     */
    private function cargarUsuariosPorId(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, nombre
             FROM users
             WHERE id IN ($placeholders)"
        );
        $stmt->execute($ids);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $result = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $nombre = trim((string)($row['nombre'] ?? ''));
            $result[$id] = $nombre;
        }

        return $result;
    }

    /**
     * @param array<int, string> $hcNumbers
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function cargarProtocolosPorHc(array $hcNumbers, string $fechaMinima): array
    {
        $hcNumbers = array_values(array_unique(array_filter(array_map(
            static fn($value) => trim((string)$value),
            $hcNumbers
        ))));

        if ($hcNumbers === []) {
            return [];
        }

        if (
            !$this->hasTable('protocolo_data')
            || !$this->hasColumnInTable('protocolo_data', 'form_id')
            || !$this->hasColumnInTable('protocolo_data', 'hc_number')
            || !$this->hasColumnInTable('protocolo_data', 'fecha_inicio')
        ) {
            return [];
        }

        $lateralidadExpr = $this->hasColumnInTable('protocolo_data', 'lateralidad')
            ? 'lateralidad'
            : 'NULL AS lateralidad';
        $membreteExpr = $this->hasColumnInTable('protocolo_data', 'membrete')
            ? 'membrete'
            : 'NULL AS membrete';
        $statusExpr = $this->hasColumnInTable('protocolo_data', 'status')
            ? 'status'
            : 'NULL AS status';
        $hcCompactos = [];
        $hcNumericos = [];
        $hcAlfanumericos = [];
        foreach ($hcNumbers as $hcNumber) {
            $compacto = preg_replace('/\s+/', '', trim((string)$hcNumber)) ?? '';
            if ($compacto === '') {
                continue;
            }

            $hcCompactos[$compacto] = true;
            if (ctype_digit($compacto)) {
                $sinCeros = ltrim($compacto, '0');
                $hcNumericos[$sinCeros !== '' ? $sinCeros : '0'] = true;
            } else {
                $hcAlfanumericos[strtoupper($compacto)] = true;
            }
        }

        if ($hcCompactos === [] && $hcNumericos === [] && $hcAlfanumericos === []) {
            return [];
        }

        $hcExpr = "REPLACE(TRIM(CAST(hc_number AS CHAR)), ' ', '')";
        $conditions = [];
        $params = [];

        if ($hcCompactos !== []) {
            $values = array_keys($hcCompactos);
            $conditions[] = $hcExpr . ' IN (' . implode(', ', array_fill(0, count($values), '?')) . ')';
            $params = array_merge($params, $values);
        }

        if ($hcNumericos !== []) {
            $values = array_keys($hcNumericos);
            $conditions[] = "TRIM(LEADING '0' FROM {$hcExpr}) IN (" . implode(', ', array_fill(0, count($values), '?')) . ')';
            $params = array_merge($params, $values);
        }

        if ($hcAlfanumericos !== []) {
            $values = array_keys($hcAlfanumericos);
            $conditions[] = "UPPER({$hcExpr}) IN (" . implode(', ', array_fill(0, count($values), '?')) . ')';
            $params = array_merge($params, $values);
        }

        $whereHc = implode(' OR ', $conditions);
        $sql = "SELECT
                form_id,
                hc_number,
                fecha_inicio,
                {$lateralidadExpr},
                {$membreteExpr},
                {$statusExpr}
            FROM protocolo_data
            WHERE ({$whereHc})
              AND fecha_inicio IS NOT NULL
              AND CAST(fecha_inicio AS CHAR) <> ''
              AND CAST(fecha_inicio AS CHAR) >= ?
            ORDER BY hc_number ASC, fecha_inicio ASC, form_id ASC";

        $stmt = $this->db->prepare($sql);
        $params[] = $fechaMinima;
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $grouped = [];
        foreach ($rows as $row) {
            $hc = trim((string)($row['hc_number'] ?? ''));
            if ($hc === '') {
                continue;
            }
            $grouped[$hc][] = $row;
        }

        return $grouped;
    }

    private function resolverProtocoloConfirmado(array $row, array $protocolosByFormId, array $usuariosById): ?array
    {
        $formId = trim((string)($row['protocolo_confirmado_form_id'] ?? ''));
        if ($formId === '') {
            return null;
        }

        $protocolo = $protocolosByFormId[$formId] ?? [
            'form_id' => $formId,
            'hc_number' => $row['protocolo_confirmado_hc_number'] ?? ($row['hc_number'] ?? null),
            'fecha_inicio' => $row['protocolo_confirmado_fecha_inicio'] ?? null,
            'lateralidad' => $row['protocolo_confirmado_lateralidad'] ?? null,
            'membrete' => $row['protocolo_confirmado_membrete'] ?? null,
            'status' => null,
        ];

        $payload = $this->formatearProtocolo($protocolo);
        $confirmadoBy = (int)($row['protocolo_confirmado_by'] ?? 0);
        $payload['confirmado_at'] = $row['protocolo_confirmado_at'] ?? null;
        $payload['confirmado_by'] = $confirmadoBy > 0 ? ($usuariosById[$confirmadoBy] ?? null) : null;
        $payload['confirmado_by_id'] = $confirmadoBy > 0 ? $confirmadoBy : null;

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatearProtocolo(array $protocolo): array
    {
        return [
            'form_id' => trim((string)($protocolo['form_id'] ?? '')),
            'hc_number' => trim((string)($protocolo['hc_number'] ?? '')),
            'fecha_inicio' => $protocolo['fecha_inicio'] ?? null,
            'lateralidad' => trim((string)($protocolo['lateralidad'] ?? '')),
            'membrete' => trim((string)($protocolo['membrete'] ?? '')),
            'status' => isset($protocolo['status']) ? (int)$protocolo['status'] : null,
        ];
    }

    private function resolverLateralidadSolicitud(array $row): ?string
    {
        $ojo = trim((string)($row['ojo'] ?? ''));
        if ($ojo !== '') {
            return $ojo;
        }

        $derivacion = trim((string)($row['derivacion_lateralidad'] ?? ''));
        if ($derivacion !== '') {
            return $derivacion;
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizarLateralidades(?string $valor): array
    {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return [];
        }

        $valor = strtoupper($valor);
        $valor = strtr($valor, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        $tokens = preg_split('/[^A-Z0-9]+/', $valor) ?: [];
        $result = [];
        $hasBoth = false;

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (in_array($token, ['AO', 'AMBOS', 'AMBAS', 'BILATERAL', 'BILATERALES', 'BILATERALIDAD', 'B'], true)) {
                $hasBoth = true;
                break;
            }

            if (in_array($token, ['OD', 'DER', 'DERECHO', 'DERECHA'], true)) {
                $result['OD'] = true;
                continue;
            }

            if (in_array($token, ['OI', 'IZQ', 'IZQUIERDO', 'IZQUIERDA'], true)) {
                $result['OI'] = true;
                continue;
            }
        }

        if ($hasBoth) {
            return ['OD', 'OI'];
        }

        return array_keys($result);
    }

    private function toTimestamp(?string $value): int
    {
        if ($value === null) {
            return 0;
        }

        $timestamp = strtotime($value);
        return $timestamp ?: 0;
    }

    private function normalizarHcClave(?string $value): string
    {
        $normalized = preg_replace('/\s+/', '', trim((string)$value)) ?? '';
        if ($normalized === '') {
            return '';
        }

        if (ctype_digit($normalized)) {
            $withoutLeadingZeroes = ltrim($normalized, '0');
            return $withoutLeadingZeroes !== '' ? $withoutLeadingZeroes : '0';
        }

        return strtoupper($normalized);
    }

    /**
     * Construye una condición SQL para comparar nombres de médico tolerando el token "SNS".
     */
    private function buildDoctorNameMatchCondition(string $leftExpression, string $rightExpression): string
    {
        $leftNormalized = $this->normalizeDoctorNameSql($leftExpression);
        $rightNormalized = $this->normalizeDoctorNameSql($rightExpression);

        return sprintf(
            "(UPPER(TRIM(%s)) = UPPER(TRIM(%s))
              OR %s = %s
              OR %s LIKE CONCAT('%%', %s, '%%')
              OR %s LIKE CONCAT('%%', %s, '%%'))",
            $leftExpression,
            $rightExpression,
            $leftNormalized,
            $rightNormalized,
            $leftNormalized,
            $rightNormalized,
            $rightNormalized,
            $leftNormalized
        );
    }

    private function normalizeDoctorNameSql(string $expression): string
    {
        return "TRIM(REPLACE(REPLACE(REPLACE(CONCAT(' ', UPPER(TRIM({$expression})), ' '), ' SNS ', ' '), '  ', ' '), '  ', ' '))";
    }

    private function hasTable(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $exists = false;

        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS total
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table"
            );
            $stmt->bindValue(':table', $table, PDO::PARAM_STR);
            $stmt->execute();
            $exists = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable) {
            $exists = false;
        }

        if (!$exists) {
            try {
                $safeTable = str_replace('`', '', $table);
                $this->db->query("SELECT 1 FROM `{$safeTable}` LIMIT 1");
                $exists = true;
            } catch (\Throwable) {
                $exists = false;
            }
        }

        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    private function hasColumnInTable(string $table, string $column): bool
    {
        if (isset($this->tableColumnsCache[$table][$column])) {
            return $this->tableColumnsCache[$table][$column];
        }

        if (!$this->hasTable($table)) {
            $this->tableColumnsCache[$table][$column] = false;
            return false;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS total
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table
                   AND COLUMN_NAME = :column"
            );
            $stmt->bindValue(':table', $table, PDO::PARAM_STR);
            $stmt->bindValue(':column', $column, PDO::PARAM_STR);
            $stmt->execute();
            $exists = ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable) {
            $exists = false;
        }

        if (!$exists) {
            try {
                $safeTable = str_replace('`', '', $table);
                $stmt = $this->db->prepare("SHOW COLUMNS FROM `{$safeTable}` LIKE :column");
                $stmt->bindValue(':column', $column, PDO::PARAM_STR);
                $stmt->execute();
                $exists = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Throwable) {
                $exists = false;
            }
        }

        if (!$exists) {
            try {
                $safeTable = str_replace('`', '', $table);
                $safeColumn = str_replace('`', '', $column);
                $this->db->query("SELECT `{$safeColumn}` FROM `{$safeTable}` LIMIT 0");
                $exists = true;
            } catch (\Throwable) {
                $exists = false;
            }
        }

        if (!isset($this->tableColumnsCache[$table])) {
            $this->tableColumnsCache[$table] = [];
        }
        $this->tableColumnsCache[$table][$column] = $exists;

        return $exists;
    }

    private function consultaDataTieneColumna(string $columna): bool
    {
        return $this->hasColumnInTable('consulta_data', $columna);
    }

    public function llamarTurno(?int $id, ?int $turno, string $nuevoEstado = 'Llamado'): ?array
    {
        $this->db->beginTransaction();

        try {
            if ($turno !== null && $turno > 0) {
                $sql = "SELECT sp.id, sp.turno, sp.estado FROM solicitud_procedimiento sp WHERE sp.turno = :turno FOR UPDATE";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':turno', $turno, \PDO::PARAM_INT);
                $stmt->execute();
                $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $sql = "SELECT sp.id, sp.turno, sp.estado FROM solicitud_procedimiento sp WHERE sp.id = :id FOR UPDATE";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
                $stmt->execute();
                $registro = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$registro && $id !== null) {
                    $fallback = $this->db->prepare("SELECT sp.id, sp.turno, sp.estado
                        FROM solicitud_procedimiento sp
                        WHERE sp.form_id = :form_id
                        ORDER BY sp.id DESC
                        LIMIT 1
                        FOR UPDATE");
                    $fallback->bindParam(':form_id', $id, \PDO::PARAM_INT);
                    $fallback->execute();
                    $registro = $fallback->fetch(PDO::FETCH_ASSOC);
                }
            }

            if (!$registro) {
                $this->db->rollBack();
                return null;
            }

            $estadoActualNormalizado = $this->normalizarEstadoTurnero((string)($registro['estado'] ?? ''));

            if ($estadoActualNormalizado === null) {
                $this->db->rollBack();
                return null;
            }

            if (empty($registro['turno'])) {
                $registro['turno'] = $this->asignarTurnoSiNecesario((int)$registro['id']);
            }

            $update = $this->db->prepare('UPDATE solicitud_procedimiento SET estado = :estado WHERE id = :id');
            $update->bindParam(':estado', $nuevoEstado, \PDO::PARAM_STR);
            $update->bindParam(':id', $registro['id'], \PDO::PARAM_INT);
            $update->execute();

            $detallesStmt = $this->db->prepare("SELECT
                    sp.id,
                    sp.turno,
                    sp.estado,
                    sp.hc_number,
                    sp.form_id,
                    sp.prioridad,
                    sp.created_at,
                    TRIM(CONCAT_WS(' ',
                      NULLIF(TRIM(pd.fname), ''),
                      NULLIF(TRIM(pd.mname), ''),
                      NULLIF(TRIM(pd.lname), ''),
                      NULLIF(TRIM(pd.lname2), '')
                    )) AS full_name
                FROM solicitud_procedimiento sp
                INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
                WHERE sp.id = :id");

            $detallesStmt->bindParam(':id', $registro['id'], \PDO::PARAM_INT);
            $detallesStmt->execute();
            $detalles = $detallesStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $this->db->commit();

            return $detalles;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function normalizarEstadoTurnero(string $estado): ?string
    {
        $mapa = [
            'recibido' => 'Recibido',
            'recibida' => 'Recibido',
            'llamado' => 'Llamado',
            'en atencion' => 'En atención',
            'en atención' => 'En atención',
            'atendido' => 'Atendido',
        ];

        $estadoLimpio = trim($estado);
        $clave = function_exists('mb_strtolower')
            ? mb_strtolower($estadoLimpio, 'UTF-8')
            : strtolower($estadoLimpio);

        return $mapa[$clave] ?? null;
    }

    private function asignarTurnoSiNecesario(int $id): ?int
    {
        $consulta = $this->db->prepare('SELECT turno FROM solicitud_procedimiento WHERE id = :id FOR UPDATE');
        $consulta->bindParam(':id', $id, \PDO::PARAM_INT);
        $consulta->execute();
        $actual = $consulta->fetchColumn();

        if ($actual !== false && $actual !== null) {
            return (int)$actual;
        }

        $maxStmt = $this->db->query('SELECT turno FROM solicitud_procedimiento WHERE turno IS NOT NULL ORDER BY turno DESC LIMIT 1 FOR UPDATE');
        $maxTurno = $maxStmt ? (int)$maxStmt->fetchColumn() : 0;
        $siguiente = $maxTurno + 1;

        $update = $this->db->prepare('UPDATE solicitud_procedimiento SET turno = :turno WHERE id = :id AND turno IS NULL');
        $update->bindParam(':turno', $siguiente, \PDO::PARAM_INT);
        $update->bindParam(':id', $id, \PDO::PARAM_INT);
        $update->execute();

        if ($update->rowCount() === 0) {
            $consulta->execute();
            $actual = $consulta->fetchColumn();
            return $actual !== false ? (int)$actual : null;
        }

        return $siguiente;
    }

    public function listarUsuariosAsignables(): array
    {
        $service = new LeadConfigurationService($this->db);

        return $service->getAssignableUsers();
    }

    public function obtenerFuentesCrm(): array
    {
        $service = new LeadConfigurationService($this->db);

        return $service->getSources();
    }

    private function selectSolicitudColumn(string $column, ?string $alias = null): string
    {
        $alias = $alias ?? $column;

        if ($this->hasSolicitudColumn($column)) {
            return 'sp.' . $this->quoteIdentifier($column) . ' AS ' . $this->quoteIdentifier($alias);
        }

        return 'NULL AS ' . $this->quoteIdentifier($alias);
    }

    private function hasSolicitudColumn(string $column): bool
    {
        if ($this->solicitudColumns === null) {
            $this->solicitudColumns = [];
            try {
                $stmt = $this->db->query('SHOW COLUMNS FROM solicitud_procedimiento');
                $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
                foreach ($rows as $row) {
                    $field = (string) ($row['Field'] ?? '');
                    if ($field !== '') {
                        $this->solicitudColumns[$field] = true;
                    }
                }
            } catch (\Throwable) {
                $this->solicitudColumns = [];
            }
        }

        return isset($this->solicitudColumns[$column]);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

}
