<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Services;

use Illuminate\Support\Facades\DB;

class SolicitudesSlaSettingsService
{
    public const BASE_SETTING_NAME = 'solicitudes_operational_sla_rules';
    public const STAGE_SETTING_NAME = 'solicitudes_operational_stage_sla_rules';

    /**
     * @return array<string,array<string,mixed>>
     */
    public function baseRules(): array
    {
        $defaults = $this->defaultBaseRules();
        $stored = $this->storedSetting(self::BASE_SETTING_NAME);

        return $this->mergeRecursive($defaults, $this->normalizeBaseRules($stored));
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function stageRules(): array
    {
        $defaults = $this->defaultStageRules();
        $stored = $this->storedSetting(self::STAGE_SETTING_NAME);

        return $this->mergeRecursive($defaults, $this->normalizeStageRules($stored));
    }

    /**
     * @param array<string,mixed> $rules
     */
    public function saveBaseRules(array $rules): void
    {
        $normalized = $this->normalizeBaseRules($rules);
        if ($normalized === []) {
            $normalized = $this->defaultBaseRules();
        }

        $this->persist(self::BASE_SETTING_NAME, $normalized);
    }

    /**
     * @param array<string,mixed> $rules
     */
    public function saveStageRules(array $rules): void
    {
        $normalized = $this->normalizeStageRules($rules);
        if ($normalized === []) {
            $normalized = $this->defaultStageRules();
        }

        $this->persist(self::STAGE_SETTING_NAME, $normalized);
    }

    /**
     * @return array<string,string>
     */
    public function categoryLabels(): array
    {
        return [
            'publico' => 'Público',
            'privado' => 'Privado',
            'particular' => 'Particular',
            'fundacional' => 'Fundacional',
            'otros' => 'Otros',
        ];
    }

    /**
     * @return array<string,string>
     */
    public function stageLabels(): array
    {
        $labels = [];
        foreach ((new SolicitudesStateMachineService())->stages() as $stage) {
            $slug = trim((string) ($stage['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            $labels[$slug] = (string) ($stage['label'] ?? $slug);
        }

        return $labels;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function defaultBaseRules(): array
    {
        return [
            'publico' => [
                'label' => 'Vigencia derivación',
                'action' => 'Validar vigencia o renovar derivación',
                'source' => 'derivacion',
                'missing_derivacion_hours' => 4,
                'warning_hours' => 72,
                'critical_hours' => 24,
            ],
            'privado' => [
                'label' => 'Validar cobertura',
                'action' => 'Confirmar cobertura con aseguradora',
                'source' => 'cobertura',
                'hours' => 72,
                'warning_hours' => 36,
                'critical_hours' => 12,
            ],
            'particular' => [
                'label' => 'Seguimiento comercial',
                'action' => 'Contactar paciente y confirmar siguiente paso',
                'source' => 'seguimiento_comercial',
                'hours' => 72,
                'warning_hours' => 24,
                'critical_hours' => 8,
            ],
            'fundacional' => [
                'label' => 'Validar autorización',
                'action' => 'Confirmar autorización del convenio o fundación',
                'source' => 'autorizacion',
                'hours' => 72,
                'warning_hours' => 24,
                'critical_hours' => 6,
            ],
            'otros' => [
                'label' => 'Seguimiento operativo',
                'action' => 'Definir siguiente acción de la solicitud',
                'source' => 'seguimiento',
                'hours' => 48,
                'warning_hours' => 24,
                'critical_hours' => 6,
            ],
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function defaultStageRules(): array
    {
        return [
            'llamado' => [
                'label' => 'Contacto inicial pendiente',
                'action' => 'Llamar y confirmar continuidad del caso',
                'source' => 'etapa_llamado',
                'hours' => 24,
                'warning_hours' => 8,
                'critical_hours' => 4,
            ],
            'revision-codigos' => [
                'label' => 'Cobertura en revisión',
                'action' => 'Resolver cobertura, códigos o autorización base',
                'source' => 'etapa_cobertura',
                'hours' => 48,
                'warning_hours' => 24,
                'critical_hours' => 8,
            ],
            'espera-documentos' => [
                'label' => 'Documentación estancada',
                'action' => 'Completar soportes y destrabar documentación',
                'source' => 'etapa_documentacion',
                'hours' => 72,
                'warning_hours' => 24,
                'critical_hours' => 8,
                'by_rule_key' => [
                    'publico' => [
                        'hours' => 96,
                        'warning_hours' => 36,
                        'critical_hours' => 12,
                    ],
                    'particular' => [
                        'hours' => 48,
                        'warning_hours' => 24,
                        'critical_hours' => 8,
                    ],
                ],
            ],
            'apto-oftalmologo' => [
                'label' => 'Apto oftalmólogo pendiente',
                'action' => 'Coordinar apto con oftalmología y registrar resultado',
                'source' => 'etapa_apto_oftalmologo',
                'hours' => 72,
                'warning_hours' => 24,
                'critical_hours' => 8,
            ],
            'apto-anestesia' => [
                'label' => 'Apto anestesia pendiente',
                'action' => 'Coordinar valoración de anestesia y destrabar agenda',
                'source' => 'etapa_apto_anestesia',
                'hours' => 72,
                'warning_hours' => 24,
                'critical_hours' => 8,
                'by_rule_key' => [
                    'publico' => [
                        'hours' => 96,
                        'warning_hours' => 36,
                        'critical_hours' => 12,
                    ],
                ],
            ],
            'listo-para-agenda' => [
                'label' => 'Pendiente de agenda',
                'action' => 'Asignar fecha quirúrgica y cerrar coordinación',
                'source' => 'etapa_agenda',
                'hours' => 48,
                'warning_hours' => 24,
                'critical_hours' => 8,
                'by_rule_key' => [
                    'particular' => [
                        'hours' => 36,
                        'warning_hours' => 18,
                        'critical_hours' => 6,
                    ],
                ],
            ],
            'programada' => [
                'label' => 'Seguimiento prequirúrgico',
                'action' => 'Confirmar preparación final y evitar caída de agenda',
                'source' => 'etapa_programada',
                'hours' => 48,
                'warning_hours' => 24,
                'critical_hours' => 8,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $rules
     * @return array<string,array<string,mixed>>
     */
    private function normalizeBaseRules(array $rules): array
    {
        $allowed = array_keys($this->defaultBaseRules());
        $normalized = [];
        foreach ($allowed as $key) {
            $row = $rules[$key] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $normalized[$key] = [
                'label' => $this->string($row['label'] ?? null),
                'action' => $this->string($row['action'] ?? null),
                'source' => $this->string($row['source'] ?? null),
                'hours' => $this->intOrNull($row['hours'] ?? null),
                'missing_derivacion_hours' => $this->intOrNull($row['missing_derivacion_hours'] ?? null),
                'warning_hours' => $this->intOrNull($row['warning_hours'] ?? null),
                'critical_hours' => $this->intOrNull($row['critical_hours'] ?? null),
            ];
        }

        return $this->filterRecursive($normalized);
    }

    /**
     * @param array<string,mixed> $rules
     * @return array<string,array<string,mixed>>
     */
    private function normalizeStageRules(array $rules): array
    {
        $allowedStages = array_keys($this->defaultStageRules());
        $allowedCategories = array_keys($this->categoryLabels());
        $normalized = [];

        foreach ($allowedStages as $stageKey) {
            $row = $rules[$stageKey] ?? null;
            if (!is_array($row)) {
                continue;
            }

            $stageRule = [
                'label' => $this->string($row['label'] ?? null),
                'action' => $this->string($row['action'] ?? null),
                'source' => $this->string($row['source'] ?? null),
                'hours' => $this->intOrNull($row['hours'] ?? null),
                'warning_hours' => $this->intOrNull($row['warning_hours'] ?? null),
                'critical_hours' => $this->intOrNull($row['critical_hours'] ?? null),
            ];

            $overrides = [];
            $byRuleKey = $row['by_rule_key'] ?? null;
            if (is_array($byRuleKey)) {
                foreach ($allowedCategories as $categoryKey) {
                    $override = $byRuleKey[$categoryKey] ?? null;
                    if (!is_array($override)) {
                        continue;
                    }

                    $overrides[$categoryKey] = array_filter([
                        'hours' => $this->intOrNull($override['hours'] ?? null),
                        'warning_hours' => $this->intOrNull($override['warning_hours'] ?? null),
                        'critical_hours' => $this->intOrNull($override['critical_hours'] ?? null),
                    ], static fn($value): bool => $value !== null);
                }
            }

            if ($overrides !== []) {
                $stageRule['by_rule_key'] = $overrides;
            }

            $normalized[$stageKey] = $stageRule;
        }

        return $this->filterRecursive($normalized);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function filterRecursive(array $payload): array
    {
        $filtered = [];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $value = $this->filterRecursive($value);
                if ($value === []) {
                    continue;
                }

                $filtered[$key] = $value;
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && is_array($base[$key] ?? null)) {
                $base[$key] = $this->mergeRecursive($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @return array<string,mixed>
     */
    private function storedSetting(string $name): array
    {
        try {
            $value = DB::table('app_settings')->where('name', $name)->value('value');
        } catch (\Throwable) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function persist(string $name, array $payload): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['name' => $name],
            [
                'category' => 'solicitudes',
                'value' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'type' => 'json',
                'autoload' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function string(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return max(1, (int) round((float) $value));
    }
}
