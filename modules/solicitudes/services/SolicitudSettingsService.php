<?php

namespace Modules\Solicitudes\Services;

use Helpers\JsonLogger;
use Models\SettingsModel;
use PDO;
use Throwable;

/**
 * Gestiona settings del módulo de solicitudes con fallback a valores por defecto.
 *
 * Keys soportadas:
 * - solicitudes.sla.warning_hours (int, default 72)
 * - solicitudes.sla.critical_hours (int, default 24)
 * - solicitudes.sla.labels (json map, default labels actuales)
 * - solicitudes.turnero.allowed_states (json array, default estados actuales + variantes normalizadas)
 * - solicitudes.turnero.default_state (string, default "Llamado")
 * - solicitudes.turnero.refresh_ms (int, default 30000)
 * - solicitudes.report.formats (json array, default ["pdf","excel"])
 * - solicitudes.report.quick_metrics (json map, default métricas actuales)
 */
class SolicitudSettingsService
{
    private const DEFAULT_SLA_WARNING = 72;
    private const DEFAULT_SLA_CRITICAL = 24;
    private const DEFAULT_TURNERO_REFRESH_MS = 30000;

    private const DEFAULT_SLA_LABELS = [
        'en_rango' => ['color' => 'success', 'label' => 'SLA en rango', 'icon' => 'mdi-check-circle-outline'],
        'advertencia' => ['color' => 'warning', 'label' => 'SLA 72h', 'icon' => 'mdi-timer-sand'],
        'critico' => ['color' => 'danger', 'label' => 'SLA crítico', 'icon' => 'mdi-alert-octagon'],
        'vencido' => ['color' => 'dark', 'label' => 'SLA vencido', 'icon' => 'mdi-alert'],
        'sin_fecha' => ['color' => 'secondary', 'label' => 'SLA sin fecha', 'icon' => 'mdi-calendar-remove'],
        'cerrado' => ['color' => 'secondary', 'label' => 'SLA cerrado', 'icon' => 'mdi-lock-outline'],
    ];

    private const DEFAULT_TURNERO_STATES = [
        'Recibido',
        'Llamado',
        'En atención',
        'Atendido',
        'recibido',
        'llamado',
        'en atencion',
        'en atención',
        'atendido',
    ];

    private const DEFAULT_REPORT_FORMATS = ['pdf', 'excel'];

    private const DEFAULT_QUICK_METRICS = [
        'anestesia' => [
            'label' => 'Pendientes de apto de anestesia',
            'estado' => 'apto-anestesia',
        ],
        'cobertura' => [
            'label' => 'Pendientes de cobertura',
            'estado' => 'revision-codigos',
        ],
        'sla-vencido' => [
            'label' => 'SLA vencido',
            'sla_status' => 'vencido',
        ],
    ];

    private SettingsModel $settings;
    private ?array $cache = null;

    public function __construct(PDO $pdo, ?SettingsModel $settings = null)
    {
        $this->settings = $settings ?? new SettingsModel($pdo);
    }

    public function getSlaWarningHours(): int
    {
        $value = $this->getSettingsValue('solicitudes.sla.warning_hours');
        $parsed = $this->parsePositiveInt($value, self::DEFAULT_SLA_WARNING);
        return $parsed;
    }

    public function getSlaCriticalHours(): int
    {
        $value = $this->getSettingsValue('solicitudes.sla.critical_hours');
        $parsed = $this->parsePositiveInt($value, self::DEFAULT_SLA_CRITICAL);
        return $parsed;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getSlaLabels(): array
    {
        $value = $this->getSettingsValue('solicitudes.sla.labels');
        $parsed = $this->parseJsonArray($value, self::DEFAULT_SLA_LABELS, 'solicitudes.sla.labels');
        return $parsed;
    }

    /**
     * @return array<int, string>
     */
    public function getTurneroAllowedStates(): array
    {
        $value = $this->getSettingsValue('solicitudes.turnero.allowed_states');
        $parsed = $this->parseJsonArray($value, self::DEFAULT_TURNERO_STATES, 'solicitudes.turnero.allowed_states');
        $states = array_values(array_filter(array_map('trim', array_map('strval', $parsed))));

        return $states !== [] ? $states : self::DEFAULT_TURNERO_STATES;
    }

    public function getTurneroDefaultState(): string
    {
        $value = $this->getSettingsValue('solicitudes.turnero.default_state');
        $candidate = trim((string)($value ?? ''));
        if ($candidate === '') {
            $candidate = 'Llamado';
        }

        $allowed = $this->getTurneroAllowedStates();
        foreach ($allowed as $state) {
            if (strcasecmp($state, $candidate) === 0) {
                return $state;
            }
        }

        return 'Llamado';
    }

    public function getTurneroRefreshMs(): int
    {
        $value = $this->getSettingsValue('solicitudes.turnero.refresh_ms');
        $refresh = $this->parsePositiveInt($value, self::DEFAULT_TURNERO_REFRESH_MS);
        return $refresh;
    }

    /**
     * @return array<int, string>
     */
    public function getReportFormats(): array
    {
        $value = $this->getSettingsValue('solicitudes.report.formats');
        $parsed = $this->parseJsonArray($value, self::DEFAULT_REPORT_FORMATS, 'solicitudes.report.formats');
        $formats = array_values(array_unique(array_filter(array_map(static function ($format) {
            if (!is_string($format) && !is_numeric($format)) {
                return null;
            }
            $value = strtolower(trim((string) $format));
            return in_array($value, ['pdf', 'excel'], true) ? $value : null;
        }, $parsed))));

        return $formats !== [] ? $formats : self::DEFAULT_REPORT_FORMATS;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getQuickMetrics(): array
    {
        $value = $this->getSettingsValue('solicitudes.report.quick_metrics');
        $parsed = $this->parseJsonArray($value, self::DEFAULT_QUICK_METRICS, 'solicitudes.report.quick_metrics');

        $validated = [];
        foreach ($parsed as $key => $config) {
            if (!is_array($config) || (!isset($config['estado']) && !isset($config['sla_status']))) {
                continue;
            }
            $label = trim((string)($config['label'] ?? ''));
            $metric = [
                'label' => $label !== '' ? $label : (self::DEFAULT_QUICK_METRICS[$key]['label'] ?? ''),
            ];
            if (isset($config['estado'])) {
                $metric['estado'] = (string)$config['estado'];
            }
            if (isset($config['sla_status'])) {
                $metric['sla_status'] = (string)$config['sla_status'];
            }

            $keyString = trim((string)$key);
            if ($keyString !== '') {
                $validated[$keyString] = $metric;
            }
        }

        return $validated !== [] ? $validated : self::DEFAULT_QUICK_METRICS;
    }

    private function getSettingsValue(string $key): ?string
    {
        $options = $this->loadSettings();
        return $options[$key] ?? null;
    }

    private function loadSettings(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $keys = [
            'solicitudes.sla.warning_hours',
            'solicitudes.sla.critical_hours',
            'solicitudes.sla.labels',
            'solicitudes.turnero.allowed_states',
            'solicitudes.turnero.default_state',
            'solicitudes.turnero.refresh_ms',
            'solicitudes.report.formats',
            'solicitudes.report.quick_metrics',
        ];

        try {
            $this->cache = $this->settings->getOptions($keys);
        } catch (Throwable $exception) {
            JsonLogger::log(
                'solicitudes_settings',
                'No se pudieron cargar settings de solicitudes',
                $exception
            );
            $this->cache = [];
        }

        return $this->cache;
    }

    private function parsePositiveInt(?string $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            $parsed = (int) $value;
            return $parsed > 0 ? $parsed : $default;
        }

        return $default;
    }

    /**
     * @param array<mixed> $default
     * @return array<mixed>
     */
    private function parseJsonArray(?string $value, array $default, string $key): array
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            JsonLogger::log(
                'solicitudes_settings',
                'Settings de solicitudes con JSON inválido',
                null,
                [
                    'key' => $key,
                    'value' => $value,
                ]
            );
            return $default;
        }

        return $decoded;
    }
}
