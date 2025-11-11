<?php

namespace Modules\CiveExtension\Services;

use Modules\CiveExtension\Models\HealthCheckModel;
use PDO;
use RuntimeException;
use Throwable;

class HealthCheckService
{
    private ConfigService $configService;
    private HealthCheckModel $healthChecks;

    public function __construct(private PDO $pdo)
    {
        $this->configService = new ConfigService($pdo);
        $this->healthChecks = new HealthCheckModel($pdo);
    }

    /**
     * @param bool $force Si es true, ignora flags y fuerza la ejecución inmediata.
     * @return array{status:string,message:string,details:array<string,mixed>}
     */
    public function runScheduledChecks(bool $force = false): array
    {
        $config = $this->configService->getExtensionConfig();
        $healthConfig = $config['health'];

        if (!$force && (!$healthConfig['enabled'] || empty($healthConfig['endpoints']))) {
            return [
                'status' => 'skipped',
                'message' => 'Los health checks están desactivados.',
                'details' => [],
            ];
        }

        if (empty($healthConfig['endpoints'])) {
            return [
                'status' => 'skipped',
                'message' => 'No hay endpoints configurados para verificar.',
                'details' => [],
            ];
        }

        $results = [];
        $failures = 0;

        foreach ($healthConfig['endpoints'] as $endpoint) {
            try {
                $result = $this->checkEndpoint($endpoint['url'], $endpoint['method'], $config['api']['timeoutMs']);
                $result['name'] = $endpoint['name'];
                $results[] = $result;

                $this->healthChecks->store([
                    'endpoint' => $endpoint['url'],
                    'method' => $endpoint['method'],
                    'status_code' => $result['statusCode'],
                    'success' => $result['success'],
                    'latency_ms' => $result['latencyMs'],
                    'error_message' => $result['success'] ? null : $result['error'] ?? null,
                    'response_excerpt' => $result['responseExcerpt'],
                ]);

                if (!$result['success']) {
                    $failures++;
                }
            } catch (Throwable $exception) {
                $failures++;
                $results[] = [
                    'name' => $endpoint['name'],
                    'success' => false,
                    'statusCode' => null,
                    'latencyMs' => null,
                    'error' => $exception->getMessage(),
                    'responseExcerpt' => null,
                ];

                $this->healthChecks->store([
                    'endpoint' => $endpoint['url'],
                    'method' => $endpoint['method'],
                    'status_code' => null,
                    'success' => false,
                    'latency_ms' => null,
                    'error_message' => $exception->getMessage(),
                    'response_excerpt' => null,
                ]);
            }
        }

        return [
            'status' => $failures > 0 ? 'warning' : 'success',
            'message' => $failures > 0
                ? sprintf('Se detectaron %d endpoint(s) con incidencias.', $failures)
                : 'Todos los endpoints respondieron correctamente.',
            'details' => [
                'results' => $results,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function latestResults(int $limit = 20): array
    {
        return $this->healthChecks->latest($limit);
    }

    /**
     * @return array{success:bool,statusCode:int|null,latencyMs:int|null,error:?string,responseExcerpt:?string}
     */
    private function checkEndpoint(string $url, string $method, int $timeoutMs): array
    {
        $start = microtime(true);
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('No fue posible inicializar cURL.');
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT_MS => max(1000, $timeoutMs),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        if (strtoupper($method) === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latencyMs = (int) round((microtime(true) - $start) * 1000);

        if ($body === false && $error !== '') {
            return [
                'success' => false,
                'statusCode' => $status ?: null,
                'latencyMs' => $latencyMs,
                'error' => $error,
                'responseExcerpt' => null,
            ];
        }

        $success = $status >= 200 && $status < 300;

        return [
            'success' => $success,
            'statusCode' => $status ?: null,
            'latencyMs' => $latencyMs,
            'error' => $success ? null : ($error !== '' ? $error : 'Respuesta HTTP no exitosa'),
            'responseExcerpt' => $body !== false ? mb_substr($body, 0, 500) : null,
        ];
    }
}
