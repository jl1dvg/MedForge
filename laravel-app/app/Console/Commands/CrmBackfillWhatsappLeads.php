<?php

namespace App\Console\Commands;

use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmContactResolverService;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmBackfillWhatsappLeads extends Command
{
    protected $signature = 'crm:backfill-whatsapp-leads {--dry-run}';
    protected $description = 'Create CRM opportunities for WhatsApp leads that have none yet';

    public function __construct(
        private readonly CrmContactResolverService $contactResolver,
        private readonly CrmOpportunityService $opportunityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) $this->warn('Dry-run — no se escribirá nada.');

        // WhatsApp leads whose contact (by cedula OR phone) has no CRM opportunity
        $leads = DB::table('whatsapp_leads as wl')
            ->whereNotExists(function ($q): void {
                $q->from('crm_contacts as cc')
                    ->join('crm_opportunities as opp', 'opp.contact_id', '=', 'cc.id')
                    ->where(function ($inner): void {
                        $inner->whereColumn('cc.cedula', 'wl.cedula')
                              ->orWhereColumn('cc.phone', 'wl.wa_number');
                    });
            })
            ->whereNotNull('wl.wa_number')
            ->select('wl.id', 'wl.wa_number', 'wl.cedula', 'wl.patient_full_name', 'wl.display_name', 'wl.motivo_baja')
            ->get();

        $this->info("Leads sin oportunidad CRM: {$leads->count()}");

        $created = 0;
        foreach ($leads as $lead) {
            $name = $lead->patient_full_name ?: ($lead->display_name ?: $lead->wa_number);
            $title = 'Lead WhatsApp: ' . ($lead->motivo_baja ?: 'sin motivo registrado');

            if ($dryRun) {
                $this->line("[dry] Lead #{$lead->id} ({$name}): {$title}");
                $created++;
                continue;
            }

            $contact = $this->contactResolver->resolve(
                phone: $lead->wa_number,
                name: $name,
                cedula: $lead->cedula ?: null,
                source: 'whatsapp',
            );

            $this->opportunityService->upsertFromEvent(
                contact: $contact,
                title: $title,
                source: 'whatsapp',
                sourceId: $lead->id,
                sourceType: 'whatsapp_lead',
            );

            $created++;
        }

        $this->info("Procesados: {$created}");
        return 0;
    }
}
