<?php

namespace App\Console\Commands;

use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmConsolidateOpportunities extends Command
{
    protected $signature = 'crm:consolidate-opportunities
                            {--dry-run : Solo reporta, no escribe}
                            {--limit=500 : Contactos a procesar}
                            {--force-intent : Permite ejecutar aunque CRM_OPPORTUNITY_MODEL=intent esté activo (PELIGROSO)}';

    protected $description = 'Consolida múltiples oportunidades por contacto en una sola (una por paciente)';

    public function handle(): int
    {
        if (config('crm.intent_model_enabled') && !$this->option('force-intent')) {
            $this->error('ABORTADO: CRM_OPPORTUNITY_MODEL=intent está activo.');
            $this->line('En modo intent, múltiples oportunidades por contacto son episodios clínicos intencionales.');
            $this->line('Ejecutar este comando destruiría episodios válidos.');
            $this->line('Si realmente necesitas consolidar, usa el flag: --force-intent');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit  = (int) $this->option('limit');

        if ($dryRun) {
            $this->warn('Modo dry-run — no se escribirá nada.');
        }

        // Find contacts with more than one opportunity
        $contactIds = DB::table('crm_opportunities')
            ->select('contact_id')
            ->groupBy('contact_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit($limit)
            ->pluck('contact_id');

        $this->info("Contactos con oportunidades duplicadas: {$contactIds->count()}");

        $totalMerged = 0;

        foreach ($contactIds as $contactId) {
            $opps = CrmOpportunity::query()
                ->where('contact_id', $contactId)
                ->orderBy('created_at', 'asc')
                ->get();

            $canonical = $opps->first();
            $extras    = $opps->slice(1);

            if ($dryRun) {
                $this->line("[dry] Contact #{$contactId}: keep opp #{$canonical->id}, merge " . $extras->count() . " into it");
                $totalMerged += $extras->count();
                continue;
            }

            $mergedCount = 0;
            DB::transaction(function () use ($canonical, $extras, &$mergedCount): void {
                foreach ($extras as $extra) {
                    // Move all activities from extra to canonical
                    CrmActivity::query()
                        ->where('opportunity_id', $extra->id)
                        ->update(['opportunity_id' => $canonical->id]);

                    // Create a merge activity on canonical
                    CrmActivity::query()->create([
                        'opportunity_id' => $canonical->id,
                        'type'           => CrmActivity::TYPE_NOTA,
                        'description'    => "Registro consolidado desde opp #{$extra->id} ({$extra->source} #{$extra->source_id})",
                        'user_id'        => null,
                        'created_at'     => now(),
                    ]);

                    // Update source tables to point to canonical
                    if ($extra->source_id) {
                        $this->updateSourceTable($extra->source_type, $extra->source_id, $canonical->id);
                    }

                    $extra->delete();
                    $mergedCount++;
                }

                // Determine best stage from clinical records
                $bestStage = $this->mapStageFromClinical($canonical->contact_id);
                $canonical->stage            = $bestStage;
                $canonical->last_activity_at = $canonical->last_activity_at ?? now();
                $canonical->save();
            });

            $totalMerged += $mergedCount;
        }

        $this->info("Oportunidades consolidadas/eliminadas: {$totalMerged}");
        if (!$dryRun) {
            $this->info('Corre: php artisan migrate (para agregar UNIQUE constraint)');
        }

        return 0;
    }

    private function updateSourceTable(?string $sourceType, int $sourceId, int $canonicalOppId): void
    {
        $table = match ($sourceType) {
            'solicitud_procedimiento' => 'solicitud_procedimiento',
            'consulta_examenes'       => 'consulta_examenes',
            default                   => null,
        };

        if ($table !== null) {
            DB::table($table)->where('id', $sourceId)->update(['crm_opportunity_id' => $canonicalOppId]);
        }
    }

    private function mapStageFromClinical(int $contactId): string
    {
        // Check solicitud states in priority order
        $hasEnProceso = DB::table('solicitud_procedimiento')
            ->join('crm_opportunities', 'crm_opportunities.id', '=', 'solicitud_procedimiento.crm_opportunity_id')
            ->where('crm_opportunities.contact_id', $contactId)
            ->where('solicitud_procedimiento.estado', 'en_proceso')
            ->exists();

        if ($hasEnProceso) {
            return CrmOpportunity::STAGE_EN_EVALUACION;
        }

        $hasAprobada = DB::table('solicitud_procedimiento')
            ->join('crm_opportunities', 'crm_opportunities.id', '=', 'solicitud_procedimiento.crm_opportunity_id')
            ->where('crm_opportunities.contact_id', $contactId)
            ->where('solicitud_procedimiento.estado', 'aprobada')
            ->exists();

        if ($hasAprobada) {
            return CrmOpportunity::STAGE_CONTACTADO;
        }

        // Find most recent clinical record date
        $latestExamen = DB::table('consulta_examenes')
            ->join('crm_opportunities', 'crm_opportunities.id', '=', 'consulta_examenes.crm_opportunity_id')
            ->where('crm_opportunities.contact_id', $contactId)
            ->max('consulta_examenes.consulta_fecha');

        $latestSolicitud = DB::table('solicitud_procedimiento')
            ->join('crm_opportunities', 'crm_opportunities.id', '=', 'solicitud_procedimiento.crm_opportunity_id')
            ->where('crm_opportunities.contact_id', $contactId)
            ->max('solicitud_procedimiento.fecha');

        $latest = collect([$latestExamen, $latestSolicitud])->filter()->max();

        if ($latest === null) {
            return CrmOpportunity::STAGE_NUEVO;
        }

        $daysSince = now()->diffInDays($latest);

        if ($daysSince <= 30) {
            return CrmOpportunity::STAGE_NUEVO;
        }
        if ($daysSince <= 90) {
            return CrmOpportunity::STAGE_CONTACTADO;
        }

        // Older than 90 days — treat as historical (already served)
        return CrmOpportunity::STAGE_GANADO;
    }
}
