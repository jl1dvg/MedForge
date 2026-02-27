<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SolicitudesReadParityService
{
    private const DEFAULT_PIPELINE = [
        'Recibido',
        'Contacto inicial',
        'Seguimiento',
        'Docs completos',
        'Autorizado',
        'Agendado',
        'Cerrado',
        'Perdido',
    ];

    /**
     * @var array<int, array{slug:string,label:string,order:int,column:string,required:bool}>
     */
    private const DEFAULT_STAGES = [
        ['slug' => 'recibida', 'label' => 'Recibida', 'order' => 10, 'column' => 'recibida', 'required' => true],
        ['slug' => 'llamado', 'label' => 'Llamado', 'order' => 20, 'column' => 'llamado', 'required' => true],
        ['slug' => 'en-atencion', 'label' => 'En atencion', 'order' => 30, 'column' => 'revision-codigos', 'required' => true],
        ['slug' => 'revision-codigos', 'label' => 'Cobertura', 'order' => 40, 'column' => 'revision-codigos', 'required' => true],
        ['slug' => 'espera-documentos', 'label' => 'Documentacion', 'order' => 50, 'column' => 'espera-documentos', 'required' => true],
        ['slug' => 'apto-oftalmologo', 'label' => 'Apto oftalmologo', 'order' => 60, 'column' => 'apto-oftalmologo', 'required' => true],
        ['slug' => 'apto-anestesia', 'label' => 'Apto anestesia', 'order' => 70, 'column' => 'apto-anestesia', 'required' => true],
        ['slug' => 'listo-para-agenda', 'label' => 'Listo para agenda', 'order' => 80, 'column' => 'listo-para-agenda', 'required' => true],
        ['slug' => 'programada', 'label' => 'Programada', 'order' => 90, 'column' => 'programada', 'required' => true],
    ];

    private const META_CIRUGIA_CONFIRMADA_KEYS = [
        'cirugia_confirmada_form_id',
        'cirugia_confirmada_hc_number',
        'cirugia_confirmada_fecha_inicio',
        'cirugia_confirmada_lateralidad',
        'cirugia_confirmada_membrete',
        'cirugia_confirmada_by',
        'cirugia_confirmada_at',
    ];

    /**
     * @var array<string, bool>
     */
    private array $columnExistsCache = [];

    /**
     * @var array<string, bool>
     */
    private array $tableExistsCache = [];

    public function dashboardData(array $payload): array
    {
        $filters = $this->sanitizeFilters($payload);
        $range = $this->resolveRange($filters, 90);

        return [
            'filters' => [
                'date_from' => $range['from'],
                'date_to' => $range['to'],
            ],
            'data' => [
                'range' => [
                    'start' => $range['from'],
                    'end' => $range['to'],
                ],
                'volumen' => [
                    'por_mes' => $this->querySolicitudesPorMes($range['start'], $range['end']),
                    'por_procedimiento' => $this->querySolicitudesPorProcedimiento($range['start'], $range['end']),
                    'por_doctor' => $this->querySolicitudesPorDoctor($range['start'], $range['end']),
                    'por_afiliacion' => $this->querySolicitudesPorAfiliacion($range['start'], $range['end']),
                    'por_prioridad' => $this->querySolicitudesPorPrioridad($range['start'], $range['end']),
                ],
                'kanban' => $this->queryKanbanMetrics($range['start'], $range['end']),
                'cobertura' => $this->queryMailMetrics($range['start'], $range['end']),
            ],
        ];
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,options:array<string,mixed>}
     */
    public function kanbanData(array $payload): array
    {
        $filters = $this->sanitizeFilters($payload);
        if ($filters['fechaTexto'] === '' && $filters['date_from'] === null && $filters['date_to'] === null) {
            $end = new DateTimeImmutable('today');
            $start = $end->sub(new DateInterval('P30D'));
            $filters['fechaTexto'] = $start->format('d-m-Y') . ' - ' . $end->format('d-m-Y');
            $filters['date_from'] = $start->format('Y-m-d');
            $filters['date_to'] = $end->format('Y-m-d');
        }

        $rows = $this->querySolicitudesKanban($filters);
        $checklistMap = $this->queryChecklistMap(array_values(array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            $rows
        )));

        $kanban = [];
        foreach ($rows as $row) {
            $row = $this->normalizeSolicitudRow($row);
            $solicitudId = (int) ($row['id'] ?? 0);
            $checklistRows = $checklistMap[$solicitudId] ?? [];
            [$checklist, $progress, $kanbanState] = $this->buildChecklistContext((string) ($row['estado'] ?? ''), $checklistRows);

            $row['checklist'] = $checklist;
            $row['checklist_progress'] = $progress;
            $row['kanban_estado'] = $kanbanState['slug'];
            $row['kanban_estado_label'] = $kanbanState['label'];
            $row['kanban_next'] = [
                'slug' => $progress['next_slug'] ?? null,
                'label' => $progress['next_label'] ?? null,
            ];

            $kanban[] = array_merge($row, $this->computeOperationalMetadata($row));
        }

        $kanban = $this->sortSolicitudes($kanban, $this->kanbanPreferences()['sort'] ?? 'fecha_desc');
        $columnLimit = (int) ($this->kanbanPreferences()['column_limit'] ?? 0);
        if ($columnLimit > 0) {
            $kanban = $this->applyColumnLimit($kanban, $columnLimit);
        }

        $afiliaciones = $this->distinctSortedValues($kanban, 'afiliacion');
        $doctores = $this->distinctSortedValues($kanban, 'doctor');

        return [
            'data' => $kanban,
            'options' => [
                'afiliaciones' => $afiliaciones,
                'doctores' => $doctores,
                'crm' => [
                    'responsables' => $this->assignableUsers(),
                    'etapas' => $this->pipelineStages(),
                    'fuentes' => $this->sources(),
                    'kanban' => $this->kanbanPreferences(),
                ],
                'metrics' => $this->buildOperationalMetrics($kanban),
            ],
        ];
    }

    /**
     * @return array{data:array<int,array<string,mixed>>}
     */
    public function turneroData(array $requestedStates): array
    {
        $allowed = $this->turneroAllowedStates();
        $stateMap = [];
        foreach ($allowed as $state) {
            $key = $this->normalizeTurneroKey($state);
            if ($key !== '' && !isset($stateMap[$key])) {
                $stateMap[$key] = $state;
            }
        }

        $normalizedStates = [];
        foreach ($requestedStates as $state) {
            $key = $this->normalizeTurneroKey((string) $state);
            if ($key !== '' && isset($stateMap[$key])) {
                $normalizedStates[] = $stateMap[$key];
            }
        }

        if ($normalizedStates === []) {
            $normalizedStates = ['Llamado', 'En atencion'];
        }

        $placeholders = implode(', ', array_fill(0, count($normalizedStates), '?'));
        $rows = DB::select(
            'SELECT
                sp.id,
                sp.hc_number,
                sp.form_id,
                TRIM(CONCAT_WS(" ", NULLIF(TRIM(pd.fname), ""), NULLIF(TRIM(pd.mname), ""), NULLIF(TRIM(pd.lname), ""), NULLIF(TRIM(pd.lname2), ""))) AS full_name,
                sp.estado,
                sp.prioridad,
                sp.procedimiento,
                sp.created_at,
                sp.turno
             FROM solicitud_procedimiento sp
             INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
             WHERE sp.estado IN (' . $placeholders . ')
             ORDER BY CASE WHEN sp.turno IS NULL THEN 1 ELSE 0 END,
                      sp.turno DESC,
                      sp.created_at DESC,
                      sp.id DESC',
            $normalizedStates
        );

        $data = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $item['full_name'] = trim((string) ($item['full_name'] ?? '')) !== ''
                ? (string) $item['full_name']
                : 'Paciente sin nombre';
            $item['turno'] = isset($item['turno']) ? (int) $item['turno'] : null;

            $stateKey = $this->normalizeTurneroKey((string) ($item['estado'] ?? ''));
            $item['estado'] = $stateMap[$stateKey] ?? ($item['estado'] ?? null);

            $item['hora'] = null;
            $item['fecha'] = null;
            $created = $this->parseDate($item['created_at'] ?? null);
            if ($created instanceof DateTimeImmutable) {
                $item['hora'] = $created->format('H:i');
                $item['fecha'] = $created->format('d/m/Y');
            }

            $data[] = $item;
        }

        return ['data' => $data];
    }

    /**
     * @return array<string,mixed>
     */
    public function crmResumen(int $solicitudId): array
    {
        $detalle = $this->queryCrmDetalle($solicitudId);
        if ($detalle === null) {
            throw new RuntimeException('Solicitud no encontrada');
        }

        return [
            'detalle' => $detalle,
            'notas' => $this->queryCrmNotas($solicitudId),
            'adjuntos' => $this->queryCrmAdjuntos($solicitudId),
            'tareas' => $this->queryCrmTareas($solicitudId),
            'campos_personalizados' => $this->queryCrmMeta($solicitudId),
            'lead' => null,
            'crm_resumen' => null,
            'project' => null,
            'bloqueos_agenda' => $this->queryBloqueosAgenda($solicitudId),
            'cobertura_mails' => $this->queryCoberturaMails($solicitudId),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function conciliacionCirugiasMes(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $derivacionLateralidadExpr = $this->selectSolicitudColumn('derivacion_lateralidad');
        $hasMetaTable = $this->tableExists('solicitud_crm_meta');
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

        $rows = array_map(static fn(object $row): array => (array) $row, DB::select($sql, $params));
        if ($rows === []) {
            return [];
        }

        $hcNumbers = [];
        $usuarioIds = [];
        $rawHcByKey = [];

        foreach ($rows as $row) {
            $hc = trim((string) ($row['hc_number'] ?? ''));
            if ($hc !== '') {
                $hcNumbers[$hc] = true;
                $hcKey = $this->normalizarHcClave($hc);
                if ($hcKey !== '') {
                    $rawHcByKey[$hcKey][$hc] = true;
                }
            }

            if ($hasMetaTable) {
                $confirmadoBy = (int) ($row['protocolo_confirmado_by'] ?? 0);
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
            $hcKey = $this->normalizarHcClave((string) $hc);
            foreach ($protocolos as $protocolo) {
                $formId = trim((string) ($protocolo['form_id'] ?? ''));
                if ($formId !== '') {
                    $protocolosByFormId[$formId] = $protocolo;
                }

                $protocoloHcKey = $hcKey !== ''
                    ? $hcKey
                    : $this->normalizarHcClave((string) ($protocolo['hc_number'] ?? ''));
                if ($protocoloHcKey !== '') {
                    $protocolosByHcKey[$protocoloHcKey][] = $protocolo;
                }
            }
        }

        $rowsByHc = [];
        foreach ($rows as $index => $row) {
            $hc = $this->normalizarHcClave((string) ($row['hc_number'] ?? ''));
            if ($hc === '') {
                continue;
            }
            $rowsByHc[$hc][] = $index;
        }

        foreach ($rowsByHc as $hc => $indexes) {
            usort($indexes, function (int $a, int $b) use ($rows): int {
                $tsA = $this->toTimestamp((string) ($rows[$a]['fecha_solicitud'] ?? ''));
                $tsB = $this->toTimestamp((string) ($rows[$b]['fecha_solicitud'] ?? ''));

                if ($tsA === $tsB) {
                    return ((int) ($rows[$a]['id'] ?? 0)) <=> ((int) ($rows[$b]['id'] ?? 0));
                }

                return $tsA <=> $tsB;
            });

            foreach ($indexes as $index) {
                $row = &$rows[$index];
                $confirmado = $this->resolverProtocoloConfirmado($row, $protocolosByFormId, $usuariosById);

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
                $fechaSolicitudTs = $this->toTimestamp((string) ($row['fecha_solicitud'] ?? ''));

                foreach ($protocolosPaciente as $protocolo) {
                    $formId = trim((string) ($protocolo['form_id'] ?? ''));
                    if ($formId === '') {
                        continue;
                    }

                    $fechaProtocoloTs = $this->toTimestamp((string) ($protocolo['fecha_inicio'] ?? ''));
                    $isPosterior = !($fechaSolicitudTs > 0 && $fechaProtocoloTs > 0 && $fechaProtocoloTs < $fechaSolicitudTs);

                    $lateralidadProtocolo = trim((string) ($protocolo['lateralidad'] ?? ''));
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
            $nombre = trim((string) ($row['full_name'] ?? ''));
            $row['full_name'] = $nombre !== '' ? $nombre : null;
            $row['ojo_resuelto'] = $this->resolverLateralidadSolicitud($row);
            $estado = strtolower(trim((string) ($row['estado'] ?? '')));
            if ($row['protocolo_confirmado'] === null && $estado === 'completado' && $row['protocolo_posterior_compatible'] !== null) {
                $row['protocolo_confirmado'] = $row['protocolo_posterior_compatible'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerSolicitudConciliacionPorId(int $solicitudId): ?array
    {
        if ($solicitudId <= 0) {
            return null;
        }

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
            WHERE sp.id = ?
            LIMIT 1",
            $derivacionLateralidadExpr
        );

        $rows = DB::select($sql, [$solicitudId]);
        if ($rows === []) {
            return null;
        }

        $row = (array) $rows[0];
        $row['ojo_resuelto'] = $this->resolverLateralidadSolicitud($row);

        return $row;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function obtenerProtocoloConciliacionPorFormId(string $formId): ?array
    {
        $formId = trim($formId);
        if ($formId === '') {
            return null;
        }

        if (
            !$this->tableExists('protocolo_data')
            || !$this->tableHasColumn('protocolo_data', 'form_id')
            || !$this->tableHasColumn('protocolo_data', 'hc_number')
        ) {
            return null;
        }

        $lateralidadExpr = $this->tableHasColumn('protocolo_data', 'lateralidad')
            ? 'lateralidad'
            : 'NULL AS lateralidad';
        $membreteExpr = $this->tableHasColumn('protocolo_data', 'membrete')
            ? 'membrete'
            : 'NULL AS membrete';
        $statusExpr = $this->tableHasColumn('protocolo_data', 'status')
            ? 'status'
            : 'NULL AS status';
        $fechaExpr = $this->tableHasColumn('protocolo_data', 'fecha_inicio')
            ? 'fecha_inicio'
            : 'NULL AS fecha_inicio';

        $rows = DB::select(
            "SELECT
                form_id,
                hc_number,
                {$fechaExpr},
                {$lateralidadExpr},
                {$membreteExpr},
                {$statusExpr}
            FROM protocolo_data
            WHERE form_id = ?
            ORDER BY fecha_inicio DESC
            LIMIT 1",
            [$formId]
        );

        if ($rows === []) {
            return null;
        }

        return (array) $rows[0];
    }

    /**
     * @param array<int, array<string,mixed>> $rows
     * @param array{hc_number?:string,solicitud_id?:int,form_id?:string,limit?:int} $options
     * @return array<string,mixed>
     */
    public function diagnosticarConciliacion(array $rows, array $options = []): array
    {
        $hcFilterRaw = trim((string) ($options['hc_number'] ?? ''));
        $hcFilterKey = $this->normalizarHcClave($hcFilterRaw);
        $solicitudIdFilter = (int) ($options['solicitud_id'] ?? 0);
        $formIdFilter = trim((string) ($options['form_id'] ?? ''));
        $limit = (int) ($options['limit'] ?? 25);
        $limit = max(1, min(200, $limit));

        $selected = [];
        foreach ($rows as $row) {
            if ($solicitudIdFilter > 0 && (int) ($row['id'] ?? 0) !== $solicitudIdFilter) {
                continue;
            }

            if ($formIdFilter !== '' && trim((string) ($row['form_id'] ?? '')) !== $formIdFilter) {
                continue;
            }

            if ($hcFilterKey !== '') {
                $rowHcKey = $this->normalizarHcClave((string) ($row['hc_number'] ?? ''));
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
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function diagnosticarFilaConciliacion(array $row): array
    {
        $hcRaw = trim((string) ($row['hc_number'] ?? ''));
        $hcKey = $this->normalizarHcClave($hcRaw);
        $fechaSolicitud = (string) ($row['fecha_solicitud'] ?? '');
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
            $protocoloFechaTs = $this->toTimestamp((string) ($protocolo['fecha_inicio'] ?? ''));
            $isPosterior = !($fechaSolicitudTs > 0 && $protocoloFechaTs > 0 && $protocoloFechaTs < $fechaSolicitudTs);

            $lateralidadProtocolo = trim((string) ($protocolo['lateralidad'] ?? ''));
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
                    'form_id' => trim((string) ($protocolo['form_id'] ?? '')),
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
                'id' => (int) ($row['id'] ?? 0),
                'form_id' => trim((string) ($row['form_id'] ?? '')),
                'hc_number' => $hcRaw,
                'hc_key' => $hcKey,
                'fecha_solicitud' => $fechaSolicitud !== '' ? $fechaSolicitud : null,
                'lateralidad' => $solicitudLateralidad,
                'lateralidad_normalizada' => $solicitudLateralidadNormalizada,
                'estado' => trim((string) ($row['estado'] ?? '')),
            ],
            'resumen' => [
                'total_protocolos' => $totalProtocolos,
                'posteriores' => $posteriores,
                'compatibles_lateralidad' => $compatibles,
                'compatibles_posteriores' => $compatiblesPosteriores,
                'motivo' => $motivo,
            ],
            'match_actual' => $actual ? [
                'form_id' => trim((string) ($actual['form_id'] ?? '')),
                'fecha_inicio' => $actual['fecha_inicio'] ?? null,
                'lateralidad' => trim((string) ($actual['lateralidad'] ?? '')),
            ] : null,
            'match_esperado' => $esperado ? [
                'form_id' => trim((string) ($esperado['form_id'] ?? '')),
                'fecha_inicio' => $esperado['fecha_inicio'] ?? null,
                'lateralidad' => trim((string) ($esperado['lateralidad'] ?? '')),
            ] : null,
            'protocolos_muestra' => $muestra,
        ];
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    private function cargarProtocolosDebugPorHc(string $hcRaw, string $hcKey): array
    {
        $collected = [];
        $grouped = $this->cargarProtocolosPorHc($hcRaw !== '' ? [$hcRaw] : [], '1900-01-01 00:00:00');

        foreach ($grouped as $hcValue => $protocolos) {
            $groupKey = $this->normalizarHcClave((string) $hcValue);
            if ($hcKey !== '' && $groupKey !== '' && $groupKey !== $hcKey) {
                continue;
            }
            foreach ($protocolos as $protocolo) {
                $formId = trim((string) ($protocolo['form_id'] ?? ''));
                if ($formId !== '') {
                    $collected[$formId] = $protocolo;
                }
            }
        }

        if ($collected !== []) {
            usort($collected, function (array $a, array $b): int {
                $tsA = $this->toTimestamp((string) ($a['fecha_inicio'] ?? ''));
                $tsB = $this->toTimestamp((string) ($b['fecha_inicio'] ?? ''));
                if ($tsA === $tsB) {
                    return strcmp((string) ($a['form_id'] ?? ''), (string) ($b['form_id'] ?? ''));
                }
                return $tsA <=> $tsB;
            });
            return array_values($collected);
        }

        if (
            !$this->tableExists('protocolo_data')
            || !$this->tableHasColumn('protocolo_data', 'form_id')
            || !$this->tableHasColumn('protocolo_data', 'hc_number')
            || !$this->tableHasColumn('protocolo_data', 'fecha_inicio')
        ) {
            return [];
        }

        $lateralidadExpr = $this->tableHasColumn('protocolo_data', 'lateralidad')
            ? 'lateralidad'
            : 'NULL AS lateralidad';
        $membreteExpr = $this->tableHasColumn('protocolo_data', 'membrete')
            ? 'membrete'
            : 'NULL AS membrete';
        $statusExpr = $this->tableHasColumn('protocolo_data', 'status')
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

        $rows = array_map(static fn(object $row): array => (array) $row, DB::select($sql, $params));

        return array_values(array_filter($rows, static function (array $row): bool {
            return trim((string) ($row['form_id'] ?? '')) !== '';
        }));
    }

    public function lateralidadesCompatibles(?string $solicitudLateralidad, ?string $protocoloLateralidad): bool
    {
        $solicitud = $this->normalizarLateralidades($solicitudLateralidad);
        $protocolo = $this->normalizarLateralidades($protocoloLateralidad);

        // Si falta lateralidad en alguno de los lados, se considera "compatible por dato incompleto"
        // para permitir conciliación asistida por usuario.
        if ($solicitud === [] || $protocolo === []) {
            return true;
        }

        return array_intersect($solicitud, $protocolo) !== [];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{afiliacion:string,doctor:string,prioridad:string,fechaTexto:string,date_from:?string,date_to:?string,search:string}
     */
    private function sanitizeFilters(array $payload): array
    {
        $dateFrom = $this->normalizeDateInput($payload['date_from'] ?? null);
        $dateTo = $this->normalizeDateInput($payload['date_to'] ?? null);

        $fechaTexto = trim((string) ($payload['fechaTexto'] ?? ''));
        if (($dateFrom === null || $dateTo === null) && $fechaTexto !== '') {
            [$parsedFrom, $parsedTo] = $this->parseDateRange($fechaTexto);
            $dateFrom ??= $parsedFrom;
            $dateTo ??= $parsedTo;
        }

        return [
            'afiliacion' => trim((string) ($payload['afiliacion'] ?? '')),
            'doctor' => trim((string) ($payload['doctor'] ?? '')),
            'prioridad' => trim((string) ($payload['prioridad'] ?? '')),
            'fechaTexto' => $fechaTexto,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => trim((string) ($payload['search'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{start:DateTimeImmutable,end:DateTimeImmutable,from:string,to:string}
     */
    private function resolveRange(array $filters, int $fallbackDays): array
    {
        $now = new DateTimeImmutable('now');
        $start = $filters['date_from'] ? $this->parseDate($filters['date_from']) : null;
        $end = $filters['date_to'] ? $this->parseDate($filters['date_to']) : null;

        if (!$start) {
            $start = $now->sub(new DateInterval('P' . max(1, $fallbackDays) . 'D'));
        }
        if (!$end) {
            $end = $now;
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => $start,
            'end' => $end,
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    private function querySolicitudesKanban(array $filters): array
    {
        $sql = 'SELECT
                sp.id,
                sp.hc_number,
                sp.form_id,
                TRIM(CONCAT_WS(" ", NULLIF(TRIM(pd.fname), ""), NULLIF(TRIM(pd.mname), ""), NULLIF(TRIM(pd.lname), ""), NULLIF(TRIM(pd.lname2), ""))) AS full_name,
                sp.tipo,
                COALESCE(NULLIF(TRIM(sp.afiliacion), ""), NULLIF(TRIM(pd.afiliacion), "")) AS afiliacion,
                pd.celular AS paciente_celular,
                sp.procedimiento,
                sp.doctor,
                sp.estado,
                cd.fecha AS fecha,
                COALESCE(cd.fecha, sp.fecha, sp.created_at) AS fecha_programada,
                sp.duracion,
                sp.ojo,
                sp.prioridad,
                sp.producto,
                sp.observacion,
                sp.created_at,
                ' . ($this->tableHasColumn('solicitud_procedimiento', 'turno') ? 'sp.turno' : 'NULL') . ' AS turno,
                ' . ($this->tableHasColumn('solicitud_procedimiento', 'secuencia') ? 'sp.secuencia' : 'NULL') . ' AS secuencia,
                ' . ($this->tableHasColumn('solicitud_procedimiento', 'derivacion_fecha_vigencia_sel') ? 'sp.derivacion_fecha_vigencia_sel' : 'NULL') . ' AS derivacion_fecha_vigencia,
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
                      AND u.profile_photo <> ""
                      AND LOWER(TRIM(sp.doctor)) LIKE CONCAT("%", LOWER(TRIM(u.nombre)), "%")
                    ORDER BY u.id ASC
                    LIMIT 1
                ) AS doctor_avatar,
                COALESCE(notas.total_notas, 0) AS crm_total_notas,
                COALESCE(adjuntos.total_adjuntos, 0) AS crm_total_adjuntos,
                COALESCE(tareas.tareas_pendientes, 0) AS crm_tareas_pendientes,
                COALESCE(tareas.tareas_total, 0) AS crm_tareas_total,
                tareas.proximo_vencimiento AS crm_proximo_vencimiento
            FROM solicitud_procedimiento sp
            INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
            LEFT JOIN (
                SELECT c.hc_number, c.form_id, MAX(c.fecha) AS fecha
                FROM consulta_data c
                GROUP BY c.hc_number, c.form_id
            ) cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
            LEFT JOIN solicitud_crm_detalles detalles ON detalles.solicitud_id = sp.id
            LEFT JOIN users responsable ON detalles.responsable_id = responsable.id
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
                SELECT source_ref_id,
                       COUNT(*) AS tareas_total,
                       SUM(CASE WHEN status IN ("pendiente", "en_progreso", "en_proceso") THEN 1 ELSE 0 END) AS tareas_pendientes,
                       MIN(CASE WHEN status IN ("pendiente", "en_progreso", "en_proceso") THEN COALESCE(due_at, CONCAT(due_date, " 23:59:59")) END) AS proximo_vencimiento
                FROM crm_tasks
                WHERE source_module = "solicitudes"
                GROUP BY source_ref_id
            ) tareas ON tareas.source_ref_id = sp.id
            WHERE sp.procedimiento IS NOT NULL
              AND TRIM(sp.procedimiento) <> ""
              AND TRIM(sp.procedimiento) <> "SELECCIONE"';

        $params = [];

        if ($filters['afiliacion'] !== '') {
            $sql .= ' AND COALESCE(NULLIF(TRIM(sp.afiliacion), ""), NULLIF(TRIM(pd.afiliacion), "")) LIKE ?';
            $params[] = '%' . $filters['afiliacion'] . '%';
        }

        if ($filters['doctor'] !== '') {
            $sql .= ' AND sp.doctor LIKE ?';
            $params[] = '%' . $filters['doctor'] . '%';
        }

        if ($filters['prioridad'] !== '') {
            $sql .= ' AND sp.prioridad = ?';
            $params[] = $filters['prioridad'];
        }

        if ($filters['date_from'] !== null) {
            $sql .= ' AND DATE(COALESCE(cd.fecha, sp.fecha, sp.created_at)) >= ?';
            $params[] = $filters['date_from'];
        }

        if ($filters['date_to'] !== null) {
            $sql .= ' AND DATE(COALESCE(cd.fecha, sp.fecha, sp.created_at)) <= ?';
            $params[] = $filters['date_to'];
        }

        $sql .= ' ORDER BY COALESCE(cd.fecha, sp.fecha, sp.created_at) DESC, sp.id DESC';

        $rows = array_map(static fn(object $row): array => (array) $row, DB::select($sql, $params));

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search === '') {
            return $rows;
        }

        $term = mb_strtolower($search);
        $keys = ['full_name', 'hc_number', 'procedimiento', 'doctor', 'afiliacion', 'estado', 'crm_pipeline_stage'];

        return array_values(array_filter($rows, static function (array $row) use ($keys, $term): bool {
            foreach ($keys as $key) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($value !== '' && str_contains(mb_strtolower($value), $term)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function queryChecklistMap(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        try {
            $rows = DB::table('solicitud_checklist')
                ->select(['solicitud_id', 'etapa_slug', 'completado_at'])
                ->whereIn('solicitud_id', $ids)
                ->orderBy('id')
                ->get();
        } catch (Throwable) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $sid = (int) ($row->solicitud_id ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $map[$sid] ??= [];
            $map[$sid][] = [
                'etapa_slug' => (string) ($row->etapa_slug ?? ''),
                'completado_at' => $row->completado_at,
            ];
        }

        return $map;
    }

    /**
     * @param array<int,array<string,mixed>> $checklistRows
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>,2:array{slug:string,label:string}}
     */
    private function buildChecklistContext(string $legacyState, array $checklistRows): array
    {
        $bySlug = [];
        foreach ($checklistRows as $row) {
            $slug = $this->normalizeKanbanSlug((string) ($row['etapa_slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $bySlug[$slug] = [
                'completed' => !empty($row['completado_at']),
                'completado_at' => $row['completado_at'] !== null ? (string) $row['completado_at'] : null,
            ];
        }

        $legacySlug = $this->normalizeKanbanSlug($legacyState);
        $stageIndex = $this->stageIndex($legacySlug);

        $checklist = [];
        foreach (self::DEFAULT_STAGES as $index => $stage) {
            $slug = $stage['slug'];
            $fromDb = $bySlug[$slug] ?? null;
            $completed = $fromDb['completed'] ?? false;

            // Fallback cuando no hay filas de checklist: inferir por estado legacy.
            if ($fromDb === null && $stageIndex !== null) {
                $completed = $index <= $stageIndex;
            }

            $checklist[] = [
                'slug' => $slug,
                'label' => $stage['label'],
                'order' => $stage['order'],
                'required' => $stage['required'],
                'completed' => $completed,
                'completado_at' => $fromDb['completado_at'] ?? null,
            ];
        }

        $total = count($checklist);
        $completed = count(array_filter($checklist, static fn(array $item): bool => !empty($item['completed'])));
        $percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;

        $next = null;
        foreach ($checklist as $item) {
            if (empty($item['completed']) && !empty($item['required'])) {
                $next = $item;
                break;
            }
        }

        $progress = [
            'total' => $total,
            'completed' => $completed,
            'percent' => $percent,
            'next_slug' => $next['slug'] ?? null,
            'next_label' => $next['label'] ?? null,
        ];

        if ($legacySlug === 'completado') {
            $kanbanSlug = 'completado';
        } elseif ($legacySlug === 'programada') {
            $kanbanSlug = 'programada';
        } elseif (in_array($legacySlug, ['recibida', 'llamado'], true)) {
            $kanbanSlug = $legacySlug;
        } elseif ($next !== null) {
            $stage = $this->stageBySlug((string) $next['slug']);
            $kanbanSlug = (string) ($stage['column'] ?? $next['slug']);
        } else {
            $kanbanSlug = 'programada';
        }

        return [
            $checklist,
            $progress,
            [
                'slug' => $kanbanSlug,
                'label' => $this->kanbanLabel($kanbanSlug),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeSolicitudRow(array $row): array
    {
        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function computeOperationalMetadata(array $row): array
    {
        $now = new DateTimeImmutable('now');
        $deadline = $this->parseDate($row['fecha_programada'] ?? ($row['fecha'] ?? null))
            ?? $this->parseDate($row['created_at'] ?? null);

        $warningHours = max(1, (int) ($this->settingsOptions(['solicitudes_sla_warning_hours'])['solicitudes_sla_warning_hours'] ?? 24));
        $criticalHours = max(1, (int) ($this->settingsOptions(['solicitudes_sla_critical_hours'])['solicitudes_sla_critical_hours'] ?? 6));

        $hoursRemaining = null;
        $slaStatus = 'sin_fecha';
        if ($deadline instanceof DateTimeImmutable) {
            $hoursRemaining = ($deadline->getTimestamp() - $now->getTimestamp()) / 3600;
            if ($hoursRemaining < 0) {
                $slaStatus = 'vencido';
            } elseif ($hoursRemaining <= $criticalHours) {
                $slaStatus = 'critico';
            } elseif ($hoursRemaining <= $warningHours) {
                $slaStatus = 'advertencia';
            } else {
                $slaStatus = 'en_rango';
            }
        }

        $autoPriority = 'normal';
        if (in_array($slaStatus, ['vencido', 'critico'], true)) {
            $autoPriority = 'urgente';
        } elseif ($slaStatus === 'advertencia') {
            $autoPriority = 'pendiente';
        }

        $manualPriority = trim((string) ($row['prioridad'] ?? ''));
        $showPriority = $manualPriority !== '' ? $manualPriority : ucfirst($autoPriority);

        $alerts = [];
        $alertReprogramacion = $deadline instanceof DateTimeImmutable && $deadline < $now->sub(new DateInterval('PT2H'));
        if ($alertReprogramacion) {
            $alerts[] = 'Requiere reprogramacion';
        }

        $alertDocs = (int) ($row['crm_total_adjuntos'] ?? 0) <= 0;
        if ($alertDocs) {
            $alerts[] = 'Faltan documentos de soporte';
        }

        $alertAuth = (int) ($row['crm_tareas_pendientes'] ?? 0) > 0;
        if ($alertAuth) {
            $alerts[] = 'Autorizacion pendiente';
        }

        return [
            'prioridad' => $showPriority,
            'prioridad_origen' => $manualPriority !== '' ? 'manual' : 'automatico',
            'prioridad_automatica' => $autoPriority,
            'prioridad_automatica_label' => ucfirst($autoPriority),
            'sla_status' => $slaStatus,
            'sla_deadline' => $deadline?->format(DateTimeImmutable::ATOM),
            'sla_hours_remaining' => $hoursRemaining !== null ? round($hoursRemaining, 2) : null,
            'alert_reprogramacion' => $alertReprogramacion,
            'alert_pendiente_consentimiento' => false,
            'alert_documentos_faltantes' => $alertDocs,
            'alert_autorizacion_pendiente' => $alertAuth,
            'alertas_operativas' => $alerts,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private function buildOperationalMetrics(array $rows): array
    {
        $metrics = [
            'sla' => ['en_rango' => 0, 'advertencia' => 0, 'critico' => 0, 'vencido' => 0, 'sin_fecha' => 0],
            'alerts' => [
                'requiere_reprogramacion' => 0,
                'pendiente_consentimiento' => 0,
                'documentos_faltantes' => 0,
                'autorizacion_pendiente' => 0,
            ],
            'prioridad' => ['urgente' => 0, 'pendiente' => 0, 'normal' => 0],
            'teams' => [],
        ];

        foreach ($rows as $row) {
            $sla = (string) ($row['sla_status'] ?? 'sin_fecha');
            if (!isset($metrics['sla'][$sla])) {
                $metrics['sla'][$sla] = 0;
            }
            $metrics['sla'][$sla]++;

            $auto = (string) ($row['prioridad_automatica'] ?? 'normal');
            if (!isset($metrics['prioridad'][$auto])) {
                $metrics['prioridad'][$auto] = 0;
            }
            $metrics['prioridad'][$auto]++;

            if (!empty($row['alert_reprogramacion'])) {
                $metrics['alerts']['requiere_reprogramacion']++;
            }
            if (!empty($row['alert_pendiente_consentimiento'])) {
                $metrics['alerts']['pendiente_consentimiento']++;
            }
            if (!empty($row['alert_documentos_faltantes'])) {
                $metrics['alerts']['documentos_faltantes']++;
            }
            if (!empty($row['alert_autorizacion_pendiente'])) {
                $metrics['alerts']['autorizacion_pendiente']++;
            }

            $teamKey = (string) ($row['crm_responsable_id'] ?? 'sin_asignar');
            if (!isset($metrics['teams'][$teamKey])) {
                $metrics['teams'][$teamKey] = [
                    'responsable_id' => $row['crm_responsable_id'] ?? null,
                    'responsable_nombre' => $row['crm_responsable_nombre'] ?? 'Sin responsable',
                    'total' => 0,
                    'vencido' => 0,
                    'critico' => 0,
                    'advertencia' => 0,
                    'reprogramar' => 0,
                    'sin_consentimiento' => 0,
                    'documentos' => 0,
                    'autorizaciones' => 0,
                ];
            }

            $metrics['teams'][$teamKey]['total']++;
            if ($sla === 'vencido') {
                $metrics['teams'][$teamKey]['vencido']++;
            }
            if ($sla === 'critico') {
                $metrics['teams'][$teamKey]['critico']++;
            }
            if ($sla === 'advertencia') {
                $metrics['teams'][$teamKey]['advertencia']++;
            }
            if (!empty($row['alert_reprogramacion'])) {
                $metrics['teams'][$teamKey]['reprogramar']++;
            }
            if (!empty($row['alert_pendiente_consentimiento'])) {
                $metrics['teams'][$teamKey]['sin_consentimiento']++;
            }
            if (!empty($row['alert_documentos_faltantes'])) {
                $metrics['teams'][$teamKey]['documentos']++;
            }
            if (!empty($row['alert_autorizacion_pendiente'])) {
                $metrics['teams'][$teamKey]['autorizaciones']++;
            }
        }

        uasort($metrics['teams'], static function (array $a, array $b): int {
            $scoreA = ($a['vencido'] * 3) + ($a['critico'] * 2) + $a['advertencia'];
            $scoreB = ($b['vencido'] * 3) + ($b['critico'] * 2) + $b['advertencia'];

            if ($scoreA === $scoreB) {
                return strcmp((string) ($a['responsable_nombre'] ?? ''), (string) ($b['responsable_nombre'] ?? ''));
            }

            return $scoreB <=> $scoreA;
        });

        return $metrics;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function assignableUsers(): array
    {
        $rows = DB::select('SELECT id, nombre, email, profile_photo, especialidad FROM users ORDER BY nombre');

        $users = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $item['avatar'] = $this->formatProfilePhoto($item['profile_photo'] ?? null);
            $item['profile_photo'] = $item['avatar'];
            $users[] = $item;
        }

        return $users;
    }

    /**
     * @return array<int,string>
     */
    private function pipelineStages(): array
    {
        $options = $this->settingsOptions(['crm_pipeline_stages']);
        $raw = trim((string) ($options['crm_pipeline_stages'] ?? ''));
        if ($raw === '') {
            return self::DEFAULT_PIPELINE;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded) && $decoded !== []) {
            $values = array_values(array_filter(array_map(static fn($value): string => trim((string) $value), $decoded), static fn(string $value): bool => $value !== ''));
            return $values !== [] ? $values : self::DEFAULT_PIPELINE;
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('/[,\n\r;]+/', $raw) ?: []), static fn(string $value): bool => $value !== ''));

        return $parts !== [] ? $parts : self::DEFAULT_PIPELINE;
    }

    /**
     * @return array<int,string>
     */
    private function sources(): array
    {
        $values = [];

        try {
            $rows = DB::select('SELECT DISTINCT fuente FROM solicitud_crm_detalles WHERE fuente IS NOT NULL AND TRIM(fuente) <> ""');
            foreach ($rows as $row) {
                $this->appendSource($values, (string) ($row->fuente ?? ''));
            }
        } catch (Throwable) {
            // Ignore source table availability issues.
        }

        try {
            $rows = DB::select('SELECT DISTINCT source FROM crm_leads WHERE source IS NOT NULL AND TRIM(source) <> ""');
            foreach ($rows as $row) {
                $this->appendSource($values, (string) ($row->source ?? ''));
            }
        } catch (Throwable) {
            // Ignore source table availability issues.
        }

        sort($values, SORT_NATURAL | SORT_FLAG_CASE);

        return $values;
    }

    /**
     * @return array{sort:string,column_limit:int}
     */
    private function kanbanPreferences(): array
    {
        $options = $this->settingsOptions(['crm_kanban_sort', 'crm_kanban_column_limit']);
        $sort = trim((string) ($options['crm_kanban_sort'] ?? 'fecha_desc'));
        if (!in_array($sort, ['fecha_desc', 'fecha_asc', 'prioridad_desc', 'prioridad_asc'], true)) {
            $sort = 'fecha_desc';
        }

        return [
            'sort' => $sort,
            'column_limit' => max(0, (int) ($options['crm_kanban_column_limit'] ?? 0)),
        ];
    }

    /**
     * @return array<int,string>
     */
    private function turneroAllowedStates(): array
    {
        $options = $this->settingsOptions(['solicitudes_turnero_allowed_states']);
        $raw = trim((string) ($options['solicitudes_turnero_allowed_states'] ?? ''));
        if ($raw === '') {
            return ['Llamado', 'En atencion'];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $states = array_values(array_filter(array_map(static fn($value): string => trim((string) $value), $decoded), static fn(string $value): bool => $value !== ''));
            if ($states !== []) {
                return $states;
            }
        }

        $states = array_values(array_filter(array_map('trim', preg_split('/[,\n\r;]+/', $raw) ?: []), static fn(string $value): bool => $value !== ''));

        return $states !== [] ? $states : ['Llamado', 'En atencion'];
    }

    /**
     * @return array<string,string>
     */
    private function settingsOptions(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        try {
            $rows = DB::select(
                'SELECT name, value FROM settings WHERE name IN (' . $placeholders . ')',
                array_values($keys)
            );
        } catch (Throwable) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $name = (string) ($row->name ?? '');
            if ($name === '') {
                continue;
            }
            $options[$name] = (string) ($row->value ?? '');
        }

        return $options;
    }

    /**
     * @return array{labels:array<int,string>,totals:array<int,int>}
     */
    private function querySolicitudesPorMes(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $rows = DB::select(
            'SELECT DATE_FORMAT(COALESCE(sp.created_at, sp.fecha), "%Y-%m") AS mes, COUNT(*) AS total
             FROM solicitud_procedimiento sp
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN ? AND ?
             GROUP BY DATE_FORMAT(COALESCE(sp.created_at, sp.fecha), "%Y-%m")
             ORDER BY mes ASC',
            [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
        );

        $labels = [];
        $totals = [];
        foreach ($rows as $row) {
            $labels[] = (string) ($row->mes ?? '');
            $totals[] = (int) ($row->total ?? 0);
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    /**
     * @return array{labels:array<int,string>,totals:array<int,int>}
     */
    private function querySolicitudesPorProcedimiento(DateTimeImmutable $start, DateTimeImmutable $end, int $limit = 10): array
    {
        $rows = DB::select(
            'SELECT CONCAT_WS(" · ", NULLIF(TRIM(sp.procedimiento), ""), NULLIF(TRIM(sp.producto), "")) AS procedimiento,
                    COUNT(*) AS total
             FROM solicitud_procedimiento sp
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN ? AND ?
               AND sp.procedimiento IS NOT NULL
               AND TRIM(sp.procedimiento) <> ""
               AND TRIM(sp.procedimiento) <> "SELECCIONE"
             GROUP BY CONCAT_WS(" · ", NULLIF(TRIM(sp.procedimiento), ""), NULLIF(TRIM(sp.producto), ""))
             ORDER BY total DESC
             LIMIT ' . max(1, (int) $limit),
            [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
        );

        $labels = [];
        $totals = [];
        foreach ($rows as $row) {
            $labels[] = trim((string) ($row->procedimiento ?? '')) !== '' ? (string) $row->procedimiento : 'Sin procedimiento';
            $totals[] = (int) ($row->total ?? 0);
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    /**
     * @return array{labels:array<int,string>,totals:array<int,int>}
     */
    private function querySolicitudesPorDoctor(DateTimeImmutable $start, DateTimeImmutable $end, int $limit = 15): array
    {
        $rows = DB::select(
            'SELECT COALESCE(NULLIF(TRIM(sp.doctor), ""), "Sin asignar") AS doctor, COUNT(*) AS total
             FROM solicitud_procedimiento sp
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN ? AND ?
             GROUP BY COALESCE(NULLIF(TRIM(sp.doctor), ""), "Sin asignar")
             ORDER BY total DESC
             LIMIT ' . max(1, (int) $limit),
            [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
        );

        $labels = [];
        $totals = [];
        foreach ($rows as $row) {
            $labels[] = (string) ($row->doctor ?? 'Sin asignar');
            $totals[] = (int) ($row->total ?? 0);
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    /**
     * @return array{labels:array<int,string>,totals:array<int,int>}
     */
    private function querySolicitudesPorAfiliacion(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $rows = DB::select(
            'SELECT COALESCE(NULLIF(TRIM(sp.afiliacion), ""), NULLIF(TRIM(pd.afiliacion), ""), "Sin afiliacion") AS afiliacion,
                    COUNT(*) AS total
             FROM solicitud_procedimiento sp
             LEFT JOIN patient_data pd ON sp.hc_number = pd.hc_number
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN ? AND ?
             GROUP BY COALESCE(NULLIF(TRIM(sp.afiliacion), ""), NULLIF(TRIM(pd.afiliacion), ""), "Sin afiliacion")
             ORDER BY total DESC',
            [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
        );

        $labels = [];
        $totals = [];
        foreach ($rows as $row) {
            $labels[] = (string) ($row->afiliacion ?? 'Sin afiliacion');
            $totals[] = (int) ($row->total ?? 0);
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    /**
     * @return array{labels:array<int,string>,totals:array<int,int>}
     */
    private function querySolicitudesPorPrioridad(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $rows = DB::select(
            'SELECT COALESCE(NULLIF(TRIM(sp.prioridad), ""), "Sin prioridad") AS prioridad,
                    COUNT(*) AS total
             FROM solicitud_procedimiento sp
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN ? AND ?
             GROUP BY COALESCE(NULLIF(TRIM(sp.prioridad), ""), "Sin prioridad")
             ORDER BY total DESC',
            [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
        );

        $labels = [];
        $totals = [];
        foreach ($rows as $row) {
            $labels[] = (string) ($row->prioridad ?? 'Sin prioridad');
            $totals[] = (int) ($row->total ?? 0);
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    /**
     * @return array<string,mixed>
     */
    private function queryKanbanMetrics(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $rows = DB::select(
            'SELECT sp.id, sp.estado
             FROM solicitud_procedimiento sp
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN ? AND ?',
            [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 23:59:59')]
        );

        $solicitudes = [];
        foreach ($rows as $row) {
            $solicitudes[] = [
                'id' => (int) ($row->id ?? 0),
                'estado' => (string) ($row->estado ?? ''),
            ];
        }

        $checklistMap = $this->queryChecklistMap(array_values(array_map(static fn(array $item): int => (int) $item['id'], $solicitudes)));

        $wip = [];
        foreach (self::DEFAULT_STAGES as $stage) {
            $column = (string) $stage['column'];
            $wip[$column] = ['label' => $this->kanbanLabel($column), 'total' => 0];
        }
        $wip['completado'] = ['label' => 'Completado', 'total' => 0];

        $progressBuckets = ['0-25' => 0, '25-50' => 0, '50-75' => 0, '75-100' => 0];
        $nextStages = [];

        $totalProgress = 0.0;
        $completedCount = 0;

        foreach ($solicitudes as $row) {
            [$checklist, $progress, $kanbanState] = $this->buildChecklistContext((string) ($row['estado'] ?? ''), $checklistMap[(int) $row['id']] ?? []);
            unset($checklist);

            $slug = (string) ($kanbanState['slug'] ?? '');
            if (!isset($wip[$slug])) {
                $wip[$slug] = ['label' => $this->kanbanLabel($slug), 'total' => 0];
            }
            $wip[$slug]['total']++;

            if (in_array($slug, ['programada', 'completado'], true)) {
                $completedCount++;
            }

            $percent = (float) ($progress['percent'] ?? 0.0);
            $totalProgress += $percent;

            if ($percent < 25) {
                $progressBuckets['0-25']++;
            } elseif ($percent < 50) {
                $progressBuckets['25-50']++;
            } elseif ($percent < 75) {
                $progressBuckets['50-75']++;
            } else {
                $progressBuckets['75-100']++;
            }

            $nextSlug = (string) ($progress['next_slug'] ?? '');
            if ($nextSlug !== '') {
                if (!isset($nextStages[$nextSlug])) {
                    $nextStages[$nextSlug] = ['label' => $this->kanbanLabel($nextSlug), 'total' => 0];
                }
                $nextStages[$nextSlug]['total']++;
            }
        }

        $total = count($solicitudes);
        $avgProgress = $total > 0 ? round($totalProgress / $total, 2) : 0.0;

        return [
            'total' => $total,
            'completed' => $completedCount,
            'avg_progress' => $avgProgress,
            'wip' => $wip,
            'progress_buckets' => $progressBuckets,
            'next_stages' => $nextStages,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function queryMailMetrics(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $from = $start->format('Y-m-d 00:00:00');
        $to = $end->format('Y-m-d 23:59:59');

        try {
            $statusRow = DB::selectOne(
                'SELECT
                    SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN status <> "sent" THEN 1 ELSE 0 END) AS failed,
                    COUNT(*) AS total
                 FROM solicitud_mail_log
                 WHERE COALESCE(sent_at, created_at) BETWEEN ? AND ?',
                [$from, $to]
            );

            $templateRows = DB::select(
                'SELECT COALESCE(NULLIF(TRIM(template_key), ""), "Sin plantilla") AS template_key,
                        COUNT(*) AS total
                 FROM solicitud_mail_log
                 WHERE COALESCE(sent_at, created_at) BETWEEN ? AND ?
                 GROUP BY COALESCE(NULLIF(TRIM(template_key), ""), "Sin plantilla")
                 ORDER BY total DESC
                 LIMIT 10',
                [$from, $to]
            );

            $attachmentRow = DB::selectOne(
                'SELECT AVG(attachment_size) AS avg_size, COUNT(attachment_size) AS count_with_attachment
                 FROM solicitud_mail_log
                 WHERE COALESCE(sent_at, created_at) BETWEEN ? AND ?
                   AND attachment_size IS NOT NULL',
                [$from, $to]
            );

            $userRows = DB::select(
                'SELECT COALESCE(u.nombre, "Sin usuario") AS usuario, COUNT(*) AS total
                 FROM solicitud_mail_log sml
                 LEFT JOIN users u ON u.id = sml.sent_by_user_id
                 WHERE COALESCE(sml.sent_at, sml.created_at) BETWEEN ? AND ?
                 GROUP BY COALESCE(u.nombre, "Sin usuario")
                 ORDER BY total DESC
                 LIMIT 10',
                [$from, $to]
            );
        } catch (Throwable) {
            return [
                'status' => ['sent' => 0, 'failed' => 0, 'total' => 0],
                'templates' => ['labels' => [], 'totals' => []],
                'attachments' => ['avg_size' => null, 'count_with_attachment' => 0],
                'users' => ['labels' => [], 'totals' => []],
            ];
        }

        $templateLabels = [];
        $templateTotals = [];
        foreach ($templateRows as $row) {
            $templateLabels[] = (string) ($row->template_key ?? '');
            $templateTotals[] = (int) ($row->total ?? 0);
        }

        $userLabels = [];
        $userTotals = [];
        foreach ($userRows as $row) {
            $userLabels[] = (string) ($row->usuario ?? '');
            $userTotals[] = (int) ($row->total ?? 0);
        }

        return [
            'status' => [
                'sent' => (int) ($statusRow->sent ?? 0),
                'failed' => (int) ($statusRow->failed ?? 0),
                'total' => (int) ($statusRow->total ?? 0),
            ],
            'templates' => [
                'labels' => $templateLabels,
                'totals' => $templateTotals,
            ],
            'attachments' => [
                'avg_size' => $attachmentRow?->avg_size !== null ? (float) $attachmentRow->avg_size : null,
                'count_with_attachment' => (int) ($attachmentRow->count_with_attachment ?? 0),
            ],
            'users' => [
                'labels' => $userLabels,
                'totals' => $userTotals,
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function queryCrmDetalle(int $solicitudId): ?array
    {
        $sql = 'SELECT
                sp.id,
                sp.hc_number,
                sp.form_id,
                sp.estado,
                sp.prioridad,
                sp.doctor,
                sp.procedimiento,
                sp.ojo,
                sp.created_at,
                sp.observacion,
                sp.turno,
                cd.fecha AS fecha_consulta,
                pd.afiliacion,
                pd.celular AS paciente_celular,
                TRIM(CONCAT_WS(" ", NULLIF(TRIM(pd.fname), ""), NULLIF(TRIM(pd.mname), ""), NULLIF(TRIM(pd.lname), ""), NULLIF(TRIM(pd.lname2), ""))) AS paciente_nombre,
                detalles.crm_lead_id,
                detalles.crm_project_id,
                detalles.pipeline_stage AS crm_pipeline_stage,
                detalles.fuente AS crm_fuente,
                detalles.contacto_email AS crm_contacto_email,
                detalles.contacto_telefono AS crm_contacto_telefono,
                detalles.responsable_id AS crm_responsable_id,
                detalles.followers AS crm_followers,
                responsable.nombre AS crm_responsable_nombre,
                responsable.email AS crm_responsable_email,
                responsable.profile_photo AS crm_responsable_avatar,
                (
                    SELECT u.profile_photo
                    FROM users u
                    WHERE u.profile_photo IS NOT NULL
                      AND u.profile_photo <> ""
                      AND LOWER(TRIM(sp.doctor)) LIKE CONCAT("%", LOWER(TRIM(u.nombre)), "%")
                    ORDER BY u.id ASC
                    LIMIT 1
                ) AS doctor_avatar,
                cl.status AS crm_lead_status,
                cl.source AS crm_lead_source,
                cl.updated_at AS crm_lead_updated_at,
                COALESCE(notas.total_notas, 0) AS crm_total_notas,
                COALESCE(adjuntos.total_adjuntos, 0) AS crm_total_adjuntos,
                COALESCE(tareas.tareas_pendientes, 0) AS crm_tareas_pendientes,
                COALESCE(tareas.tareas_total, 0) AS crm_tareas_total,
                tareas.proximo_vencimiento AS crm_proximo_vencimiento
            FROM solicitud_procedimiento sp
            LEFT JOIN patient_data pd ON sp.hc_number = pd.hc_number
            LEFT JOIN consulta_data cd ON sp.hc_number = cd.hc_number AND sp.form_id = cd.form_id
            LEFT JOIN solicitud_crm_detalles detalles ON detalles.solicitud_id = sp.id
            LEFT JOIN users responsable ON detalles.responsable_id = responsable.id
            LEFT JOIN crm_leads cl ON detalles.crm_lead_id = cl.id
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
                SELECT source_ref_id,
                       COUNT(*) AS tareas_total,
                       SUM(CASE WHEN status <> "completada" THEN 1 ELSE 0 END) AS tareas_pendientes,
                       MIN(CASE WHEN status <> "completada" THEN COALESCE(due_at, CONCAT(due_date, " 23:59:59")) END) AS proximo_vencimiento
                FROM crm_tasks
                WHERE source_module = "solicitudes"
                  AND company_id = ?
                GROUP BY source_ref_id
            ) tareas ON tareas.source_ref_id = sp.id
            WHERE sp.id = ?
            LIMIT 1';

        $rows = DB::select($sql, [$this->resolveCompanyId(), $solicitudId]);
        if ($rows === []) {
            return null;
        }

        $row = (array) $rows[0];
        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);
        $row['crm_pipeline_stage'] = $this->normalizePipelineStage((string) ($row['crm_pipeline_stage'] ?? ''));
        $row['seguidores'] = $this->parseFollowers($row['crm_followers'] ?? null);
        unset($row['crm_followers']);

        return $row;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function queryCrmNotas(int $solicitudId): array
    {
        try {
            $rows = DB::select(
                'SELECT n.id, n.nota, n.created_at, n.autor_id, u.nombre AS autor_nombre
                 FROM solicitud_crm_notas n
                 LEFT JOIN users u ON n.autor_id = u.id
                 WHERE n.solicitud_id = ?
                 ORDER BY n.created_at DESC
                 LIMIT 100',
                [$solicitudId]
            );
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn(object $row): array => (array) $row, $rows);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function queryCrmAdjuntos(int $solicitudId): array
    {
        try {
            $rows = DB::select(
                'SELECT a.id, a.nombre_original, a.ruta_relativa, a.mime_type, a.tamano_bytes, a.descripcion, a.created_at, a.subido_por, u.nombre AS subido_por_nombre
                 FROM solicitud_crm_adjuntos a
                 LEFT JOIN users u ON a.subido_por = u.id
                 WHERE a.solicitud_id = ?
                 ORDER BY a.created_at DESC',
                [$solicitudId]
            );
        } catch (Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $ruta = trim((string) ($item['ruta_relativa'] ?? ''));
            $item['url'] = $ruta !== '' ? '/' . ltrim($ruta, '/') : '';
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function queryCrmTareas(int $solicitudId): array
    {
        try {
            $rows = DB::select(
                'SELECT t.id,
                        t.title AS titulo,
                        t.description AS descripcion,
                        t.status AS estado,
                        t.assigned_to,
                        t.created_by,
                        COALESCE(t.due_date, DATE(t.due_at)) AS due_date,
                        t.remind_at,
                        t.created_at,
                        t.completed_at,
                        asignado.nombre AS assigned_name,
                        creador.nombre AS created_name
                 FROM crm_tasks t
                 LEFT JOIN users asignado ON t.assigned_to = asignado.id
                 LEFT JOIN users creador ON t.created_by = creador.id
                 WHERE t.company_id = ?
                   AND t.source_module = "solicitudes"
                   AND t.source_ref_id = ?
                 ORDER BY
                    CASE WHEN t.status IN ("pendiente", "en_progreso", "en_proceso") THEN 0 ELSE 1 END,
                    COALESCE(t.due_date, DATE(t.due_at)) IS NULL,
                    COALESCE(t.due_date, DATE(t.due_at)) ASC,
                    t.created_at DESC',
                [$this->resolveCompanyId(), (string) $solicitudId]
            );
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn(object $row): array => (array) $row, $rows);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function queryCrmMeta(int $solicitudId): array
    {
        try {
            $rows = DB::select(
                'SELECT id, meta_key, meta_value, meta_type, created_at, updated_at
                 FROM solicitud_crm_meta
                 WHERE solicitud_id = ?
                 ORDER BY meta_key',
                [$solicitudId]
            );
        } catch (Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $result[] = [
                'id' => (int) ($item['id'] ?? 0),
                'key' => (string) ($item['meta_key'] ?? ''),
                'value' => $item['meta_value'] ?? null,
                'type' => (string) ($item['meta_type'] ?? ''),
                'created_at' => $item['created_at'] ?? null,
                'updated_at' => $item['updated_at'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function queryBloqueosAgenda(int $solicitudId): array
    {
        try {
            $rows = DB::select(
                'SELECT id, doctor, sala, fecha_inicio, fecha_fin, motivo, created_by, created_at
                 FROM crm_calendar_blocks
                 WHERE solicitud_id = ?
                 ORDER BY fecha_inicio DESC, id DESC',
                [$solicitudId]
            );
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn(object $row): array => (array) $row, $rows);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function queryCoberturaMails(int $solicitudId, int $limit = 5): array
    {
        try {
            $rows = DB::select(
                'SELECT id, solicitud_id, template_key, status, error_message, sent_at, created_at, sent_by_user_id
                 FROM solicitud_mail_log
                 WHERE solicitud_id = ?
                 ORDER BY COALESCE(sent_at, created_at) DESC
                 LIMIT ' . max(1, $limit),
                [$solicitudId]
            );
        } catch (Throwable) {
            return [];
        }

        return array_map(static fn(object $row): array => (array) $row, $rows);
    }

    private function resolveCompanyId(): int
    {
        try {
            $row = DB::selectOne('SELECT company_id FROM crm_tasks WHERE company_id IS NOT NULL LIMIT 1');
            $value = (int) ($row->company_id ?? 0);
            if ($value > 0) {
                return $value;
            }
        } catch (Throwable) {
            // ignore and fallback
        }

        return 1;
    }

    /**
     * @param array<int,string> $values
     */
    private function appendSource(array &$values, string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $normalized = mb_strtolower($value, 'UTF-8');
        foreach ($values as $existing) {
            if (mb_strtolower((string) $existing, 'UTF-8') === $normalized) {
                return;
            }
        }

        $values[] = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function sortSolicitudes(array $rows, string $sort): array
    {
        usort($rows, function (array $a, array $b) use ($sort): int {
            $dateA = strtotime((string) ($a['fecha_programada'] ?? $a['created_at'] ?? '')) ?: 0;
            $dateB = strtotime((string) ($b['fecha_programada'] ?? $b['created_at'] ?? '')) ?: 0;

            if ($sort === 'fecha_asc') {
                return $dateA <=> $dateB;
            }

            if ($sort === 'prioridad_desc' || $sort === 'prioridad_asc') {
                $priorityRank = static function (?string $value): int {
                    $normalized = mb_strtolower(trim((string) $value));
                    return match ($normalized) {
                        'urgente' => 3,
                        'pendiente' => 2,
                        'normal' => 1,
                        default => 0,
                    };
                };

                $rankA = $priorityRank((string) ($a['prioridad'] ?? $a['prioridad_automatica'] ?? ''));
                $rankB = $priorityRank((string) ($b['prioridad'] ?? $b['prioridad_automatica'] ?? ''));

                if ($rankA === $rankB) {
                    return $dateB <=> $dateA;
                }

                return $sort === 'prioridad_asc' ? ($rankA <=> $rankB) : ($rankB <=> $rankA);
            }

            return $dateB <=> $dateA;
        });

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function applyColumnLimit(array $rows, int $columnLimit): array
    {
        if ($columnLimit <= 0) {
            return $rows;
        }

        $counts = [];
        $filtered = [];
        foreach ($rows as $row) {
            $column = (string) ($row['kanban_estado'] ?? $row['estado'] ?? 'sin-estado');
            $counts[$column] = ($counts[$column] ?? 0) + 1;
            if ($counts[$column] <= $columnLimit) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,string>
     */
    private function distinctSortedValues(array $rows, string $key): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $values[$value] = true;
        }

        $result = array_keys($values);
        sort($result, SORT_NATURAL | SORT_FLAG_CASE);

        return $result;
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
        $rows = DB::select("SELECT id, nombre FROM users WHERE id IN ($placeholders)", $ids);

        $result = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $result[$id] = trim((string) ($item['nombre'] ?? ''));
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
            static fn($value): string => trim((string) $value),
            $hcNumbers
        ))));

        if ($hcNumbers === []) {
            return [];
        }

        if (
            !$this->tableExists('protocolo_data')
            || !$this->tableHasColumn('protocolo_data', 'form_id')
            || !$this->tableHasColumn('protocolo_data', 'hc_number')
            || !$this->tableHasColumn('protocolo_data', 'fecha_inicio')
        ) {
            return [];
        }

        $lateralidadExpr = $this->tableHasColumn('protocolo_data', 'lateralidad')
            ? 'lateralidad'
            : 'NULL AS lateralidad';
        $membreteExpr = $this->tableHasColumn('protocolo_data', 'membrete')
            ? 'membrete'
            : 'NULL AS membrete';
        $statusExpr = $this->tableHasColumn('protocolo_data', 'status')
            ? 'status'
            : 'NULL AS status';
        $hcCompactos = [];
        $hcNumericos = [];
        $hcAlfanumericos = [];
        foreach ($hcNumbers as $hcNumber) {
            $compacto = preg_replace('/\s+/', '', trim((string) $hcNumber)) ?? '';
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

        $params[] = $fechaMinima;
        $rows = array_map(static fn(object $row): array => (array) $row, DB::select($sql, $params));

        $grouped = [];
        foreach ($rows as $row) {
            $hc = trim((string) ($row['hc_number'] ?? ''));
            if ($hc === '') {
                continue;
            }
            $grouped[$hc][] = $row;
        }

        return $grouped;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,array<string,mixed>> $protocolosByFormId
     * @param array<int,string> $usuariosById
     * @return array<string,mixed>|null
     */
    private function resolverProtocoloConfirmado(array $row, array $protocolosByFormId, array $usuariosById): ?array
    {
        $formId = trim((string) ($row['protocolo_confirmado_form_id'] ?? ''));
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
        $confirmadoBy = (int) ($row['protocolo_confirmado_by'] ?? 0);
        $payload['confirmado_at'] = $row['protocolo_confirmado_at'] ?? null;
        $payload['confirmado_by'] = $confirmadoBy > 0 ? ($usuariosById[$confirmadoBy] ?? null) : null;
        $payload['confirmado_by_id'] = $confirmadoBy > 0 ? $confirmadoBy : null;

        return $payload;
    }

    /**
     * @param array<string,mixed> $protocolo
     * @return array<string,mixed>
     */
    private function formatearProtocolo(array $protocolo): array
    {
        return [
            'form_id' => trim((string) ($protocolo['form_id'] ?? '')),
            'hc_number' => trim((string) ($protocolo['hc_number'] ?? '')),
            'fecha_inicio' => $protocolo['fecha_inicio'] ?? null,
            'lateralidad' => trim((string) ($protocolo['lateralidad'] ?? '')),
            'membrete' => trim((string) ($protocolo['membrete'] ?? '')),
            'status' => isset($protocolo['status']) ? (int) $protocolo['status'] : null,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolverLateralidadSolicitud(array $row): ?string
    {
        $ojo = trim((string) ($row['ojo'] ?? ''));
        if ($ojo !== '') {
            return $ojo;
        }

        $derivacion = trim((string) ($row['derivacion_lateralidad'] ?? ''));
        if ($derivacion !== '') {
            return $derivacion;
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function normalizarLateralidades(?string $valor): array
    {
        $valor = trim((string) $valor);
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
        $normalized = preg_replace('/\s+/', '', trim((string) $value)) ?? '';
        if ($normalized === '') {
            return '';
        }

        if (ctype_digit($normalized)) {
            $withoutLeadingZeroes = ltrim($normalized, '0');
            return $withoutLeadingZeroes !== '' ? $withoutLeadingZeroes : '0';
        }

        return strtoupper($normalized);
    }

    private function selectSolicitudColumn(string $column, ?string $alias = null): string
    {
        $alias = $alias ?? $column;
        if ($this->tableHasColumn('solicitud_procedimiento', $column)) {
            return 'sp.`' . str_replace('`', '', $column) . '` AS `' . str_replace('`', '', $alias) . '`';
        }

        return 'NULL AS `' . str_replace('`', '', $alias) . '`';
    }

    private function normalizePipelineStage(string $stage): string
    {
        $stage = trim($stage);
        if ($stage === '') {
            $pipeline = $this->pipelineStages();
            return $pipeline[0] ?? self::DEFAULT_PIPELINE[0];
        }

        foreach ($this->pipelineStages() as $candidate) {
            if (strcasecmp($candidate, $stage) === 0) {
                return $candidate;
            }
        }

        return $stage;
    }

    /**
     * @return array<int,int>
     */
    private function parseFollowers(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $item) {
            $id = (int) $item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function formatProfilePhoto(mixed $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (preg_match('~^(?:https?:)?//~i', $path) === 1) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }

    private function normalizeDateInput(mixed $value): ?string
    {
        $date = $this->parseDate($value);

        return $date?->format('Y-m-d');
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function parseDateRange(string $value): array
    {
        if (!str_contains($value, ' - ')) {
            $single = $this->normalizeDateInput($value);
            return [$single, $single];
        }

        [$from, $to] = explode(' - ', $value, 2);

        return [$this->normalizeDateInput($from), $this->normalizeDateInput($to)];
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        $raw = trim((string) $value);
        if ($raw === '' || $raw === '0000-00-00' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d', DateTimeImmutable::ATOM, 'd-m-Y', 'd/m/Y'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $raw);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeTurneroKey(string $state): string
    {
        $value = trim($state);
        if ($value === '') {
            return '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($value, \Normalizer::FORM_D);
            if (is_string($normalized)) {
                $value = preg_replace('/\p{Mn}/u', '', $normalized) ?? $value;
            }
        }

        $value = strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
            'ñ' => 'n', 'Ñ' => 'n',
        ]);

        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeKanbanSlug(string $value): string
    {
        $slug = mb_strtolower(trim($value), 'UTF-8');
        $slug = str_replace('_', '-', $slug);
        $slug = preg_replace('/[^\p{L}\p{N}-]+/u', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');

        $aliases = [
            'recibido' => 'recibida',
            'en-atencion' => 'en-atencion',
            'en-atenci-n' => 'en-atencion',
            'revision-de-codigos' => 'revision-codigos',
            'docs-completos' => 'espera-documentos',
            'documentos-completos' => 'espera-documentos',
            'apto-oftalmologo' => 'apto-oftalmologo',
            'apto-oftalm-logo' => 'apto-oftalmologo',
            'apto-anestesia' => 'apto-anestesia',
            'listo-para-agenda' => 'listo-para-agenda',
            'protocolo-completo' => 'programada',
            'facturado' => 'programada',
            'facturada-cerrada' => 'programada',
            'cerrado' => 'programada',
            'cerrada' => 'programada',
            'completa' => 'completado',
        ];

        return $aliases[$slug] ?? $slug;
    }

    private function stageBySlug(string $slug): ?array
    {
        foreach (self::DEFAULT_STAGES as $stage) {
            if ($stage['slug'] === $slug) {
                return $stage;
            }
        }

        return null;
    }

    private function stageIndex(string $slug): ?int
    {
        foreach (self::DEFAULT_STAGES as $index => $stage) {
            if ($stage['slug'] === $slug || $stage['column'] === $slug) {
                return $index;
            }
        }

        return null;
    }

    private function kanbanLabel(string $slug): string
    {
        $slug = $this->normalizeKanbanSlug($slug);

        foreach (self::DEFAULT_STAGES as $stage) {
            if ($stage['slug'] === $slug || $stage['column'] === $slug) {
                return (string) $stage['label'];
            }
        }

        return $slug !== '' ? ucfirst(str_replace('-', ' ', $slug)) : 'Sin estado';
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $exists = false;
        try {
            $rows = DB::select('SHOW TABLES LIKE ?', [$table]);
            $exists = !empty($rows);
        } catch (Throwable) {
            $exists = false;
        }

        if (!$exists) {
            try {
                DB::select('SELECT 1 FROM `' . str_replace('`', '', $table) . '` LIMIT 1');
                $exists = true;
            } catch (Throwable) {
                $exists = false;
            }
        }

        $this->tableExistsCache[$table] = $exists;

        return $exists;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }

        if (!$this->tableExists($table)) {
            $this->columnExistsCache[$key] = false;
            return false;
        }

        $exists = false;

        try {
            $rows = DB::select('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ?', [$column]);
            $exists = !empty($rows);
        } catch (Throwable) {
            $exists = false;
        }

        if (!$exists) {
            try {
                $rows = DB::select(
                    'SELECT COUNT(*) AS total
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = ?
                       AND COLUMN_NAME = ?',
                    [$table, $column]
                );
                $exists = ((int) ($rows[0]->total ?? 0)) > 0;
            } catch (Throwable) {
                $exists = false;
            }
        }

        if (!$exists) {
            try {
                $safeTable = str_replace('`', '', $table);
                $safeColumn = str_replace('`', '', $column);
                DB::select('SELECT `' . $safeColumn . '` FROM `' . $safeTable . '` LIMIT 0');
                $exists = true;
            } catch (Throwable) {
                $exists = false;
            }
        }

        $this->columnExistsCache[$key] = $exists;

        return $this->columnExistsCache[$key];
    }
}
