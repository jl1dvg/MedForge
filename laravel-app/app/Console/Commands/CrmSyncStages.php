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
                            {--limit=5000 : Oportunidades a procesar}';

    protected $description = 'Sincroniza el stage del CRM con el kanban más avanzado de solicitudes quirúrgicas';

    /**
     * Estado normalizado → CRM stage.
     */
    private const ESTADO_TO_CRM = [
        'recibida'          => CrmOpportunity::STAGE_NUEVO,
        'recibido'          => CrmOpportunity::STAGE_NUEVO,
        'atrasada'          => CrmOpportunity::STAGE_NUEVO,
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

    /** Higher = more advanced. Cancelled/archived = -1 (don't use). */
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
        'cancelado' => -1, 'cancelada' => -1,
        'archivado' => -1, 'archivada' => -1,
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

        // ── Step 1: get all active opps with linked cedulas ─────────────────
        $opps = DB::table('crm_opportunities as opp')
            ->join('crm_contacts as cc', function ($join): void {
                $join->on('cc.id', '=', 'opp.contact_id')
                     ->whereNotNull('cc.cedula')
                     ->where('cc.cedula', '!=', '');
            })
            ->whereNotIn('opp.stage', [CrmOpportunity::STAGE_GANADO, CrmOpportunity::STAGE_PERDIDO])
            ->select('opp.id as opp_id', 'opp.stage as current_stage', 'cc.cedula')
            ->limit($limit)
            ->get();

        if ($opps->isEmpty()) {
            $this->info('Oportunidades con solicitud linkeable: 0');
            return 0;
        }

        $this->info("Oportunidades candidatas: {$opps->count()}");

        // ── Step 2: batch-fetch ALL solicitudes for those cedulas ────────────
        $cedulas = $opps->pluck('cedula')->unique()->values()->all();

        $allEstados = DB::table('solicitud_procedimiento')
            ->whereIn('hc_number', $cedulas)
            ->select('hc_number', 'estado')
            ->get()
            ->groupBy('hc_number'); // Collection<cedula, Collection<{hc_number,estado}>>

        $this->info("Solicitudes cargadas para {$allEstados->count()} cédulas.");

        // ── Step 3: for each opp, find the most advanced target stage ────────
        $stageOrder = array_flip(CrmOpportunity::STAGES);
        $updated    = 0;
        $skipped    = 0;
        $unknown    = [];

        foreach ($opps as $row) {
            $solicitudes = $allEstados->get($row->cedula);

            if ($solicitudes === null || $solicitudes->isEmpty()) {
                $skipped++;
                continue;
            }

            $bestStage  = null;
            $bestRank   = -2;
            $bestEstado = '';

            foreach ($solicitudes as $sp) {
                $norm = $this->normalizeSlug((string) $sp->estado);
                $rank = self::ESTADO_RANK[$norm] ?? null;

                if ($rank === null) {
                    $unknown[$norm] = ($unknown[$norm] ?? 0) + 1;
                    continue;
                }

                $crmStage = self::ESTADO_TO_CRM[$norm] ?? null;
                if ($crmStage === null || $rank <= $bestRank) {
                    continue;
                }

                $bestRank   = $rank;
                $bestStage  = $crmStage;
                $bestEstado = (string) $sp->estado;
            }

            if ($bestStage === null) {
                $skipped++;
                continue;
            }

            $currentOrder = $stageOrder[$row->current_stage] ?? 0;
            $newOrder     = $stageOrder[$bestStage] ?? 0;

            if ($newOrder <= $currentOrder) {
                $skipped++;
                continue;
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

        $this->info("Actualizadas: {$updated} | Saltadas (ya al día o sin mapeo): {$skipped}");

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
