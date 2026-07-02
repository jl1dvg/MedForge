<?php

namespace App\Console\Commands;

use App\Modules\ControlCenter\Services\InstanceTelemetryAgentService;
use Illuminate\Console\Command;

class ControlCenterSendTelemetryCommand extends Command
{
    protected $signature = 'control-center:send-telemetry
        {--endpoint= : Endpoint central /v2/control-center/telemetry/heartbeat}
        {--token= : Token de telemetria de la instancia}
        {--instance= : Slug de la instancia}
        {--app-version= : Version instalada a reportar}';

    protected $description = 'Envia health y consumo real de esta instancia al Control Center central';

    public function handle(InstanceTelemetryAgentService $agent): int
    {
        try {
            $result = $agent->send(
                $this->option('endpoint') !== null ? (string) $this->option('endpoint') : null,
                $this->option('token') !== null ? (string) $this->option('token') : null,
                $this->option('instance') !== null ? (string) $this->option('instance') : null,
                $this->option('app-version') !== null ? (string) $this->option('app-version') : null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $status = (int) ($result['http_status'] ?? 0);
        $payload = $result['payload'] ?? [];
        $responseText = $this->formatResponse($result['response'] ?? null, (string) ($result['body'] ?? ''));

        $this->line('Endpoint: ' . ($result['endpoint'] ?? ''));
        $this->line('Instancia: ' . ($payload['instance_slug'] ?? ''));
        $this->line('HTTP status: ' . $status);
        $this->line('Respuesta del servidor:');
        $this->line($responseText);

        if (!($result['ok'] ?? false)) {
            $this->error('Error enviando telemetria.');

            return self::FAILURE;
        }

        $this->info('Telemetria enviada correctamente.');

        return self::SUCCESS;
    }

    private function formatResponse(mixed $response, string $body): string
    {
        if (is_array($response)) {
            return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        $body = trim($body);

        return $body !== '' ? $body : '(sin cuerpo)';
    }
}
