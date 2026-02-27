<?php

namespace Modules\Solicitudes\Services;

use PDO;
use RuntimeException;

class SolicitudEstadoService
{
    private PDO $pdo;

    /**
     * Definición de columnas del tablero (agrupación visual).
     * slug => ['label' => string, 'color' => bootstrap contextual]
     */
    private const DEFAULT_COLUMNS = [
        'recibida' => ['label' => 'Recibida', 'color' => 'primary'],
        'llamado' => ['label' => 'Llamado', 'color' => 'warning'],
        'revision-codigos' => ['label' => '⚠️ Cobertura', 'color' => 'info'],
        'espera-documentos' => ['label' => '⚠️ Documentación', 'color' => 'secondary'],
        'apto-oftalmologo' => ['label' => '⚠️ Oftalmólogo', 'color' => 'secondary'],
        'apto-anestesia' => ['label' => '⚠️ Anestesia', 'color' => 'warning'],
        'listo-para-agenda' => ['label' => '✅ Listo', 'color' => 'dark'],
        'programada' => ['label' => 'Programada', 'color' => 'primary'],
        'completado' => ['label' => 'Completado', 'color' => 'secondary'],
    ];

    /**
     * Definición de etapas en orden secuencial.
     */
    private const DEFAULT_STAGES = [
        ['slug' => 'recibida', 'label' => 'Recibida', 'order' => 10, 'column' => 'recibida', 'required' => true],
        ['slug' => 'llamado', 'label' => 'Llamado', 'order' => 20, 'column' => 'llamado', 'required' => true],
        ['slug' => 'en-atencion', 'label' => 'En atención', 'order' => 30, 'column' => 'revision-codigos', 'required' => true],
        ['slug' => 'revision-codigos', 'label' => '⚠ Cobertura', 'order' => 40, 'column' => 'revision-codigos', 'required' => true],
        ['slug' => 'espera-documentos', 'label' => '⚠ Documentación', 'order' => 50, 'column' => 'espera-documentos', 'required' => true],

        // ✅ Aptos vuelven a ser requeridos
        ['slug' => 'apto-oftalmologo', 'label' => '⚠ Apto oftalmólogo', 'order' => 60, 'column' => 'apto-oftalmologo', 'required' => true],
        ['slug' => 'apto-anestesia', 'label' => '⚠ Apto anestesia', 'order' => 70, 'column' => 'apto-anestesia', 'required' => true],

        ['slug' => 'listo-para-agenda', 'label' => 'Listo para agenda', 'order' => 80, 'column' => 'listo-para-agenda', 'required' => true],
        ['slug' => 'programada', 'label' => 'Programada', 'order' => 90, 'column' => 'programada', 'required' => true],
    ];

    private const OVERRIDE_PERMISSION = 'solicitudes.checklist.override';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getStages(): array
    {
        $stages = self::DEFAULT_STAGES;
        usort($stages, static fn(array $a, array $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        return $stages;
    }

    public function getColumns(): array
    {
        return self::DEFAULT_COLUMNS;
    }

    public function normalizeSlug(?string $slug): string
    {
        $slug = $slug ?? '';
        $base = strtolower(trim($slug));
        $base = str_replace(['_', '  '], ['-', ' '], $base);
        $base = preg_replace('/[^\p{L}\p{N}\-\s]/u', '', $base) ?? '';
        $base = preg_replace('/\s+/', '-', $base) ?? '';

        $aliases = [
            'recibido' => 'recibida',
            'en-atencion' => 'en-atencion',
            'en-atención' => 'en-atencion',
            'revision-codigos' => 'revision-codigos',
            'revision-de-codigos' => 'revision-codigos',
            'docs-completos' => 'espera-documentos',
            'documentos-completos' => 'espera-documentos',
            'espera-documentos' => 'espera-documentos',
            'apto-oftalmologo' => 'apto-oftalmologo',
            'apto-oftalmólogo' => 'apto-oftalmologo',
            'apto-anestesia' => 'apto-anestesia',
            'listo-para-agenda' => 'listo-para-agenda',
            'protocolo-completo' => 'programada',
            'protocolo-listo' => 'programada',
            'facturada-cerrada' => 'programada',
            'facturado' => 'programada',
            'cerrada' => 'programada',
            'cerrado' => 'programada',
            'completado' => 'completado',
            'completa' => 'completado',
        ];

        return $aliases[$base] ?? $base;
    }

    /**
     * Enrich solicitudes rows with checklist and derived kanban state.
     *
     * @param array<int, array<string, mixed>> $solicitudes
     * @param array $userPermissions
     * @return array<int, array<string, mixed>>
     */
    public function enrichSolicitudes(array $solicitudes, array $userPermissions = []): array
    {
        if (empty($solicitudes)) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(array_map(static function ($row) {
            return isset($row['id']) ? (int)$row['id'] : null;
        }, $solicitudes))));

        $rowsBySolicitud = $this->fetchChecklistRows($ids);
        $seededIds = [];

        foreach ($solicitudes as $row) {
            $solicitudId = isset($row['id']) ? (int)$row['id'] : 0;
            if ($solicitudId <= 0) {
                continue;
            }

            $legacyEstado = isset($row['estado']) ? (string)$row['estado'] : '';
            $existingRows = $rowsBySolicitud[$solicitudId] ?? [];
            if ($this->ensureChecklistRows($solicitudId, $existingRows, $legacyEstado)) {
                $seededIds[] = $solicitudId;
            }
        }

        if ($seededIds) {
            $refreshedRows = $this->fetchChecklistRows($seededIds);
            foreach ($refreshedRows as $sid => $rows) {
                $rowsBySolicitud[$sid] = $rows;
            }
        }

        foreach ($solicitudes as &$row) {
            $solicitudId = isset($row['id']) ? (int)$row['id'] : 0;
            $legacyEstado = isset($row['estado']) ? (string)$row['estado'] : '';
            $checklist = $this->buildChecklistForSolicitud(
                $solicitudId,
                $rowsBySolicitud[$solicitudId] ?? [],
                $userPermissions,
                $legacyEstado
            );
            $progress = $this->computeProgress($checklist);
            $kanban = $this->computeKanbanEstado($checklist);

            $stateSlug = $this->normalizeSlug($legacyEstado);
            // Estados legacy explícitos que deben conservar columna de Kanban.
            if ($stateSlug === 'completado') {
                $kanban = [
                    'slug' => 'completado',
                    'label' => $this->getColumns()['completado']['label'] ?? 'Completado',
                    'next_slug' => null,
                    'next_label' => null,
                ];
            } elseif (in_array($stateSlug, ['recibida', 'llamado'], true)) {
                $stage = $this->getStageBySlug($stateSlug);
                if ($stage) {
                    $kanban = [
                        'slug' => $stage['column'] ?? $stage['slug'],
                        'label' => $stage['label'] ?? ($stage['column'] ?? $stage['slug']),
                        'next_slug' => $progress['next_slug'] ?? null,
                        'next_label' => $progress['next_label'] ?? null,
                    ];
                }
            }

            $row['checklist'] = $checklist;
            $row['checklist_progress'] = $progress;
            $row['kanban_estado'] = $kanban['slug'];
            $row['kanban_estado_label'] = $kanban['label'];
            $row['kanban_next'] = [
                'slug' => $kanban['next_slug'] ?? $progress['next_slug'] ?? null,
                'label' => $kanban['next_label'] ?? $progress['next_label'] ?? null,
            ];
            $row['estado_legacy'] = $row['estado'] ?? null;
            $row['estado'] = $kanban['slug'];
        }
        unset($row);

        return $solicitudes;
    }

    /**
     * Marca o desmarca una etapa respetando el orden secuencial y permisos.
     *
     * @param array $userPermissions permisos actuales normalizados
     * @return array<string, mixed>
     */
    public function actualizarEtapa(
        int     $solicitudId,
        string  $etapaSlug,
        bool    $completado,
        ?int    $usuarioId,
        array   $userPermissions = [],
        bool    $force = false,
        ?string $nota = null
    ): array
    {
        $slug = $this->normalizeSlug($etapaSlug);
        $stage = $this->getStageBySlug($slug);

        if (!$stage) {
            throw new RuntimeException('Etapa no reconocida');
        }

        $checklistRows = $this->fetchChecklistRows([$solicitudId]);
        $antes = $this->buildChecklistForSolicitud($solicitudId, $checklistRows[$solicitudId] ?? [], $userPermissions);
        $kanbanAntes = $this->computeKanbanEstado($antes);

        $isAptoStage = in_array($slug, ['apto-oftalmologo', 'apto-anestesia'], true);
        if ($completado && $isAptoStage) {
            // Para etapas de apto (oftalmólogo / anestesia), permitimos marcarla
            // en cualquier momento sin exigir etapas previas.
            $force = true;
        }

        if ($completado && !$force) {
            $pendientePrevio = $this->findPrimerPendiente($antes, $stage['order']);
            if ($pendientePrevio && $pendientePrevio['slug'] !== $slug) {
                throw new RuntimeException('Debes completar las etapas previas antes de avanzar');
            }
        }

        if (!$completado && !$force && !$this->canOverride($userPermissions)) {
            throw new RuntimeException('No tienes permisos para desmarcar esta etapa');
        }

        if (!$this->canToggleStage($stage, $userPermissions) && !$force) {
            throw new RuntimeException('No tienes permisos para modificar esta etapa');
        }

        $this->pdo->beginTransaction();

        try {
            if ($force && $completado) {
                if ($slug === 'en-atencion') {
                    // Completa recibida, llamado y en atención, y limpia etapas posteriores
                    $this->forceCompleteUntil($solicitudId, $slug, $usuarioId, $nota);
                    $this->clearAfterStage($solicitudId, $slug);
                } elseif (in_array($slug, ['apto-oftalmologo', 'apto-anestesia'], true)) {
                    // Para aptos, sólo marcar esa etapa sin tocar previas ni posteriores
                    $this->markSingle($solicitudId, $slug, $usuarioId, $nota, true);
                } else {
                    $this->forceCompleteUntil($solicitudId, $slug, $usuarioId, $nota);
                }
            } else {
                $this->markSingle($solicitudId, $slug, $usuarioId, $nota, $completado);
                if ($slug === 'en-atencion') {
                    $this->clearAfterStage($solicitudId, $slug);
                }
            }

            $log = $this->pdo->prepare(
                'INSERT INTO solicitud_checklist_log (solicitud_id, etapa_slug, accion, actor_id, nota, old_completado_at, new_completado_at)
                 VALUES (:solicitud_id, :etapa_slug, :accion, :actor_id, :nota, :old_completado_at, :new_completado_at)'
            );

            $log->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
            $log->bindValue(':etapa_slug', $slug, PDO::PARAM_STR);
            $log->bindValue(':accion', $completado ? ($force ? 'forzar' : 'completar') : 'desmarcar', PDO::PARAM_STR);
            $log->bindValue(':actor_id', $usuarioId, $usuarioId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $log->bindValue(':nota', $nota !== null && $nota !== '' ? $nota : null, ($nota !== null && $nota !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL);

            $etapaAntes = $antes[array_search($slug, array_column($antes, 'slug'), true)] ?? null;
            $oldDate = $etapaAntes && $etapaAntes['completed'] ? ($etapaAntes['completado_at'] ?? null) : null;
            $log->bindValue(':old_completado_at', $oldDate, $oldDate ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $log->bindValue(':new_completado_at', $completado ? date('Y-m-d H:i:s') : null, $completado ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $log->execute();

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $despuesRows = $this->fetchChecklistRows([$solicitudId]);
        $despuesChecklist = $this->buildChecklistForSolicitud($solicitudId, $despuesRows[$solicitudId] ?? [], $userPermissions);
        $progress = $this->computeProgress($despuesChecklist);
        $kanban = $this->computeKanbanEstado($despuesChecklist);

// Actualizar estado legacy SIEMPRE al nuevo estado de tablero
        $this->actualizarEstadoLegacy($solicitudId, $kanban['slug']);

        return [
            'estado_anterior' => $kanbanAntes['slug'],
            'kanban_estado' => $kanban['slug'],
            'kanban_estado_label' => $kanban['label'],
            'kanban_next' => [
                'slug' => $kanban['next_slug'] ?? $progress['next_slug'] ?? null,
                'label' => $kanban['next_label'] ?? $progress['next_label'] ?? null,
            ],
            'checklist' => $despuesChecklist,
            'checklist_progress' => $progress,
        ];
    }

    private function fetchChecklistRows(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT solicitud_id, etapa_slug, checked, completado_at, completado_por, nota
             FROM solicitud_checklist
             WHERE solicitud_id IN ($placeholders)"
        );
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $sid = (int)($row['solicitud_id'] ?? 0);
            if (!isset($grouped[$sid])) {
                $grouped[$sid] = [];
            }
            $grouped[$sid][] = $row;
        }

        return $grouped;
    }

    private function buildChecklistForSolicitud(int $solicitudId, array $rows, array $userPermissions = [], string $legacyEstado = ''): array
    {
        $stages = $this->getStages();
        $legacySlug = $this->mapLegacyEstado($legacyEstado);
        $legacyOrder = null;
        if ($legacySlug !== null) {
            foreach ($stages as $stage) {
                if (($stage['slug'] ?? null) === $legacySlug) {
                    $legacyOrder = $stage['order'] ?? null;
                    break;
                }
            }
        }
        $map = [];
        foreach ($rows as $row) {
            $slug = $this->normalizeSlug($row['etapa_slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $map[$slug] = $row;
        }

        $hasChecklistRows = !empty($map);

        $checklist = [];
        foreach ($stages as $stage) {
            $slug = $stage['slug'];
            $row = $map[$slug] ?? [];
            $checkedValue = null;
            if (array_key_exists('checked', $row)) {
                $checkedValue = (int)$row['checked'];
            }
            $completed = $checkedValue !== null ? (bool)$checkedValue : !empty($row['completado_at']);
            if (
                !$completed &&
                !$hasChecklistRows &&
                $legacyOrder !== null &&
                ($stage['order'] ?? 0) <= $legacyOrder
            ) {
                $completed = true;
            }

            $checklist[] = [
                'solicitud_id' => $solicitudId,
                'slug' => $slug,
                'label' => $stage['label'],
                'order' => $stage['order'],
                'column' => $stage['column'],
                'required' => (bool)($stage['required'] ?? true),
                'completed' => $completed,
                'checked' => $completed ? 1 : 0,
                'completado_at' => $row['completado_at'] ?? null,
                'completado_por' => $row['completado_por'] ?? null,
                'nota' => $row['nota'] ?? null,
                'can_toggle' => $this->canToggleStage($stage, $userPermissions) || $this->canOverride($userPermissions),
            ];
        }

        usort($checklist, static fn(array $a, array $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return $checklist;
    }

    private function computeProgress(array $checklist): array
    {
        $total = count($checklist);
        $completed = 0;
        $next = null;

        foreach ($checklist as $item) {
            if (!empty($item['completed'])) {
                $completed++;
                continue;
            }

            if ($next === null) {
                $next = $item;
            }
        }

        $percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'completed' => $completed,
            'percent' => $percent,
            'next_slug' => $next['slug'] ?? null,
            'next_label' => $next['label'] ?? null,
        ];
    }

    private function computeKanbanEstado(array $checklist): array
    {
        $columns = $this->getColumns();
        $nextPending = null;
        $lastRequiredCompleted = null;

        foreach ($checklist as $item) {
            $required = $item['required'] ?? true;

            if (!empty($item['completed'])) {
                if ($required) {
                    $lastRequiredCompleted = $item;
                }
                continue;
            }

            // Etapas NO requeridas (apto) no definen columna del kanban
            if (!$required) {
                continue;
            }

            if ($nextPending === null) {
                $nextPending = $item;
                break;
            }
        }

        if ($nextPending) {
            $slug = $nextPending['column'] ?? $nextPending['slug'];
            $label = $columns[$slug]['label'] ?? ($nextPending['label'] ?? $slug);
            return [
                'slug' => $slug,
                'label' => $label,
                'next_slug' => $nextPending['slug'] ?? $nextPending['column'] ?? null,
                'next_label' => $nextPending['label'] ?? null,
            ];
        }

        $fallback = $lastRequiredCompleted ?: ($checklist[0] ?? null);
        $slug = $fallback
            ? ($fallback['column'] ?? $fallback['slug'])
            : 'recibida';
        $label = $columns[$slug]['label'] ?? ($fallback['label'] ?? $slug);

        return [
            'slug' => $slug,
            'label' => $label,
            'next_slug' => null,
            'next_label' => null,
        ];
    }

    private function findPrimerPendiente(array $checklist, int $hastaOrden): ?array
    {
        foreach ($checklist as $item) {
            if (($item['order'] ?? 0) >= $hastaOrden) {
                break;
            }
            if (empty($item['completed']) && ($item['required'] ?? true)) {
                return $item;
            }
        }

        return null;
    }

    private function canToggleStage(array $stage, array $userPermissions): bool
    {
        $requiredRoles = $stage['roles'] ?? [];
        if (empty($requiredRoles)) {
            return true;
        }

        $permissions = array_map('strval', $userPermissions);
        foreach ($requiredRoles as $perm) {
            if (in_array($perm, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    private function canOverride(array $userPermissions): bool
    {
        $permissions = array_map('strval', $userPermissions);
        return in_array('superuser', $permissions, true) || in_array(self::OVERRIDE_PERMISSION, $permissions, true);
    }

    private function mapLegacyEstado(?string $estado): ?string
    {
        $slug = $this->normalizeSlug($estado ?? '');
        if ($slug === '') {
            return null;
        }

        $map = [
            'nueva' => 'recibida',
            'nueva-solicitud' => 'recibida',
            'recibido' => 'recibida',
            'recibida' => 'recibida',
            'llamado' => 'llamado',
            'en-atencion' => 'en-atencion',
            'en-atención' => 'en-atencion',
            'revision-codigos' => 'revision-codigos',
            'revision-de-codigos' => 'revision-codigos',
            'docs-completos' => 'espera-documentos',
            'documentos-completos' => 'espera-documentos',
            'aprobacion-anestesia' => 'apto-anestesia',
            'aprobacion-oftalmologo' => 'apto-oftalmologo',
            'apto-oftalmologo' => 'apto-oftalmologo',
            'apto-anestesia' => 'apto-anestesia',
            'listo-para-agenda' => 'listo-para-agenda',
            'programada' => 'programada',
            'operada' => 'programada',
            'protocolo-completo' => 'programada',
            'facturado' => 'programada',
            'facturada' => 'programada',
            'cerrado' => 'programada',
            'cerrada' => 'programada',
        ];

        return $map[$slug] ?? $slug;
    }

    private function getStageBySlug(string $slug): ?array
    {
        foreach ($this->getStages() as $stage) {
            if ($stage['slug'] === $slug) {
                return $stage;
            }
        }

        return null;
    }

    private function forceCompleteUntil(int $solicitudId, string $slugObjetivo, ?int $usuarioId, ?string $nota): void
    {
        $stages = $this->getStages();
        usort($stages, static fn(array $a, array $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        $now = date('Y-m-d H:i:s');
        $found = false;

        foreach ($stages as $stage) {
            $this->markSingle($solicitudId, $stage['slug'], $usuarioId, $nota, true, $now);

            if ($stage['slug'] === $slugObjetivo) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new RuntimeException('Etapa no encontrada para completar forzado');
        }
    }

    private function markSingle(
        int     $solicitudId,
        string  $slug,
        ?int    $usuarioId,
        ?string $nota,
        bool    $completed = true,
        ?string $timestamp = null
    ): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO solicitud_checklist (solicitud_id, etapa_slug, checked, completado_at, completado_por, nota)
             VALUES (:solicitud_id, :etapa_slug, :checked, :completado_at, :completado_por, :nota)
             ON DUPLICATE KEY UPDATE
                checked = VALUES(checked),
                completado_at = VALUES(completado_at),
                completado_por = VALUES(completado_por),
                nota = VALUES(nota)'
        );

        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':etapa_slug', $slug, PDO::PARAM_STR);
        $stmt->bindValue(':checked', $completed ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(
            ':completado_at',
            $completed ? ($timestamp ?? date('Y-m-d H:i:s')) : null,
            $completed ? PDO::PARAM_STR : PDO::PARAM_NULL
        );
        $stmt->bindValue(
            ':completado_por',
            $completed && $usuarioId ? $usuarioId : null,
            $completed && $usuarioId ? PDO::PARAM_INT : PDO::PARAM_NULL
        );
        $stmt->bindValue(
            ':nota',
            $nota !== null && $nota !== '' ? $nota : null,
            ($nota !== null && $nota !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL
        );
        $stmt->execute();
    }

    private function clearAfterStage(int $solicitudId, string $slug): void
    {
        $stages = $this->getStages();
        usort($stages, static fn(array $a, array $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        $targetOrder = null;
        foreach ($stages as $stage) {
            if ($stage['slug'] === $slug) {
                $targetOrder = $stage['order'] ?? null;
                break;
            }
        }

        if ($targetOrder === null) {
            return;
        }

        if ($slug === 'en-atencion') {
            $slugsToClear = [
                'revision-codigos',
                'espera-documentos',
                'apto-oftalmologo',
                'apto-anestesia',
                'listo-para-agenda',
                'programada',
            ];
        } else {
            $slugsToClear = array_map(
                static fn(array $stage) => $stage['slug'],
                array_filter($stages, static fn(array $stage) => ($stage['order'] ?? 0) > $targetOrder)
            );
        }

        if (empty($slugsToClear)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($slugsToClear), '?'));
        $sql = "UPDATE solicitud_checklist 
                SET checked = 0, completado_at = NULL, completado_por = NULL, nota = NULL
                WHERE solicitud_id = ? AND etapa_slug IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $params = array_merge([$solicitudId], $slugsToClear);
        $stmt->execute($params);
    }

    private function ensureChecklistRows(int $solicitudId, array $rows, string $legacyEstado): bool
    {
        $stages = $this->getStages();
        if (empty($stages)) {
            return false;
        }

        $existing = [];
        foreach ($rows as $row) {
            $slug = $this->normalizeSlug($row['etapa_slug'] ?? '');
            if ($slug !== '') {
                $existing[$slug] = true;
            }
        }

        if (count($existing) >= count($stages)) {
            return false;
        }

        $legacyOrder = null;
        if (empty($existing)) {
            $legacySlug = $this->mapLegacyEstado($legacyEstado);
            if ($legacySlug !== null) {
                foreach ($stages as $stage) {
                    if (($stage['slug'] ?? null) === $legacySlug) {
                        $legacyOrder = $stage['order'] ?? null;
                        break;
                    }
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO solicitud_checklist (solicitud_id, etapa_slug, checked, completado_at, completado_por, nota)
             VALUES (:solicitud_id, :etapa_slug, :checked, :completado_at, :completado_por, :nota)
             ON DUPLICATE KEY UPDATE
                checked = VALUES(checked),
                completado_at = VALUES(completado_at),
                completado_por = VALUES(completado_por),
                nota = VALUES(nota)'
        );

        foreach ($stages as $stage) {
            $slug = $stage['slug'] ?? '';
            if ($slug === '' || isset($existing[$slug])) {
                continue;
            }

            $checked = $legacyOrder !== null && ($stage['order'] ?? 0) <= $legacyOrder ? 1 : 0;
            $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
            $stmt->bindValue(':etapa_slug', $slug, PDO::PARAM_STR);
            $stmt->bindValue(':checked', $checked, PDO::PARAM_INT);
            $stmt->bindValue(
                ':completado_at',
                $checked ? $now : null,
                $checked ? PDO::PARAM_STR : PDO::PARAM_NULL
            );
            $stmt->bindValue(':completado_por', null, PDO::PARAM_NULL);
            $stmt->bindValue(':nota', null, PDO::PARAM_NULL);
            $stmt->execute();
        }

        return true;
    }

    private function actualizarEstadoLegacy(int $solicitudId, string $estadoSlug): void
    {
        $stmt = $this->pdo->prepare('UPDATE solicitud_procedimiento SET estado = :estado WHERE id = :id');
        $stmt->bindValue(':estado', $estadoSlug, PDO::PARAM_STR);
        $stmt->bindValue(':id', $solicitudId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
