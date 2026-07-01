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
        {--version= : Version instalada a reportar}';

    protected $description = 'Envia health y consumo real de esta instancia al Control Center central';

    public function handle(InstanceTelemetryAgentService $agent): int
    {
        try {
            $result = $agent->send(
                $this->option('endpoint') !== null ? (string) $this->option('endpoint') : null,
                $this->option('token') !== null ? (string) $this->option('token') : null,
                $this->option('instance') !== null ? (string) $this->option('instance') : null,
                $this->option('version') !== null ? (string) $this->option('version') : null,
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $status = (int) ($result['http_status'] ?? 0);
        if (!($result['ok'] ?? false)) {
            $this->error("Control Center rechazo la telemetria. HTTP {$status}");

            return self::FAILURE;
        }

        $payload = $result['payload'] ?? [];
        $this->info('Telemetria enviada a Control Center.');
        $this->line('Instancia: ' . ($payload['instance_slug'] ?? ''));
        $this->line('Version: ' . ($payload['app_version'] ?? ''));
        $this->line('HTTP: ' . $status);

        return self::SUCCESS;
    }
}
