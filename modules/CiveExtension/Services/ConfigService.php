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
        'procedures_cache_ttl_ms' => 300000,
        'health_enabled' => true,
        'health_max_age_minutes' => 60,
        'refresh_interval_ms' => 900000,
        'openai_model' => 'gpt-3.5-turbo',
        'debug_api_logging' => true,
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
     *     debug: array{apiLogging:bool},
     *     refreshIntervalMs:int
     * }
     */
    public function getExtensionConfig(): array
    {
        $options = $this->settings->getOptions([
            'cive_extension_control_base_url',
            'cive_extension_api_base_url',
            'cive_extension_timeout_ms',
            'cive_extension_max_retries',
            'cive_extension_retry_delay_ms',
            'cive_extension_procedures_cache_ttl_ms',
            'cive_extension_openai_api_key',
            'cive_extension_openai_model',
            'cive_extension_health_enabled',
            'cive_extension_health_endpoints',
            'cive_extension_health_max_age_minutes',
            'cive_extension_refresh_interval_ms',
            'cive_extension_debug_api_logging',
        ]);

        $apiBaseUrl = $this->sanitizeUrl($options['cive_extension_api_base_url'] ?? self::DEFAULTS['api_base_url']);
        $timeoutMs = $this->sanitizeInt($options['cive_extension_timeout_ms'] ?? null, self::DEFAULTS['api_timeout_ms']);
        $maxRetries = $this->sanitizeInt($options['cive_extension_max_retries'] ?? null, self::DEFAULTS['api_max_retries']);
        $retryDelayMs = $this->sanitizeInt($options['cive_extension_retry_delay_ms'] ?? null, self::DEFAULTS['api_retry_delay_ms']);
        $cacheTtlMs = $this->sanitizeInt($options['cive_extension_procedures_cache_ttl_ms'] ?? null, self::DEFAULTS['procedures_cache_ttl_ms']);
        $refreshIntervalMs = $this->sanitizeInt($options['cive_extension_refresh_interval_ms'] ?? null, self::DEFAULTS['refresh_interval_ms']);

        $healthEnabled = (bool)($options['cive_extension_health_enabled'] ?? self::DEFAULTS['health_enabled']);
        $healthMaxAge = $this->sanitizeInt($options['cive_extension_health_max_age_minutes'] ?? null, self::DEFAULTS['health_max_age_minutes']);
        $healthEndpointsRaw = (string)($options['cive_extension_health_endpoints'] ?? '');
        $healthEndpoints = $this->parseHealthEndpoints($healthEndpointsRaw);

        $openAiKey = trim((string)($options['cive_extension_openai_api_key'] ?? ''));
        $openAiModel = trim((string)($options['cive_extension_openai_model'] ?? self::DEFAULTS['openai_model']));

        $controlBaseUrl = $this->determineControlBaseUrl($options, $apiBaseUrl);
        $subscriptionEndpoint = rtrim($apiBaseUrl, '/') . '/subscription/check.php';

        $debugApiLogging = isset($options['cive_extension_debug_api_logging'])
            ? (bool)$options['cive_extension_debug_api_logging']
            : self::DEFAULTS['debug_api_logging'];

        return [
            'api' => [
                'baseUrl' => $apiBaseUrl,
                'timeoutMs' => $timeoutMs,
                'maxRetries' => $maxRetries,
                'retryDelayMs' => $retryDelayMs,
                'cacheTtlMs' => $cacheTtlMs,
            ],
            'openAi' => [
                'apiKey' => $openAiKey,
                'model' => $openAiModel !== '' ? $openAiModel : self::DEFAULTS['openai_model'],
            ],
            'control' => [
                'baseUrl' => $controlBaseUrl,
                'configEndpoint' => $this->buildControlEndpoint($controlBaseUrl, '/api/cive-extension/config'),
                'healthEndpoint' => $this->buildControlEndpoint($controlBaseUrl, '/api/cive-extension/health-check'),
                'healthHistoryEndpoint' => $this->buildControlEndpoint($controlBaseUrl, '/api/cive-extension/health-checks'),
                'subscriptionEndpoint' => $subscriptionEndpoint,
            ],
            'health' => [
                'enabled' => $healthEnabled && !empty($healthEndpoints),
                'endpoints' => $healthEndpoints,
                'maxAgeMinutes' => $healthMaxAge,
            ],
            'debug' => [
                'apiLogging' => $debugApiLogging,
            ],
            'refreshIntervalMs' => $refreshIntervalMs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFrontendBootstrapConfig(): array
    {
        $config = $this->getExtensionConfig();

        return [
            'controlBaseUrl' => $config['control']['baseUrl'],
            'controlEndpoint' => $config['control']['configEndpoint'],
            'healthEndpoint' => $config['control']['healthEndpoint'],
            'healthHistoryEndpoint' => $config['control']['healthHistoryEndpoint'],
            'subscriptionEndpoint' => $config['control']['subscriptionEndpoint'],
            'refreshIntervalMs' => $config['refreshIntervalMs'],
            'api' => [
                'baseUrl' => $config['api']['baseUrl'],
                'timeoutMs' => $config['api']['timeoutMs'],
                'maxRetries' => $config['api']['maxRetries'],
                'retryDelayMs' => $config['api']['retryDelayMs'],
                'cacheTtlMs' => $config['api']['cacheTtlMs'],
            ],
            'health' => [
                'enabled' => $config['health']['enabled'],
                'endpoints' => $config['health']['endpoints'],
                'maxAgeMinutes' => $config['health']['maxAgeMinutes'],
            ],
            'debug' => [
                'apiLogging' => $config['debug']['apiLogging'],
            ],
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

    /**
     * @param array<string, mixed> $options
     */
    private function determineControlBaseUrl(array $options, string $apiBaseUrl): string
    {
        $configured = $options['cive_extension_control_base_url'] ?? '';
        if (is_string($configured) && trim($configured) !== '') {
            return $this->sanitizeUrl($configured);
        }

        if (defined('BASE_URL')) {
            $baseUrl = trim((string) BASE_URL);
            if ($baseUrl !== '') {
                try {
                    return $this->sanitizeUrl($baseUrl);
                } catch (RuntimeException $exception) {
                    error_log('Control base URL derivation failed using BASE_URL: ' . $exception->getMessage());
                }
            }
        }

        return $this->deriveControlBaseFromApi($apiBaseUrl);
    }

    private function deriveControlBaseFromApi(string $apiBaseUrl): string
    {
        $parsed = parse_url($apiBaseUrl);
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            return rtrim($apiBaseUrl, '/');
        }

        $base = $parsed['scheme'] . '://' . $parsed['host'];
        if (!empty($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }

        $path = $parsed['path'] ?? '';
        if ($path !== '') {
            $normalized = trim($path, '/');
            if ($normalized !== '') {
                $segments = explode('/', $normalized);
                if (count($segments) > 1) {
                    array_pop($segments);
                    $base .= '/' . implode('/', $segments);
                }
            }
        }

        return rtrim($base, '/');
    }

    private function buildControlEndpoint(string $baseUrl, string $path): string
    {
        $normalizedBase = rtrim($baseUrl, '/');
        $normalizedPath = '/' . ltrim($path, '/');

        return $normalizedBase . $normalizedPath;
    }
}
