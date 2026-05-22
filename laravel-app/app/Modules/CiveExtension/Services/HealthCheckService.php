<?php

declare(strict_types=1);

namespace App\Modules\CiveExtension\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class HealthCheckService
{
    public function __construct(private readonly ConfigService $configService) {}

    /**
     * @param bool $force If true, ignores flags and forces immediate execution.
     * @return array{status:string, message:string, details:array<string, mixed>}
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

                $this->storeResult([
                    'endpoint' => $endpoint['url'],
                    'method' => $endpoint['method'],
                    'status_code' => $result['statusCode'],
                    'success' => $result['success'],
                    'latency_ms' => $result['latencyMs'],
                    'error_message' => $result['success'] ? null : ($result['error'] ?? null),
                    'response_excerpt' => $result['responseExcerpt'],
                ]);

                if (!$result['success']) {
                    $failures++;
                }
            } catch (Throwable $exception) {
                $failures++;
                Log::warning('CiveExtension health check failed', [
                    'endpoint' => $endpoint['url'] ?? 'unknown',
                    'error' => $exception->getMessage(),
                ]);
                $results[] = [
                    'name' => $endpoint['name'],
                    'success' => false,
                    'statusCode' => null,
                    'latencyMs' => null,
                    'error' => $exception->getMessage(),
                    'responseExcerpt' => null,
                ];

                $this->storeResult([
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
        return DB::table('cive_extension_health_checks')
            ->select(['id', 'endpoint', 'method', 'status_code', 'success', 'latency_ms', 'error_message', 'response_excerpt', 'created_at'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(static fn($row) => (array) $row)
            ->all();
    }

    /**
     * @param array{endpoint:string, method:string, status_code:int|null, success:bool, latency_ms:int|null, error_message:string|null, response_excerpt:string|null} $payload
     */
    private function storeResult(array $payload): void
    {
        DB::table('cive_extension_health_checks')->insert([
            'endpoint' => $payload['endpoint'],
            'method' => $payload['method'],
            'status_code' => $payload['status_code'],
            'success' => $payload['success'] ? 1 : 0,
            'latency_ms' => $payload['latency_ms'],
            'error_message' => $payload['error_message'],
            'response_excerpt' => $payload['response_excerpt'],
        ]);
    }

    /**
     * @return array{success:bool, statusCode:int|null, latencyMs:int|null, error:string|null, responseExcerpt:string|null}
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
            'responseExcerpt' => $body !== false ? mb_substr((string) $body, 0, 500) : null,
        ];
    }
}
