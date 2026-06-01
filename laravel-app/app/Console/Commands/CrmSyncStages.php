<?php

namespace App\Console\Commands;

use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmSyncStages extends Command
{
    protected $signature = 'crm:sync-stages
                            {--dry-run : Solo reporta, no escribe}
                            {--limit=500 : Oportunidades a procesar por lote}';

    protected $description = 'Sincroniza el stage del CRM con el kanban actual de solicitudes quirúrgicas';

    /**
     * Solicitud kanban slug → CRM stage (same mapping as the listener).
     */
    private const KANBAN_TO_CRM = [
        'recibida'          => CrmOpportunity::STAGE_NUEVO,
        'llamado'           => CrmOpportunity::STAGE_CONTACTADO,
        'en-atencion'       => CrmOpportunity::STAGE_EN_EVALUACION,
        'revision-codigos'  => CrmOpportunity::STAGE_EN_EVALUACION,
        'espera-documentos' => CrmOpportunity::STAGE_EN_EVALUACION,
        'apto-oftalmologo'  => CrmOpportunity::STAGE_EN_EVALUACION,
        'apto-anestesia'    => CrmOpportunity::STAGE_EN_EVALUACION,
        'listo-para-agenda' => CrmOpportunity::STAGE_EN_EVALUACION,
        'programada'        => CrmOpportunity::STAGE_COMPROMETIDO,
        'completado'        => CrmOpportunity::STAGE_GANADO,
    ];

    public function __construct(
        private readonly CrmOpportunityService $opportunityService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = (int) $this->option('limit');

        if ($dryRun) {
            $this->warn('Modo dry-run — no se escribirá nada.');
        }

        // Find opportunities that have solicitud activities, along with the solicitud's current estado
        $rows = DB::table('crm_opportunities as opp')
            ->join('crm_activities as act', function ($join): void {
                $join->on('act.opportunity_id', '=', 'opp.id')
                     ->where('act.source_type', '=', 'solicitud_procedimiento')
                     ->where('act.type', '=', CrmActivity::TYPE_SOLICITUD);
            })
            ->join('solicitud_procedimiento as sp', 'sp.id', '=', 'act.source_id')
            ->whereNotIn('opp.stage', [CrmOpportunity::STAGE_GANADO, CrmOpportunity::STAGE_PERDIDO])
            ->select('opp.id as opp_id', 'opp.stage as current_stage', 'sp.estado as sp_estado', 'sp.id as sp_id')
            ->limit($limit)
            ->get();

        $this->info("Oportunidades con solicitudes vinculadas: {$rows->count()}");

        $updated  = 0;
        $skipped  = 0;
        $stageOrder = array_flip(CrmOpportunity::STAGES);

        foreach ($rows as $row) {
            $kanbanSlug = $this->normalizeKanbanSlug((string) $row->sp_estado);
            $targetStage = self::KANBAN_TO_CRM[$kanbanSlug] ?? null;

            if ($targetStage === null) {
                $skipped++;
                continue;
            }

            $currentOrder = $stageOrder[$row->current_stage] ?? 0;
            $newOrder     = $stageOrder[$targetStage] ?? 0;

            if ($newOrder <= $currentOrder) {
                $skipped++;
                continue; // Don't downgrade
            }

            if ($dryRun) {
                $this->line("[dry] Opp #{$row->opp_id}: {$row->current_stage} → {$targetStage} (sp#{$row->sp_id} kanban:{$kanbanSlug})");
            } else {
                $opp = CrmOpportunity::query()->find($row->opp_id);
                if ($opp instanceof CrmOpportunity) {
                    $this->opportunityService->changeStage($opp, $targetStage);
                }
            }

            $updated++;
        }

        $this->info("Actualizadas: {$updated} | Saltadas (ya al día o sin mapeo): {$skipped}");

        return 0;
    }

    private function normalizeKanbanSlug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', '_' => '-']);
        $value = preg_replace('/[^a-z0-9-]/', '-', $value) ?? $value;
        $value = trim($value, '-');

        $aliases = [
            'recibido'          => 'recibida',
            'en-atenci-n'       => 'en-atencion',
            'revision-de-codigos' => 'revision-codigos',
            'apto-oftalm-logo'  => 'apto-oftalmologo',
            'completa'          => 'completado',
            'cerrado'           => 'completado',
            'cerrada'           => 'completado',
            'programado'        => 'programada',
            'facturado'         => 'programada',
        ];

        return $aliases[$value] ?? $value;
    }
}
