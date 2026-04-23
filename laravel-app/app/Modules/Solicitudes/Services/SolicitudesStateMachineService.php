<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

class SolicitudesStateMachineService
{
    public const STATE_PROGRAMADA = 'programada';
    public const STATE_COMPLETADO = 'completado';

    /**
     * @var array<int, array{slug:string,label:string,order:int,column:string,required:bool}>
     */
    private const STAGES = [
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

    /**
     * @return array<int, array{slug:string,label:string,order:int,column:string,required:bool}>
     */
    public function stages(): array
    {
        return self::STAGES;
    }

    /**
     * @param array<int,array<string,mixed>> $checklistRows
     * @param array{include_nota?:bool,include_can_toggle?:bool,empty_label?:string} $options
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>,2:array{slug:string,label:string}}
     */
    public function buildChecklistContext(string $legacyState, array $checklistRows, array $options = []): array
    {
        $includeNota = (bool) ($options['include_nota'] ?? false);
        $includeCanToggle = (bool) ($options['include_can_toggle'] ?? false);
        $emptyLabel = (string) ($options['empty_label'] ?? '');

        $bySlug = [];
        foreach ($checklistRows as $row) {
            $slug = $this->normalizeKanbanSlug((string) ($row['etapa_slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $bySlug[$slug] = [
                'completed' => !empty($row['completado_at']),
                'completado_at' => $row['completado_at'] !== null ? (string) $row['completado_at'] : null,
                'nota' => isset($row['nota']) ? trim((string) $row['nota']) : null,
            ];
        }

        $legacySlug = $this->normalizeKanbanSlug($legacyState);
        $stageIndex = $this->stageIndex($legacySlug);

        $checklist = [];
        foreach (self::STAGES as $index => $stage) {
            $slug = $stage['slug'];
            $fromDb = $bySlug[$slug] ?? null;
            $completed = $fromDb['completed'] ?? false;

            if ($legacySlug === self::STATE_COMPLETADO) {
                $completed = true;
            }

            if ($fromDb === null && $stageIndex !== null) {
                $completed = $index <= $stageIndex;
            }

            $item = [
                'slug' => $slug,
                'label' => $stage['label'],
                'order' => $stage['order'],
                'required' => $stage['required'],
                'completed' => $completed,
                'completado_at' => $fromDb['completado_at'] ?? null,
            ];

            if ($includeNota) {
                $item['nota'] = $fromDb['nota'] ?? null;
            }

            if ($includeCanToggle) {
                $item['can_toggle'] = true;
            }

            $checklist[] = $item;
        }

        $total = count($checklist);
        $completedCount = count(array_filter($checklist, static fn(array $item): bool => !empty($item['completed'])));
        $percent = $total > 0 ? round(($completedCount / $total) * 100, 1) : 0.0;

        $next = null;
        if ($legacySlug !== self::STATE_COMPLETADO) {
            foreach ($checklist as $item) {
                if (!empty($item['required']) && empty($item['completed'])) {
                    $next = $item;
                    break;
                }
            }
        }

        $progress = [
            'total' => $total,
            'completed' => $completedCount,
            'percent' => $percent,
            'next_slug' => $next['slug'] ?? null,
            'next_label' => $next['label'] ?? null,
        ];

        if ($legacySlug === self::STATE_COMPLETADO) {
            $kanbanSlug = self::STATE_COMPLETADO;
        } elseif ($legacySlug === self::STATE_PROGRAMADA) {
            $kanbanSlug = self::STATE_PROGRAMADA;
        } elseif (in_array($legacySlug, ['recibida', 'llamado'], true)) {
            $kanbanSlug = $legacySlug;
        } elseif ($next !== null) {
            $stage = $this->stageBySlug((string) $next['slug']);
            $kanbanSlug = (string) ($stage['column'] ?? $next['slug']);
        } else {
            $kanbanSlug = self::STATE_PROGRAMADA;
        }

        return [
            $checklist,
            $progress,
            [
                'slug' => $kanbanSlug,
                'label' => $this->kanbanLabel($kanbanSlug, $emptyLabel),
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $checklistRows
     * @param array{include_nota?:bool,include_can_toggle?:bool,empty_label?:string} $options
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>,2:array{slug:string,label:string}}
     */
    public function resolvePersistedChecklistContext(array $checklistRows, string $fallbackState = '', array $options = []): array
    {
        $seedState = $checklistRows === [] ? $fallbackState : '';

        return $this->buildChecklistContext($seedState, $checklistRows, $options);
    }

    /**
     * @param array<int,array<string,mixed>> $checklistRows
     * @return array{slug:string,label:string}
     */
    public function resolveOperationalState(array $checklistRows, string $fallbackState = '', array $options = []): array
    {
        [, , $kanban] = $this->resolvePersistedChecklistContext($checklistRows, $fallbackState, $options);

        return $kanban;
    }

    /**
     * @return array<int,array{slug:string,label:string,order:int,column:string,required:bool,completed:bool}>
     */
    public function bootstrapStagesFromLegacyState(string $legacyState): array
    {
        $legacySlug = $this->normalizeKanbanSlug($legacyState);
        $stageIndex = $this->stageIndex($legacySlug);
        $stages = [];

        foreach (self::STAGES as $index => $stage) {
            $stage['completed'] = $stageIndex !== null && $index <= $stageIndex;
            if ($legacySlug === self::STATE_COMPLETADO) {
                $stage['completed'] = true;
            }
            $stages[] = $stage;
        }

        return $stages;
    }

    public function normalizeKanbanSlug(string $value): string
    {
        $value = trim($value);
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
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            '_' => '-',
        ]);

        $slug = mb_strtolower(trim($value), 'UTF-8');
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
            'protocolo-completo' => self::STATE_PROGRAMADA,
            'facturado' => self::STATE_PROGRAMADA,
            'facturada-cerrada' => self::STATE_PROGRAMADA,
            'cerrado' => self::STATE_PROGRAMADA,
            'cerrada' => self::STATE_PROGRAMADA,
            'completa' => self::STATE_COMPLETADO,
        ];

        return $aliases[$slug] ?? $slug;
    }

    /**
     * @return array{slug:string,label:string,order:int,column:string,required:bool}|null
     */
    public function stageBySlug(string $slug): ?array
    {
        foreach (self::STAGES as $stage) {
            if ($stage['slug'] === $slug) {
                return $stage;
            }
        }

        return null;
    }

    public function stageIndex(string $slug): ?int
    {
        if ($slug === '') {
            return null;
        }

        foreach (self::STAGES as $index => $stage) {
            if ($stage['slug'] === $slug || $stage['column'] === $slug) {
                return $index;
            }
        }

        return null;
    }

    public function kanbanLabel(string $slug, string $emptyLabel = ''): string
    {
        $slug = $this->normalizeKanbanSlug($slug);

        foreach (self::STAGES as $stage) {
            if ($stage['slug'] === $slug || $stage['column'] === $slug) {
                return (string) $stage['label'];
            }
        }

        if ($slug === '') {
            return $emptyLabel;
        }

        return ucfirst(str_replace('-', ' ', $slug));
    }

    /**
     * @return array{slug:string,label:string}
     */
    public function completedTerminalState(): array
    {
        return [
            'slug' => self::STATE_COMPLETADO,
            'label' => $this->kanbanLabel(self::STATE_COMPLETADO),
        ];
    }

    /**
     * @return array{slug:string,label:string}
     */
    public function scheduledTerminalState(): array
    {
        return [
            'slug' => self::STATE_PROGRAMADA,
            'label' => $this->kanbanLabel(self::STATE_PROGRAMADA),
        ];
    }

    public function isTerminalState(string $slug): bool
    {
        $slug = $this->normalizeKanbanSlug($slug);

        return in_array($slug, [self::STATE_PROGRAMADA, self::STATE_COMPLETADO], true);
    }

    /**
     * @return array{reopened:bool,entered_terminal:bool,previous_terminal:bool,next_terminal:bool,terminal_slug:?string}
     */
    public function describeTransition(string $previousState, string $nextState): array
    {
        $previousSlug = $this->normalizeKanbanSlug($previousState);
        $nextSlug = $this->normalizeKanbanSlug($nextState);
        $previousTerminal = $this->isTerminalState($previousSlug);
        $nextTerminal = $this->isTerminalState($nextSlug);

        return [
            'reopened' => $previousTerminal && !$nextTerminal && $nextSlug !== '',
            'entered_terminal' => !$previousTerminal && $nextTerminal,
            'previous_terminal' => $previousTerminal,
            'next_terminal' => $nextTerminal,
            'terminal_slug' => $nextTerminal ? $nextSlug : null,
        ];
    }
}
