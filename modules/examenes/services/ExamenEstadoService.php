<?php

namespace Modules\Examenes\Services;

class ExamenEstadoService
{
    /**
     * Definición de columnas del tablero (agrupación visual).
     * slug => ['label' => string, 'color' => bootstrap contextual]
     */
    private const DEFAULT_COLUMNS = [
        'recibida' => ['label' => 'Recibido', 'color' => 'primary'],
        'llamado' => ['label' => 'Llamado', 'color' => 'warning'],
        'revision-codigos' => ['label' => 'Revisión de Cobertura', 'color' => 'info'],
        'espera-documentos' => ['label' => 'Documentación', 'color' => 'secondary'],
        'apto-oftalmologo' => ['label' => 'Apto oftalmólogo', 'color' => 'secondary'],
        'apto-anestesia' => ['label' => 'Apto anestesia', 'color' => 'warning'],
        'listo-para-agenda' => ['label' => 'Listo para agenda', 'color' => 'dark'],
        'programada' => ['label' => 'Programada', 'color' => 'primary'],
        'completado' => ['label' => 'Completado', 'color' => 'success'],
    ];

    /**
     * Etapas ordenadas.
     */
    private const DEFAULT_STAGES = [
        ['slug' => 'recibida', 'label' => 'Recibido', 'order' => 10, 'column' => 'recibida', 'required' => true],
        ['slug' => 'llamado', 'label' => 'Llamado', 'order' => 20, 'column' => 'llamado', 'required' => true],
        ['slug' => 'en-atencion', 'label' => 'En atención', 'order' => 30, 'column' => 'revision-codigos', 'required' => true],
        ['slug' => 'revision-codigos', 'label' => 'Revisión de Cobertura', 'order' => 40, 'column' => 'revision-codigos', 'required' => true],
        ['slug' => 'espera-documentos', 'label' => 'Documentación pendiente', 'order' => 50, 'column' => 'espera-documentos', 'required' => true],
        ['slug' => 'apto-oftalmologo', 'label' => 'Apto oftalmólogo', 'order' => 60, 'column' => 'apto-oftalmologo', 'required' => true],
        ['slug' => 'apto-anestesia', 'label' => 'Apto anestesia', 'order' => 70, 'column' => 'apto-anestesia', 'required' => true],
        ['slug' => 'listo-para-agenda', 'label' => 'Listo para agenda', 'order' => 80, 'column' => 'listo-para-agenda', 'required' => true],
        ['slug' => 'programada', 'label' => 'Programada', 'order' => 90, 'column' => 'programada', 'required' => true],
    ];

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
            'recibida' => 'recibida',
            'llamado' => 'llamado',
            'en-atencion' => 'en-atencion',
            'en-atención' => 'en-atencion',
            'revision-codigos' => 'revision-codigos',
            'revision-de-codigos' => 'revision-codigos',
            'revision-cobertura' => 'revision-codigos',
            'revision-de-cobertura' => 'revision-codigos',
            'revision cobertura' => 'revision-codigos',
            'espera-documentos' => 'espera-documentos',
            'documentacion' => 'espera-documentos',
            'documentación' => 'espera-documentos',
            'apto-oftalmologo' => 'apto-oftalmologo',
            'apto-oftalmólogo' => 'apto-oftalmologo',
            'apto-anestesia' => 'apto-anestesia',
            'listo-para-agenda' => 'listo-para-agenda',
            'protocolo-completo' => 'programada',
            'programada' => 'programada',
            'completado' => 'completado',
        ];

        return $aliases[$base] ?? $base;
    }

    private function getStageBySlug(string $slug): ?array
    {
        foreach ($this->getStages() as $stage) {
            if (($stage['slug'] ?? null) === $slug) {
                return $stage;
            }
        }
        return null;
    }

    private function mapLegacyEstado(?string $estado): ?string
    {
        $slug = $this->normalizeSlug($estado);
        if ($slug === '') {
            return null;
        }

        return $slug;
    }

    private function buildChecklistForExamen(int $examenId, string $legacyEstado): array
    {
        $stages = $this->getStages();
        $legacySlug = $this->mapLegacyEstado($legacyEstado);
        $legacyOrder = null;

        if ($legacySlug !== null) {
            $stage = $this->getStageBySlug($legacySlug);
            $legacyOrder = $stage['order'] ?? null;
        }

        $checklist = [];
        foreach ($stages as $stage) {
            $slug = $stage['slug'];
            $completed = $legacyOrder !== null && ($stage['order'] ?? 0) <= $legacyOrder;

            $checklist[] = [
                'examen_id' => $examenId,
                'slug' => $slug,
                'label' => $stage['label'],
                'order' => $stage['order'],
                'column' => $stage['column'],
                'required' => (bool)($stage['required'] ?? true),
                'completed' => $completed,
                'can_toggle' => true,
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

        foreach ($checklist as $stage) {
            if (!empty($stage['completed'])) {
                $completed++;
            } elseif ($next === null) {
                $next = $stage;
            }
        }

        $percent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

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
        $lastCompleted = null;
        foreach ($checklist as $stage) {
            if (!empty($stage['completed'])) {
                $lastCompleted = $stage;
            } else {
                break;
            }
        }

        $target = $lastCompleted ?? ($checklist[0] ?? null);
        $progress = $this->computeProgress($checklist);

        return [
            'slug' => $target['column'] ?? $target['slug'] ?? 'recibida',
            'label' => $target['label'] ?? ucfirst($target['slug'] ?? 'Recibido'),
            'next_slug' => $progress['next_slug'] ?? null,
            'next_label' => $progress['next_label'] ?? null,
        ];
    }

    /**
     * Enriquecemos los registros de exámenes con checklist y estado de tablero.
     *
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    public function enrichExamenes(array $examenes): array
    {
        foreach ($examenes as &$row) {
            $legacyEstado = isset($row['estado']) ? (string) $row['estado'] : '';
            $checklist = $this->buildChecklistForExamen((int) ($row['id'] ?? 0), $legacyEstado);
            $progress = $this->computeProgress($checklist);
            $kanban = $this->computeKanbanEstado($checklist);

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
            $row['estado_label'] = $kanban['label'];
        }
        unset($row);

        return $examenes;
    }

    public function getUpdateTarget(string $estado, bool $completado): array
    {
        $slug = $this->normalizeSlug($estado);
        $stage = $this->getStageBySlug($slug) ?? $this->getStages()[0];

        if (!$completado) {
            $previous = $this->findPreviousStage($stage['order'] ?? 0);
            $stage = $previous ?? $stage;
        }

        $label = $stage['label'] ?? $estado;

        return [
            'slug' => $stage['slug'] ?? $slug,
            'label' => $label,
        ];
    }

    private function findPreviousStage(int $order): ?array
    {
        $candidates = array_filter($this->getStages(), static function (array $stage) use ($order): bool {
            return ($stage['order'] ?? 0) < $order;
        });

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, static fn(array $a, array $b) => ($b['order'] ?? 0) <=> ($a['order'] ?? 0));

        return $candidates[0];
    }
}
