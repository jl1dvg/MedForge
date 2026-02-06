<?php

namespace Modules\CRM\Services;

use Models\SettingsModel;
use PDO;
use PDOException;
use RuntimeException;

class LeadConfigurationService
{
    public const CONTEXT_CRM = 'crm';
    public const CONTEXT_EXAMENES = 'examenes';

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

    private const DEFAULT_SORTS = [
        self::CONTEXT_CRM => 'fecha_desc',
        self::CONTEXT_EXAMENES => 'creado_desc',
    ];

    private const KANBAN_OPTION_KEYS = [
        self::CONTEXT_CRM => [
            'sort' => 'crm_kanban_sort',
            'column_limit' => 'crm_kanban_column_limit',
        ],
        self::CONTEXT_EXAMENES => [
            'sort' => 'examenes_kanban_sort',
            'column_limit' => 'examenes_kanban_column_limit',
        ],
    ];

    private PDO $pdo;
    private ?SettingsModel $settingsModel = null;
    private ?array $cachedPipeline = null;
    private ?array $cachedStageTemplates = null;
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $cachedKanbanPreferences = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        try {
            $this->settingsModel = new SettingsModel($pdo);
        } catch (RuntimeException $exception) {
            $this->settingsModel = null;
        }
    }

    /**
     * @return string[]
     */
    public function getPipelineStages(): array
    {
        if ($this->cachedPipeline !== null) {
            return $this->cachedPipeline;
        }

        $stages = $this->loadPipelineStagesFromSettings();
        if (empty($stages)) {
            $stages = self::DEFAULT_PIPELINE;
        }

        $this->cachedPipeline = $stages;

        return $this->cachedPipeline;
    }

    public function getInitialStage(): string
    {
        $pipeline = $this->getPipelineStages();

        return $pipeline[0] ?? self::DEFAULT_PIPELINE[0];
    }

    public function getWonStage(): string
    {
        $pipeline = $this->getPipelineStages();
        foreach ($pipeline as $stage) {
            $normalized = $this->normalizeText($stage);
            if (str_contains($normalized, 'cerrad') || str_contains($normalized, 'ganad') || str_contains($normalized, 'convert')) {
                return $stage;
            }
        }

        $lastIndex = count($pipeline) - 1;
        if ($lastIndex < 0) {
            return self::DEFAULT_PIPELINE[6];
        }

        $lastStage = $pipeline[$lastIndex];
        $lostStage = $this->getLostStage();
        if ($lostStage !== null && strcasecmp($lastStage, $lostStage) === 0 && $lastIndex > 0) {
            return $pipeline[$lastIndex - 1];
        }

        return $lastStage;
    }

    public function getLostStage(): ?string
    {
        $pipeline = $this->getPipelineStages();
        foreach ($pipeline as $stage) {
            $normalized = $this->normalizeText($stage);
            if (str_contains($normalized, 'perd') || str_contains($normalized, 'lost') || str_contains($normalized, 'cancel')) {
                return $stage;
            }
        }

        return null;
    }

    public function normalizeStage(?string $stage, bool $fallbackToFirst = true): string
    {
        $stage = trim((string) $stage);
        $pipeline = $this->getPipelineStages();

        if ($stage === '') {
            return $fallbackToFirst ? ($pipeline[0] ?? self::DEFAULT_PIPELINE[0]) : '';
        }

        foreach ($pipeline as $candidate) {
            if (strcasecmp($candidate, $stage) === 0) {
                return $candidate;
            }
        }

        if ($fallbackToFirst) {
            return $pipeline[0] ?? $stage;
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getKanbanPreferences(string $context = self::CONTEXT_CRM): array
    {
        $context = $this->normalizeContext($context);

        if (isset($this->cachedKanbanPreferences[$context])) {
            return $this->cachedKanbanPreferences[$context];
        }

        $defaults = self::DEFAULT_SORTS[$context] ?? self::DEFAULT_SORTS[self::CONTEXT_CRM];

        $sort = $defaults;
        $columnLimit = 0;

        if ($this->settingsModel instanceof SettingsModel) {
            try {
                $keys = self::KANBAN_OPTION_KEYS[$context] ?? self::KANBAN_OPTION_KEYS[self::CONTEXT_CRM];
                $options = $this->settingsModel->getOptions(array_values($keys));

                $sortKey = $keys['sort'];
                $columnKey = $keys['column_limit'];

                if (!empty($options[$sortKey])) {
                    $sort = $this->sanitizeSort($options[$sortKey], $context);
                }

                if (isset($options[$columnKey])) {
                    $columnLimit = max(0, (int) $options[$columnKey]);
                }
            } catch (PDOException $exception) {
                // Ignorar y usar valores por defecto
            }
        }

        $this->cachedKanbanPreferences[$context] = [
            'sort' => $sort,
            'column_limit' => $columnLimit,
        ];

        return $this->cachedKanbanPreferences[$context];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAssignableUsers(): array
    {
        $stmt = $this->pdo->query('SELECT id, nombre, email, profile_photo, especialidad FROM users ORDER BY nombre');
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $usuario): array {
            $usuario['avatar'] = $this->formatProfilePhoto($usuario['profile_photo'] ?? null);

            return $usuario;
        }, $usuarios);
    }

    private function formatProfilePhoto(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return function_exists('asset') ? asset($path) : $path;
    }

    /**
     * @return string[]
     */
    public function getSources(): array
    {
        $sources = [];

        $stmtDetalles = $this->pdo->query("SELECT DISTINCT fuente FROM solicitud_crm_detalles WHERE fuente IS NOT NULL AND fuente <> ''");
        if ($stmtDetalles) {
            foreach ($stmtDetalles->fetchAll(PDO::FETCH_COLUMN) as $fuente) {
                $this->appendSource($sources, $fuente);
            }
        }

        $stmtLeads = $this->pdo->query("SELECT DISTINCT source FROM crm_leads WHERE source IS NOT NULL AND source <> ''");
        if ($stmtLeads) {
            foreach ($stmtLeads->fetchAll(PDO::FETCH_COLUMN) as $fuente) {
                $this->appendSource($sources, $fuente);
            }
        }

        sort($sources, SORT_NATURAL | SORT_FLAG_CASE);

        return $sources;
    }

    /**
     * @return array<int, array{stage: string, template: string, language: string, components: array<string, mixed>}>
     */
    public function getStageTemplates(): array
    {
        if ($this->cachedStageTemplates !== null) {
            return $this->cachedStageTemplates;
        }

        $templates = [];

        if ($this->settingsModel instanceof SettingsModel) {
            try {
                $raw = $this->settingsModel->getOption('crm_whatsapp_stage_templates');
                $templates = $this->parseStageTemplates($raw ?? '');
            } catch (PDOException $exception) {
                $templates = [];
            }
        }

        $this->cachedStageTemplates = $templates;

        return $this->cachedStageTemplates;
    }

    public function findStageTemplate(string $stage): ?array
    {
        $stage = trim($stage);
        if ($stage === '') {
            return null;
        }

        $normalizedStage = $this->normalizeStage($stage, false);
        if ($normalizedStage === '') {
            return null;
        }

        foreach ($this->getStageTemplates() as $template) {
            if (strcasecmp($template['stage'], $normalizedStage) === 0) {
                return $template;
            }
        }

        return null;
    }

    private function appendSource(array &$sources, $candidate): void
    {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            return;
        }

        $normalized = mb_strtolower($candidate, 'UTF-8');
        foreach ($sources as $existing) {
            if (mb_strtolower((string) $existing, 'UTF-8') === $normalized) {
                return;
            }
        }

        $sources[] = mb_convert_case($candidate, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @return array<int, array{stage: string, template: string, language: string, components: array<string, mixed>}>
     */
    private function parseStageTemplates(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $pipeline = $this->getPipelineStages();

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $this->parseStageTemplatesFromArray($decoded, $pipeline);
        }

        $rules = [];
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        foreach ($lines as $line) {
            $rule = $this->parseStageTemplateLine($line, $pipeline);
            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @param array<int|string, mixed> $data
     * @param string[] $pipeline
     * @return array<int, array{stage: string, template: string, language: string, components: array<string, mixed>}>
     */
    private function parseStageTemplatesFromArray(array $data, array $pipeline): array
    {
        $rules = [];

        $isList = array_keys($data) === range(0, count($data) - 1);
        foreach ($data as $key => $value) {
            $rule = $isList
                ? $this->parseStageTemplateEntry($value, $pipeline)
                : $this->normalizeStageTemplate((string) $key, $value, $pipeline);

            if ($rule !== null) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    /**
     * @param mixed $entry
     * @param string[] $pipeline
     */
    private function parseStageTemplateEntry($entry, array $pipeline): ?array
    {
        if (!is_array($entry)) {
            return null;
        }

        $stage = (string) ($entry['stage'] ?? '');
        $template = (string) ($entry['template'] ?? '');

        return $this->normalizeStageTemplate($stage, $entry, $pipeline, $template);
    }

    /**
     * @param mixed $config
     * @param string[] $pipeline
     */
    private function normalizeStageTemplate(string $stage, $config, array $pipeline, ?string $templateOverride = null): ?array
    {
        $stage = $this->matchStageName(trim($stage), $pipeline) ?? trim($stage);
        if ($stage === '') {
            return null;
        }

        if (is_string($config)) {
            $config = ['template' => $config];
        }

        if (!is_array($config)) {
            return null;
        }

        $template = $templateOverride ?? (string) ($config['template'] ?? '');
        if (trim($template) === '') {
            return null;
        }

        $language = trim((string) ($config['language'] ?? 'es')) ?: 'es';
        $components = $this->normalizeTemplateComponents($config['components'] ?? [], $stage);

        return [
            'stage' => $stage,
            'template' => $template,
            'language' => $language,
            'components' => $components,
        ];
    }

    /**
     * @param string[] $pipeline
     */
    private function parseStageTemplateLine(string $line, array $pipeline): ?array
    {
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 2) {
            return null;
        }

        [$stageCandidate, $template, $language, $components] = array_pad($parts, 4, null);
        $stage = $this->matchStageName($stageCandidate, $pipeline) ?? $stageCandidate;

        if ($stage === '' || $template === null || trim($template) === '') {
            return null;
        }

        $language = $language !== null && trim($language) !== '' ? trim($language) : 'es';
        $components = $this->normalizeTemplateComponents($components, $stage);

        return [
            'stage' => $stage,
            'template' => $template,
            'language' => $language,
            'components' => $components,
        ];
    }

    /**
     * @param mixed $components
     * @return array<string, mixed>
     */
    private function normalizeTemplateComponents($components, ?string $stage = null): array
    {
        if (is_string($components)) {
            $decoded = json_decode($components, true);
            if (is_array($decoded)) {
                $components = $decoded;
            } else {
                if ($this->shouldLogTemplateParseError()) {
                    $suffix = $stage ? " para la etapa '{$stage}'" : '';
                    error_log('No fue posible interpretar los componentes de la plantilla de etapa' . $suffix . ': JSON inválido.');
                }
            }
        }

        return is_array($components) ? $components : [];
    }

    private function shouldLogTemplateParseError(): bool
    {
        $env = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? ''));
        if (in_array($env, ['local', 'development', 'dev', 'testing'], true)) {
            return true;
        }

        $debugFlag = strtolower((string) ($_ENV['DEBUG_TEMPLATES'] ?? getenv('DEBUG_TEMPLATES') ?? ''));

        return in_array($debugFlag, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param string[] $pipeline
     */
    private function matchStageName(string $candidate, array $pipeline): ?string
    {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        foreach ($pipeline as $stage) {
            if (strcasecmp($stage, $candidate) === 0) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function loadPipelineStagesFromSettings(): array
    {
        if (!($this->settingsModel instanceof SettingsModel)) {
            return [];
        }

        try {
            $raw = $this->settingsModel->getOption('crm_pipeline_stages');
        } catch (PDOException $exception) {
            return [];
        }

        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $stages = array_map(static fn($value) => trim((string) $value), $decoded);
        } else {
            $stages = preg_split('/\r\n|\r|\n/', $raw) ?: [];
            $stages = array_map(static fn($value) => trim($value), $stages);
        }

        $stages = array_values(array_filter($stages, static fn($value) => $value !== ''));

        return $stages;
    }

    private function sanitizeSort(string $sort, string $context): string
    {
        $sort = strtolower(trim($sort));
        $allowed = ['fecha_desc', 'fecha_asc', 'creado_desc', 'creado_asc'];

        if (!in_array($sort, $allowed, true)) {
            return $this->getDefaultSortFor($context);
        }

        return $sort;
    }

    private function getDefaultSortFor(string $context): string
    {
        return self::DEFAULT_SORTS[$context] ?? self::DEFAULT_SORTS[self::CONTEXT_CRM];
    }

    private function normalizeContext(string $context): string
    {
        $context = strtolower(trim($context));

        if (isset(self::KANBAN_OPTION_KEYS[$context])) {
            return $context;
        }

        return self::CONTEXT_CRM;
    }

    private function normalizeText(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}
