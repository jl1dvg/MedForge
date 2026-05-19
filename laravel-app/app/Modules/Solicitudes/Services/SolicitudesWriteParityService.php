<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use App\Modules\Solicitudes\Services\Traits\SolicitudesDbHelperTrait;
use DateTime;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class SolicitudesWriteParityService
{
    use SolicitudesDbHelperTrait;

    private const META_CIRUGIA_CONFIRMADA_KEYS = [
        'cirugia_confirmada_form_id',
        'cirugia_confirmada_hc_number',
        'cirugia_confirmada_fecha_inicio',
        'cirugia_confirmada_lateralidad',
        'cirugia_confirmada_membrete',
        'cirugia_confirmada_by',
        'cirugia_confirmada_at',
    ];

    private SolicitudesStateMachineService $stateMachine;
    private SolicitudesKanbanService $kanban;
    private SolicitudesCrmWriteService $crmWrite;

    public function __construct(
        private readonly PDO $db,
        private readonly SolicitudesReadParityService $readService,
        ?SolicitudesStateMachineService $stateMachine = null,
    ) {
        $this->stateMachine = $stateMachine ?? new SolicitudesStateMachineService();
        $this->kanban  = new SolicitudesKanbanService($this->db, $this->stateMachine, $this->readService);
        $this->crmWrite = new SolicitudesCrmWriteService($this->db, $this->readService);
    }

    // =========================================================================
    // Kanban delegations
    // =========================================================================

    /** @return array<string,mixed> */
    public function apiEstadoGet(string $hcNumber): array
    {
        return $this->kanban->apiEstadoGet($hcNumber);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function apiEstadoPost(array $payload): array
    {
        return $this->kanban->apiEstadoPost($payload);
    }

    /** @return array<string,mixed> */
    public function actualizarEstado(
        int $id,
        int $formId,
        string $estado,
        bool $completado,
        bool $force,
        ?int $userId,
        ?string $nota = null,
    ): array {
        return $this->kanban->actualizarEstado($id, $formId, $estado, $completado, $force, $userId, $nota);
    }

    /** @return array<string,mixed>|null */
    public function turneroLlamar(?int $id, ?int $turno, string $nuevoEstado): ?array
    {
        return $this->kanban->turneroLlamar($id, $turno, $nuevoEstado);
    }

    /** @return array<string,mixed> */
    public function crmChecklistState(int $solicitudId): array
    {
        return $this->kanban->crmChecklistState($solicitudId);
    }

    /** @return array<string,mixed> */
    public function crmActualizarChecklist(int $solicitudId, string $etapa, bool $completado, ?int $userId): array
    {
        return $this->kanban->crmActualizarChecklist($solicitudId, $etapa, $completado, $userId);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function crmBootstrap(int $solicitudId, array $payload, ?int $userId): array
    {
        return $this->kanban->crmBootstrap($solicitudId, $payload, $userId);
    }

    /** @return array<string,mixed> */
    public function crmActualizarTareaEstado(int $solicitudId, int $tareaId, string $estado, array $payloadExtra = []): array
    {
        return $this->kanban->crmActualizarTareaEstado($solicitudId, $tareaId, $estado, $payloadExtra);
    }

    // =========================================================================
    // CRM write delegations
    // =========================================================================

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function crmGuardarDetalles(int $solicitudId, array $payload, ?int $userId): array
    {
        return $this->crmWrite->crmGuardarDetalles($solicitudId, $payload, $userId);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function crmRegistrarBloqueo(int $solicitudId, array $payload, ?int $userId): array
    {
        return $this->crmWrite->crmRegistrarBloqueo($solicitudId, $payload, $userId);
    }

    /** @return array<string,mixed> */
    public function crmSubirAdjunto(
        int $solicitudId,
        string $nombreOriginal,
        string $rutaRelativa,
        ?string $mimeType,
        ?int $tamanoBytes,
        ?int $usuarioId,
        ?string $descripcion = null,
    ): array {
        return $this->crmWrite->crmSubirAdjunto(
            $solicitudId,
            $nombreOriginal,
            $rutaRelativa,
            $mimeType,
            $tamanoBytes,
            $usuarioId,
            $descripcion,
        );
    }

    /** @return array<string,mixed> */
    public function crmAgregarNota(int $solicitudId, string $nota, ?int $autorId): array
    {
        return $this->crmWrite->crmAgregarNota($solicitudId, $nota, $autorId);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function crmGuardarTarea(int $solicitudId, array $payload, ?int $autorId): array
    {
        return $this->crmWrite->crmGuardarTarea($solicitudId, $payload, $autorId);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function crmCrearPropuesta(int $solicitudId, array $payload, ?int $autorId): array
    {
        return $this->crmWrite->crmCrearPropuesta($solicitudId, $payload, $autorId);
    }

    // =========================================================================
    // Derivacion / cirugía (Phase 3 Step 3 — not yet extracted)
    // =========================================================================

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function guardarDetallesCirugia(int $solicitudId, array $payload): array
    {
        if (isset($payload['updates']) && is_array($payload['updates'])) {
            $updates = $payload['updates'];
            $allowed = [
                'estado',
                'doctor',
                'fecha',
                'prioridad',
                'observacion',
                'procedimiento',
                'producto',
                'ojo',
                'afiliacion',
                'duracion',
                'lente_id',
                'lente_nombre',
                'lente_poder',
                'lente_observacion',
                'incision',
            ];

            $campos = [];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $updates)) {
                    $campos[$key] = $updates[$key];
                }
            }

            $resultado = $this->actualizarSolicitudParcial($solicitudId, $campos);
            if (($resultado['success'] ?? false) !== true) {
                throw new RuntimeException((string) ($resultado['message'] ?? 'No se pudieron guardar los cambios'));
            }

            return [
                'success' => true,
                'message' => (string) ($resultado['message'] ?? 'Cambios guardados'),
                'data'    => $resultado['data'] ?? null,
            ];
        }

        $hcNumber = $payload['hcNumber'] ?? ($payload['hc_number'] ?? null);
        if ($hcNumber && isset($payload['form_id'], $payload['solicitudes']) && is_array($payload['solicitudes'])) {
            $resultado = $this->guardarSolicitudesBatchUpsert($payload);
            if (($resultado['success'] ?? false) !== true) {
                throw new RuntimeException((string) ($resultado['message'] ?? 'No se pudieron guardar las solicitudes'));
            }

            return $resultado;
        }

        throw new RuntimeException('Datos no válidos o incompletos');
    }

    /** @param array<string,mixed> $payload */
    public function guardarDerivacionPreseleccion(?int $solicitudId, array $payload): bool
    {
        $codigo      = trim((string) ($payload['codigo_derivacion'] ?? ''));
        $pedidoId    = trim((string) ($payload['pedido_id_mas_antiguo'] ?? ''));
        $lateralidad = trim((string) ($payload['lateralidad'] ?? ''));
        $vigencia    = trim((string) ($payload['fecha_vigencia'] ?? ''));
        $prefactura  = trim((string) ($payload['prefactura'] ?? ''));

        if (($solicitudId ?? 0) <= 0) {
            $solicitudId = isset($payload['solicitud_id']) ? (int) $payload['solicitud_id'] : 0;
        }

        if ($solicitudId <= 0) {
            $formId   = trim((string) ($payload['form_id'] ?? ''));
            $hcNumber = trim((string) ($payload['hc_number'] ?? ''));
            if ($formId !== '' && $hcNumber !== '') {
                $resolved    = DB::table('solicitud_procedimiento')
                    ->where('form_id', $formId)
                    ->where('hc_number', $hcNumber)
                    ->orderByDesc('id')
                    ->value('id');
                $solicitudId = $resolved ? (int) $resolved : 0;
            }
        }

        if ($solicitudId <= 0 || $codigo === '' || $pedidoId === '') {
            throw new RuntimeException('Datos incompletos para guardar la derivación seleccionada.');
        }

        $updated = DB::table('solicitud_procedimiento')
            ->where('id', $solicitudId)
            ->update([
                'derivacion_codigo'             => $codigo,
                'derivacion_pedido_id'          => $pedidoId,
                'derivacion_lateralidad'        => $lateralidad !== '' ? $lateralidad : null,
                'derivacion_fecha_vigencia_sel' => $vigencia    !== '' ? $vigencia    : null,
                'derivacion_prefactura'         => $prefactura  !== '' ? $prefactura  : null,
            ]);

        if ($updated > 0) {
            return true;
        }

        return DB::table('solicitud_procedimiento')->where('id', $solicitudId)->exists();
    }

    /** @return array<string,mixed> */
    public function confirmarConciliacionCirugia(int $solicitudId, string $protocoloFormId, ?int $userId): array
    {
        if ($solicitudId <= 0) {
            throw new RuntimeException('Solicitud inválida', 422);
        }

        $protocoloFormId = trim($protocoloFormId);
        if ($protocoloFormId === '') {
            throw new RuntimeException('Debes indicar el protocolo a asociar.', 422);
        }

        $solicitud = $this->readService->obtenerSolicitudConciliacionPorId($solicitudId);
        if ($solicitud === null) {
            throw new RuntimeException('No se encontró la solicitud.', 404);
        }

        $protocolo = $this->readService->obtenerProtocoloConciliacionPorFormId($protocoloFormId);
        if ($protocolo === null) {
            throw new RuntimeException('No se encontró el protocolo seleccionado.', 404);
        }

        $hcSolicitud = trim((string) ($solicitud['hc_number'] ?? ''));
        $hcProtocolo = trim((string) ($protocolo['hc_number'] ?? ''));
        if ($hcSolicitud === '' || $hcProtocolo === '' || $hcSolicitud !== $hcProtocolo) {
            throw new RuntimeException('El protocolo no pertenece al mismo paciente.', 422);
        }

        $fechaSolicitudTs = strtotime((string) ($solicitud['fecha_solicitud'] ?? '')) ?: 0;
        $fechaProtocoloTs = strtotime((string) ($protocolo['fecha_inicio'] ?? '')) ?: 0;
        if ($fechaSolicitudTs > 0 && $fechaProtocoloTs > 0 && $fechaProtocoloTs < $fechaSolicitudTs) {
            throw new RuntimeException('El protocolo debe ser posterior a la solicitud.', 422);
        }

        $lateralidadSolicitud = trim((string) ($solicitud['ojo_resuelto'] ?? ($solicitud['ojo'] ?? '')));
        $lateralidadProtocolo = trim((string) ($protocolo['lateralidad'] ?? ''));
        if (!$this->readService->lateralidadesCompatibles($lateralidadSolicitud, $lateralidadProtocolo)) {
            throw new RuntimeException('La lateralidad del protocolo no es compatible con la solicitud.', 422);
        }

        $this->guardarConfirmacionCirugiaMeta($solicitudId, $protocolo, $userId);

        $nota = sprintf(
            'Confirmación manual de cirugía con protocolo %s (%s).',
            (string) ($protocolo['form_id'] ?? ''),
            (string) ($protocolo['lateralidad'] ?? 'sin lateralidad')
        );

        $this->kanban->completeAllChecklistStages($solicitudId, $userId, $nota);
        $tareasActualizadas = $this->completarTodasLasTareasConciliacion($solicitudId);

        $completedState = $this->stateMachine->completedTerminalState();
        $this->kanban->persistEstado($solicitudId, (string) ($completedState['slug'] ?? SolicitudesStateMachineService::STATE_COMPLETADO));

        $checklistState = $this->kanban->crmChecklistState($solicitudId);

        return [
            'message'               => 'Solicitud confirmada y marcada como completada.',
            'estado'                => (string) ($completedState['slug'] ?? SolicitudesStateMachineService::STATE_COMPLETADO),
            'checklist'             => $checklistState['checklist'] ?? [],
            'checklist_progress'    => $checklistState['checklist_progress'] ?? [],
            'tareas_actualizadas'   => $tareasActualizadas,
            'protocolo_confirmado'  => [
                'form_id'        => trim((string) ($protocolo['form_id'] ?? '')),
                'hc_number'      => trim((string) ($protocolo['hc_number'] ?? '')),
                'fecha_inicio'   => $protocolo['fecha_inicio'] ?? null,
                'lateralidad'    => trim((string) ($protocolo['lateralidad'] ?? '')),
                'membrete'       => trim((string) ($protocolo['membrete'] ?? '')),
                'status'         => isset($protocolo['status']) ? (int) $protocolo['status'] : null,
                'confirmado_at'  => date('Y-m-d H:i:s'),
                'confirmado_by_id' => $userId ?: null,
            ],
        ];
    }

    // =========================================================================
    // Private helpers — derivacion only
    // =========================================================================

    private function completarTodasLasTareasConciliacion(int $solicitudId): int
    {
        $now = date('Y-m-d H:i:s');

        $crm = DB::table('crm_tasks')
            ->where('source_module', 'solicitudes')
            ->where('source_ref_id', (string) $solicitudId)
            ->where('company_id', $this->resolveCompanyId())
            ->where(static function ($q): void {
                $q->whereNull('status')->orWhere('status', '!=', 'completada');
            })
            ->update(['status' => 'completada', 'completed_at' => $now, 'updated_at' => $now]);

        $tareas = DB::table('solicitud_crm_tareas')
            ->where('solicitud_id', $solicitudId)
            ->where(static function ($q): void {
                $q->whereNull('estado')->orWhere('estado', '!=', 'completada');
            })
            ->update(['estado' => 'completada', 'completed_at' => $now]);

        return $crm + $tareas;
    }

    /** @param array<string,mixed> $protocolo */
    private function guardarConfirmacionCirugiaMeta(int $solicitudId, array $protocolo, ?int $usuarioId): void
    {
        $formId = trim((string) ($protocolo['form_id'] ?? ''));
        if ($formId === '') {
            throw new RuntimeException('No se puede guardar confirmación sin form_id de protocolo.');
        }

        $now = date('Y-m-d H:i:s');

        $metaValues = [
            'cirugia_confirmada_form_id'      => $formId,
            'cirugia_confirmada_hc_number'    => trim((string) ($protocolo['hc_number'] ?? '')),
            'cirugia_confirmada_fecha_inicio' => trim((string) ($protocolo['fecha_inicio'] ?? '')),
            'cirugia_confirmada_lateralidad'  => trim((string) ($protocolo['lateralidad'] ?? '')),
            'cirugia_confirmada_membrete'     => trim((string) ($protocolo['membrete'] ?? '')),
            'cirugia_confirmada_by'           => $usuarioId ? (string) $usuarioId : '',
            'cirugia_confirmada_at'           => $now,
        ];

        $metaTypes = [
            'cirugia_confirmada_form_id'      => 'texto',
            'cirugia_confirmada_hc_number'    => 'texto',
            'cirugia_confirmada_fecha_inicio' => 'fecha',
            'cirugia_confirmada_lateralidad'  => 'texto',
            'cirugia_confirmada_membrete'     => 'texto',
            'cirugia_confirmada_by'           => 'numero',
            'cirugia_confirmada_at'           => 'fecha',
        ];

        DB::table('solicitud_crm_meta')
            ->where('solicitud_id', $solicitudId)
            ->whereIn('meta_key', self::META_CIRUGIA_CONFIRMADA_KEYS)
            ->delete();

        $inserts = [];
        foreach (self::META_CIRUGIA_CONFIRMADA_KEYS as $metaKey) {
            $value = $metaValues[$metaKey] ?? '';
            if ($value === '') {
                continue;
            }
            $inserts[] = [
                'solicitud_id' => $solicitudId,
                'meta_key'     => $metaKey,
                'meta_value'   => $value,
                'meta_type'    => $metaTypes[$metaKey] ?? 'texto',
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        if ($inserts !== []) {
            DB::table('solicitud_crm_meta')->insert($inserts);
        }
    }

    /**
     * @param array<string,mixed> $campos
     * @return array<string,mixed>
     */
    private function actualizarSolicitudParcial(int $id, array $campos): array
    {
        $limpiar = static function (mixed $valor): mixed {
            if (is_string($valor)) {
                $valor = trim($valor);
                if ($valor === '' || strtoupper($valor) === 'SELECCIONE') {
                    return null;
                }
                return $valor;
            }
            return $valor === '' ? null : $valor;
        };

        $normFecha = static function (mixed $valor): ?string {
            $valor = is_string($valor) ? trim($valor) : $valor;
            if (!$valor) {
                return null;
            }
            if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $valor)) {
                return $valor;
            }
            if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $valor)) {
                $format = strlen($valor) === 19 ? 'Y-m-d\\TH:i:s' : 'Y-m-d\\TH:i';
                $date   = DateTime::createFromFormat($format, $valor);
                if ($date instanceof DateTime) {
                    return $date->format('Y-m-d H:i:s');
                }
            }
            $formats = ['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'm/d/Y H:i', 'm-d-Y H:i'];
            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, (string) $valor);
                if ($date instanceof DateTime) {
                    return $date->format(strlen($format) >= 10 ? 'Y-m-d H:i:s' : 'Y-m-d');
                }
            }
            return null;
        };

        // Todos los campos permitidos tienen migración confirmada en solicitud_procedimiento.
        $permitidos = [
            'estado', 'doctor', 'fecha', 'prioridad', 'observacion',
            'procedimiento', 'producto', 'ojo', 'afiliacion', 'duracion',
            'lente_id', 'lente_nombre', 'lente_poder', 'lente_observacion', 'incision',
        ];

        $updates = [];
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
                $valor = implode(',', array_filter(array_map(static fn(mixed $item): mixed => $limpiar($item), $valor)));
            } else {
                $valor = $limpiar($valor);
            }

            $updates[$campo] = $valor;
        }

        if ($updates === []) {
            return ['success' => false, 'message' => 'No se enviaron campos para actualizar'];
        }

        $rowsAffected = DB::table('solicitud_procedimiento')->where('id', $id)->update($updates);

        $row = DB::table('solicitud_procedimiento as sp')
            ->selectRaw('sp.*, COALESCE(cd.fecha, sp.fecha) AS fecha_programada')
            ->leftJoin('consulta_data as cd', static function ($join): void {
                $join->on('cd.hc_number', '=', 'sp.hc_number')
                     ->on('cd.form_id', '=', 'sp.form_id');
            })
            ->where('sp.id', $id)
            ->first();

        return [
            'success'       => true,
            'message'       => 'Solicitud actualizada correctamente',
            'rows_affected' => $rowsAffected,
            'data'          => $row ? (array) $row : null,
        ];
    }

    /** @return array<string,mixed> */
    private function guardarSolicitudesBatchUpsert(array $data): array
    {
        $hcNumber    = (string) ($data['hcNumber'] ?? ($data['hc_number'] ?? ''));
        $formId      = (string) ($data['form_id'] ?? '');
        $solicitudes = $data['solicitudes'] ?? null;

        if ($hcNumber === '' || $formId === '' || !is_array($solicitudes)) {
            return ['success' => false, 'message' => 'Datos no válidos o incompletos'];
        }

        $clean = static function (mixed $value): mixed {
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '' || in_array(mb_strtoupper($value), ['SELECCIONE', 'NINGUNO'], true)) {
                    return null;
                }
                return $value;
            }
            return $value === '' ? null : $value;
        };

        $normPrioridad = static function (mixed $value): string {
            $value = is_string($value) ? mb_strtoupper(trim($value)) : $value;
            return ($value === 'SI' || $value === 1 || $value === '1' || $value === true) ? 'SI' : 'NO';
        };

        $normFecha = static function (mixed $value): ?string {
            $value = is_string($value) ? trim($value) : $value;
            if (!$value) {
                return null;
            }
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value)) {
                return $value;
            }
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $value)) {
                $format = strlen($value) === 19 ? 'Y-m-d\\TH:i:s' : 'Y-m-d\\TH:i';
                $dt     = DateTime::createFromFormat($format, $value);
                if ($dt instanceof DateTime) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }
            $formats = ['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'm/d/Y H:i', 'm-d-Y H:i'];
            foreach ($formats as $format) {
                $dt = DateTime::createFromFormat($format, (string) $value);
                if ($dt instanceof DateTime) {
                    return $dt->format(strlen($format) >= 10 ? 'Y-m-d H:i:s' : 'Y-m-d');
                }
            }
            return null;
        };

        $missing = [];
        foreach ($solicitudes as $idx => $solicitud) {
            if (!is_array($solicitud)) {
                continue;
            }
            if ($clean($solicitud['procedimiento'] ?? null) === null) {
                $missing[] = $solicitud['secuencia'] ?? ($idx + 1);
            }
        }

        if ($missing !== []) {
            return [
                'success' => false,
                'message' => 'El procedimiento es obligatorio en todas las solicitudes (faltante en: ' . implode(', ', $missing) . ')',
            ];
        }

        $rows = [];
        foreach ($solicitudes as $solicitud) {
            if (!is_array($solicitud)) {
                continue;
            }

            $ojoValue = $solicitud['ojo'] ?? null;
            if (is_array($ojoValue)) {
                $ojoValue = implode(',', array_values(array_filter(array_map($clean, $ojoValue))));
            } else {
                $ojoValue = $clean($ojoValue);
            }

            $lenteId     = $clean($solicitud['lente_id'] ?? null);
            $lenteNombre = $clean($solicitud['lente_nombre'] ?? null);
            $lentePoder  = $clean($solicitud['lente_poder'] ?? null);
            $lenteObs    = $clean($solicitud['lente_observacion'] ?? null);
            $incision    = $clean($solicitud['incision'] ?? null);

            // Extrae campos del detalle principal si no vienen en el top level
            $detalles = $solicitud['detalles'] ?? [];
            if (is_array($detalles)) {
                $detallePlano = null;
                foreach ($detalles as $detalle) {
                    if (!is_array($detalle)) {
                        continue;
                    }
                    $detallePlano = $detalle;
                    if (!empty($detalle['principal']) || !empty($detalle['tipo'])) {
                        break;
                    }
                }

                if (is_array($detallePlano)) {
                    $lenteId     = $lenteId     ?: $clean($detallePlano['id_lente_intraocular'] ?? ($detallePlano['lente_id'] ?? null));
                    $lenteNombre = $lenteNombre ?: $clean($detallePlano['lente'] ?? ($detallePlano['lente_nombre'] ?? null));
                    $lentePoder  = $lentePoder  ?: $clean($detallePlano['poder'] ?? ($detallePlano['lente_poder'] ?? null));
                    $lenteObs    = $lenteObs    ?: $clean($detallePlano['observaciones'] ?? ($detallePlano['lente_observacion'] ?? null));
                    $incision    = $incision    ?: $clean($detallePlano['incision'] ?? null);
                    if (!$ojoValue) {
                        $ojoValue = $clean($detallePlano['lateralidad'] ?? null);
                    }
                }
            }

            $rows[] = [
                'hc_number'        => $hcNumber,
                'form_id'          => $formId,
                'secuencia'        => $solicitud['secuencia'] ?? null,
                'tipo'             => $clean($solicitud['tipo'] ?? null),
                'afiliacion'       => $clean($solicitud['afiliacion'] ?? null),
                'procedimiento'    => $clean($solicitud['procedimiento'] ?? null),
                'doctor'           => $clean($solicitud['doctor'] ?? null),
                'fecha'            => $normFecha($solicitud['fecha'] ?? null),
                'duracion'         => $clean($solicitud['duracion'] ?? null),
                'ojo'              => $ojoValue,
                'prioridad'        => $normPrioridad($solicitud['prioridad'] ?? 'NO'),
                'producto'         => $clean($solicitud['producto'] ?? null),
                'observacion'      => $clean($solicitud['observacion'] ?? null),
                'sesiones'         => $clean($solicitud['sesiones'] ?? null),
                'lente_id'         => $lenteId,
                'lente_nombre'     => $lenteNombre,
                'lente_poder'      => $lentePoder,
                'lente_observacion'=> $lenteObs,
                'incision'         => $incision,
            ];
        }

        // unique_solicitud index: (hc_number, form_id, secuencia)
        DB::table('solicitud_procedimiento')->upsert(
            $rows,
            ['hc_number', 'form_id', 'secuencia'],
            ['tipo', 'afiliacion', 'procedimiento', 'doctor', 'fecha', 'duracion', 'ojo',
             'prioridad', 'producto', 'observacion', 'sesiones',
             'lente_id', 'lente_nombre', 'lente_poder', 'lente_observacion', 'incision'],
        );

        return [
            'success' => true,
            'message' => 'Solicitudes guardadas o actualizadas correctamente',
            'total'   => count($rows),
        ];
    }
}
