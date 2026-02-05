<?php

namespace Modules\Examenes\Services;

use Helpers\JsonLogger;
use Models\SettingsModel;
use PDO;
use Throwable;

class ExamenSettingsService
{
    private const DEFAULT_REPORT_FORMATS = ['pdf', 'excel'];

    private const DEFAULT_QUICK_METRICS = [
        'cobertura' => [
            'label' => 'Pendientes de cobertura',
            'estado' => 'revision-cobertura',
        ],
        'agenda' => [
            'label' => 'Listos para agenda',
            'estado' => 'listo-para-agenda',
        ],
        'llamado' => [
            'label' => 'Pendientes de llamada',
            'estado' => 'llamado',
        ],
    ];

    private SettingsModel $settings;
    private ?array $cache = null;

    public function __construct(PDO $pdo, ?SettingsModel $settings = null)
    {
        $this->settings = $settings ?? new SettingsModel($pdo);
    }

    /**
     * @return array<int, string>
     */
    public function getReportFormats(): array
    {
        $value = $this->getSettingsValue('examenes.report.formats');
        $parsed = $this->parseJsonArray($value, self::DEFAULT_REPORT_FORMATS, 'examenes.report.formats');
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
        $value = $this->getSettingsValue('examenes.report.quick_metrics');
        $parsed = $this->parseJsonArray($value, self::DEFAULT_QUICK_METRICS, 'examenes.report.quick_metrics');

        $validated = [];
        foreach ($parsed as $key => $config) {
            if (!is_array($config) || (!isset($config['estado']) && !isset($config['sla_status']))) {
                continue;
            }

            $label = trim((string) ($config['label'] ?? ''));
            $metric = [
                'label' => $label !== '' ? $label : (self::DEFAULT_QUICK_METRICS[$key]['label'] ?? ''),
            ];

            if (isset($config['estado'])) {
                $metric['estado'] = (string) $config['estado'];
            }

            if (isset($config['sla_status'])) {
                $metric['sla_status'] = (string) $config['sla_status'];
            }

            $keyString = trim((string) $key);
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
            'examenes.report.formats',
            'examenes.report.quick_metrics',
        ];

        try {
            $this->cache = $this->settings->getOptions($keys);
        } catch (Throwable $exception) {
            JsonLogger::log(
                'examenes_settings',
                'No se pudieron cargar settings de exámenes',
                $exception
            );
            $this->cache = [];
        }

        return $this->cache;
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
                'examenes_settings',
                'Settings de exámenes con JSON inválido',
                null,
                ['key' => $key]
            );
            return $default;
        }

        return $decoded;
    }
}
