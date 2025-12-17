<?php

namespace Modules\Examenes\Services;

class ExamenEstadoService
{
    /**
     * Definición de columnas visibles en el kanban de exámenes.
     * slug => ['label' => string, 'color' => bootstrap contextual]
     */
    private const DEFAULT_COLUMNS = [
        'recibido' => ['label' => 'Recibido', 'color' => 'primary'],
        'llamado' => ['label' => 'Llamado', 'color' => 'warning'],
        'revision-cobertura' => ['label' => '⚠️ Cobertura', 'color' => 'info'],
        'listo-para-agenda' => ['label' => '✅ Listo', 'color' => 'dark'],
        'completado' => ['label' => 'Completado', 'color' => 'secondary'],
    ];

    /**
     * Definición de etapas en orden secuencial.
     */
    private const DEFAULT_STAGES = [
        ['slug' => 'recibido', 'label' => 'Recibido', 'order' => 10, 'column' => 'recibido', 'required' => true],
        ['slug' => 'llamado', 'label' => 'Llamado', 'order' => 20, 'column' => 'llamado', 'required' => true],
        ['slug' => 'revision-cobertura', 'label' => '⚠ Revisión de cobertura', 'order' => 30, 'column' => 'revision-cobertura', 'required' => true],
        ['slug' => 'listo-para-agenda', 'label' => 'Listo para agenda', 'order' => 40, 'column' => 'listo-para-agenda', 'required' => true],
        ['slug' => 'completado', 'label' => 'Completado', 'order' => 50, 'column' => 'completado', 'required' => false],
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
            'recibida' => 'recibido',
            'en-recepcion' => 'recibido',
            'llamado' => 'llamado',
            'en-turno' => 'llamado',
            'en-atencion' => 'revision-cobertura',
            'en-atención' => 'revision-cobertura',
            'revision-de-cobertura' => 'revision-cobertura',
            'revision-cobertura' => 'revision-cobertura',
            'revision-coberturas' => 'revision-cobertura',
            'cobertura' => 'revision-cobertura',
            'listo-para-agenda' => 'listo-para-agenda',
            'listo-para-agendar' => 'listo-para-agenda',
            'agenda' => 'listo-para-agenda',
            'agendado' => 'listo-para-agenda',
            'atendido' => 'completado',
            'completada' => 'completado',
            'completado' => 'completado',
        ];

        return $aliases[$base] ?? $base;
    }

    /**
     * Normaliza y enriquece los exámenes con metadatos de Kanban.
     *
     * @param array<int, array<string, mixed>> $examenes
     * @return array<int, array<string, mixed>>
     */
    public function enrichExamenes(array $examenes): array
    {
        if (empty($examenes)) {
            return [];
        }

        foreach ($examenes as &$row) {
            $legacyEstado = isset($row['estado']) ? (string) $row['estado'] : '';
            $slug = $this->normalizeSlug($legacyEstado);
            $stage = $this->getStageBySlug($slug) ?? $this->getStageBySlug('recibido');

            $next = $this->getNextStage($stage['order'] ?? null);
            $columnSlug = $stage['column'] ?? $stage['slug'] ?? $slug;

            $row['estado_legacy'] = $legacyEstado;
            $row['estado'] = $columnSlug;
            $row['kanban_estado'] = $columnSlug;
            $row['kanban_estado_label'] = $stage['label'] ?? $columnSlug;
            $row['kanban_next'] = [
                'slug' => $next['slug'] ?? null,
                'label' => $next['label'] ?? null,
            ];
        }
        unset($row);

        return $examenes;
    }

    private function getStageBySlug(?string $slug): ?array
    {
        if (!$slug) {
            return null;
        }

        foreach (self::DEFAULT_STAGES as $stage) {
            if (($stage['slug'] ?? '') === $slug) {
                return $stage;
            }
        }

        return null;
    }

    private function getNextStage($currentOrder): ?array
    {
        if ($currentOrder === null) {
            return null;
        }

        $ordered = $this->getStages();
        foreach ($ordered as $stage) {
            if (($stage['order'] ?? 0) > $currentOrder) {
                return $stage;
            }
        }

        return null;
    }
}
