<?php

namespace App\Modules\ControlCenter\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class InstanceTelemetryAgentService
{
    /**
     * @return array<string, mixed>
     */
    public function send(?string $endpoint = null, ?string $token = null, ?string $instanceSlug = null, ?string $appVersion = null, bool $debugHttp = false, ?array $payload = null): array
    {
        $endpoint = $this->required($endpoint ?? config('control_center.telemetry_endpoint'), 'CONTROL_CENTER_TELEMETRY_ENDPOINT');
        $token = $this->required($token ?? config('control_center.telemetry_token'), 'CONTROL_CENTER_TELEMETRY_TOKEN');
        $instanceSlug = $this->required($instanceSlug ?? config('control_center.instance_slug'), 'CONTROL_CENTER_INSTANCE_SLUG');
        $payload ??= $this->payload($instanceSlug, $appVersion ?? config('control_center.app_version'));
        $headers = $this->headersForToken($token);

        if ($debugHttp) {
            $debugStream = fopen('php://stderr', 'w');
            $client = Http::withOptions($debugStream === false ? [] : ['debug' => $debugStream])
                ->withHeaders($headers)
                ->timeout(max(3, (int) config('control_center.telemetry_timeout', 10)));

            $response = $client->send('POST', $endpoint, ['json' => $payload]);
        } else {
            $response = Http::withHeaders($headers)
                ->asJson()
                ->timeout(max(3, (int) config('control_center.telemetry_timeout', 10)))
                ->post($endpoint, $payload);
        }

        return [
            'endpoint' => $endpoint,
            'headers_contain_authorization' => array_key_exists('Authorization', $headers),
            'http_status' => $response->status(),
            'ok' => $response->successful(),
            'payload' => $payload,
            'response' => $response->json(),
            'body' => $response->body(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function headersForToken(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(string $instanceSlug, ?string $appVersion = null): array
    {
        $now = Carbon::now(config('app.timezone', 'America/Guayaquil'));

        $dbOk = $this->checkDatabase();
        $queueOk = $this->checkQueueConfig();
        $cacheOk = $this->checkCache();
        $storageOk = $this->checkStorage();
        $schedulerOk = $this->checkSchedulerConfig();

        return [
            'instance_slug' => $instanceSlug,
            'app_version' => $appVersion ?: null,
            'environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'db_ok' => $dbOk,
            'queue_ok' => $queueOk,
            'cache_ok' => $cacheOk,
            'storage_ok' => $storageOk,
            'scheduler_ok' => $schedulerOk,
            'telemetry_status' => $this->telemetryStatus($dbOk, $queueOk, $cacheOk, $storageOk, $schedulerOk),
            'last_backup_at' => config('control_center.last_backup_at') ?: null,
            'checked_at' => $now->toAtomString(),
            'usage' => $this->usageMetrics($now),
        ];
    }

    private function telemetryStatus(bool $dbOk, bool $queueOk, bool $cacheOk, bool $storageOk, bool $schedulerOk): string
    {
        if ($dbOk && $queueOk && $cacheOk && $storageOk && $schedulerOk) {
            return 'healthy';
        }

        if (!$dbOk || !$storageOk) {
            return 'error';
        }

        return 'degraded';
    }

    private function checkDatabase(): bool
    {
        return $this->safeBool(function (): bool {
            DB::connection()->getPdo();
            DB::select('select 1');

            return true;
        });
    }

    private function checkCache(): bool
    {
        return $this->safeBool(function (): bool {
            $key = 'control_center.telemetry_agent.' . bin2hex(random_bytes(6));
            Cache::put($key, 'ok', 30);
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $ok;
        });
    }

    private function checkStorage(): bool
    {
        return $this->safeBool(fn (): bool => is_writable(storage_path()) && is_writable(storage_path('logs')));
    }

    private function checkQueueConfig(): bool
    {
        return $this->safeBool(function (): bool {
            $connection = (string) config('queue.default', 'sync');

            return $connection !== '' && is_array(config("queue.connections.{$connection}"));
        });
    }

    private function checkSchedulerConfig(): bool
    {
        return $this->safeBool(fn (): bool => is_file(base_path('routes/console.php')));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function usageMetrics(Carbon $now): array
    {
        $periodStart = $now->copy()->startOfMonth()->toDateString();
        $periodEnd = $now->copy()->endOfMonth()->toDateString();
        $metrics = [];

        $this->appendCountMetric($metrics, 'active_users', 'users', null, $periodStart, $periodEnd, 'users');
        $this->appendCountMetric($metrics, 'whatsapp_messages', 'whatsapp_messages', ['created_at', $periodStart, $periodEnd], $periodStart, $periodEnd, 'messages');
        $this->appendCountMetric($metrics, 'whatsapp_conversations', 'whatsapp_conversations', ['created_at', $periodStart, $periodEnd], $periodStart, $periodEnd, 'conversations');

        return $metrics;
    }

    /**
     * @param array<int, array<string, mixed>> $metrics
     * @param array{0:string,1:string,2:string}|null $dateFilter
     */
    private function appendCountMetric(array &$metrics, string $metric, string $table, ?array $dateFilter, string $periodStart, string $periodEnd, string $unit): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table);
        if ($dateFilter !== null && Schema::hasColumn($table, $dateFilter[0])) {
            $query->whereDate($dateFilter[0], '>=', $dateFilter[1])
                ->whereDate($dateFilter[0], '<=', $dateFilter[2]);
        }

        $metrics[] = [
            'metric' => $metric,
            'value' => (float) $query->count(),
            'unit' => $unit,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    /**
     * @param callable(): bool $callback
     */
    private function safeBool(callable $callback): bool
    {
        try {
            return (bool) $callback();
        } catch (\Throwable) {
            return false;
        }
    }

    private function required(mixed $value, string $name): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw new \InvalidArgumentException("Configura {$name} para enviar telemetria Control Center.");
        }

        return $value;
    }
}
