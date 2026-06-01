<?php

namespace App\Console\Commands;

use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmSyncStages extends Command
{
    protected $signature = 'crm:sync-stages
                            {--dry-run : Solo reporta, no escribe}
                            {--limit=500 : Oportunidades a procesar por lote}';

    protected $description = 'Sincroniza el stage del CRM con el kanban más avanzado de solicitudes quirúrgicas';

    /**
     * Estado (lowercase/normalized) → CRM stage.
     * Includes legacy estados from pre-kanban era.
     */
    private const ESTADO_TO_CRM = [
        // Active kanban stages
        'recibida'          => CrmOpportunity::STAGE_NUEVO,
        'recibido'          => CrmOpportunity::STAGE_NUEVO,
        'atrasada'          => CrmOpportunity::STAGE_NUEVO,   // overdue but not yet contacted
        'atrasado'          => CrmOpportunity::STAGE_NUEVO,
        'llamado'           => CrmOpportunity::STAGE_CONTACTADO,
        'en-atencion'       => CrmOpportunity::STAGE_EN_EVALUACION,
        'en atencion'       => CrmOpportunity::STAGE_EN_EVALUACION,
        'en atención'       => CrmOpportunity::STAGE_EN_EVALUACION,
        'revision-codigos'  => CrmOpportunity::STAGE_EN_EVALUACION,
        'espera-documentos' => CrmOpportunity::STAGE_EN_EVALUACION,
        'apto-oftalmologo'  => CrmOpportunity::STAGE_EN_EVALUACION,
        'apto-anestesia'    => CrmOpportunity::STAGE_EN_EVALUACION,
        'listo-para-agenda' => CrmOpportunity::STAGE_EN_EVALUACION,
        'programada'        => CrmOpportunity::STAGE_COMPROMETIDO,
        'programado'        => CrmOpportunity::STAGE_COMPROMETIDO,
        // Terminal stages
        'completado'        => CrmOpportunity::STAGE_GANADO,
        'completada'        => CrmOpportunity::STAGE_GANADO,
        'completa'          => CrmOpportunity::STAGE_GANADO,
        'cerrado'           => CrmOpportunity::STAGE_GANADO,
        'cerrada'           => CrmOpportunity::STAGE_GANADO,
        'facturado'         => CrmOpportunity::STAGE_GANADO,
        'facturada'         => CrmOpportunity::STAGE_GANADO,
        'archivado'         => CrmOpportunity::STAGE_PERDIDO,
        'archivada'         => CrmOpportunity::STAGE_PERDIDO,
        'cancelado'         => CrmOpportunity::STAGE_PERDIDO,
        'cancelada'         => CrmOpportunity::STAGE_PERDIDO,
    ];

    /**
     * Numeric rank for each kanban estado — used to pick the most advanced solicitud.
     * Higher = more advanced in the pipeline.
     */
    private const ESTADO_RANK = [
        'atrasada' => 0, 'atrasado' => 0,
        'recibida' => 1, 'recibido' => 1,
        'llamado' => 2,
        'en-atencion' => 3, 'en atencion' => 3, 'en atención' => 3,
        'revision-codigos' => 4,
        'espera-documentos' => 5,
        'apto-oftalmologo' => 6,
        'apto-anestesia' => 7,
        'listo-para-agenda' => 8,
        'programada' => 9, 'programado' => 9,
        'completado' => 10, 'completada' => 10, 'completa' => 10,
        'cerrado' => 10, 'cerrada' => 10, 'facturado' => 10, 'facturada' => 10,
        'cancelado' => -1, 'cancelada' => -1, 'archivado' => -1, 'archivada' => -1,
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

        // Get all active opportunities with at least one linked solicitud (via cedula = hc_number)
        $opps = DB::table('crm_opportunities as opp')
            ->join('crm_contacts as cc', function ($join): void {
                $join->on('cc.id', '=', 'opp.contact_id')
                     ->whereNotNull('cc.cedula')
                     ->where('cc.cedula', '!=', '');
            })
            ->whereNotIn('opp.stage', [CrmOpportunity::STAGE_GANADO, CrmOpportunity::STAGE_PERDIDO])
            ->whereExists(function ($sub): void {
                $sub->from('solicitud_procedimiento as sp')
                    ->whereColumn('sp.hc_number', 'cc.cedula');
            })
            ->select('opp.id as opp_id', 'opp.stage as current_stage', 'cc.cedula')
            ->limit($limit)
            ->get();

        $this->info("Oportunidades con solicitud linkeable: {$opps->count()}");

        $updated  = 0;
        $skipped  = 0;
        $unknown  = [];
        $stageOrder = array_flip(CrmOpportunity::STAGES);

        foreach ($opps as $row) {
            // Get all solicitudes for this patient and pick the most advanced
            $solicitudes = DB::table('solicitud_procedimiento')
                ->where('hc_number', $row->cedula)
                ->pluck('estado');

            $bestStage    = null;
            $bestRank     = -2;
            $bestEstado   = '';

            foreach ($solicitudes as $estado) {
                $normalized = $this->normalizeSlug((string) $estado);
                $rank       = self::ESTADO_RANK[$normalized] ?? null;

                if ($rank === null) {
                    $unknown[$normalized] = ($unknown[$normalized] ?? 0) + 1;
                    continue;
                }

                $crmStage = self::ESTADO_TO_CRM[$normalized] ?? null;
                if ($crmStage === null) {
                    continue;
                }

                if ($rank > $bestRank) {
                    $bestRank   = $rank;
                    $bestStage  = $crmStage;
                    $bestEstado = $estado;
                }
            }

            if ($bestStage === null) {
                $skipped++;
                continue;
            }

            $currentOrder = $stageOrder[$row->current_stage] ?? 0;
            $newOrder     = $stageOrder[$bestStage] ?? 0;

            if ($newOrder <= $currentOrder) {
                $skipped++;
                continue; // Don't downgrade
            }

            if ($dryRun) {
                $this->line("[dry] Opp #{$row->opp_id}: {$row->current_stage} → {$bestStage} (mejor estado: '{$bestEstado}')");
            } else {
                $opp = CrmOpportunity::query()->find($row->opp_id);
                if ($opp instanceof CrmOpportunity) {
                    $this->opportunityService->changeStage($opp, $bestStage);
                }
            }

            $updated++;
        }

        $this->info("Actualizadas: {$updated} | Saltadas: {$skipped}");

        if (!empty($unknown)) {
            $this->warn('Estados no reconocidos (considera agregar al mapa):');
            foreach ($unknown as $estado => $count) {
                $this->line("  '{$estado}': {$count} veces");
            }
        }

        return 0;
    }

    private function normalizeSlug(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', '_' => '-',
        ]);
    }
}
