<?php

namespace App\Console\Commands;

use App\Modules\ControlCenter\Services\ControlCenterService;
use Illuminate\Console\Command;

class ControlCenterRecordDeploymentCommand extends Command
{
    protected $signature = 'control-center:record-deployment
        {--instance= : Slug de la instancia Control Center}
        {--version= : Version instalada}
        {--status=installed : Estado del deployment}
        {--commit= : Commit SHA asociado}
        {--actor= : Actor o pipeline que reporta}
        {--deployed-at= : Fecha/hora del deployment}';

    protected $description = 'Registra de forma idempotente un deployment real en Control Center';

    public function handle(ControlCenterService $service): int
    {
        $instance = trim((string) $this->option('instance'));
        $version = trim((string) $this->option('version'));
        $status = trim((string) ($this->option('status') ?: 'installed'));

        if ($instance === '' || $version === '') {
            $this->error('Debes enviar --instance y --version.');

            return self::FAILURE;
        }

        $result = $service->recordDeployment(
            $instance,
            $version,
            $status,
            $this->option('commit') !== null ? trim((string) $this->option('commit')) : null,
            $this->option('actor') !== null ? trim((string) $this->option('actor')) : 'artisan',
            $this->option('deployed-at') !== null ? trim((string) $this->option('deployed-at')) : null,
        );

        $deployment = $result['deployment'] ?? [];
        $this->info('Deployment registrado en Control Center.');
        $this->line('Instancia: ' . $instance);
        $this->line('Version: ' . $version);
        $this->line('Estado: ' . $status);
        if (is_array($deployment) && isset($deployment['id'])) {
            $this->line('Deployment ID: ' . $deployment['id']);
        }

        return self::SUCCESS;
    }
}
