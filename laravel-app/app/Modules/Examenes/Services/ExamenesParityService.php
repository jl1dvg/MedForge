<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\Examenes\Models\ExamenModel;
use Modules\Examenes\Services\ExamenEstadoService;
use Modules\Notifications\Services\PusherConfigService;
use PDO;
use Throwable;

class ExamenesParityService
{
    private const PUSHER_CHANNEL = 'examenes-kanban';

    private static bool $legacyAutoloaderRegistered = false;

    private ExamenModel $examenModel;

    private ExamenEstadoService $estadoService;

    private LeadConfigurationService $leadConfig;

    private PusherConfigService $pusherConfig;

    public function __construct(private readonly PDO $db)
    {
        $this->ensureLegacyClassAutoloading();

        $this->examenModel = new ExamenModel($this->db);
        $this->estadoService = new ExamenEstadoService();
        $this->leadConfig = new LeadConfigurationService($this->db);
        $this->pusherConfig = new PusherConfigService($this->db);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function kanbanData(array $payload): array
    {
        $filtros = [
            'afiliacion' => trim((string) ($payload['afiliacion'] ?? '')),
            'doctor' => trim((string) ($payload['doctor'] ?? '')),
            'prioridad' => trim((string) ($payload['prioridad'] ?? '')),
            'estado' => trim((string) ($payload['estado'] ?? '')),
            'fechaTexto' => trim((string) ($payload['fechaTexto'] ?? '')),
            'con_pendientes' => (string) ($payload['con_pendientes'] ?? ''),
        ];

        $kanbanPreferences = [
            'sort' => 'fecha_desc',
            'column_limit' => 0,
        ];
        $pipelineStages = [];
        $responsables = [];
        $fuentes = [];

        try {
            $kanbanPreferences = $this->leadConfig->getKanbanPreferences(LeadConfigurationService::CONTEXT_EXAMENES);
        } catch (Throwable) {
            // Degradar a defaults cuando la configuración CRM no esté disponible.
        }

        try {
            $pipelineStages = $this->leadConfig->getPipelineStages();
        } catch (Throwable) {
            // Degradar a lista vacía cuando no existan tablas de CRM.
        }

        $examenes = $this->examenModel->fetchExamenesConDetallesFiltrado($filtros);
        $examenes = array_map(fn(array $row): array => $this->transformExamenRow($row), $examenes);
        $examenes = $this->estadoService->enrichExamenes($examenes);
        $examenes = $this->agruparExamenesPorSolicitud($examenes);

        if ($this->isTruthy($filtros['con_pendientes'] ?? null)) {
            $examenes = array_values(array_filter(
                $examenes,
                static fn(array $item): bool => (int) ($item['pendientes_estudios_total'] ?? 0) > 0
            ));
        }

        $examenes = $this->ordenarExamenes($examenes, (string) ($kanbanPreferences['sort'] ?? 'fecha_desc'));
        $examenes = $this->limitarExamenesPorEstado($examenes, (int) ($kanbanPreferences['column_limit'] ?? 0));

        try {
            $responsables = array_map(
                fn(array $usuario): array => $this->transformResponsable($usuario),
                $this->leadConfig->getAssignableUsers()
            );
        } catch (Throwable) {
            $responsables = [];
        }

        try {
            $fuentes = $this->leadConfig->getSources();
        } catch (Throwable) {
            $fuentes = [];
        }

        $afiliaciones = array_values(array_unique(array_filter(array_map(
            static fn(array $row): ?string => isset($row['afiliacion']) ? (string) $row['afiliacion'] : null,
            $examenes
        ))));
        sort($afiliaciones, SORT_NATURAL | SORT_FLAG_CASE);

        $doctores = array_values(array_unique(array_filter(array_map(
            static fn(array $row): ?string => isset($row['doctor']) ? (string) $row['doctor'] : null,
            $examenes
        ))));
        sort($doctores, SORT_NATURAL | SORT_FLAG_CASE);

        return [
            'status' => 200,
            'payload' => [
                'data' => $examenes,
                'options' => [
                    'afiliaciones' => $afiliaciones,
                    'doctores' => $doctores,
                    'crm' => [
                        'responsables' => $responsables,
                        'etapas' => $pipelineStages,
                        'fuentes' => $fuentes,
                        'kanban' => $kanbanPreferences,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function actualizarEstado(array $payload, ?int $userId): array
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $estado = trim((string) ($payload['estado'] ?? ''));
        if ($estado === '') {
            $estado = trim((string) ($payload['etapa'] ?? ''));
        }
        $origen = trim((string) ($payload['origen'] ?? 'kanban'));
        $observacion = isset($payload['observacion']) ? trim((string) $payload['observacion']) : null;

        if ($id <= 0 || $estado === '') {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'Datos incompletos',
                ],
            ];
        }

        $resultado = $this->examenModel->actualizarEstado(
            $id,
            $estado,
            $userId,
            $origen !== '' ? $origen : 'kanban',
            $observacion
        );

        $this->pusherConfig->trigger(
            $resultado + [
                'channels' => $this->pusherConfig->getNotificationChannels(),
            ],
            self::PUSHER_CHANNEL,
            PusherConfigService::EVENT_STATUS_UPDATED
        );

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'estado' => $resultado['estado'] ?? $estado,
                'turno' => $resultado['turno'] ?? null,
                'estado_anterior' => $resultado['estado_anterior'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function turneroData(array $query): array
    {
        $estados = $this->parseEstados($query['estado'] ?? null);

        $examenes = $this->examenModel->fetchTurneroExamenes($estados);

        foreach ($examenes as &$examen) {
            $nombreCompleto = trim((string) ($examen['full_name'] ?? ''));
            $examen['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
            $examen['turno'] = isset($examen['turno']) ? (int) $examen['turno'] : null;

            $estadoNormalizado = $this->normalizarEstadoTurnero((string) ($examen['estado'] ?? ''));
            $examen['estado'] = $estadoNormalizado ?? ($examen['estado'] ?? null);
            $examen['hora'] = null;
            $examen['fecha'] = null;

            if (!empty($examen['created_at'])) {
                $timestamp = strtotime((string) $examen['created_at']);
                if ($timestamp !== false) {
                    $examen['hora'] = date('H:i', $timestamp);
                    $examen['fecha'] = date('d/m/Y', $timestamp);
                }
            }
        }
        unset($examen);

        return [
            'status' => 200,
            'payload' => [
                'data' => $examenes,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function turneroLlamar(array $payload, ?int $userId): array
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : null;
        $turno = isset($payload['turno']) ? (int) $payload['turno'] : null;
        $estadoSolicitado = isset($payload['estado']) ? trim((string) $payload['estado']) : 'Llamado';
        $estadoNormalizado = $this->normalizarEstadoTurnero($estadoSolicitado);

        if ($estadoNormalizado === null) {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'Estado no permitido para el turnero',
                ],
            ];
        }

        if ((!$id || $id <= 0) && (!$turno || $turno <= 0)) {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'error' => 'Debe especificar un ID o número de turno',
                ],
            ];
        }

        $registro = $this->examenModel->llamarTurno(
            $id,
            $turno,
            $estadoNormalizado,
            $userId,
            'turnero'
        );

        if (!$registro) {
            return [
                'status' => 404,
                'payload' => [
                    'success' => false,
                    'error' => 'No se encontró el examen indicado',
                ],
            ];
        }

        $nombreCompleto = trim((string) ($registro['full_name'] ?? ''));
        $registro['full_name'] = $nombreCompleto !== '' ? $nombreCompleto : 'Paciente sin nombre';
        $registro['estado'] = $this->normalizarEstadoTurnero((string) ($registro['estado'] ?? ''))
            ?? ($registro['estado'] ?? null);

        try {
            $this->pusherConfig->trigger(
                [
                    'id' => (int) ($registro['id'] ?? $id ?? 0),
                    'turno' => $registro['turno'] ?? $turno,
                    'estado' => $registro['estado'] ?? $estadoNormalizado,
                    'hc_number' => $registro['hc_number'] ?? null,
                    'full_name' => $registro['full_name'] ?? null,
                    'kanban_estado' => $registro['kanban_estado'] ?? ($registro['estado'] ?? null),
                    'triggered_by' => $userId,
                ],
                self::PUSHER_CHANNEL,
                PusherConfigService::EVENT_TURNERO_UPDATED
            );
        } catch (Throwable) {
            // Ignorar errores de notificación para no bloquear la operación principal.
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'data' => $registro,
            ],
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function apiEstadoGet(?string $hcNumber): array
    {
        $hcNumber = trim((string) ($hcNumber ?? ''));

        if ($hcNumber === '') {
            return [
                'status' => 400,
                'payload' => [
                    'success' => false,
                    'message' => 'Parámetro hcNumber requerido',
                ],
            ];
        }

        return [
            'status' => 200,
            'payload' => $this->obtenerEstadosPorHc($hcNumber),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function apiEstadoPost(array $payload, ?int $userId): array
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        if ($id <= 0 && isset($payload['examen_id'])) {
            $id = (int) $payload['examen_id'];
        }

        if ($id <= 0) {
            return [
                'status' => 400,
                'payload' => [
                    'success' => false,
                    'message' => 'Parámetro id requerido para actualizar el examen',
                ],
            ];
        }

        $campos = [
            'estado' => $payload['estado'] ?? null,
            'doctor' => $payload['doctor'] ?? null,
            'solicitante' => $payload['solicitante'] ?? null,
            'consulta_fecha' => $payload['consulta_fecha'] ?? ($payload['fecha'] ?? null),
            'prioridad' => $payload['prioridad'] ?? null,
            'observaciones' => $payload['observaciones'] ?? ($payload['observacion'] ?? null),
            'examen_nombre' => $payload['examen_nombre'] ?? ($payload['examen'] ?? null),
            'examen_codigo' => $payload['examen_codigo'] ?? null,
            'lateralidad' => $payload['lateralidad'] ?? ($payload['ojo'] ?? null),
            'turno' => $payload['turno'] ?? null,
        ];

        $resultado = $this->examenModel->actualizarExamenParcial(
            $id,
            $campos,
            $userId,
            'api_estado',
            isset($payload['observacion']) ? trim((string) $payload['observacion']) : null
        );

        $status = (!is_array($resultado) || (($resultado['success'] ?? false) === false)) ? 422 : 200;

        return [
            'status' => $status,
            'payload' => is_array($resultado) ? $resultado : ['success' => false],
        ];
    }

    /**
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function derivacionDetalle(?string $hcNumber, ?string $formId, ?int $examenId, ?int $userId): array
    {
        $hcNumber = trim((string) ($hcNumber ?? ''));
        $formId = trim((string) ($formId ?? ''));
        $examenId = $examenId !== null && $examenId > 0 ? $examenId : null;

        if ($hcNumber === '' || $formId === '') {
            return [
                'status' => 400,
                'payload' => [
                    'success' => false,
                    'message' => 'Faltan parámetros para consultar la derivación.',
                ],
            ];
        }

        try {
            $derivacion = $this->ensureDerivacion($formId, $hcNumber, $examenId);
        } catch (Throwable) {
            return [
                'status' => 200,
                'payload' => [
                    'success' => true,
                    'has_derivacion' => false,
                    'derivacion_status' => 'error',
                    'derivacion' => null,
                ],
            ];
        }

        if (!$derivacion) {
            return [
                'status' => 200,
                'payload' => [
                    'success' => true,
                    'has_derivacion' => false,
                    'derivacion_status' => 'missing',
                    'message' => 'No hay derivación registrada para este examen.',
                    'derivacion' => null,
                ],
            ];
        }

        $vigenciaStatus = $this->resolveDerivacionVigenciaStatus(
            isset($derivacion['fecha_vigencia']) && is_string($derivacion['fecha_vigencia'])
                ? $derivacion['fecha_vigencia']
                : null
        );

        $estadoSugerido = null;
        if ($vigenciaStatus) {
            $examen = $this->examenModel->obtenerExamenPorFormHc($formId, $hcNumber, $examenId);
            if ($examen) {
                $estadoSugerido = $this->resolveEstadoPorDerivacion($vigenciaStatus, (string) ($examen['estado'] ?? ''));
                if ($estadoSugerido !== null) {
                    $this->actualizarEstadoPorFormHc(
                        $formId,
                        $hcNumber,
                        $estadoSugerido,
                        $userId,
                        'derivacion_vigencia',
                        'Actualizado por vigencia de derivación'
                    );
                }
            }
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'has_derivacion' => true,
                'derivacion_status' => 'ok',
                'message' => null,
                'derivacion' => $derivacion,
                'vigencia_status' => $vigenciaStatus,
                'estado_sugerido' => $estadoSugerido,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function derivacionPreseleccion(array $payload): array
    {
        $hcNumber = trim((string) ($payload['hc_number'] ?? ''));
        $formId = trim((string) ($payload['form_id'] ?? ''));
        $examenId = isset($payload['examen_id']) ? (int) $payload['examen_id'] : null;

        if ($hcNumber === '' || $formId === '') {
            return [
                'status' => 400,
                'payload' => [
                    'success' => false,
                    'message' => 'Faltan parámetros para consultar derivaciones disponibles.',
                ],
            ];
        }

        $seleccion = null;
        if ($examenId !== null && $examenId > 0) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccion($examenId);
        }

        if (!$seleccion) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccionPorFormHc($formId, $hcNumber);
        }

        if (!empty($seleccion['derivacion_pedido_id'])) {
            return [
                'status' => 200,
                'payload' => [
                    'success' => true,
                    'selected' => [
                        'codigo_derivacion' => $seleccion['derivacion_codigo'] ?? null,
                        'pedido_id_mas_antiguo' => $seleccion['derivacion_pedido_id'] ?? null,
                        'lateralidad' => $seleccion['derivacion_lateralidad'] ?? null,
                        'fecha_vigencia' => $seleccion['derivacion_fecha_vigencia_sel'] ?? null,
                        'prefactura' => $seleccion['derivacion_prefactura'] ?? null,
                    ],
                    'needs_selection' => false,
                    'options' => [],
                ],
            ];
        }

        $script = $this->projectRootPath() . '/scrapping/scrape_index_admisiones_hc.py';
        if (!is_file($script)) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'message' => 'No se encontró el script de admisiones.',
                ],
            ];
        }

        $cmd = sprintf(
            'python3 %s %s --group --quiet 2>&1',
            escapeshellarg($script),
            escapeshellarg($hcNumber)
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $rawOutput = trim(implode("\n", $output));
        $parsed = null;

        for ($i = count($output) - 1; $i >= 0; $i--) {
            $line = trim((string) $output[$i]);
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $parsed = $decoded;
                break;
            }
        }

        if (!$parsed) {
            return [
                'status' => 500,
                'payload' => [
                    'success' => false,
                    'message' => 'No se pudo interpretar la respuesta del scraper de admisiones.',
                    'raw_output' => $rawOutput,
                    'exit_code' => $exitCode,
                ],
            ];
        }

        $grouped = is_array($parsed['grouped'] ?? null) ? $parsed['grouped'] : [];
        $options = [];
        foreach ($grouped as $item) {
            if (!is_array($item)) {
                continue;
            }

            $data = is_array($item['data'] ?? null) ? $item['data'] : [];

            $options[] = [
                'codigo_derivacion' => $item['codigo_derivacion'] ?? null,
                'pedido_id_mas_antiguo' => $item['pedido_id_mas_antiguo'] ?? null,
                'lateralidad' => $item['lateralidad'] ?? null,
                'fecha_vigencia' => $data['fecha_grupo'] ?? null,
                'prefactura' => $data['prefactura'] ?? null,
            ];
        }

        return [
            'status' => 200,
            'payload' => [
                'success' => true,
                'selected' => null,
                'needs_selection' => true,
                'options' => $options,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function guardarDerivacionPreseleccion(array $payload): array
    {
        $examenId = isset($payload['examen_id']) ? (int) $payload['examen_id'] : null;
        $codigo = trim((string) ($payload['codigo_derivacion'] ?? ''));
        $pedidoId = trim((string) ($payload['pedido_id_mas_antiguo'] ?? ''));
        $lateralidad = trim((string) ($payload['lateralidad'] ?? ''));
        $vigencia = trim((string) ($payload['fecha_vigencia'] ?? ''));
        $prefactura = trim((string) ($payload['prefactura'] ?? ''));

        if (!$examenId || $codigo === '' || $pedidoId === '') {
            return [
                'status' => 422,
                'payload' => [
                    'success' => false,
                    'message' => 'Datos incompletos para guardar la derivación seleccionada.',
                ],
            ];
        }

        $saved = $this->examenModel->guardarDerivacionPreseleccion($examenId, [
            'derivacion_codigo' => $codigo,
            'derivacion_pedido_id' => $pedidoId,
            'derivacion_lateralidad' => $lateralidad !== '' ? $lateralidad : null,
            'derivacion_fecha_vigencia_sel' => $vigencia !== '' ? $vigencia : null,
            'derivacion_prefactura' => $prefactura !== '' ? $prefactura : null,
        ]);

        return [
            'status' => 200,
            'payload' => [
                'success' => $saved,
            ],
        ];
    }

    /**
     * @return array{success:bool,hcNumber:string,total:int,examenes:array<int,array<string,mixed>>}
     */
    private function obtenerEstadosPorHc(string $hcNumber): array
    {
        $examenes = $this->examenModel->obtenerEstadosPorHc($hcNumber);
        $examenes = array_map(fn(array $row): array => $this->transformExamenRow($row), $examenes);
        $examenes = $this->estadoService->enrichExamenes($examenes);

        return [
            'success' => true,
            'hcNumber' => $hcNumber,
            'total' => count($examenes),
            'examenes' => $examenes,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function transformExamenRow(array $row): array
    {
        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);

        if (empty($row['fecha'] ?? null)) {
            $row['fecha'] = $row['consulta_fecha'] ?? $row['created_at'] ?? null;
        }

        if (empty($row['procedimiento'] ?? null)) {
            $row['procedimiento'] = $row['examen_nombre'] ?? $row['examen_codigo'] ?? null;
        }

        if (empty($row['tipo'] ?? null)) {
            $row['tipo'] = $row['examen_codigo'] ?? $row['examen_nombre'] ?? null;
        }

        if (empty($row['observacion'] ?? null)) {
            $row['observacion'] = $row['observaciones'] ?? null;
        }

        if (empty($row['ojo'] ?? null)) {
            $row['ojo'] = $row['lateralidad'] ?? null;
        }

        $dias = 0;
        $fechaReferencia = $row['consulta_fecha'] ?? $row['created_at'] ?? null;
        if ($fechaReferencia) {
            $dt = $this->parseFecha($fechaReferencia);
            if ($dt) {
                $dias = max(0, (int) floor((time() - $dt->getTimestamp()) / 86400));
            }
        }

        $row['dias_transcurridos'] = $dias;

        if (!empty($row['derivacion_fecha_vigencia_sel']) && empty($row['derivacion_fecha_vigencia'])) {
            $row['derivacion_fecha_vigencia'] = $row['derivacion_fecha_vigencia_sel'];
        }
        $row['derivacion_status'] = $this->resolveDerivacionVigenciaStatus(
            isset($row['derivacion_fecha_vigencia']) && is_string($row['derivacion_fecha_vigencia'])
                ? $row['derivacion_fecha_vigencia']
                : null
        );

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    private function agruparExamenesPorSolicitud(array $examenes): array
    {
        $agrupados = [];

        foreach ($examenes as $examen) {
            $formId = trim((string) ($examen['form_id'] ?? ''));
            $hcNumber = trim((string) ($examen['hc_number'] ?? ''));
            if ($formId === '' || $hcNumber === '') {
                continue;
            }

            $key = $formId . '|' . $hcNumber;
            $estadoEstudio = $this->normalizarEstadoCoberturaEstudio((string) ($examen['estado'] ?? ''));

            if (!isset($agrupados[$key])) {
                $agrupados[$key] = $examen;
                $agrupados[$key]['estudios'] = [];
                $agrupados[$key]['resumen_estudios'] = [
                    'total' => 0,
                    'aprobados' => 0,
                    'pendientes' => 0,
                    'rechazados' => 0,
                    'sin_respuesta' => 0,
                ];
                $agrupados[$key]['kanban_rank'] = $this->rankEstadoKanban((string) ($examen['kanban_estado'] ?? $examen['estado'] ?? ''));
            }

            $agrupados[$key]['resumen_estudios']['total']++;
            if (isset($agrupados[$key]['resumen_estudios'][$estadoEstudio])) {
                $agrupados[$key]['resumen_estudios'][$estadoEstudio]++;
            }

            $agrupados[$key]['estudios'][] = [
                'id' => $examen['id'] ?? null,
                'codigo' => $examen['examen_codigo'] ?? null,
                'nombre' => $examen['examen_nombre'] ?? null,
                'estado' => $examen['estado'] ?? null,
                'estado_cobertura' => $estadoEstudio,
                'updated_at' => $examen['updated_at'] ?? ($examen['consulta_fecha'] ?? $examen['created_at'] ?? null),
            ];

            $rank = $this->rankEstadoKanban((string) ($examen['kanban_estado'] ?? $examen['estado'] ?? ''));
            if ($rank < (int) $agrupados[$key]['kanban_rank']) {
                $agrupados[$key]['kanban_rank'] = $rank;
                $agrupados[$key]['estado'] = $examen['estado'] ?? $agrupados[$key]['estado'];
                $agrupados[$key]['kanban_estado'] = $examen['kanban_estado']
                    ?? ($agrupados[$key]['kanban_estado'] ?? $agrupados[$key]['estado']);
                $agrupados[$key]['kanban_estado_label'] = $examen['kanban_estado_label']
                    ?? ($agrupados[$key]['kanban_estado_label'] ?? $agrupados[$key]['estado']);
            }
        }

        foreach ($agrupados as &$item) {
            $total = (int) ($item['resumen_estudios']['total'] ?? 0);
            $aprobados = (int) ($item['resumen_estudios']['aprobados'] ?? 0);
            $pendientes = (int) ($item['resumen_estudios']['pendientes'] ?? 0);
            $rechazados = (int) ($item['resumen_estudios']['rechazados'] ?? 0);
            $sinRespuesta = (int) ($item['resumen_estudios']['sin_respuesta'] ?? 0);
            $noAprobados = $pendientes + $rechazados + $sinRespuesta;

            if ($total > 0 && $aprobados > 0 && $noAprobados > 0) {
                $item['estado'] = 'Parcial';
                $item['kanban_estado'] = 'parcial';
                $item['kanban_estado_label'] = 'Parcial';
            } elseif ($total > 0 && $aprobados === $total) {
                $item['estado'] = 'Listo para agenda';
                $item['kanban_estado'] = 'listo-para-agenda';
                $item['kanban_estado_label'] = 'Listo para agenda';
            }

            $pendientesTotal = $pendientes + $sinRespuesta;
            $item['alert_pendientes_estudios'] = $pendientesTotal > 0;
            $item['pendientes_estudios_total'] = $pendientesTotal;
            unset($item['kanban_rank']);
        }
        unset($item);

        return array_values($agrupados);
    }

    private function normalizarEstadoCoberturaEstudio(string $estado): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($estado), 'UTF-8') : strtolower(trim($estado));
        if ($value === '') {
            return 'sin_respuesta';
        }
        if (str_contains($value, 'aprobad')) {
            return 'aprobados';
        }
        if (str_contains($value, 'rechaz')) {
            return 'rechazados';
        }
        if (str_contains($value, 'pend') || str_contains($value, 'revision') || str_contains($value, 'recibid')) {
            return 'pendientes';
        }

        return 'sin_respuesta';
    }

    private function rankEstadoKanban(string $estado): int
    {
        $slug = $this->estadoService->normalizeSlug($estado);

        $rank = [
            'recibido' => 0,
            'llamado' => 1,
            'revision-cobertura' => 2,
            'parcial' => 3,
            'listo-para-agenda' => 4,
            'completado' => 5,
        ];

        return $rank[$slug] ?? 99;
    }

    /**
     * @param array<string, mixed> $usuario
     * @return array<string, mixed>
     */
    private function transformResponsable(array $usuario): array
    {
        $usuario['avatar'] = $this->formatProfilePhoto($usuario['avatar'] ?? ($usuario['profile_photo'] ?? null));

        if (isset($usuario['profile_photo'])) {
            $usuario['profile_photo'] = $this->formatProfilePhoto($usuario['profile_photo']);
        }

        return $usuario;
    }

    private function formatProfilePhoto(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    private function ordenarExamenes(array $examenes, string $criterio): array
    {
        $criterio = strtolower(trim($criterio));

        $comparador = match ($criterio) {
            'fecha_asc' => fn(array $a, array $b): int => $this->compararPorFecha($a, $b, 'consulta_fecha', true),
            'creado_desc' => fn(array $a, array $b): int => $this->compararPorFecha($a, $b, 'created_at', false),
            'creado_asc' => fn(array $a, array $b): int => $this->compararPorFecha($a, $b, 'created_at', true),
            default => fn(array $a, array $b): int => $this->compararPorFecha($a, $b, 'consulta_fecha', false),
        };

        usort($examenes, $comparador);

        return $examenes;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function compararPorFecha(array $a, array $b, string $campo, bool $ascendente): int
    {
        $valorA = $this->parseFecha($a[$campo] ?? null);
        $valorB = $this->parseFecha($b[$campo] ?? null);

        if ($valorA === $valorB) {
            return 0;
        }

        if ($ascendente) {
            return $valorA <=> $valorB;
        }

        return $valorB <=> $valorA;
    }

    private function resolveDerivacionVigenciaStatus(?string $fechaVigencia): ?string
    {
        if (!$fechaVigencia) {
            return null;
        }

        $dt = $this->parseFecha($fechaVigencia);
        if (!$dt) {
            return null;
        }

        $hoy = new DateTimeImmutable('today');

        return $dt >= $hoy ? 'vigente' : 'vencida';
    }

    private function resolveEstadoPorDerivacion(?string $vigenciaStatus, string $estadoActual): ?string
    {
        if (!$vigenciaStatus) {
            return null;
        }

        $slug = $this->estadoService->normalizeSlug($estadoActual);
        if ($slug === '') {
            $slug = 'recibido';
        }

        if ($slug === 'completado') {
            return null;
        }

        if ($vigenciaStatus === 'vencida') {
            return $slug !== 'revision-cobertura' ? 'Revisión de cobertura' : null;
        }

        if ($vigenciaStatus === 'vigente') {
            if (in_array($slug, ['recibido', 'llamado', 'revision-cobertura'], true)) {
                return 'Listo para agenda';
            }
        }

        return null;
    }

    private function actualizarEstadoPorFormHc(
        string $formId,
        string $hcNumber,
        string $estado,
        ?int $changedBy = null,
        ?string $origen = null,
        ?string $observacion = null
    ): void {
        if ($formId === '' || $hcNumber === '') {
            return;
        }

        $examenes = $this->examenModel->obtenerExamenesPorFormHc($formId, $hcNumber);
        foreach ($examenes as $registro) {
            $id = isset($registro['id']) ? (int) $registro['id'] : 0;
            if ($id <= 0) {
                continue;
            }

            $this->examenModel->actualizarExamenParcial(
                $id,
                ['estado' => $estado],
                $changedBy,
                $origen,
                $observacion
            );
        }
    }

    private function ensureDerivacion(string $formId, string $hcNumber, ?int $examenId = null): ?array
    {
        $seleccion = null;
        if ($examenId !== null && $examenId > 0) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccion($examenId);
        }
        if (!$seleccion) {
            $seleccion = $this->examenModel->obtenerDerivacionPreseleccionPorFormHc($formId, $hcNumber);
        }

        $lookupFormId = (string) ($seleccion['derivacion_pedido_id'] ?? $formId);
        $hasSelection = trim((string) ($seleccion['derivacion_pedido_id'] ?? '')) !== '';

        if ($hasSelection) {
            $derivacion = $this->examenModel->obtenerDerivacionPorFormId($lookupFormId);
            if ($derivacion) {
                return $derivacion;
            }
        } else {
            $derivacion = $this->examenModel->obtenerDerivacionPorFormId($formId);
            if ($derivacion) {
                return $derivacion;
            }
        }

        $script = $this->projectRootPath() . '/scrapping/scrape_derivacion.py';
        if (!is_file($script)) {
            return null;
        }

        $cmd = sprintf(
            'python3 %s %s %s',
            escapeshellarg($script),
            escapeshellarg($lookupFormId),
            escapeshellarg($hcNumber)
        );

        try {
            @exec($cmd);
        } catch (Throwable) {
            // Silenciar para no romper flujo.
        }

        return $this->examenModel->obtenerDerivacionPorFormId($lookupFormId) ?: null;
    }

    /**
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    private function limitarExamenesPorEstado(array $examenes, int $limitePorColumna): array
    {
        if ($limitePorColumna <= 0) {
            return $examenes;
        }

        $contadores = [];
        $filtrados = [];

        foreach ($examenes as $examen) {
            $estadoBase = $examen['kanban_estado'] ?? $examen['estado'] ?? 'Pendiente';
            $estado = strtolower(trim((string) $estadoBase));
            $contadores[$estado] = ($contadores[$estado] ?? 0) + 1;

            if ($contadores[$estado] <= $limitePorColumna) {
                $filtrados[] = $examen;
            }
        }

        return $filtrados;
    }

    private function parseFecha(mixed $valor): ?DateTimeImmutable
    {
        if (empty($valor)) {
            return null;
        }

        if ($valor instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($valor);
        }

        $string = is_string($valor) ? trim($valor) : '';
        if ($string === '') {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $string);
            if ($dt instanceof DateTimeImmutable) {
                if ($format === 'Y-m-d') {
                    return $dt->setTime(0, 0);
                }

                return $dt;
            }
        }

        $timestamp = strtotime($string);
        if ($timestamp !== false) {
            return (new DateTimeImmutable())->setTimestamp($timestamp);
        }

        return null;
    }

    private function normalizarEstadoTurnero(string $estado): ?string
    {
        $mapa = [
            'recibido' => 'Recibido',
            'llamado' => 'Llamado',
            'revision-cobertura' => 'Revisión de Cobertura',
            'revision-de-cobertura' => 'Revisión de Cobertura',
            'revision-codigos' => 'Revisión de Cobertura',
            'revision-de-codigos' => 'Revisión de Cobertura',
            'revision de cobertura' => 'Revisión de Cobertura',
            'revision cobertura' => 'Revisión de Cobertura',
            'listo para agenda' => 'Listo para Agenda',
            'en atencion' => 'En atención',
            'en atención' => 'En atención',
            'atendido' => 'Atendido',
        ];

        $estadoLimpio = trim($estado);
        $clave = function_exists('mb_strtolower')
            ? mb_strtolower($estadoLimpio, 'UTF-8')
            : strtolower($estadoLimpio);
        $clave = strtr($clave, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        return $mapa[$clave] ?? null;
    }

    /**
     * @param mixed $raw
     * @return array<int, string>
     */
    private function parseEstados(mixed $raw): array
    {
        if (is_array($raw)) {
            $estados = array_map(static fn(mixed $value): string => trim((string) $value), $raw);
            return array_values(array_filter($estados, static fn(string $value): bool => $value !== ''));
        }

        $input = trim((string) ($raw ?? ''));
        if ($input === '') {
            return [];
        }

        $estados = array_map('trim', explode(',', $input));

        return array_values(array_filter($estados, static fn(string $value): bool => $value !== ''));
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) ($value ?? '')));

        return in_array($normalized, ['1', 'true', 'si', 'sí', 'yes'], true);
    }

    private function projectRootPath(): string
    {
        return realpath(base_path('..')) ?: base_path('..');
    }

    private function ensureLegacyClassAutoloading(): void
    {
        if (self::$legacyAutoloaderRegistered) {
            return;
        }

        $baseDir = realpath(base_path('..')) ?: base_path('..');

        $prefixes = [
            'Modules\\' => $baseDir . '/modules/',
            'Core\\' => $baseDir . '/core/',
            'Controllers\\' => $baseDir . '/controllers/',
            'Models\\' => $baseDir . '/models/',
            'Helpers\\' => $baseDir . '/helpers/',
            'Services\\' => $baseDir . '/controllers/Services/',
        ];

        spl_autoload_register(static function (string $class) use ($prefixes): void {
            foreach ($prefixes as $prefix => $legacyBaseDir) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    continue;
                }

                $relativeClass = substr($class, $len);
                $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

                $paths = [
                    $legacyBaseDir . $relativePath,
                    $legacyBaseDir . strtolower($relativePath),
                ];

                $segments = explode(DIRECTORY_SEPARATOR, $relativePath);
                $fileName = array_pop($segments) ?: '';
                $lowerDirPath = implode(DIRECTORY_SEPARATOR, array_map('strtolower', $segments));
                if ($lowerDirPath !== '') {
                    $paths[] = rtrim($legacyBaseDir . $lowerDirPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
                }

                foreach ($paths as $path) {
                    if (is_file($path)) {
                        require_once $path;
                        return;
                    }
                }
            }
        }, true, true);

        self::$legacyAutoloaderRegistered = true;
    }
}
