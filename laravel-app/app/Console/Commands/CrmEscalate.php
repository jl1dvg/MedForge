<?php

namespace App\Console\Commands;

use App\Modules\CRM\Services\CrmEscalationService;
use Illuminate\Console\Command;

class CrmEscalate extends Command
{
    protected $signature = 'crm:escalate {--dry-run : Reporta sin escribir}';
    protected $description = 'Escala oportunidades operativas inactivas al equipo comercial';

    public function __construct(
        private readonly CrmEscalationService $escalationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo dry-run — no se escribirá nada.');
        }

        ['escalated' => $escalated] = $this->escalationService->run($dryRun);

        $this->info("Oportunidades escaladas: {$escalated}");

        return 0;
    }
}
