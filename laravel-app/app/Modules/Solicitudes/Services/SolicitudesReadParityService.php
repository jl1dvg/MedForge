<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use App\Modules\Shared\Support\AfiliacionDimensionService;
use App\Modules\Shared\Support\SettingsOptionResolver;
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

    /**
     * @var array<string, array<int, string>>
     */
    private array $columnsCache = [];

    private AfiliacionDimensionService $afiliacionDimensions;
    private SolicitudesStateMachineService $stateMachine;
    private ?SolicitudesSlaSettingsService $slaSettings = null;
    private ?SettingsOptionResolver $settingsResolver = null;

    public function __construct(?SolicitudesStateMachineService $stateMachine = null)
    {
        $this->afiliacionDimensions = new AfiliacionDimensionService(DB::connection()->getPdo());
        $this->stateMachine = $stateMachine ?? new SolicitudesStateMachineService();
    }

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
        $solicitudIds = array_values(array_map(
            static fn(array $row): int => (int) ($row['id'] ?? 0),
            $rows
        ));
        $checklistMap = $this->queryChecklistMap($solicitudIds);
        $taskMap = $this->queryChecklistTaskMap($solicitudIds);

        $kanban = [];
        foreach ($rows as $row) {
            $row = $this->normalizeSolicitudRow($row);
            $solicitudId = (int) ($row['id'] ?? 0);
            $checklistRows = $checklistMap[$solicitudId] ?? [];
            [$checklist, $progress, $kanbanState] = $this->resolveOperationalChecklistContext(
                (string) ($row['estado'] ?? ''),
                $checklistRows,
                $taskMap[$solicitudId] ?? []
            );

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

        if (($filters['mostrar_completados'] ?? false) !== true) {
            $kanban = array_values(array_filter(
                $kanban,
                static fn(array $row): bool => (string) ($row['kanban_estado'] ?? '') !== SolicitudesStateMachineService::STATE_COMPLETADO
            ));
        }

        $kanban = $this->sortSolicitudes($kanban, $this->kanbanPreferences()['sort'] ?? 'fecha_desc');
        $columnLimit = (int) ($this->kanbanPreferences()['column_limit'] ?? 0);
        if ($columnLimit > 0) {
            $kanban = $this->applyColumnLimit($kanban, $columnLimit);
        }

        $afiliaciones = $this->distinctSortedValues($kanban, 'afiliacion');
        $sedes = $this->distinctSortedValues($kanban, 'sede');
        $doctores = $this->distinctSortedValues($kanban, 'doctor');

        return [
            'data' => $kanban,
            'options' => [
                'afiliaciones' => $afiliaciones,
                'afiliacion_categorias' => $this->afiliacionDimensions->getCategoriaOptions(),
                'empresas_seguro' => $this->afiliacionDimensions->getEmpresaOptions(),
                'planes_seguro' => $this->afiliacionDimensions->getSeguroOptions('Todos los planes', $filters['empresa_seguro']),
                'sedes' => $sedes,
                'doctores' => $doctores,
                'crm' => [
                    'responsables' => $this->assignableUsers(),
                    'etapas' => $this->pipelineStages(),
                    'fuentes' => $this->sources(),
                    'kanban' => $this->kanbanPreferences(),
                    'operational_sla_rules' => $this->operationalSlaRules(),
                    'operational_stage_sla_rules' => $this->operationalStageSlaRules(),
                ],
                'metrics' => $this->buildOperationalMetrics($kanban),
            ],
        ];
    }

    /**
     * @return array{crm:array<string,mixed>}
     */
    public function crmOptions(): array
    {
        return [
            'crm' => [
                'responsables' => $this->assignableUsers(),
                'etapas' => $this->pipelineStages(),
                'fuentes' => $this->sources(),
                'kanban' => $this->kanbanPreferences(),
                'operational_sla_rules' => $this->operationalSlaRules(),
                'operational_stage_sla_rules' => $this->operationalStageSlaRules(),
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
            $normalizedStates = ['Turno llamado', 'Llamado', 'En atencion'];
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

        $detailSource = (string) ($detalle['_crm_detail_source'] ?? 'primary');
        unset($detalle['_crm_detail_source']);
        $degradedSections = [];
        $checklistRows = $this->queryChecklistRows($solicitudId);
        $taskRows = $this->queryChecklistTaskRows($solicitudId);

        try {
            $this->ensureChecklistTasksMaterialized($solicitudId);
        } catch (Throwable) {
            // No bloquear el CRM si la materialización del checklist falla.
        }

        $tareas = $this->safeCrmSection(fn(): array => $this->queryCrmTareas($solicitudId), []);
        $detalle['crm_tareas_total'] = count($tareas);
        $detalle['crm_tareas_pendientes'] = count(array_filter(
            $tareas,
            static fn(array $tarea): bool => (string) ($tarea['estado'] ?? '') !== 'completada'
        ));
        $detalle['crm_proximo_vencimiento'] = $this->resolveNextTaskDueDate($tareas);
        [$checklist, $checklistProgress, $operationalState] = $this->resolveOperationalChecklistSummary(
            $solicitudId,
            $tareas,
            $checklistRows
        );

        return [
            'detalle' => $detalle,
            'notas' => $this->safeCrmSection(fn(): array => $this->queryCrmNotas($solicitudId), [], 'notas', $degradedSections),
            'adjuntos' => $this->safeCrmSection(fn(): array => $this->queryCrmAdjuntos($solicitudId), [], 'adjuntos', $degradedSections),
            'tareas' => $tareas,
            'checklist' => $checklist,
            'checklist_progress' => $checklistProgress,
            'operational' => $operationalState,
            'campos_personalizados' => $this->safeCrmSection(fn(): array => $this->queryCrmMeta($solicitudId), [], 'campos_personalizados', $degradedSections),
            'lead' => null,
            'crm_resumen' => null,
            'project' => null,
            'propuestas' => $this->safeCrmSection(fn(): array => $this->queryCrmPropuestas($detalle), [], 'propuestas', $degradedSections),
            'bloqueos_agenda' => $this->safeCrmSection(fn(): array => $this->queryBloqueosAgenda($solicitudId), [], 'bloqueos_agenda', $degradedSections),
            'cobertura_mails' => $this->safeCrmSection(fn(): array => $this->queryCoberturaMails($solicitudId), [], 'cobertura_mails', $degradedSections),
            'whatsapp_context' => $this->safeCrmSection(fn(): array => $this->queryWhatsappContext($detalle), [], 'whatsapp_context', $degradedSections),
            'source_truth' => [
                'detail_source' => $detailSource,
                'degraded_sections' => $degradedSections,
                'uses_persisted_state_fallback' => $this->operationalFallbackState($solicitudId, $checklistRows, $taskRows) !== '',
            ],
        ];
    }

    /**
     * @template T
     * @param callable():T $callback
     * @param T $fallback
     * @param array<int,string>|null $degradedSections
     * @return T
     */
    private function safeCrmSection(
        callable $callback,
        mixed $fallback,
        ?string $section = null,
        ?array &$degradedSections = null
    ): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if ($section !== null && $degradedSections !== null) {
                $degradedSections[] = $section;
            }
            if ($section !== null) {
                logger()->warning('solicitudes.crm.section_fallback', [
                    'section' => $section,
                    'error' => $e->getMessage(),
                ]);
            }
            return $fallback;
        }
    }

    private function ensureChecklistTasksMaterialized(int $solicitudId): void
    {
        if (!$this->tableExists('crm_tasks')) {
            return;
        }

        $checklistRows = $this->queryChecklistRows($solicitudId);
        $taskRows = $this->queryChecklistTaskRows($solicitudId);
        $taskChecklistRows = $this->buildChecklistRowsFromTasks($taskRows, $checklistRows);
        [$checklist] = $this->stateMachine->resolvePersistedChecklistContext(
            $taskChecklistRows !== [] ? $taskChecklistRows : $checklistRows,
            $this->operationalFallbackState($solicitudId, $checklistRows, $taskRows),
            [
                'include_nota' => true,
                'include_can_toggle' => true,
            ]
        );

        $this->syncChecklistLinkedTasks($solicitudId, $checklist);
    }

    /**
     * @param array<int,array<string,mixed>> $checklistRows
     * @param array<int,array<string,mixed>> $taskRows
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>,2:array{slug:string,label:string}}
     */
    private function resolveOperationalChecklistContext(string $fallbackState, array $checklistRows, array $taskRows): array
    {
        $taskChecklistRows = $this->buildChecklistRowsFromTasks($taskRows, $checklistRows);
        if ($taskChecklistRows !== []) {
            return $this->stateMachine->resolvePersistedChecklistContext($taskChecklistRows, $fallbackState, [
                'include_nota' => true,
                'include_can_toggle' => true,
            ]);
        }

        return $this->stateMachine->resolvePersistedChecklistContext($checklistRows, $fallbackState, [
            'include_nota' => true,
            'include_can_toggle' => true,
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $taskRows
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function resolveOperationalChecklistSummary(int $solicitudId, array $taskRows, ?array $checklistRows = null): array
    {
        $checklistRows = is_array($checklistRows) ? $checklistRows : $this->queryChecklistRows($solicitudId);
        [$checklist, $progress, $kanban] = $this->resolveOperationalChecklistContext(
            $this->operationalFallbackState($solicitudId, $checklistRows, $taskRows),
            $checklistRows,
            $taskRows
        );

        return [$checklist, $progress, [
            'kanban_estado' => $kanban['slug'] ?? null,
            'kanban_estado_label' => $kanban['label'] ?? null,
        ]];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function conciliacionCirugiasMes(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $derivacionLateralidadExpr = $this->selectSolicitudColumn('derivacion_lateralidad');
        $afiliacionExpr = "COALESCE(NULLIF(TRIM(sp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''))";
        $afiliacionContext = $this->afiliacionDimensions->buildContext($afiliacionExpr, 'acm_con');
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

        $sql = "
            SELECT
                sp.id,
                sp.form_id,
                sp.hc_number,
                sp.procedimiento,
                {$afiliacionExpr} AS afiliacion,
                {$afiliacionContext['categoria_expr']} AS afiliacion_categoria_key,
                {$afiliacionContext['empresa_key_expr']} AS empresa_seguro_key,
                {$afiliacionContext['empresa_label_expr']} AS empresa_seguro,
                {$afiliacionContext['seguro_key_expr']} AS plan_seguro_key,
                {$afiliacionContext['seguro_label_expr']} AS plan_seguro,
                sp.ojo,
                {$derivacionLateralidadExpr},
                sp.estado,
                COALESCE(sp.created_at, sp.fecha, cd.fecha) AS fecha_solicitud,
                TRIM(CONCAT_WS(' ',
                    NULLIF(TRIM(pd.fname), ''),
                    NULLIF(TRIM(pd.mname), ''),
                    NULLIF(TRIM(pd.lname), ''),
                    NULLIF(TRIM(pd.lname2), '')
                )) AS full_name,
                {$metaSelect}
            FROM solicitud_procedimiento sp
            LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
            {$afiliacionContext['join']}
            LEFT JOIN (
                SELECT hc_number, form_id, MAX(fecha) AS fecha
                FROM consulta_data
                GROUP BY hc_number, form_id
            ) cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
            {$metaJoin}
            WHERE COALESCE(sp.created_at, sp.fecha, cd.fecha) BETWEEN ? AND ?
              AND sp.procedimiento IS NOT NULL
              AND TRIM(sp.procedimiento) <> ''
              AND UPPER(TRIM(sp.procedimiento)) <> 'SELECCIONE'
            ORDER BY fecha_solicitud DESC, sp.id DESC";

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
            $categoriaKey = strtolower(trim((string) ($row['afiliacion_categoria_key'] ?? '')));
            $row['afiliacion_categoria'] = $categoriaKey !== ''
                ? $this->afiliacionDimensions->formatCategoriaLabel($categoriaKey)
                : '';
            $estado = strtolower(trim((string) ($row['estado'] ?? '')));
            if ($row['protocolo_confirmado'] === null && $estado === 'completado' && $row['protocolo_posterior_compatible'] !== null) {
                $row['protocolo_confirmado'] = $row['protocolo_posterior_compatible'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function aplicarFiltrosConciliacion(array $rows, array $filters): array
    {
        $afiliacion = mb_strtolower(trim((string) ($filters['afiliacion'] ?? '')));
        $categoria = $this->afiliacionDimensions->normalizeCategoriaFilter((string) ($filters['afiliacion_categoria'] ?? ''));
        $empresa = $this->afiliacionDimensions->normalizeEmpresaFilter((string) ($filters['empresa_seguro'] ?? ''));
        $plan = $this->afiliacionDimensions->normalizeSeguroFilter((string) ($filters['plan_seguro'] ?? ''));

        if ($afiliacion === '' && $categoria === '' && $empresa === '' && $plan === '') {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($afiliacion, $categoria, $empresa, $plan): bool {
            $rowAfiliacion = mb_strtolower(trim((string) ($row['afiliacion'] ?? '')));
            $rowCategoria = $this->afiliacionDimensions->normalizeCategoriaFilter((string) ($row['afiliacion_categoria_key'] ?? ''));
            $rowEmpresa = $this->afiliacionDimensions->normalizeEmpresaFilter((string) ($row['empresa_seguro_key'] ?? ($row['empresa_seguro'] ?? '')));
            $rowPlan = $this->afiliacionDimensions->normalizeSeguroFilter((string) ($row['plan_seguro_key'] ?? ($row['plan_seguro'] ?? '')));

            if ($afiliacion !== '' && !str_contains($rowAfiliacion, $afiliacion)) {
                return false;
            }
            if ($categoria !== '' && $rowCategoria !== $categoria) {
                return false;
            }
            if ($empresa !== '' && $rowEmpresa !== $empresa) {
                return false;
            }
            if ($plan !== '' && $rowPlan !== $plan) {
                return false;
            }

            return true;
        }));
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
     * @return array{afiliacion:string,afiliacion_categoria:string,empresa_seguro:string,plan_seguro:string,sede:string,doctor:string,prioridad:string,fechaTexto:string,date_from:?string,date_to:?string,search:string,mostrar_completados:bool}
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
            'afiliacion_categoria' => $this->afiliacionDimensions->normalizeCategoriaFilter((string) ($payload['afiliacion_categoria'] ?? '')),
            'empresa_seguro' => $this->afiliacionDimensions->normalizeEmpresaFilter((string) ($payload['empresa_seguro'] ?? '')),
            'plan_seguro' => $this->afiliacionDimensions->normalizeSeguroFilter((string) ($payload['plan_seguro'] ?? '')),
            'sede' => $this->normalizeSedeFilter((string) ($payload['sede'] ?? '')),
            'doctor' => trim((string) ($payload['doctor'] ?? '')),
            'prioridad' => trim((string) ($payload['prioridad'] ?? '')),
            'fechaTexto' => $fechaTexto,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'search' => trim((string) ($payload['search'] ?? '')),
            'mostrar_completados' => filter_var($payload['mostrar_completados'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function normalizeSedeFilter(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        if (str_contains($value, 'ceib')) {
            return 'CEIBOS';
        }
        if (str_contains($value, 'matriz') || str_contains($value, 'villa')) {
            return 'MATRIZ';
        }

        return '';
    }

    private function sedeExpression(string $alias = 'pp'): string
    {
        $rawExpr = "LOWER(TRIM(COALESCE(NULLIF({$alias}.sede_departamento, ''), NULLIF({$alias}.id_sede, ''), '')))";

        return "CASE
            WHEN {$rawExpr} LIKE '%ceib%' THEN 'CEIBOS'
            WHEN {$rawExpr} LIKE '%matriz%' OR {$rawExpr} LIKE '%villa%' THEN 'MATRIZ'
            ELSE ''
        END";
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
        $sedeExpr = $this->sedeExpression('pp');
        $afiliacionExpr = 'COALESCE(NULLIF(TRIM(sp.afiliacion), ""), NULLIF(TRIM(pd.afiliacion), ""))';
        $afiliacionContext = $this->afiliacionDimensions->buildContext($afiliacionExpr, 'acm_sol');
        $taskJoin = 'LEFT JOIN (
                SELECT NULL AS source_ref_id,
                       0 AS tareas_total,
                       0 AS tareas_pendientes,
                       NULL AS proximo_vencimiento
            ) tareas ON 1 = 0';
        $taskColumns = $this->tableColumns('crm_tasks');
        if (
            $taskColumns !== []
            && in_array('source_ref_id', $taskColumns, true)
            && in_array('source_module', $taskColumns, true)
        ) {
            $taskCompanyFilter = '';
            if (in_array('company_id', $taskColumns, true)) {
                $taskCompanyFilter = ' AND company_id = ' . $this->resolveCompanyId();
            }

            $taskStatusExpr = in_array('status', $taskColumns, true)
                ? 'LOWER(COALESCE(status, ""))'
                : '""';
            $taskDueExpr = in_array('due_at', $taskColumns, true) && in_array('due_date', $taskColumns, true)
                ? 'COALESCE(due_at, CONCAT(due_date, " 23:59:59"))'
                : (in_array('due_at', $taskColumns, true)
                    ? 'due_at'
                    : (in_array('due_date', $taskColumns, true) ? 'CONCAT(due_date, " 23:59:59")' : 'NULL'));
            $taskChecklistSlugExpr = in_array('checklist_slug', $taskColumns, true)
                ? 'NULLIF(checklist_slug, "")'
                : (in_array('metadata', $taskColumns, true)
                    ? 'NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.checklist_slug")), "")'
                    : 'NULL');
            $taskIdentityExpr = in_array('task_key', $taskColumns, true)
                ? 'COALESCE(' . $taskChecklistSlugExpr . ', NULLIF(task_key, ""), CONCAT("manual:", id))'
                : 'COALESCE(' . $taskChecklistSlugExpr . ', CONCAT("manual:", id))';

            $taskJoin = 'LEFT JOIN (
                SELECT source_ref_id,
                       COUNT(*) AS tareas_total,
                       SUM(CASE WHEN status_agg IN ("pendiente", "en_progreso", "en_proceso") THEN 1 ELSE 0 END) AS tareas_pendientes,
                       MIN(CASE WHEN status_agg IN ("pendiente", "en_progreso", "en_proceso") THEN due_agg END) AS proximo_vencimiento
                FROM (
                    SELECT source_ref_id,
                           ' . $taskIdentityExpr . ' AS task_identity,
                           CASE
                               WHEN SUM(CASE WHEN ' . $taskStatusExpr . ' IN ("pendiente", "en_progreso", "en_proceso") THEN 1 ELSE 0 END) > 0 THEN "pendiente"
                               WHEN SUM(CASE WHEN ' . $taskStatusExpr . ' = "completada" THEN 1 ELSE 0 END) > 0 THEN "completada"
                               ELSE MAX(' . $taskStatusExpr . ')
                           END AS status_agg,
                           MIN(CASE WHEN ' . $taskStatusExpr . ' IN ("pendiente", "en_progreso", "en_proceso") THEN ' . $taskDueExpr . ' END) AS due_agg
                    FROM crm_tasks
                    WHERE source_module = "solicitudes"' . $taskCompanyFilter . '
                    GROUP BY source_ref_id, task_identity
                ) tareas_dedup
                GROUP BY source_ref_id
            ) tareas ON tareas.source_ref_id = sp.id';
        }

        $proposalJoin = 'LEFT JOIN (
                SELECT NULL AS lead_id, 0 AS total_propuestas
            ) propuestas_lead ON 1 = 0
            LEFT JOIN (
                SELECT NULL AS crm_opportunity_id, 0 AS total_propuestas
            ) propuestas_oportunidad ON 1 = 0';
        if ($this->tableExists('crm_proposals')) {
            $proposalLeadJoin = $this->tableHasColumn('crm_proposals', 'lead_id')
                ? 'LEFT JOIN (
                    SELECT lead_id, COUNT(*) AS total_propuestas
                    FROM crm_proposals
                    WHERE lead_id IS NOT NULL
                    GROUP BY lead_id
                ) propuestas_lead ON propuestas_lead.lead_id = detalles.crm_lead_id'
                : 'LEFT JOIN (SELECT NULL AS lead_id, 0 AS total_propuestas) propuestas_lead ON 1 = 0';
            $proposalOpportunityJoin = $this->tableHasColumn('crm_proposals', 'crm_opportunity_id')
                ? 'LEFT JOIN (
                    SELECT crm_opportunity_id, COUNT(*) AS total_propuestas
                    FROM crm_proposals
                    WHERE crm_opportunity_id IS NOT NULL
                    GROUP BY crm_opportunity_id
                ) propuestas_oportunidad ON propuestas_oportunidad.crm_opportunity_id = detalles.crm_opportunity_id'
                : 'LEFT JOIN (SELECT NULL AS crm_opportunity_id, 0 AS total_propuestas) propuestas_oportunidad ON 1 = 0';
            $proposalJoin = $proposalLeadJoin . "\n            " . $proposalOpportunityJoin;
        }

        $sql = 'SELECT
                sp.id,
                sp.hc_number,
                sp.form_id,
                TRIM(CONCAT_WS(" ", NULLIF(TRIM(pd.fname), ""), NULLIF(TRIM(pd.mname), ""), NULLIF(TRIM(pd.lname), ""), NULLIF(TRIM(pd.lname2), ""))) AS full_name,
                sp.tipo,
                ' . $afiliacionExpr . ' AS afiliacion,
                ' . $afiliacionContext['categoria_expr'] . ' AS afiliacion_categoria_key,
                ' . $afiliacionContext['empresa_key_expr'] . ' AS empresa_seguro_key,
                ' . $afiliacionContext['empresa_label_expr'] . ' AS empresa_seguro,
                ' . $afiliacionContext['seguro_key_expr'] . ' AS plan_seguro_key,
                ' . $afiliacionContext['seguro_label_expr'] . ' AS plan_seguro,
                ' . $sedeExpr . ' AS sede,
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
                detalles.crm_lead_id AS crm_lead_id,
                detalles.crm_opportunity_id AS crm_opportunity_id,
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
                GREATEST(COALESCE(propuestas_lead.total_propuestas, 0), COALESCE(propuestas_oportunidad.total_propuestas, 0)) AS crm_total_propuestas,
                tareas.proximo_vencimiento AS crm_proximo_vencimiento
            FROM solicitud_procedimiento sp
            INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = sp.form_id AND pp.hc_number = sp.hc_number AND COALESCE(pp.sigcenter_present, 1) = 1
            ' . $afiliacionContext['join'] . '
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
            ' . $proposalJoin . '
            ' . $taskJoin . '
            WHERE sp.procedimiento IS NOT NULL
              AND TRIM(sp.procedimiento) <> ""
              AND TRIM(sp.procedimiento) <> "SELECCIONE"';

        $params = [];

        if ($filters['afiliacion'] !== '') {
            $sql .= ' AND ' . $afiliacionExpr . ' LIKE ?';
            $params[] = '%' . $filters['afiliacion'] . '%';
        }

        if ($filters['afiliacion_categoria'] !== '') {
            $sql .= ' AND ' . $afiliacionContext['categoria_expr'] . ' = ?';
            $params[] = $filters['afiliacion_categoria'];
        }

        if ($filters['empresa_seguro'] !== '') {
            $sql .= ' AND ' . $afiliacionContext['empresa_key_expr'] . ' = ?';
            $params[] = $filters['empresa_seguro'];
        }

        if ($filters['plan_seguro'] !== '') {
            $sql .= ' AND ' . $afiliacionContext['seguro_key_expr'] . ' = ?';
            $params[] = $filters['plan_seguro'];
        }

        if ($filters['sede'] !== '') {
            $sql .= ' AND ' . $sedeExpr . ' = ?';
            $params[] = $filters['sede'];
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
        $keys = ['full_name', 'hc_number', 'procedimiento', 'doctor', 'afiliacion', 'sede', 'estado', 'crm_pipeline_stage'];

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
        return $this->stateMachine->buildChecklistContext($legacyState, $checklistRows);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function normalizeSolicitudRow(array $row): array
    {
        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);
        $categoriaKey = strtolower(trim((string) ($row['afiliacion_categoria_key'] ?? '')));
        $row['afiliacion_categoria'] = $categoriaKey !== ''
            ? $this->afiliacionDimensions->formatCategoriaLabel($categoriaKey)
            : '';

        return $row;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function computeOperationalMetadata(array $row): array
    {
        $now = new DateTimeImmutable('now');
        $sla = $this->resolveOperationalSla($row, $now);
        $deadline = $sla['deadline'];
        $hoursRemaining = $sla['hours_remaining'];
        $slaStatus = $sla['status'];

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

        $alertDerivacionVencida = ($sla['source'] ?? '') === 'derivacion' && $slaStatus === 'vencido';
        if ($alertDerivacionVencida) {
            $alerts[] = 'Derivacion vencida';
        }

        $alertDerivacionPorVencer = ($sla['source'] ?? '') === 'derivacion' && in_array($slaStatus, ['critico', 'advertencia'], true);
        if ($alertDerivacionPorVencer) {
            $alerts[] = 'Derivacion por vencer';
        }

        if (($sla['source'] ?? '') === 'derivacion_pendiente') {
            $alerts[] = 'Scrapear o seleccionar derivacion';
        }

        if (!empty($sla['stage_escalated'])) {
            $stageLabel = trim((string) ($sla['stage_label'] ?? ''));
            if ($stageLabel !== '') {
                $alerts[] = $stageLabel;
            }
        }

        return [
            'prioridad' => $showPriority,
            'prioridad_origen' => $manualPriority !== '' ? 'manual' : 'automatico',
            'prioridad_automatica' => $autoPriority,
            'prioridad_automatica_label' => ucfirst($autoPriority),
            'sla_status' => $slaStatus,
            'sla_deadline' => $deadline?->format(DateTimeImmutable::ATOM),
            'sla_hours_remaining' => $hoursRemaining !== null ? round($hoursRemaining, 2) : null,
            'sla_source' => $sla['source'],
            'sla_label' => $sla['label'],
            'sla_action' => $sla['action'],
            'sla_rule_key' => $sla['rule_key'],
            'sla_stage_slug' => $sla['stage_slug'],
            'sla_stage_label' => $sla['stage_label'],
            'sla_stage_started_at' => $sla['stage_started_at'],
            'sla_stage_escalated' => $sla['stage_escalated'],
            'derivacion_vigencia_status' => $sla['derivacion_status'],
            'derivacion_dias_restantes' => $sla['derivacion_days_remaining'],
            'alert_reprogramacion' => $alertReprogramacion,
            'alert_pendiente_consentimiento' => false,
            'alert_documentos_faltantes' => $alertDocs,
            'alert_autorizacion_pendiente' => $alertAuth,
            'alert_derivacion_vencida' => $alertDerivacionVencida,
            'alert_derivacion_por_vencer' => $alertDerivacionPorVencer,
            'alert_derivacion_pendiente' => ($sla['source'] ?? '') === 'derivacion_pendiente',
            'alertas_operativas' => $alerts,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{
     *   deadline:DateTimeImmutable|null,
     *   hours_remaining:float|null,
     *   status:string,
     *   source:string,
     *   label:string,
     *   action:string,
     *   rule_key:string,
     *   stage_slug:string|null,
     *   stage_label:string|null,
     *   stage_started_at:string|null,
     *   stage_escalated:bool,
     *   derivacion_status:string|null,
     *   derivacion_days_remaining:int|null
     * }
     */
    private function resolveOperationalSla(array $row, DateTimeImmutable $now): array
    {
        $rules = $this->operationalSlaRules();
        $ruleKey = $this->resolveOperationalSlaRuleKey($row);
        $rule = $rules[$ruleKey] ?? $rules['otros'];
        $createdAt = $this->parseDate($row['created_at'] ?? null)
            ?? $this->parseDate($row['fecha'] ?? null)
            ?? $now;
        $deadline = null;
        $source = (string) ($rule['source'] ?? $ruleKey);
        $label = (string) ($rule['label'] ?? 'Seguimiento operativo');
        $action = (string) ($rule['action'] ?? 'Definir siguiente acción');
        $derivacionStatus = null;
        $derivacionDaysRemaining = null;

        if ($ruleKey === 'publico') {
            $derivacionDeadline = $this->parseDateEndOfDay($row['derivacion_fecha_vigencia'] ?? null);
            if ($derivacionDeadline instanceof DateTimeImmutable) {
                $deadline = $derivacionDeadline;
                $source = 'derivacion';
                $label = 'Vigencia derivación';
                $action = 'Validar vigencia o renovar derivación';
                $derivacionDaysRemaining = (int) floor(($deadline->getTimestamp() - $now->getTimestamp()) / 86400);
            } else {
                $deadline = $createdAt->add(new DateInterval('PT' . max(1, (int) ($rule['missing_derivacion_hours'] ?? 4)) . 'H'));
                $source = 'derivacion_pendiente';
                $label = 'Derivación pendiente';
                $action = 'Scrapear o seleccionar derivación vigente';
            }
        } else {
            $deadline = $createdAt->add(new DateInterval('PT' . max(1, (int) ($rule['hours'] ?? 48)) . 'H'));
        }

        [$status, $hoursRemaining] = $this->resolveSlaStatus(
            $deadline,
            $now,
            max(1, (int) ($rule['warning_hours'] ?? 24)),
            max(1, (int) ($rule['critical_hours'] ?? 6))
        );
        $baseStatus = $status;

        $stageSla = $this->resolveOperationalStageSla($row, $ruleKey, $now, $createdAt);
        $stageEscalated = $this->slaSeverityRank((string) ($stageSla['status'] ?? 'sin_fecha')) > $this->slaSeverityRank($baseStatus);
        if ($stageEscalated) {
            $deadline = $stageSla['deadline'];
            $hoursRemaining = $stageSla['hours_remaining'];
            $status = (string) ($stageSla['status'] ?? $status);
            $source = (string) ($stageSla['source'] ?? $source);
            $label = (string) ($stageSla['label'] ?? $label);
            $action = (string) ($stageSla['action'] ?? $action);
        }

        if ($source === 'derivacion') {
            $derivacionStatus = $status === 'vencido'
                ? 'vencida'
                : (in_array($status, ['critico', 'advertencia'], true) ? 'por_vencer' : 'vigente');
        }

        return [
            'deadline' => $deadline,
            'hours_remaining' => $hoursRemaining,
            'status' => $status,
            'source' => $source,
            'label' => $label,
            'action' => $action,
            'rule_key' => $ruleKey,
            'stage_slug' => $stageSla['stage_slug'],
            'stage_label' => $stageSla['stage_label'],
            'stage_started_at' => $stageSla['stage_started_at'],
            'stage_escalated' => $stageEscalated && !empty($stageSla['is_escalated']),
            'derivacion_status' => $derivacionStatus,
            'derivacion_days_remaining' => $derivacionDaysRemaining,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{
     *   deadline:DateTimeImmutable|null,
     *   hours_remaining:float|null,
     *   status:string,
     *   source:string,
     *   label:string,
     *   action:string,
     *   stage_slug:string|null,
     *   stage_label:string|null,
     *   stage_started_at:string|null,
     *   is_escalated:bool
     * }
     */
    private function resolveOperationalStageSla(array $row, string $ruleKey, DateTimeImmutable $now, DateTimeImmutable $createdAt): array
    {
        $activeStageSlug = $this->resolveActiveChecklistStageSlug($row);
        if ($activeStageSlug === null) {
            return [
                'deadline' => null,
                'hours_remaining' => null,
                'status' => 'sin_fecha',
                'source' => 'sin_fecha',
                'label' => '',
                'action' => '',
                'stage_slug' => null,
                'stage_label' => null,
                'stage_started_at' => null,
                'is_escalated' => false,
            ];
        }

        $rules = $this->operationalStageSlaRules();
        $rule = $rules[$activeStageSlug] ?? null;
        if (!is_array($rule)) {
            return [
                'deadline' => null,
                'hours_remaining' => null,
                'status' => 'sin_fecha',
                'source' => 'sin_fecha',
                'label' => '',
                'action' => '',
                'stage_slug' => $activeStageSlug,
                'stage_label' => $this->stageBySlug($activeStageSlug)['label'] ?? $activeStageSlug,
                'stage_started_at' => null,
                'is_escalated' => false,
            ];
        }

        $rule = $this->mergeStageRuleByCategory($rule, $ruleKey);
        $stageStartedAt = $this->resolveActiveChecklistStageStartedAt($row, $activeStageSlug, $createdAt);
        $deadline = $stageStartedAt->add(new DateInterval('PT' . max(1, (int) ($rule['hours'] ?? 48)) . 'H'));
        [$status, $hoursRemaining] = $this->resolveSlaStatus(
            $deadline,
            $now,
            max(1, (int) ($rule['warning_hours'] ?? 24)),
            max(1, (int) ($rule['critical_hours'] ?? 6))
        );

        return [
            'deadline' => $deadline,
            'hours_remaining' => $hoursRemaining,
            'status' => $status,
            'source' => (string) ($rule['source'] ?? 'etapa'),
            'label' => (string) ($rule['label'] ?? 'Etapa estancada'),
            'action' => (string) ($rule['action'] ?? 'Destrabar la siguiente etapa'),
            'stage_slug' => $activeStageSlug,
            'stage_label' => $this->stageBySlug($activeStageSlug)['label'] ?? $activeStageSlug,
            'stage_started_at' => $stageStartedAt->format(DateTimeImmutable::ATOM),
            'is_escalated' => true,
        ];
    }

    /**
     * @return array{0:string,1:float|null}
     */
    private function resolveSlaStatus(?DateTimeImmutable $deadline, DateTimeImmutable $now, int $warningHours, int $criticalHours): array
    {
        if (!$deadline instanceof DateTimeImmutable) {
            return ['sin_fecha', null];
        }

        $hoursRemaining = ($deadline->getTimestamp() - $now->getTimestamp()) / 3600;
        if ($hoursRemaining < 0) {
            return ['vencido', $hoursRemaining];
        }

        if ($hoursRemaining <= $criticalHours) {
            return ['critico', $hoursRemaining];
        }

        if ($hoursRemaining <= $warningHours) {
            return ['advertencia', $hoursRemaining];
        }

        return ['en_rango', $hoursRemaining];
    }

    private function slaSeverityRank(string $status): int
    {
        return match ($status) {
            'vencido' => 4,
            'critico' => 3,
            'advertencia' => 2,
            'en_rango' => 1,
            default => 0,
        };
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolveActiveChecklistStageSlug(array $row): ?string
    {
        $progress = is_array($row['checklist_progress'] ?? null) ? $row['checklist_progress'] : [];
        $nextSlug = $this->normalizeKanbanSlug((string) ($progress['next_slug'] ?? ''));
        if ($nextSlug !== '') {
            return $nextSlug;
        }

        $checklist = is_array($row['checklist'] ?? null) ? $row['checklist'] : [];
        foreach ($checklist as $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = $this->normalizeKanbanSlug((string) ($item['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            if (empty($item['completed'])) {
                return $slug;
            }
        }

        $kanbanSlug = $this->normalizeKanbanSlug((string) ($row['kanban_estado'] ?? $row['estado'] ?? ''));
        if ($kanbanSlug !== '' && $this->stageBySlug($kanbanSlug) !== null) {
            return $kanbanSlug;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolveActiveChecklistStageStartedAt(array $row, string $activeStageSlug, DateTimeImmutable $createdAt): DateTimeImmutable
    {
        $checklist = is_array($row['checklist'] ?? null) ? $row['checklist'] : [];
        $activeIndex = null;
        foreach ($checklist as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $slug = $this->normalizeKanbanSlug((string) ($item['slug'] ?? ''));
            if ($slug === $activeStageSlug) {
                $activeIndex = $index;
                break;
            }
        }

        if ($activeIndex === null) {
            return $createdAt;
        }

        for ($i = $activeIndex - 1; $i >= 0; $i--) {
            $item = $checklist[$i] ?? null;
            if (!is_array($item) || empty($item['completed'])) {
                continue;
            }

            $completedAt = $this->parseDate($item['completado_at'] ?? null);
            if ($completedAt instanceof DateTimeImmutable) {
                return $completedAt;
            }
        }

        return $createdAt;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolveOperationalSlaRuleKey(array $row): string
    {
        $categoria = strtolower(trim((string) ($row['afiliacion_categoria_key'] ?? '')));
        if (in_array($categoria, ['publico', 'privado', 'particular', 'fundacional'], true)) {
            return $categoria;
        }

        $afiliacion = strtoupper(trim((string) ($row['afiliacion'] ?? '')));
        if (preg_match('/\b(IESS|ISSFA|ISSPOL|MSP)\b/', $afiliacion)) {
            return 'publico';
        }
        if (str_contains($afiliacion, 'PARTICULAR') || preg_match('/\bPAR\b/', $afiliacion)) {
            return 'particular';
        }
        if (str_contains($afiliacion, 'FUNDACION') || str_contains($afiliacion, 'FUNDACIÓN')) {
            return 'fundacional';
        }

        return 'privado';
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function operationalSlaRules(): array
    {
        return $this->slaSettings()->baseRules();
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function operationalStageSlaRules(): array
    {
        return $this->slaSettings()->stageRules();
    }

    /**
     * @param array<string,mixed> $rule
     * @return array<string,mixed>
     */
    private function mergeStageRuleByCategory(array $rule, string $ruleKey): array
    {
        $overrides = $rule['by_rule_key'] ?? null;
        if (!is_array($overrides) || !isset($overrides[$ruleKey]) || !is_array($overrides[$ruleKey])) {
            unset($rule['by_rule_key']);
            return $rule;
        }

        $rule = $this->mergeStageRuleRecursive($rule, $overrides[$ruleKey]);
        unset($rule['by_rule_key']);

        return $rule;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private function mergeStageRuleRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && is_array($base[$key] ?? null)) {
                $base[$key] = $this->mergeStageRuleRecursive($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    private function slaSettings(): SolicitudesSlaSettingsService
    {
        if ($this->slaSettings === null) {
            $this->slaSettings = new SolicitudesSlaSettingsService();
        }

        return $this->slaSettings;
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
            return ['Turno llamado', 'Llamado', 'En atencion'];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $states = array_values(array_filter(array_map(static fn($value): string => trim((string) $value), $decoded), static fn(string $value): bool => $value !== ''));
            if ($states !== []) {
                return $states;
            }
        }

        $states = array_values(array_filter(array_map('trim', preg_split('/[,\n\r;]+/', $raw) ?: []), static fn(string $value): bool => $value !== ''));

        return $states !== [] ? $states : ['Turno llamado', 'Llamado', 'En atencion'];
    }

    /**
     * @return array<string,string>
     */
    private function settingsOptions(array $keys): array
    {
        if ($this->settingsResolver === null) {
            $this->settingsResolver = new SettingsOptionResolver();
        }

        return $this->settingsResolver->getOptions($keys);
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

        $solicitudIds = array_values(array_map(static fn(array $item): int => (int) $item['id'], $solicitudes));
        $checklistMap = $this->queryChecklistMap($solicitudIds);
        $taskMap = $this->queryChecklistTaskMap($solicitudIds);

        $wip = [];
        foreach ($this->stateMachine->stages() as $stage) {
            $column = (string) $stage['column'];
            $wip[$column] = ['label' => $this->kanbanLabel($column), 'total' => 0];
        }
        $wip['completado'] = ['label' => 'Completado', 'total' => 0];

        $progressBuckets = ['0-25' => 0, '25-50' => 0, '50-75' => 0, '75-100' => 0];
        $nextStages = [];

        $totalProgress = 0.0;
        $completedCount = 0;

        foreach ($solicitudes as $row) {
            [$checklist, $progress, $kanbanState] = $this->resolveOperationalChecklistContext(
                (string) ($row['estado'] ?? ''),
                $checklistMap[(int) $row['id']] ?? [],
                $taskMap[(int) $row['id']] ?? []
            );
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
        $taskJoin = 'LEFT JOIN (
                SELECT NULL AS source_ref_id,
                       0 AS tareas_total,
                       0 AS tareas_pendientes,
                       NULL AS proximo_vencimiento
            ) tareas ON 1 = 0';
        $proposalJoin = 'LEFT JOIN (
                SELECT NULL AS lead_id, 0 AS total_propuestas
            ) propuestas_lead ON 1 = 0
            LEFT JOIN (
                SELECT NULL AS crm_opportunity_id, 0 AS total_propuestas
            ) propuestas_oportunidad ON 1 = 0';
        $bindings = [];

        $taskColumns = $this->tableColumns('crm_tasks');
        if (
            $taskColumns !== []
            && in_array('source_ref_id', $taskColumns, true)
            && in_array('source_module', $taskColumns, true)
        ) {
            $taskCompanyFilter = '';
            if (in_array('company_id', $taskColumns, true)) {
                $taskCompanyFilter = ' AND company_id = ?';
                $bindings[] = $this->resolveCompanyId();
            }

            $taskStatusExpr = in_array('status', $taskColumns, true)
                ? 'LOWER(COALESCE(status, ""))'
                : '""';
            $taskDueExpr = in_array('due_at', $taskColumns, true) && in_array('due_date', $taskColumns, true)
                ? 'COALESCE(due_at, CONCAT(due_date, " 23:59:59"))'
                : (in_array('due_at', $taskColumns, true)
                    ? 'due_at'
                    : (in_array('due_date', $taskColumns, true) ? 'CONCAT(due_date, " 23:59:59")' : 'NULL'));
            $taskChecklistSlugExpr = in_array('checklist_slug', $taskColumns, true)
                ? 'NULLIF(checklist_slug, "")'
                : (in_array('metadata', $taskColumns, true)
                    ? 'NULLIF(JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.checklist_slug")), "")'
                    : 'NULL');
            $taskIdentityExpr = in_array('task_key', $taskColumns, true)
                ? 'COALESCE(' . $taskChecklistSlugExpr . ', NULLIF(task_key, ""), CONCAT("manual:", id))'
                : 'COALESCE(' . $taskChecklistSlugExpr . ', CONCAT("manual:", id))';

            $taskJoin = 'LEFT JOIN (
                SELECT source_ref_id,
                       COUNT(*) AS tareas_total,
                       SUM(CASE WHEN status_agg IN ("pendiente", "en_progreso", "en_proceso") THEN 1 ELSE 0 END) AS tareas_pendientes,
                       MIN(CASE WHEN status_agg IN ("pendiente", "en_progreso", "en_proceso") THEN due_agg END) AS proximo_vencimiento
                FROM (
                    SELECT source_ref_id,
                           ' . $taskIdentityExpr . ' AS task_identity,
                           CASE
                               WHEN SUM(CASE WHEN ' . $taskStatusExpr . ' IN ("pendiente", "en_progreso", "en_proceso") THEN 1 ELSE 0 END) > 0 THEN "pendiente"
                               WHEN SUM(CASE WHEN ' . $taskStatusExpr . ' = "completada" THEN 1 ELSE 0 END) > 0 THEN "completada"
                               ELSE MAX(' . $taskStatusExpr . ')
                           END AS status_agg,
                           MIN(CASE WHEN ' . $taskStatusExpr . ' IN ("pendiente", "en_progreso", "en_proceso") THEN ' . $taskDueExpr . ' END) AS due_agg
                    FROM crm_tasks
                    WHERE source_module = "solicitudes"'
                    . $taskCompanyFilter . '
                    GROUP BY source_ref_id, task_identity
                ) tareas_dedup
                GROUP BY source_ref_id
            ) tareas ON tareas.source_ref_id = sp.id';
        }

        if ($this->tableExists('crm_proposals')) {
            $proposalLeadJoin = $this->tableHasColumn('crm_proposals', 'lead_id')
                ? 'LEFT JOIN (
                    SELECT lead_id, COUNT(*) AS total_propuestas
                    FROM crm_proposals
                    WHERE lead_id IS NOT NULL
                    GROUP BY lead_id
                ) propuestas_lead ON propuestas_lead.lead_id = detalles.crm_lead_id'
                : 'LEFT JOIN (SELECT NULL AS lead_id, 0 AS total_propuestas) propuestas_lead ON 1 = 0';
            $proposalOpportunityJoin = $this->tableHasColumn('crm_proposals', 'crm_opportunity_id')
                ? 'LEFT JOIN (
                    SELECT crm_opportunity_id, COUNT(*) AS total_propuestas
                    FROM crm_proposals
                    WHERE crm_opportunity_id IS NOT NULL
                    GROUP BY crm_opportunity_id
                ) propuestas_oportunidad ON propuestas_oportunidad.crm_opportunity_id = COALESCE(detalles.crm_opportunity_id, sp.crm_opportunity_id)'
                : 'LEFT JOIN (SELECT NULL AS crm_opportunity_id, 0 AS total_propuestas) propuestas_oportunidad ON 1 = 0';
            $proposalJoin = $proposalLeadJoin . "\n            " . $proposalOpportunityJoin;
        }

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
                COALESCE(detalles.crm_opportunity_id, sp.crm_opportunity_id) AS crm_opportunity_id,
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
                GREATEST(COALESCE(propuestas_lead.total_propuestas, 0), COALESCE(propuestas_oportunidad.total_propuestas, 0)) AS crm_total_propuestas,
                tareas.proximo_vencimiento AS crm_proximo_vencimiento
            FROM solicitud_procedimiento sp
            LEFT JOIN patient_data pd ON sp.hc_number = pd.hc_number
            LEFT JOIN (
                SELECT c.hc_number, c.form_id, MAX(c.fecha) AS fecha
                FROM consulta_data c
                GROUP BY c.hc_number, c.form_id
            ) cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
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
            ' . $taskJoin . '
            ' . $proposalJoin . '
            WHERE sp.id = ?
            LIMIT 1';

        $bindings[] = $solicitudId;

        try {
            $rows = DB::select($sql, $bindings);
            if ($rows === []) {
                return null;
            }

            $row = (array) $rows[0];
            $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
            $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);
            $row['crm_pipeline_stage'] = $this->normalizePipelineStage((string) ($row['crm_pipeline_stage'] ?? ''));
            $row['seguidores'] = $this->parseFollowers($row['crm_followers'] ?? null);
            $row['_crm_detail_source'] = 'primary';
            unset($row['crm_followers']);

            return $row;
        } catch (Throwable $e) {
            logger()->warning('solicitudes.crm.detail_fallback', [
                'solicitud_id' => $solicitudId,
                'error' => $e->getMessage(),
            ]);
            return $this->queryCrmDetalleFallback($solicitudId);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function queryCrmDetalleFallback(int $solicitudId): ?array
    {
        try {
            $rows = DB::select(
                'SELECT
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
                    TRIM(CONCAT_WS(" ", NULLIF(TRIM(pd.fname), ""), NULLIF(TRIM(pd.mname), ""), NULLIF(TRIM(pd.lname), ""), NULLIF(TRIM(pd.lname2), ""))) AS paciente_nombre
                 FROM solicitud_procedimiento sp
                 LEFT JOIN patient_data pd ON sp.hc_number = pd.hc_number
                 LEFT JOIN (
                    SELECT c.hc_number, c.form_id, MAX(c.fecha) AS fecha
                    FROM consulta_data c
                    GROUP BY c.hc_number, c.form_id
                 ) cd ON cd.hc_number = sp.hc_number AND cd.form_id = sp.form_id
                 WHERE sp.id = ?
                 LIMIT 1',
                [$solicitudId]
            );
        } catch (Throwable) {
            return null;
        }

        if ($rows === []) {
            return null;
        }

        $row = (array) $rows[0];
        return array_merge($row, [
            '_crm_detail_source' => 'fallback',
            'crm_lead_id' => null,
            'crm_opportunity_id' => null,
            'crm_project_id' => null,
            'crm_pipeline_stage' => null,
            'crm_fuente' => null,
            'crm_contacto_email' => null,
            'crm_contacto_telefono' => null,
            'crm_responsable_id' => null,
            'crm_responsable_nombre' => null,
            'crm_responsable_email' => null,
            'crm_responsable_avatar' => null,
            'doctor_avatar' => null,
            'crm_lead_status' => null,
            'crm_lead_source' => null,
            'crm_lead_updated_at' => null,
            'crm_total_notas' => 0,
            'crm_total_adjuntos' => 0,
            'crm_total_propuestas' => 0,
            'crm_tareas_pendientes' => 0,
            'crm_tareas_total' => 0,
            'crm_proximo_vencimiento' => null,
            'seguidores' => [],
        ]);
    }

    /**
     * @param array<string,mixed> $detalle
     * @return array<string,mixed>
     */
    private function queryWhatsappContext(array $detalle): array
    {
        $telefono = trim((string) ($detalle['crm_contacto_telefono'] ?? $detalle['paciente_celular'] ?? ''));
        $hcNumber = trim((string) ($detalle['hc_number'] ?? ''));
        $normalized = $this->normalizeWhatsappPhone($telefono);
        $search = $normalized !== '' ? $normalized : preg_replace('/\D+/', '', $telefono);
        $searchUrl = $search !== '' ? '/v2/whatsapp/chat?search=' . urlencode($search) : null;

        $context = [
            'available' => false,
            'matched' => false,
            'search' => $search !== '' ? $search : null,
            'search_url' => $searchUrl,
            'conversation_id' => null,
            'conversation_url' => null,
            'wa_number' => null,
            'display_name' => null,
            'last_message_at' => null,
            'unread_count' => 0,
        ];

        if (!$this->tableExists('whatsapp_conversations')) {
            return $context;
        }

        try {
            $row = DB::selectOne(
                'SELECT id, wa_number, display_name, patient_full_name, last_message_at, unread_count
                 FROM whatsapp_conversations
                 WHERE (? <> "" AND patient_hc_number = ?)
                    OR (? <> "" AND (wa_number = ? OR wa_number = CONCAT("+", ?) OR RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(wa_number, "+", ""), " ", ""), "-", ""), "(", ""), ")", ""), 10) = RIGHT(?, 10)))
                 ORDER BY
                    CASE WHEN ? <> "" AND patient_hc_number = ? THEN 0 ELSE 1 END,
                    last_message_at DESC,
                    id DESC
                 LIMIT 1',
                [
                    $hcNumber,
                    $hcNumber,
                    $normalized,
                    $normalized,
                    $normalized,
                    $normalized,
                    $hcNumber,
                    $hcNumber,
                ]
            );
        } catch (Throwable) {
            return $context;
        }

        if (!is_object($row)) {
            return $context;
        }

        $conversationId = (int) ($row->id ?? 0);
        $waNumber = trim((string) ($row->wa_number ?? ''));

        return [
            'available' => true,
            'matched' => $conversationId > 0,
            'search' => $search !== '' ? $search : null,
            'search_url' => $searchUrl,
            'conversation_id' => $conversationId > 0 ? $conversationId : null,
            'conversation_url' => $conversationId > 0 ? '/v2/whatsapp/chat?conversation=' . $conversationId : $searchUrl,
            'wa_number' => $waNumber !== '' ? $waNumber : null,
            'display_name' => trim((string) (($row->display_name ?? '') ?: ($row->patient_full_name ?? ''))) ?: null,
            'last_message_at' => $row->last_message_at ?? null,
            'unread_count' => (int) ($row->unread_count ?? 0),
        ];
    }

    private function normalizeWhatsappPhone(?string $value): string
    {
        $number = preg_replace('/\D+/', '', (string) $value);
        if ($number === '') {
            return '';
        }

        if (str_starts_with($number, '0')) {
            $number = ltrim($number, '0');
        }

        if (!str_starts_with($number, '593') && strlen($number) <= 10) {
            $number = '593' . $number;
        }

        return $number;
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
        $columns = $this->tableColumns('crm_tasks');
        if ($columns === []) {
            return [];
        }

        $select = ['t.id'];
        if (in_array('title', $columns, true)) {
            $select[] = 't.title AS titulo';
        } else {
            $select[] = 'NULL AS titulo';
        }
        if (in_array('description', $columns, true)) {
            $select[] = 't.description AS descripcion';
        } else {
            $select[] = 'NULL AS descripcion';
        }
        if (in_array('status', $columns, true)) {
            $select[] = 't.status AS estado';
        } else {
            $select[] = 'NULL AS estado';
        }
        if (in_array('checklist_slug', $columns, true)) {
            $select[] = 't.checklist_slug';
        } else {
            $select[] = 'NULL AS checklist_slug';
        }
        if (in_array('task_key', $columns, true)) {
            $select[] = 't.task_key';
        } else {
            $select[] = 'NULL AS task_key';
        }
        if (in_array('metadata', $columns, true)) {
            $select[] = 't.metadata';
        } else {
            $select[] = 'NULL AS metadata';
        }
        if (in_array('assigned_to', $columns, true)) {
            $select[] = 't.assigned_to';
        } else {
            $select[] = 'NULL AS assigned_to';
        }
        if (in_array('created_by', $columns, true)) {
            $select[] = 't.created_by';
        } else {
            $select[] = 'NULL AS created_by';
        }
        if (in_array('priority', $columns, true)) {
            $select[] = 't.priority';
        } else {
            $select[] = 'NULL AS priority';
        }
        if (in_array('due_date', $columns, true) && in_array('due_at', $columns, true)) {
            $select[] = 'COALESCE(t.due_date, DATE(t.due_at)) AS due_date';
        } elseif (in_array('due_date', $columns, true)) {
            $select[] = 't.due_date AS due_date';
        } elseif (in_array('due_at', $columns, true)) {
            $select[] = 'DATE(t.due_at) AS due_date';
        } else {
            $select[] = 'NULL AS due_date';
        }
        if (in_array('remind_at', $columns, true)) {
            $select[] = 't.remind_at';
        } else {
            $select[] = 'NULL AS remind_at';
        }
        if (in_array('created_at', $columns, true)) {
            $select[] = 't.created_at';
        } else {
            $select[] = 'NULL AS created_at';
        }
        if (in_array('completed_at', $columns, true)) {
            $select[] = 't.completed_at';
        } else {
            $select[] = 'NULL AS completed_at';
        }

        $joins = [];
        if (in_array('assigned_to', $columns, true)) {
            $joins[] = 'LEFT JOIN users asignado ON t.assigned_to = asignado.id';
            $select[] = 'asignado.nombre AS assigned_name';
        } else {
            $select[] = 'NULL AS assigned_name';
        }
        if (in_array('created_by', $columns, true)) {
            $joins[] = 'LEFT JOIN users creador ON t.created_by = creador.id';
            $select[] = 'creador.nombre AS created_name';
        } else {
            $select[] = 'NULL AS created_name';
        }

        $orderDueExpr = in_array('due_date', $columns, true) && in_array('due_at', $columns, true)
            ? 'COALESCE(t.due_date, DATE(t.due_at))'
            : (in_array('due_date', $columns, true)
                ? 't.due_date'
                : (in_array('due_at', $columns, true) ? 'DATE(t.due_at)' : 'NULL'));
        $orderStatusExpr = in_array('status', $columns, true)
            ? 'CASE WHEN t.status IN ("pendiente", "en_progreso", "en_proceso") THEN 0 ELSE 1 END'
            : '0';

        try {
            $rows = DB::select(
                'SELECT ' . implode(', ', $select) . '
                 FROM crm_tasks t
                 ' . implode(' ', $joins) . '
                 WHERE t.company_id = ?
                   AND t.source_module = "solicitudes"
                   AND t.source_ref_id = ?
                 ORDER BY
                    ' . $orderStatusExpr . ',
                    (' . $orderDueExpr . ') IS NULL,
                    ' . $orderDueExpr . ' ASC,
                    t.created_at DESC',
                [$this->resolveCompanyId(), (string) $solicitudId]
            );
        } catch (Throwable) {
            return [];
        }

        $items = array_map(function (object $row): array {
            $item = (array) $row;
            $metadata = $this->decodeTaskMetadata($item['metadata'] ?? null);

            if (trim((string) ($item['checklist_slug'] ?? '')) === '' && is_array($metadata)) {
                $item['checklist_slug'] = trim((string) ($metadata['checklist_slug'] ?? ''));
            }
            if (trim((string) ($item['task_key'] ?? '')) === '' && is_array($metadata)) {
                $item['task_key'] = trim((string) ($metadata['task_key'] ?? ''));
            }
            $slug = $this->normalizeKanbanSlug((string) ($item['checklist_slug'] ?? ''));
            $stage = $slug !== '' ? $this->stageBySlug($slug) : null;
            $item['estado'] = $this->normalizeTaskStatus((string) ($item['estado'] ?? 'pendiente'));
            $item['status'] = $item['estado'];
            $item['task_type'] = $slug !== '' ? 'checklist' : 'manual';
            $item['required'] = $slug !== '' ? (bool) ($stage['required'] ?? true) : false;
            $item['checklist_slug'] = $slug !== '' ? $slug : null;
            $item['checklist_label'] = $slug !== ''
                ? trim((string) (($metadata['checklist_label'] ?? null) ?: ($stage['label'] ?? $item['titulo'] ?? $slug)))
                : null;
            $item['metadata'] = is_array($metadata) ? $metadata : null;

            return $item;
        }, $rows);

        return $this->deduplicateCrmTasks($items);
    }

    private function normalizeTaskStatus(string $status): string
    {
        $normalized = trim(mb_strtolower($status, 'UTF-8'));
        if ($normalized === 'en_proceso') {
            return 'en_progreso';
        }
        if (!in_array($normalized, ['pendiente', 'en_progreso', 'completada', 'cancelada'], true)) {
            return 'pendiente';
        }

        return $normalized;
    }

    /**
     * @param array<int,array<string,mixed>> $tasks
     * @return array<int,array<string,mixed>>
     */
    private function deduplicateCrmTasks(array $tasks): array
    {
        if ($tasks === []) {
            return [];
        }

        $byKey = [];
        foreach ($tasks as $task) {
            $slug = trim((string) ($task['checklist_slug'] ?? ''));
            $taskKey = trim((string) ($task['task_key'] ?? ''));
            $identity = $slug !== ''
                ? 'checklist:' . $slug
                : ($taskKey !== '' ? 'task:' . $taskKey : 'manual:' . (string) ($task['id'] ?? uniqid('task_', true)));

            $current = $byKey[$identity] ?? null;
            if ($current === null || $this->crmTaskSortScore($task) > $this->crmTaskSortScore($current)) {
                $byKey[$identity] = $task;
            }
        }

        $items = array_values($byKey);
        usort($items, function (array $a, array $b): int {
            $statusA = (string) ($a['estado'] ?? '');
            $statusB = (string) ($b['estado'] ?? '');
            $pendingA = $statusA !== 'completada' ? 0 : 1;
            $pendingB = $statusB !== 'completada' ? 0 : 1;
            if ($pendingA !== $pendingB) {
                return $pendingA <=> $pendingB;
            }

            return $this->crmTaskSortScore($b) <=> $this->crmTaskSortScore($a);
        });

        return $items;
    }

    /**
     * @param array<string,mixed> $task
     */
    private function crmTaskSortScore(array $task): int
    {
        foreach (['completed_at', 'due_date', 'created_at'] as $field) {
            $value = $this->parseDate($task[$field] ?? null);
            if ($value instanceof DateTimeImmutable) {
                return $value->getTimestamp() + (int) ($task['id'] ?? 0);
            }
        }

        return (int) ($task['id'] ?? 0);
    }

    /**
     * @param array<int,array<string,mixed>> $tasks
     */
    private function resolveNextTaskDueDate(array $tasks): ?string
    {
        $next = null;
        foreach ($tasks as $task) {
            if ((string) ($task['estado'] ?? '') === 'completada') {
                continue;
            }

            $due = $task['due_date'] ?? $task['remind_at'] ?? null;
            $date = $this->parseDate($due);
            if (!$date instanceof DateTimeImmutable) {
                continue;
            }

            if (!$next instanceof DateTimeImmutable || $date < $next) {
                $next = $date;
            }
        }

        return $next?->format('Y-m-d H:i:s');
    }

    /**
     * @param array<int,array<string,mixed>> $checklist
     */
    private function syncChecklistLinkedTasks(int $solicitudId, array $checklist): void
    {
        $columns = $this->tableColumns('crm_tasks');
        if ($columns === [] || $checklist === []) {
            return;
        }

        $bindings = [
            $this->resolveCompanyId(),
            'solicitudes',
            (string) $solicitudId,
        ];

        try {
            $rows = DB::select(
                'SELECT id, '
                . (in_array('checklist_slug', $columns, true) ? 'checklist_slug, ' : 'NULL AS checklist_slug, ')
                . (in_array('status', $columns, true) ? 'status, ' : 'NULL AS status, ')
                . (in_array('completed_at', $columns, true) ? 'completed_at, ' : 'NULL AS completed_at, ')
                . (in_array('title', $columns, true) ? 'title, ' : 'NULL AS title, ')
                . (in_array('description', $columns, true) ? 'description, ' : 'NULL AS description, ')
                . (in_array('task_key', $columns, true) ? 'task_key, ' : 'NULL AS task_key, ')
                . (in_array('metadata', $columns, true) ? 'metadata ' : 'NULL AS metadata ')
                . 'FROM crm_tasks
                   WHERE company_id = ?
                     AND source_module = ?
                     AND source_ref_id = ?',
                $bindings
            );
        } catch (Throwable) {
            return;
        }

        $existingBySlug = [];
        foreach ($rows as $row) {
            $task = (array) $row;
            $slug = $this->extractChecklistSlugFromTaskRow($task);
            if ($slug === '') {
                continue;
            }

            $existingBySlug[$slug][] = $task;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($checklist as $item) {
            $slug = $this->normalizeKanbanSlug((string) ($item['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $title = trim((string) ($item['label'] ?? $slug));
            $completed = !empty($item['completed']);
            $targetStatus = $completed ? 'completada' : 'pendiente';
            $targetCompletedAt = $completed
                ? ($this->normalizeChecklistDateTime($item['completado_at'] ?? null) ?? $now)
                : null;
            $description = 'Checklist de solicitud';
            $taskKey = 'checklist:' . $slug;

            $rowsForSlug = $existingBySlug[$slug] ?? [];
            if ($rowsForSlug === []) {
                $payload = [];
                if (in_array('company_id', $columns, true)) {
                    $payload['company_id'] = $this->resolveCompanyId();
                }
                if (in_array('source_module', $columns, true)) {
                    $payload['source_module'] = 'solicitudes';
                }
                if (in_array('source_ref_id', $columns, true)) {
                    $payload['source_ref_id'] = (string) $solicitudId;
                }
                if (in_array('title', $columns, true)) {
                    $payload['title'] = $title;
                }
                if (in_array('description', $columns, true)) {
                    $payload['description'] = $description;
                }
                if (in_array('status', $columns, true)) {
                    $payload['status'] = $targetStatus;
                }
                if (in_array('checklist_slug', $columns, true)) {
                    $payload['checklist_slug'] = $slug;
                }
                if (in_array('task_key', $columns, true)) {
                    $payload['task_key'] = $taskKey;
                }
                if (in_array('metadata', $columns, true)) {
                    $payload['metadata'] = json_encode([
                        'task_key' => $taskKey,
                        'checklist_slug' => $slug,
                        'checklist_label' => $title,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if (in_array('completed_at', $columns, true)) {
                    $payload['completed_at'] = $targetCompletedAt;
                }
                if (in_array('created_at', $columns, true)) {
                    $payload['created_at'] = $now;
                }
                if (in_array('updated_at', $columns, true)) {
                    $payload['updated_at'] = $now;
                }

                $this->insertRow('crm_tasks', $payload);
                continue;
            }

            foreach ($rowsForSlug as $row) {
                $payload = [];
                if (in_array('status', $columns, true) && (string) ($row['status'] ?? '') !== $targetStatus) {
                    $payload['status'] = $targetStatus;
                }
                if (in_array('completed_at', $columns, true) && (($row['completed_at'] ?? null) !== $targetCompletedAt)) {
                    $payload['completed_at'] = $targetCompletedAt;
                }
                if (in_array('title', $columns, true) && trim((string) ($row['title'] ?? '')) !== $title) {
                    $payload['title'] = $title;
                }
                if (in_array('description', $columns, true) && trim((string) ($row['description'] ?? '')) === '') {
                    $payload['description'] = $description;
                }
                if (in_array('task_key', $columns, true) && trim((string) ($row['task_key'] ?? '')) === '') {
                    $payload['task_key'] = $taskKey;
                }
                if (in_array('checklist_slug', $columns, true) && trim((string) ($row['checklist_slug'] ?? '')) === '') {
                    $payload['checklist_slug'] = $slug;
                }
                if (in_array('metadata', $columns, true)) {
                    $metadata = $this->mergeChecklistTaskMetadata($row['metadata'] ?? null, $slug, $title);
                    if ($metadata !== ($row['metadata'] ?? null)) {
                        $payload['metadata'] = $metadata;
                    }
                }
                if ($payload !== [] && in_array('updated_at', $columns, true)) {
                    $payload['updated_at'] = $now;
                }

                if ($payload === []) {
                    continue;
                }

                $this->updateRow('crm_tasks', $payload, 'id = ?', [(int) ($row['id'] ?? 0)]);
            }
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeTaskMetadata(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function extractChecklistSlugFromTaskRow(array $row): string
    {
        $slug = $this->normalizeKanbanSlug((string) ($row['checklist_slug'] ?? ''));
        if ($slug !== '') {
            return $slug;
        }

        $metadata = $this->decodeTaskMetadata($row['metadata'] ?? null);
        if (!is_array($metadata)) {
            return '';
        }

        return $this->normalizeKanbanSlug((string) ($metadata['checklist_slug'] ?? ''));
    }

    private function mergeChecklistTaskMetadata(mixed $current, string $slug, string $label): ?string
    {
        $metadata = $this->decodeTaskMetadata($current) ?? [];
        $metadata['task_key'] = $metadata['task_key'] ?? ('checklist:' . $slug);
        $metadata['checklist_slug'] = $slug;
        $metadata['checklist_label'] = $label;

        return json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    /**
     * @param array<string,mixed> $detalle
     * @return array<int,array<string,mixed>>
     */
    private function queryCrmPropuestas(array $detalle): array
    {
        $leadId = (int) ($detalle['crm_lead_id'] ?? 0);
        $opportunityId = (int) ($detalle['crm_opportunity_id'] ?? 0);
        if (($leadId <= 0 && $opportunityId <= 0) || !$this->tableExists('crm_proposals')) {
            return [];
        }

        $hasOpportunityColumn = $this->tableHasColumn('crm_proposals', 'crm_opportunity_id');
        $where = [];
        $bindings = [];
        if ($hasOpportunityColumn && $opportunityId > 0) {
            $where[] = 'p.crm_opportunity_id = ?';
            $bindings[] = $opportunityId;
        }
        if ($leadId > 0) {
            $where[] = 'p.lead_id = ?';
            $bindings[] = $leadId;
        }
        if ($where === []) {
            return [];
        }

        $itemsJoin = $this->tableExists('crm_proposal_items')
            ? 'LEFT JOIN (
                    SELECT proposal_id, COUNT(*) AS items_count
                    FROM crm_proposal_items
                    GROUP BY proposal_id
                ) items ON items.proposal_id = p.id'
            : 'LEFT JOIN (SELECT NULL AS proposal_id, 0 AS items_count) items ON 1 = 0';

        try {
            $rows = DB::select(
                "SELECT
                    p.id,
                    " . ($this->tableHasColumn('crm_proposals', 'public_hash') ? 'p.public_hash,' : "NULL AS public_hash,") . "
                    p.proposal_number,
                    p.lead_id,
                    " . ($hasOpportunityColumn ? 'p.crm_opportunity_id,' : "NULL AS crm_opportunity_id,") . "
                    p.customer_id,
                    p.title,
                    p.status,
                    p.currency,
                    p.subtotal,
                    p.discount_total,
                    p.tax_rate,
                    p.tax_total,
                    p.total,
                    p.valid_until,
                    p.sent_at,
                    p.accepted_at,
                    p.rejected_at,
                    p.created_at,
                    p.updated_at,
                    COALESCE(items.items_count, 0) AS items_count
                 FROM crm_proposals p
                 {$itemsJoin}
                 WHERE (" . implode(' OR ', $where) . ")
                 ORDER BY COALESCE(p.updated_at, p.created_at) DESC, p.id DESC
                 LIMIT 10",
                $bindings
            );
        } catch (Throwable) {
            return [];
        }

        return array_map(static function (object $row): array {
            $item = (array) $row;
            $item['items_count'] = (int) ($item['items_count'] ?? 0);
            $item['subtotal'] = (float) ($item['subtotal'] ?? 0);
            $item['discount_total'] = (float) ($item['discount_total'] ?? 0);
            $item['tax_total'] = (float) ($item['tax_total'] ?? 0);
            $item['total'] = (float) ($item['total'] ?? 0);
            $item['url'] = '/crm?proposal=' . urlencode((string) ($item['id'] ?? ''));
            $item['pdf_url'] = '/v2/crm/proposals/' . urlencode((string) ($item['id'] ?? '')) . '/pdf';
            $item['public_url'] = !empty($item['public_hash'])
                ? '/proposal/' . urlencode((string) ($item['id'] ?? '')) . '/' . urlencode((string) $item['public_hash'])
                : null;

            return $item;
        }, $rows);
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
            $key = (string) ($item['meta_key'] ?? '');
            if (in_array($key, self::META_CIRUGIA_CONFIRMADA_KEYS, true)) {
                continue;
            }
            $result[] = [
                'id' => (int) ($item['id'] ?? 0),
                'key' => $key,
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
                'SELECT id, solicitud_id, template_key, status, error_message, to_emails, cc_emails, subject, attachment_name, sent_at, created_at, sent_by_user_id
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
     * @param array<int,array<string,mixed>>|null $checklistRows
     * @param array<int,array<string,mixed>>|null $taskRows
     */
    private function operationalFallbackState(int $solicitudId, ?array $checklistRows = null, ?array $taskRows = null): string
    {
        $rows = is_array($checklistRows) ? $checklistRows : $this->queryChecklistRows($solicitudId);
        if ($rows !== []) {
            return '';
        }

        $tasks = is_array($taskRows) ? $taskRows : $this->queryChecklistTaskRows($solicitudId);
        if ($this->buildChecklistRowsFromTasks($tasks, $rows) !== []) {
            return '';
        }

        return $this->resolvePersistedKanbanState($solicitudId);
    }

    private function resolvePersistedKanbanState(int $solicitudId): string
    {
        try {
            $row = DB::selectOne(
                'SELECT estado FROM solicitud_procedimiento WHERE id = ? LIMIT 1',
                [$solicitudId]
            );
        } catch (Throwable) {
            return '';
        }

        return is_object($row) && isset($row->estado) ? (string) $row->estado : '';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function queryChecklistRows(int $solicitudId): array
    {
        if (!$this->tableExists('solicitud_checklist')) {
            return [];
        }

        try {
            $rows = DB::select(
                'SELECT etapa_slug, completado_at, nota FROM solicitud_checklist WHERE solicitud_id = ? ORDER BY id',
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
    private function queryChecklistTaskRows(int $solicitudId): array
    {
        $taskMap = $this->queryChecklistTaskMap([$solicitudId]);

        return $taskMap[$solicitudId] ?? [];
    }

    /**
     * @param array<int,int> $solicitudIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private function queryChecklistTaskMap(array $solicitudIds): array
    {
        $solicitudIds = array_values(array_filter(array_map('intval', $solicitudIds), static fn(int $id): bool => $id > 0));
        if ($solicitudIds === []) {
            return [];
        }

        $columns = $this->tableColumns('crm_tasks');
        if (
            $columns === []
            || !in_array('source_module', $columns, true)
            || !in_array('source_ref_id', $columns, true)
        ) {
            return [];
        }

        $select = ['source_ref_id'];
        foreach (['id', 'title', 'status', 'completed_at', 'checklist_slug', 'task_key', 'metadata'] as $column) {
            if (in_array($column, $columns, true)) {
                $select[] = $column;
            } else {
                $select[] = 'NULL AS ' . $column;
            }
        }

        $bindings = ['solicitudes'];
        $where = 'source_module = ?';
        if (in_array('company_id', $columns, true)) {
            $where .= ' AND company_id = ?';
            $bindings[] = $this->resolveCompanyId();
        }

        $where .= ' AND source_ref_id IN (' . implode(', ', array_fill(0, count($solicitudIds), '?')) . ')';
        foreach ($solicitudIds as $id) {
            $bindings[] = (string) $id;
        }

        try {
            $rows = DB::select(
                'SELECT ' . implode(', ', $select) . ' FROM crm_tasks WHERE ' . $where,
                $bindings
            );
        } catch (Throwable) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $solicitudId = (int) ($item['source_ref_id'] ?? 0);
            if ($solicitudId <= 0) {
                continue;
            }

            $slug = $this->extractChecklistSlugFromTaskRow($item);
            if ($slug === '') {
                continue;
            }

            $grouped[$solicitudId][] = $item;
        }

        return $grouped;
    }

    /**
     * @param array<int,array<string,mixed>> $taskRows
     * @param array<int,array<string,mixed>> $checklistRows
     * @return array<int,array<string,mixed>>
     */
    private function buildChecklistRowsFromTasks(array $taskRows, array $checklistRows = []): array
    {
        if ($taskRows === []) {
            return [];
        }

        $persistedBySlug = [];
        foreach ($checklistRows as $row) {
            $slug = $this->normalizeKanbanSlug((string) ($row['etapa_slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $persistedBySlug[$slug] = $row;
        }

        $tasksBySlug = [];
        foreach ($taskRows as $row) {
            $slug = $this->extractChecklistSlugFromTaskRow($row);
            if ($slug === '') {
                continue;
            }

            $tasksBySlug[$slug] = $row;
        }

        if ($tasksBySlug === []) {
            return [];
        }

        $rows = [];
        foreach ($this->stateMachine->stages() as $stage) {
            $slug = $this->normalizeKanbanSlug((string) ($stage['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $task = $tasksBySlug[$slug] ?? null;
            $persisted = $persistedBySlug[$slug] ?? null;
            $status = strtolower(trim((string) ($task['status'] ?? '')));
            $isCompleted = in_array($status, ['completada', 'completed', 'done'], true);

            $rows[] = [
                'etapa_slug' => $slug,
                'completado_at' => $isCompleted
                    ? ($task['completed_at'] ?? ($persisted['completado_at'] ?? date('Y-m-d H:i:s')))
                    : null,
                'nota' => $persisted['nota'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,mixed> $bindings
     */
    private function updateRow(string $table, array $payload, string $where, array $bindings = []): int
    {
        if ($payload === []) {
            return 0;
        }

        $sets = [];
        $params = [];
        foreach ($payload as $column => $value) {
            $sets[] = '`' . $column . '` = ?';
            $params[] = $value;
        }
        foreach ($bindings as $value) {
            $params[] = $value;
        }

        try {
            return DB::update(
                sprintf(
                    'UPDATE `%s` SET %s WHERE %s',
                    str_replace('`', '', $table),
                    implode(', ', $sets),
                    $where
                ),
                $params
            );
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function insertRow(string $table, array $payload): void
    {
        if ($payload === []) {
            return;
        }

        try {
            DB::table($table)->insert($payload);
        } catch (Throwable) {
            // ignore: CRM read should degrade instead of fail hard
        }
    }

    private function normalizeChecklistDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $raw) === 1) {
            return strlen($raw) === 16 ? $raw . ':00' : $raw;
        }

        try {
            return (new DateTimeImmutable($raw))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
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

    private function parseDateEndOfDay(mixed $value): ?DateTimeImmutable
    {
        $date = $this->parseDate($value);
        if (!$date instanceof DateTimeImmutable) {
            return null;
        }

        $raw = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$|^\d{2}-\d{2}-\d{4}$|^\d{2}\/\d{2}\/\d{4}$/', $raw) === 1) {
            return $date->setTime(23, 59, 59);
        }

        return $date;
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
        return $this->stateMachine->normalizeKanbanSlug($value);
    }

    private function stageBySlug(string $slug): ?array
    {
        return $this->stateMachine->stageBySlug($slug);
    }

    private function stageIndex(string $slug): ?int
    {
        return $this->stateMachine->stageIndex($slug);
    }

    private function kanbanLabel(string $slug): string
    {
        return $this->stateMachine->kanbanLabel($slug, 'Sin estado');
    }

    private function tableExists(string $table): bool
    {
        // Todas las tablas del módulo tienen migración confirmada.
        // Esta verificación dinámica fue scaffolding de la migración incremental;
        // ya no es necesaria. Los guards en los call sites son dead-code candidatos
        // para limpieza en Fase E del plan de migración.
        return true;
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

    /**
     * @return array<int,string>
     */
    private function tableColumns(string $table): array
    {
        if (array_key_exists($table, $this->columnsCache)) {
            return $this->columnsCache[$table];
        }

        if (!$this->tableExists($table)) {
            $this->columnsCache[$table] = [];
            return [];
        }

        $columns = [];

        try {
            $rows = DB::select('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
            foreach ($rows as $row) {
                $field = trim((string) ($row->Field ?? ''));
                if ($field !== '') {
                    $columns[] = $field;
                }
            }
        } catch (Throwable) {
            $columns = [];
        }

        if ($columns === []) {
            try {
                $rows = DB::select(
                    'SELECT COLUMN_NAME
                     FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = ?
                     ORDER BY ORDINAL_POSITION',
                    [$table]
                );
                foreach ($rows as $row) {
                    $field = trim((string) ($row->COLUMN_NAME ?? ''));
                    if ($field !== '') {
                        $columns[] = $field;
                    }
                }
            } catch (Throwable) {
                $columns = [];
            }
        }

        $this->columnsCache[$table] = $columns;

        return $columns;
    }
}
