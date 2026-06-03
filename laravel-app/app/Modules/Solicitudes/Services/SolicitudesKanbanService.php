<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use App\Events\Crm\SolicitudKanbanEstadoCambiado;
use App\Jobs\SendSolicitudReminderJob;
use App\Models\SolicitudEstadoLog;
use App\Modules\Solicitudes\Services\Traits\SolicitudesDbHelperTrait;
use Carbon\Carbon;
use DateTime;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Manages kanban state transitions, checklist progression, and the turnero
 * for solicitudes. Extracted from SolicitudesWriteParityService.
 */
class SolicitudesKanbanService
{
    use SolicitudesDbHelperTrait;

    private const TURNERO_STATE_MAP = [
        'recibido'    => 'Recibido',
        'recibida'    => 'Recibido',
        'llamado'     => 'Turno llamado',
        'turno llamado' => 'Turno llamado',
        'turno_llamado' => 'Turno llamado',
        'turno-llamado' => 'Turno llamado',
        'en atencion' => 'En atención',
        'en atención' => 'En atención',
        'atendido'    => 'Atendido',
    ];

    public function __construct(
        private readonly PDO $db,
        private readonly SolicitudesStateMachineService $stateMachine,
        private readonly SolicitudesReadParityService $readService,
    ) {
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /** @return array<string, mixed> */
    public function apiEstadoGet(string $hcNumber): array
    {
        $rows = DB::table('solicitud_procedimiento')
            ->where('hc_number', $hcNumber)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return [
            'success'     => true,
            'hcNumber'    => $hcNumber,
            'total'       => count($rows),
            'solicitudes' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    public function apiEstadoPost(array $payload): array
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        if ($id <= 0 && isset($payload['solicitud_id'])) {
            $id = (int) $payload['solicitud_id'];
        }

        if ($id <= 0) {
            throw new RuntimeException('Parámetro id requerido para actualizar la solicitud');
        }

        $campos = [];
        foreach ([
            'estado', 'doctor', 'fecha', 'prioridad', 'observacion',
            'procedimiento', 'producto', 'ojo', 'afiliacion', 'duracion',
            'lente_id', 'lente_nombre', 'lente_poder', 'lente_observacion', 'incision',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $campos[$field] = $payload[$field];
            }
        }

        return $this->actualizarSolicitudParcial($id, $campos);
    }

    /** @return array<string, mixed> */
    public function actualizarEstado(
        int $id,
        int $formId,
        string $estado,
        bool $completado,
        bool $force,
        ?int $userId,
        ?string $nota = null,
    ): array {
        if ($id <= 0 && $formId > 0) {
            $id = $this->findIdByFormId($formId) ?? 0;
        }

        $stageSlug = $this->normalizeKanbanSlug($estado);
        if ($id <= 0 || $stageSlug === '') {
            throw new RuntimeException('Datos incompletos');
        }

        $row = $this->fetchSolicitudById($id);
        if ($row === null) {
            throw new RuntimeException('Solicitud no encontrada');
        }

        $legacyState = (string) ($row['estado'] ?? '');
        [$checklist, $progress, $kanbanState, $transitionMeta] = $this->transitionChecklistStage(
            $id,
            $stageSlug,
            $completado,
            $userId,
            $nota,
            $force,
            $legacyState
        );

        $nextState      = (string) ($kanbanState['slug'] ?? $stageSlug);
        $nextStateLabel = (string) ($kanbanState['label'] ?? $this->kanbanLabel($nextState));

        $this->persistEstadoLog($id, $legacyState, $nextState, $userId, $nota);

        // Notify CRM pipeline about the stage change
        SolicitudKanbanEstadoCambiado::dispatch(
            solicitudId: $id,
            kanbanSlug: $nextState,
            estadoAnterior: $legacyState,
            actorUserId: $userId,
        );

        if ($nextState === 'programada') {
            $this->scheduleReminders($id);
        }

        $fresh = $this->fetchSolicitudById($id);

        return [
            'kanban_estado'       => $nextState,
            'kanban_estado_label' => $nextStateLabel,
            'estado'              => $nextState,
            'estado_label'        => $nextStateLabel,
            'turno'               => isset($fresh['turno']) ? (int) $fresh['turno'] : null,
            'checklist'           => $checklist,
            'checklist_progress'  => $progress,
            'estado_anterior'     => $legacyState,
            'transition'          => $transitionMeta,
        ];
    }

    public function turneroLlamar(?int $id, ?int $turno, string $nuevoEstado): ?array
    {
        $estadoNormalizado = $this->normalizeTurneroEstado($nuevoEstado);
        if ($estadoNormalizado === null) {
            throw new RuntimeException('Estado no permitido para el turnero');
        }

        return DB::transaction(function () use ($id, $turno, $estadoNormalizado): ?array {
            $registro = null;
            if (($turno ?? 0) > 0) {
                $row = DB::table('solicitud_procedimiento')
                    ->select(['id', 'turno', 'estado'])
                    ->where('turno', $turno)
                    ->lockForUpdate()
                    ->first();
                $registro = $row !== null ? (array) $row : null;
            } else {
                $row = DB::table('solicitud_procedimiento')
                    ->select(['id', 'turno', 'estado'])
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();
                $registro = $row !== null ? (array) $row : null;

                if ($registro === null && ($id ?? 0) > 0) {
                    $fallback = DB::table('solicitud_procedimiento')
                        ->select(['id', 'turno', 'estado'])
                        ->where('form_id', $id)
                        ->orderByDesc('id')
                        ->lockForUpdate()
                        ->first();
                    $registro = $fallback !== null ? (array) $fallback : null;
                }
            }

            if ($registro === null) {
                return null;
            }

            $estadoActual = $this->normalizeTurneroEstado((string) ($registro['estado'] ?? ''));
            if ($estadoActual === null) {
                return null;
            }

            if (empty($registro['turno'])) {
                $registro['turno'] = $this->asignarTurnoSiNecesario((int) $registro['id']);
            }

            DB::table('solicitud_procedimiento')
                ->where('id', (int) $registro['id'])
                ->update(['estado' => $estadoNormalizado]);

            $detalles = DB::selectOne(
                'SELECT sp.id, sp.turno, sp.estado, sp.hc_number, sp.form_id, sp.prioridad, sp.created_at,
                        TRIM(CONCAT_WS(" ",
                            NULLIF(TRIM(pd.fname), ""), NULLIF(TRIM(pd.mname), ""),
                            NULLIF(TRIM(pd.lname), ""), NULLIF(TRIM(pd.lname2), "")
                        )) AS full_name
                 FROM solicitud_procedimiento sp
                 INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
                 WHERE sp.id = ?',
                [(int) $registro['id']]
            );

            return $detalles !== null ? (array) $detalles : null;
        });
    }

    /** @return array<string, mixed> */
    public function crmChecklistState(int $solicitudId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $rows = $this->queryChecklistRows($solicitudId);
        $this->syncChecklistLinkedTasks($solicitudId, null, $rows, null);
        $resumen = $this->readService->crmResumen($solicitudId);
        $detalle = is_array($resumen['detalle'] ?? null) ? (array) $resumen['detalle'] : [];
        $taskRows = is_array($resumen['tareas'] ?? null) ? (array) $resumen['tareas'] : [];
        [$checklist, $progress] = $this->resolveOperationalChecklistFromTasks($solicitudId, $rows, $taskRows);

        return [
            'checklist'          => $checklist,
            'checklist_progress' => $progress,
            'tasks'              => $resumen['tareas'] ?? [],
            'lead_id'            => $detalle['crm_lead_id'] ?? null,
            'project_id'         => $detalle['crm_project_id'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    public function crmBootstrap(int $solicitudId, array $payload, ?int $userId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $rows              = $this->queryChecklistRows($solicitudId);
        $taskRows          = $this->queryChecklistTaskRows($solicitudId);
        $taskChecklistRows = $this->buildChecklistRowsFromTasks($taskRows, $rows);
        $seedStages        = [];

        if ($taskChecklistRows !== []) {
            [$resolvedChecklist] = $this->stateMachine->resolvePersistedChecklistContext(
                $taskChecklistRows, '',
                ['include_nota' => true, 'include_can_toggle' => true]
            );
            foreach ($resolvedChecklist as $item) {
                $seedStages[] = ['slug' => (string) ($item['slug'] ?? ''), 'completed' => !empty($item['completed'])];
            }
        } else {
            $legacyState = $this->legacyStateBySolicitud($solicitudId);
            foreach ($this->stateMachine->bootstrapStagesFromLegacyState($legacyState) as $stage) {
                $seedStages[] = ['slug' => (string) ($stage['slug'] ?? ''), 'completed' => (bool) ($stage['completed'] ?? false)];
            }
        }

        foreach ($seedStages as $stage) {
            if ($stage['slug'] === '' || $this->checklistRowExists($solicitudId, $stage['slug'])) {
                continue;
            }
            $this->upsertChecklistRow($solicitudId, $stage['slug'], (bool) $stage['completed'], $userId, null);
        }

        $result = $this->crmChecklistState($solicitudId);

        if (($payload['force_estado_sync'] ?? false) === true) {
            $this->syncSolicitudEstadoFromChecklist($solicitudId);
            $result = $this->crmChecklistState($solicitudId);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function crmActualizarTareaEstado(int $solicitudId, int $tareaId, string $estado, array $payloadExtra = []): array
    {
        $this->assertSolicitudExists($solicitudId);

        $estado = strtolower(trim($estado));
        if (!in_array($estado, ['pendiente', 'en_progreso', 'en_proceso', 'completada', 'cancelada'], true)) {
            throw new RuntimeException('Estado de tarea inválido');
        }

        // crm_tasks: todas las columnas necesarias están confirmadas por migración.
        $taskRow = $this->crmTaskRow($solicitudId, $tareaId);

        $now     = date('Y-m-d H:i:s');
        $payload = [
            'status'       => $estado,
            'updated_at'   => $now,
            'completed_at' => $estado === 'completada' ? $now : null,
        ];

        $titulo = $this->nullableString($payloadExtra['titulo'] ?? $payloadExtra['title'] ?? null);
        if ($titulo !== null) {
            $payload['title'] = $titulo;
        }
        if (array_key_exists('descripcion', $payloadExtra) || array_key_exists('description', $payloadExtra)) {
            $payload['description'] = $this->nullableString($payloadExtra['descripcion'] ?? $payloadExtra['description'] ?? null);
        }
        if (array_key_exists('assigned_to', $payloadExtra)) {
            $payload['assigned_to'] = $this->nullableInt($payloadExtra['assigned_to']);
        }
        if (array_key_exists('due_date', $payloadExtra)) {
            $payload['due_date'] = $this->normalizeDate($payloadExtra['due_date']);
        }
        if (array_key_exists('due_at', $payloadExtra) || array_key_exists('due_date', $payloadExtra)) {
            $dueDate = $payload['due_date'] ?? null;
            $payload['due_at'] = $this->normalizeDateTime($payloadExtra['due_at'] ?? null)
                ?? ($dueDate ? $dueDate . ' 23:59:59' : null);
        }
        if (array_key_exists('remind_at', $payloadExtra)) {
            $payload['remind_at'] = $this->normalizeDateTime($payloadExtra['remind_at']);
        }
        if (array_key_exists('priority', $payloadExtra) || array_key_exists('prioridad', $payloadExtra)) {
            $payload['priority'] = $this->normalizeTaskPriority($payloadExtra['priority'] ?? $payloadExtra['prioridad'] ?? null);
        }

        DB::table('crm_tasks')
            ->where('id', $tareaId)
            ->where('source_module', 'solicitudes')
            ->where('source_ref_id', (string) $solicitudId)
            ->where('company_id', $this->resolveCompanyId())
            ->update($payload);

        $checklistSlug = $this->extractChecklistSlugFromTaskRow($taskRow);
        if ($checklistSlug !== '' && in_array($estado, ['completada', 'pendiente'], true)) {
            $rows = $this->queryChecklistRows($solicitudId);
            $this->transitionChecklistStage(
                $solicitudId, $checklistSlug, $estado === 'completada',
                null, null, false,
                $this->operationalFallbackState($solicitudId, $rows)
            );
            $this->syncChecklistLinkedTasks($solicitudId, $checklistSlug);
        }

        return $this->readService->crmResumen($solicitudId);
    }

    // Exposes persistence for DerivacionService (used until that service is extracted)
    public function persistEstado(int $solicitudId, string $stateSlug): void
    {
        $this->persistOperationalState($solicitudId, $stateSlug);
    }

    // Exposes full checklist completion for DerivacionService
    public function completeAllChecklistStages(int $solicitudId, ?int $userId, ?string $note): void
    {
        foreach ($this->stateMachine->stages() as $stage) {
            $slug = (string) ($stage['slug'] ?? '');
            if ($slug !== '') {
                $this->upsertChecklistRow($solicitudId, $slug, true, $userId, $note);
            }
        }
    }

    /** @return array<string, mixed> */
    public function crmActualizarChecklist(int $solicitudId, string $etapa, bool $completado, ?int $userId): array
    {
        $this->assertSolicitudExists($solicitudId);

        $slug = $this->normalizeKanbanSlug($etapa);
        if ($slug === '') {
            throw new RuntimeException('Etapa requerida');
        }

        $rows = $this->queryChecklistRows($solicitudId);
        $this->transitionChecklistStage(
            $solicitudId,
            $slug,
            $completado,
            $userId,
            null,
            false,
            $this->operationalFallbackState($solicitudId, $rows)
        );

        return $this->crmChecklistState($solicitudId);
    }

    // =========================================================================
    // State persistence
    // =========================================================================

    private function persistEstadoLog(
        int $solicitudId,
        string $estadoAnterior,
        string $estadoNuevo,
        ?int $userId,
        ?string $nota,
        string $origen = 'manual',
    ): void {
        if ($estadoAnterior === $estadoNuevo) {
            return;
        }

        try {
            SolicitudEstadoLog::create([
                'solicitud_id'    => $solicitudId,
                'estado_anterior' => $estadoAnterior !== '' ? $estadoAnterior : null,
                'estado_nuevo'    => $estadoNuevo,
                'user_id'         => $userId,
                'nota'            => $nota,
                'origen'          => $origen,
            ]);
        } catch (Throwable) {
            // Never block a transition if the log table isn't ready yet.
        }
    }

    private function persistOperationalState(int $solicitudId, string $stateSlug): void
    {
        // solicitud_procedimiento no tiene columna updated_at — sólo actualizamos estado.
        DB::table('solicitud_procedimiento')
            ->where('id', $solicitudId)
            ->update(['estado' => $stateSlug]);
    }

    // =========================================================================
    // Checklist / state-machine transitions
    // =========================================================================

    /**
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>,2:array{slug:string,label:string},3:array<string,mixed>}
     */
    private function transitionChecklistStage(
        int $solicitudId,
        string $stageSlug,
        bool $completed,
        ?int $userId,
        ?string $note,
        bool $force,
        string $fallbackState,
    ): array {
        $currentRows  = $this->queryChecklistRows($solicitudId);
        $currentTasks = $this->queryChecklistTaskRows($solicitudId);
        [, , $previousKanban] = $this->resolveOperationalChecklistContext(
            $solicitudId, $currentRows, $currentTasks, $fallbackState
        );
        $previousState = (string) ($previousKanban['slug'] ?? $fallbackState);

        if (!$force && $completed && !$this->canCompleteStage($currentRows, $stageSlug, $fallbackState)) {
            throw new RuntimeException('Debe completar etapas previas antes de continuar.');
        }

        $this->upsertChecklistRow($solicitudId, $stageSlug, $completed, $userId, $note);

        $rows = $this->queryChecklistRows($solicitudId);
        [$checklist, $progress] = $this->stateMachine->resolvePersistedChecklistContext($rows, $fallbackState, [
            'include_nota'       => true,
            'include_can_toggle' => true,
        ]);
        $this->syncChecklistLinkedTasks($solicitudId, null, $rows, $checklist);
        $taskRows = $this->queryChecklistTaskRows($solicitudId);
        [$checklist, $progress, $kanbanState] = $this->resolveOperationalChecklistContext(
            $solicitudId, $rows, $taskRows, $fallbackState
        );

        $nextState = (string) ($kanbanState['slug'] ?? $fallbackState);
        if ($nextState !== '') {
            $this->persistOperationalState($solicitudId, $nextState);
        }

        $transitionMeta = $this->stateMachine->describeTransition($previousState, $nextState);

        return [$checklist, $progress, $kanbanState, $transitionMeta];
    }

    private function upsertChecklistRow(
        int $solicitudId,
        string $slug,
        bool $completed,
        ?int $userId,
        ?string $note,
    ): void {
        $now         = date('Y-m-d H:i:s');
        $completedAt = $completed ? $now : null;

        $existing = DB::table('solicitud_checklist')
            ->where('solicitud_id', $solicitudId)
            ->where('etapa_slug', $slug)
            ->value('id');

        if ($existing !== null) {
            $update = [
                'checked'       => $completed ? 1 : 0,
                'completado_at' => $completedAt,
                'completado_por'=> $completed ? $userId : null,
                'updated_at'    => $now,
            ];
            if ($note !== null) {
                $update['nota'] = trim($note);
            }

            DB::table('solicitud_checklist')->where('id', (int) $existing)->update($update);
            return;
        }

        $insert = [
            'solicitud_id'  => $solicitudId,
            'etapa_slug'    => $slug,
            'checked'       => $completed ? 1 : 0,
            'completado_at' => $completedAt,
            'completado_por'=> $completed ? $userId : null,
            'nota'          => $note !== null ? trim($note) : null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ];

        DB::table('solicitud_checklist')->insert($insert);
    }

    private function checklistRowExists(int $solicitudId, string $slug): bool
    {
        return DB::table('solicitud_checklist')
            ->where('solicitud_id', $solicitudId)
            ->where('etapa_slug', $slug)
            ->exists();
    }

    private function syncSolicitudEstadoFromChecklist(int $solicitudId): void
    {
        $rows         = $this->queryChecklistRows($solicitudId);
        $taskRows     = $this->queryChecklistTaskRows($solicitudId);
        $fallbackState = $this->operationalFallbackState($solicitudId, $rows, $taskRows);
        [, , $kanban] = $this->resolveOperationalChecklistContext($solicitudId, $rows, $taskRows, $fallbackState);

        $this->persistOperationalState($solicitudId, (string) ($kanban['slug'] ?? $fallbackState));
    }

    // =========================================================================
    // Checklist queries & context resolvers
    // =========================================================================

    /** @return array<int, array<string, mixed>> */
    private function queryChecklistRows(int $solicitudId): array
    {
        return DB::table('solicitud_checklist')
            ->select('etapa_slug', 'completado_at', 'nota')
            ->where('solicitud_id', $solicitudId)
            ->orderBy('id')
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function queryChecklistTaskRows(int $solicitudId): array
    {
        // crm_tasks schema confirmado. checklist_slug y task_key no existen como columnas
        // propias — el código los extrae de la columna metadata (JSON).
        return DB::table('crm_tasks')
            ->select(['id', 'status', 'completed_at', 'metadata'])
            ->where('source_module', 'solicitudes')
            ->where('source_ref_id', (string) $solicitudId)
            ->where('company_id', $this->resolveCompanyId())
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $tasks
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>}
     */
    private function resolveOperationalChecklistFromTasks(int $solicitudId, array $rows, array $tasks): array
    {
        [$checklist, $progress] = $this->resolveOperationalChecklistContext(
            $solicitudId,
            $rows,
            $tasks,
            $this->operationalFallbackState($solicitudId, $rows, $tasks)
        );

        return [$checklist, $progress];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, array<string, mixed>> $tasks
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>,2:array{slug:string,label:string}}
     */
    private function resolveOperationalChecklistContext(
        int $solicitudId,
        array $rows,
        array $tasks,
        string $fallbackState,
    ): array {
        $taskChecklistRows = $this->buildChecklistRowsFromTasks($tasks, $rows);
        $source            = $taskChecklistRows !== [] ? $taskChecklistRows : $rows;

        return $this->stateMachine->resolvePersistedChecklistContext(
            $source,
            $fallbackState,
            ['include_nota' => true, 'include_can_toggle' => true]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $taskRows
     * @param array<int, array<string, mixed>> $checklistRows
     * @return array<int, array<string, mixed>>
     */
    private function buildChecklistRowsFromTasks(array $taskRows, array $checklistRows = []): array
    {
        if ($taskRows === []) {
            return [];
        }

        $persistedBySlug = [];
        foreach ($checklistRows as $row) {
            $slug = $this->normalizeKanbanSlug((string) ($row['etapa_slug'] ?? ''));
            if ($slug !== '') {
                $persistedBySlug[$slug] = $row;
            }
        }

        $tasksBySlug = [];
        foreach ($taskRows as $task) {
            if (!is_array($task)) {
                continue;
            }
            $slug = $this->extractChecklistSlugFromTaskRow($task);
            if ($slug !== '') {
                $tasksBySlug[$slug] = $task;
            }
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

            $task      = $tasksBySlug[$slug] ?? null;
            $persisted = $persistedBySlug[$slug] ?? null;
            $status    = strtolower(trim((string) ($task['estado'] ?? $task['status'] ?? '')));
            $isCompleted = in_array($status, ['completada', 'completed', 'done'], true);

            $rows[] = [
                'etapa_slug'    => $slug,
                'completado_at' => $isCompleted
                    ? ($task['completed_at'] ?? ($persisted['completado_at'] ?? date('Y-m-d H:i:s')))
                    : null,
                'nota'          => $persisted['nota'] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $checklistRows
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>,2:array{slug:string,label:string}}
     */
    private function buildChecklistContext(string $legacyState, array $checklistRows): array
    {
        return $this->stateMachine->buildChecklistContext($legacyState, $checklistRows, [
            'include_nota'       => true,
            'include_can_toggle' => true,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function canCompleteStage(array $rows, string $targetSlug, string $fallbackState): bool
    {
        [$checklist] = $this->stateMachine->resolvePersistedChecklistContext($rows, $fallbackState, [
            'include_nota'       => true,
            'include_can_toggle' => true,
        ]);

        $targetOrder = null;
        foreach ($this->stateMachine->stages() as $stage) {
            if ($stage['slug'] === $targetSlug) {
                $targetOrder = (int) $stage['order'];
                break;
            }
        }

        if ($targetOrder === null) {
            return true;
        }

        foreach ($checklist as $item) {
            $itemOrder = (int) ($item['order'] ?? 0);
            if ($itemOrder >= $targetOrder) {
                continue;
            }
            if (!empty($item['required']) && empty($item['completed'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>>|null $rows
     * @param array<int, array<string, mixed>>|null $tasks
     */
    private function operationalFallbackState(int $solicitudId, ?array $rows = null, ?array $tasks = null): string
    {
        $checklistRows = $rows ?? $this->queryChecklistRows($solicitudId);
        if ($checklistRows !== []) {
            return '';
        }

        $taskRows = $tasks ?? $this->queryChecklistTaskRows($solicitudId);
        if ($this->buildChecklistRowsFromTasks($taskRows, $checklistRows) !== []) {
            return '';
        }

        return $this->legacyStateBySolicitud($solicitudId);
    }

    private function syncChecklistLinkedTasks(
        int $solicitudId,
        ?string $targetSlug = null,
        ?array $rows = null,
        ?array $checklist = null,
    ): int {
        $checklistRows     = $rows ?? $this->queryChecklistRows($solicitudId);
        $resolvedChecklist = $checklist;
        if (!is_array($resolvedChecklist)) {
            [$resolvedChecklist] = $this->stateMachine->resolvePersistedChecklistContext(
                $checklistRows,
                $this->operationalFallbackState($solicitudId, $checklistRows, null),
                ['include_nota' => true, 'include_can_toggle' => true]
            );
        }

        $items = array_values(array_filter(
            is_array($resolvedChecklist) ? $resolvedChecklist : [],
            function (array $item) use ($targetSlug): bool {
                $slug = $this->normalizeKanbanSlug((string) ($item['slug'] ?? ''));
                return $slug !== '' && ($targetSlug === null || $slug === $targetSlug);
            }
        ));

        if ($items === []) {
            return 0;
        }

        $companyId = $this->resolveCompanyId();

        // checklist_slug y task_key no existen como columnas propias — se guardan en metadata.
        $existingRows = DB::table('crm_tasks')
            ->select(['id', 'status', 'completed_at', 'title', 'description', 'metadata'])
            ->where('source_module', 'solicitudes')
            ->where('source_ref_id', (string) $solicitudId)
            ->where('company_id', $companyId)
            ->get()
            ->map(static fn ($row) => (array) $row)
            ->all();

        $existingBySlug = [];
        foreach ($existingRows as $row) {
            $slug = $this->extractChecklistSlugFromTaskRow($row);
            if ($slug !== '') {
                $existingBySlug[$slug][] = $row;
            }
        }

        $updatedCount = 0;
        $now          = date('Y-m-d H:i:s');

        foreach ($items as $item) {
            $slug = $this->normalizeKanbanSlug((string) ($item['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $title             = trim((string) ($item['label'] ?? $slug));
            $isCompleted       = !empty($item['completed']);
            $targetStatus      = $isCompleted ? 'completada' : 'pendiente';
            $targetCompletedAt = $isCompleted
                ? ($this->normalizeDateTime($item['completado_at'] ?? null) ?? $now)
                : null;

            $rowsForSlug = $existingBySlug[$slug] ?? [];

            if ($rowsForSlug === []) {
                DB::table('crm_tasks')->insert([
                    'company_id'    => $companyId,
                    'source_module' => 'solicitudes',
                    'source_ref_id' => (string) $solicitudId,
                    'title'         => $title,
                    'description'   => 'Checklist de solicitud',
                    'status'        => $targetStatus,
                    'metadata'      => json_encode([
                        'task_key'        => 'checklist:' . $slug,
                        'checklist_slug'  => $slug,
                        'checklist_label' => $title,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'completed_at'  => $targetCompletedAt,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
                $updatedCount++;
                continue;
            }

            foreach ($rowsForSlug as $row) {
                $payload = [];
                if ((string) ($row['status'] ?? '') !== $targetStatus) {
                    $payload['status'] = $targetStatus;
                }
                if (($row['completed_at'] ?? null) !== $targetCompletedAt) {
                    $payload['completed_at'] = $targetCompletedAt;
                }
                if (trim((string) ($row['title'] ?? '')) !== $title) {
                    $payload['title'] = $title;
                }
                if (trim((string) ($row['description'] ?? '')) === '') {
                    $payload['description'] = 'Checklist de solicitud';
                }
                $metadata = $this->mergeChecklistTaskMetadata($row['metadata'] ?? null, $slug, $title);
                if ($metadata !== ($row['metadata'] ?? null)) {
                    $payload['metadata'] = $metadata;
                }
                if ($payload === []) {
                    continue;
                }

                $payload['updated_at'] = $now;

                $updatedCount += DB::table('crm_tasks')
                    ->where('id', (int) $row['id'])
                    ->where('source_module', 'solicitudes')
                    ->where('source_ref_id', (string) $solicitudId)
                    ->where('company_id', $companyId)
                    ->update($payload);
            }
        }

        return $updatedCount;
    }

    // =========================================================================
    // Reminders
    // =========================================================================

    private function scheduleReminders(int $solicitudId): void
    {
        try {
            $row      = $this->fetchSolicitudById($solicitudId);
            $fechaRaw = trim((string) (($row['sigcenter_fecha_inicio'] ?? '') ?: ($row['fecha'] ?? '')));

            if ($fechaRaw === '' || !str_contains($fechaRaw, '-')) {
                return;
            }

            $fechaCirugia = Carbon::parse($fechaRaw);
            if ($fechaCirugia->isPast()) {
                return;
            }

            $delay2d = $fechaCirugia->copy()->subDays(2);
            if ($delay2d->isFuture()) {
                SendSolicitudReminderJob::dispatch($solicitudId, 'preop_2d', $fechaRaw)->delay($delay2d);
            }

            $delay24h = $fechaCirugia->copy()->subHours(24);
            if ($delay24h->isFuture()) {
                SendSolicitudReminderJob::dispatch($solicitudId, 'preop_24h', $fechaRaw)->delay($delay24h);
            }

            SendSolicitudReminderJob::dispatch($solicitudId, 'postop', $fechaRaw)
                ->delay($fechaCirugia->copy()->addDay());

            $this->insertNota(
                $solicitudId,
                null,
                sprintf(
                    'Recordatorios quirúrgicos programados para %s: preop (2d antes), preop (24h antes), postop (1d después).',
                    $fechaCirugia->format('d/m/Y')
                )
            );
        } catch (Throwable) {
            // Best-effort — never block the transition.
        }
    }

    // =========================================================================
    // Partial field update (used by apiEstadoPost)
    // =========================================================================

    /** @return array<string, mixed> */
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

        $permitidos = [
            'estado', 'doctor', 'fecha', 'prioridad', 'observacion',
            'procedimiento', 'producto', 'ojo', 'afiliacion', 'duracion',
            'lente_id', 'lente_nombre', 'lente_poder', 'lente_observacion', 'incision',
        ];

        // Todos los campos en $permitidos tienen migración confirmada en solicitud_procedimiento.
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
                $valor = implode(',', array_filter(array_map(static fn(mixed $i): mixed => $limpiar($i), $valor)));
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

    // =========================================================================
    // Normalization helpers
    // =========================================================================

    private function normalizeTurneroEstado(string $estado): ?string
    {
        $limpio = trim($estado);
        if ($limpio === '') {
            return null;
        }

        $key = mb_strtolower($limpio, 'UTF-8');

        return self::TURNERO_STATE_MAP[$key] ?? null;
    }

    private function kanbanLabel(string $slug): string
    {
        return $this->stateMachine->kanbanLabel($slug);
    }

    private function stageIndex(string $slug): ?int
    {
        return $this->stateMachine->stageIndex($slug);
    }

    private function stageBySlug(string $slug): ?array
    {
        return $this->stateMachine->stageBySlug($slug);
    }

    // =========================================================================
    // Turno assignment
    // =========================================================================

    private function asignarTurnoSiNecesario(int $id): ?int
    {
        // Called inside a DB::transaction, so lockForUpdate is valid here.
        $actual = DB::table('solicitud_procedimiento')
            ->where('id', $id)
            ->lockForUpdate()
            ->value('turno');

        if ($actual !== null) {
            return (int) $actual;
        }

        $maxTurno = (int) DB::table('solicitud_procedimiento')
            ->whereNotNull('turno')
            ->orderByDesc('turno')
            ->lockForUpdate()
            ->value('turno');

        $siguiente = $maxTurno + 1;

        $updated = DB::table('solicitud_procedimiento')
            ->where('id', $id)
            ->whereNull('turno')
            ->update(['turno' => $siguiente]);

        if ($updated === 0) {
            // Another concurrent request assigned the turno — re-read the committed value.
            $actual = DB::table('solicitud_procedimiento')->where('id', $id)->value('turno');
            return $actual !== null ? (int) $actual : null;
        }

        return $siguiente;
    }

    /** @param array<int, string> $columns */
    /** @return array<string, mixed> */
    private function crmTaskRow(int $solicitudId, int $tareaId): array
    {
        // checklist_slug y task_key no existen como columnas propias — vienen de metadata.
        $row = DB::table('crm_tasks')
            ->select(['id', 'metadata', 'status'])
            ->where('id', $tareaId)
            ->where('source_module', 'solicitudes')
            ->where('source_ref_id', (string) $solicitudId)
            ->where('company_id', $this->resolveCompanyId())
            ->first();

        if ($row === null) {
            throw new RuntimeException('No se encontró la tarea CRM.');
        }

        return (array) $row;
    }

    private function findIdByFormId(int $formId): ?int
    {
        if ($formId <= 0) {
            return null;
        }

        $id = DB::table('solicitud_procedimiento')
            ->where('form_id', $formId)
            ->orderByDesc('created_at')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
