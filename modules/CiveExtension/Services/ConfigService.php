<?php

namespace Modules\CiveExtension\Services;

use Models\SettingsModel;
use PDO;
use RuntimeException;

class ConfigService
{
    private SettingsModel $settings;

    private const DEFAULTS = [
        'api_base_url' => 'https://asistentecive.consulmed.me/api',
        'api_timeout_ms' => 12000,
        'api_max_retries' => 2,
        'api_retry_delay_ms' => 600,
        'api_credentials' => 'same-origin',
        'procedures_cache_ttl_ms' => 300000,
        'health_enabled' => false,
        'health_max_age_minutes' => 60,
        'refresh_interval_ms' => 900000,
        'openai_model' => 'gpt-3.5-turbo',
        'local_mode' => false,
        'extension_id_local' => 'JORGE',
        'extension_id_remote' => 'CIVE',
    ];

    public function __construct(PDO $pdo)
    {
        $this->settings = new SettingsModel($pdo);
    }

    /**
     * @return array{
     *     api: array{baseUrl:string, timeoutMs:int, maxRetries:int, retryDelayMs:int, cacheTtlMs:int},
     *     openAi: array{apiKey:string, model:string},
     *     health: array{enabled:bool, endpoints:array<int, array{name:string, method:string, url:string}>, maxAgeMinutes:int},
     *     refreshIntervalMs:int
     * }
     */
    public function getExtensionConfig(): array
    {
        $options = $this->settings->getOptions([
            'cive_extension_api_base_url',
            'cive_extension_timeout_ms',
            'cive_extension_max_retries',
            'cive_extension_retry_delay_ms',
            'cive_extension_api_credentials_mode',
            'cive_extension_procedures_cache_ttl_ms',
            'cive_extension_openai_api_key',
            'cive_extension_openai_model',
            'cive_extension_health_enabled',
            'cive_extension_health_endpoints',
            'cive_extension_health_max_age_minutes',
            'cive_extension_refresh_interval_ms',
            'cive_extension_local_mode',
            'cive_extension_extension_id_local',
            'cive_extension_extension_id_remote',
        ]);

        $apiBaseUrl = $this->sanitizeUrl($options['cive_extension_api_base_url'] ?? self::DEFAULTS['api_base_url']);
        $timeoutMs = $this->sanitizeInt($options['cive_extension_timeout_ms'] ?? null, self::DEFAULTS['api_timeout_ms']);
        $maxRetries = $this->sanitizeInt($options['cive_extension_max_retries'] ?? null, self::DEFAULTS['api_max_retries']);
        $retryDelayMs = $this->sanitizeInt($options['cive_extension_retry_delay_ms'] ?? null, self::DEFAULTS['api_retry_delay_ms']);
        $credentialsMode = $this->sanitizeCredentialsMode($options['cive_extension_api_credentials_mode'] ?? null);
        $cacheTtlMs = $this->sanitizeInt($options['cive_extension_procedures_cache_ttl_ms'] ?? null, self::DEFAULTS['procedures_cache_ttl_ms']);
        $refreshIntervalMs = $this->sanitizeInt($options['cive_extension_refresh_interval_ms'] ?? null, self::DEFAULTS['refresh_interval_ms']);

        $healthEnabled = (bool)($options['cive_extension_health_enabled'] ?? self::DEFAULTS['health_enabled']);
        $healthMaxAge = $this->sanitizeInt($options['cive_extension_health_max_age_minutes'] ?? null, self::DEFAULTS['health_max_age_minutes']);
        $healthEndpointsRaw = (string)($options['cive_extension_health_endpoints'] ?? '');
        $healthEndpoints = $this->parseHealthEndpoints($healthEndpointsRaw);

        $localMode = (bool)($options['cive_extension_local_mode'] ?? self::DEFAULTS['local_mode']);
        $extensionIdLocal = trim((string)($options['cive_extension_extension_id_local'] ?? self::DEFAULTS['extension_id_local']));
        $extensionIdRemote = trim((string)($options['cive_extension_extension_id_remote'] ?? self::DEFAULTS['extension_id_remote']));
        $extensionId = $localMode
            ? ($extensionIdLocal !== '' ? $extensionIdLocal : self::DEFAULTS['extension_id_local'])
            : ($extensionIdRemote !== '' ? $extensionIdRemote : self::DEFAULTS['extension_id_remote']);

        $openAiKey = trim((string)($options['cive_extension_openai_api_key'] ?? ''));
        $openAiModel = trim((string)($options['cive_extension_openai_model'] ?? self::DEFAULTS['openai_model']));

        return [
            'api' => [
                'baseUrl' => $apiBaseUrl,
                'timeoutMs' => $timeoutMs,
                'maxRetries' => $maxRetries,
                'retryDelayMs' => $retryDelayMs,
                'cacheTtlMs' => $cacheTtlMs,
                'credentialsMode' => $credentialsMode,
            ],
            'openAi' => [
                'apiKey' => $openAiKey,
                'model' => $openAiModel !== '' ? $openAiModel : self::DEFAULTS['openai_model'],
            ],
            'health' => [
                'enabled' => $healthEnabled && !empty($healthEndpoints),
                'endpoints' => $healthEndpoints,
                'maxAgeMinutes' => $healthMaxAge,
            ],
            'refreshIntervalMs' => $refreshIntervalMs,
            'flags' => [
                'esLocal' => $localMode,
                'extensionId' => $extensionId,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFrontendBootstrapConfig(): array
    {
        $config = $this->getExtensionConfig();
        $baseUrl = rtrim((string) \BASE_URL, '/');
        $controlEndpoint = $baseUrl . '/api/cive-extension/config';
        $healthEndpoint = $baseUrl . '/api/cive-extension/health-check';
        $healthHistoryEndpoint = $baseUrl . '/api/cive-extension/health-checks';

        return [
            'controlEndpoint' => $controlEndpoint,
            'healthEndpoint' => $healthEndpoint,
            'healthHistoryEndpoint' => $healthHistoryEndpoint,
            'subscriptionEndpoint' => rtrim($config['api']['baseUrl'], '/') . '/subscription/check.php',
            'refreshIntervalMs' => $config['refreshIntervalMs'],
            'api' => [
                'baseUrl' => $config['api']['baseUrl'],
                'timeoutMs' => $config['api']['timeoutMs'],
                'maxRetries' => $config['api']['maxRetries'],
                'retryDelayMs' => $config['api']['retryDelayMs'],
                'cacheTtlMs' => $config['api']['cacheTtlMs'],
                'credentialsMode' => $config['api']['credentialsMode'],
            ],
            'health' => [
                'enabled' => $config['health']['enabled'],
                'endpoints' => $config['health']['endpoints'],
                'maxAgeMinutes' => $config['health']['maxAgeMinutes'],
            ],
            'flags' => $config['flags'],
        ];
    }

    private function sanitizeUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return self::DEFAULTS['api_base_url'];
        }

        $parsed = parse_url($value);
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new RuntimeException('La URL base del API de CIVE Extension no es válida.');
        }

        return rtrim($value, '/');
    }

    private function sanitizeInt(?string $value, int $default): int
    {
        if ($value === null || trim($value) === '') {
            return $default;
        }

        if (!is_numeric($value)) {
            return $default;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : $default;
    }

    /**
     * @return array<int, array{name:string, method:string, url:string}>
     */
    private function parseHealthEndpoints(string $raw): array
    {
        $lines = preg_split('/\r?\n/', $raw) ?: [];
        $endpoints = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) === 1) {
                $method = 'GET';
                $name = $parts[0];
                $url = $parts[0];
            } elseif (count($parts) === 2) {
                $method = 'GET';
                [$name, $url] = $parts;
            } else {
                [$name, $method, $url] = [$parts[0], strtoupper($parts[1]), $parts[2]];
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            if (!in_array($method, ['GET', 'POST', 'HEAD'], true)) {
                $method = 'GET';
            }

            $endpoints[] = [
                'name' => $name !== '' ? $name : $url,
                'method' => $method,
                'url' => $url,
            ];
        }

        return $endpoints;
    }

    private function sanitizeCredentialsMode(?string $value): string
    {
        $value = strtolower(trim((string)($value ?? '')));
        if (in_array($value, ['omit', 'same-origin', 'include'], true)) {
            return $value;
        }

        return self::DEFAULTS['api_credentials'];
    }
}
