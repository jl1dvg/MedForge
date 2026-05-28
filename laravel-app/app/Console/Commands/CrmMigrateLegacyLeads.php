<?php

namespace App\Console\Commands;

use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmActivityService;
use App\Modules\CRM\Services\CrmContactResolverService;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmMigrateLegacyLeads extends Command
{
    protected $signature   = 'crm:migrate-legacy-leads {--dry-run : Solo reporta, no escribe}';
    protected $description = 'Migra leads del CRM legacy (crm_leads) a crm_contacts + crm_opportunities';

    public function __construct(
        private readonly CrmContactResolverService $contactResolver,
        private readonly CrmOpportunityService $opportunityService,
        private readonly CrmActivityService $activityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (!Schema::hasTable('crm_leads')) {
            $this->error('Tabla crm_leads no existe — nada que migrar.');
            return 1;
        }

        $leads = DB::table('crm_leads')->orderBy('id')->get();
        $this->info("Leads a migrar: {$leads->count()}");

        $migrated = 0;
        $skipped  = 0;

        foreach ($leads as $lead) {
            $phone  = (string) ($lead->phone ?? '');
            $cedula = (string) ($lead->hc_number ?? '');
            $name   = (string) ($lead->name ?? 'Paciente');
            $status = (string) ($lead->status ?? 'nuevo');

            if ($phone === '' && $cedula === '') {
                $this->warn("  Skip lead #{$lead->id} — sin teléfono ni cédula");
                $skipped++;
                continue;
            }

            $stage = match ($status) {
                'contactado'    => CrmOpportunity::STAGE_EN_CONTACTO,
                'propuesta'     => CrmOpportunity::STAGE_PROPUESTA,
                'ganado', 'won' => CrmOpportunity::STAGE_GANADO,
                'perdido'       => CrmOpportunity::STAGE_PERDIDO,
                default         => CrmOpportunity::STAGE_NUEVO,
            };

            if ($dryRun) {
                $this->line("  [dry] Lead #{$lead->id} → contacto ({$name}) + oportunidad ({$stage})");
                $migrated++;
                continue;
            }

            $contact = $this->contactResolver->resolve(
                phone: $phone ?: '+000',
                name: $name,
                cedula: $cedula ?: null,
                source: (string) ($lead->source ?? 'manual'),
            );

            $opp = CrmOpportunity::query()->create([
                'contact_id'  => $contact->id,
                'title'       => 'Lead migrado: ' . $name,
                'stage'       => $stage,
                'source'      => (string) ($lead->source ?? 'manual'),
                'source_id'   => $lead->id,
                'source_type' => 'legacy_crm_lead',
                'assigned_to' => $lead->assigned_to ?? null,
            ]);

            $this->activityService->logSystemEvent(
                $opp->id,
                "Migrado desde crm_leads legacy (ID: {$lead->id}, status original: {$status})",
            );

            $migrated++;
        }

        $this->info("Migrados: {$migrated} | Saltados: {$skipped}");
        if ($dryRun) {
            $this->warn('Modo dry-run — no se escribió nada. Correr sin --dry-run para ejecutar.');
        }

        return 0;
    }
}
