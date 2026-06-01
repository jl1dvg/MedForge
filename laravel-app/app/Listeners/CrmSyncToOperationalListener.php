<?php

namespace App\Listeners;

use App\Events\Crm\CrmStageChanged;
use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Models\CrmStageMapping;
use Illuminate\Support\Facades\DB;

/**
 * Pushes a CRM stage change back to the linked operational sources
 * (solicitud_procedimiento or consulta_examenes).
 *
 * Rule — "kanban dirige": if the operational record is already at a stage
 * whose CRM equivalent is >= the new CRM stage, the kanban wins and the
 * update is skipped. A 'conflicto_sync' activity is logged instead so the
 * coordinator can review.
 *
 * This listener writes directly to the `estado` column (no checklist logic,
 * no event re-dispatch) to avoid feedback loops with CrmOpportunityListener.
 */
class CrmSyncToOperationalListener
{
    public function handleCrmStageChanged(CrmStageChanged $event): void
    {
        $opp = $event->opportunity;

        // Collect all source records linked to this opportunity via activities
        $linkedSources = CrmActivity::query()
            ->where('opportunity_id', $opp->id)
            ->whereNotNull('source_type')
            ->whereNotNull('source_id')
            ->whereIn('source_type', ['solicitud_procedimiento', 'consulta_examenes'])
            ->select('source_type', 'source_id')
            ->distinct()
            ->get();

        // Also include the opportunity's own source if clinical
        if (
            $opp->source_id !== null
            && in_array($opp->source_type, ['solicitud_procedimiento', 'consulta_examenes'], true)
        ) {
            $linked = $linkedSources->firstWhere(fn ($r) => $r->source_type === $opp->source_type && (int) $r->source_id === $opp->source_id);
            if ($linked === null) {
                $linkedSources->push((object) ['source_type' => $opp->source_type, 'source_id' => $opp->source_id]);
            }
        }

        if ($linkedSources->isEmpty()) {
            return;
        }

        $stageOrder = array_flip(CrmOpportunity::STAGES);
        $newOrder   = $stageOrder[$event->toStage] ?? 0;

        foreach ($linkedSources as $link) {
            $sourceType = (string) $link->source_type;
            $sourceId   = (int) $link->source_id;

            $this->syncOneSource($opp, $sourceType, $sourceId, $event->toStage, $newOrder, $stageOrder, $event->userId);
        }
    }

    private function syncOneSource(
        CrmOpportunity $opp,
        string $sourceType,
        int $sourceId,
        string $newCrmStage,
        int $newCrmOrder,
        array $stageOrder,
        ?int $userId,
    ): void {
        $reverseMap = CrmStageMapping::reverseForSourceType($sourceType);
        $targetSourceState = $reverseMap[$newCrmStage] ?? null;

        if ($targetSourceState === null) {
            // No reverse mapping configured for this CRM stage → nothing to push
            return;
        }

        $table     = $sourceType === 'solicitud_procedimiento' ? 'solicitud_procedimiento' : 'consulta_examenes';
        $stateCol  = $sourceType === 'solicitud_procedimiento' ? 'estado' : 'estado';

        $currentState = DB::table($table)->where('id', $sourceId)->value($stateCol);
        if ($currentState === null) {
            return;
        }

        $currentState = (string) $currentState;

        // Determine the CRM equivalent of the current kanban state
        $forwardMap   = CrmStageMapping::forSourceType($sourceType);
        $currentCrmStage = $forwardMap[$currentState] ?? null;
        $currentCrmOrder = $currentCrmStage !== null ? ($stageOrder[$currentCrmStage] ?? 0) : -1;

        // "Kanban dirige": if operational record is already at an equivalent or
        // higher CRM stage, respect it — log conflict and skip.
        if ($currentCrmOrder >= $newCrmOrder) {
            $this->logConflict(
                $opp->id,
                $sourceType,
                $sourceId,
                $newCrmStage,
                $currentState,
                $userId,
            );
            return;
        }

        // Push the target state to the operational record (no event, no loop)
        DB::table($table)->where('id', $sourceId)->update([$stateCol => $targetSourceState]);

        // Log the sync in solicitud_estado_log if that table exists (best-effort)
        if ($sourceType === 'solicitud_procedimiento') {
            try {
                DB::table('solicitud_estado_log')->insert([
                    'solicitud_id'    => $sourceId,
                    'estado_anterior' => $currentState,
                    'estado_nuevo'    => $targetSourceState,
                    'user_id'         => $userId,
                    'nota'            => "Sincronizado desde CRM ({$newCrmStage})",
                    'origen'          => 'crm_sync',
                    'created_at'      => now()->toDateTimeString(),
                    'updated_at'      => now()->toDateTimeString(),
                ]);
            } catch (\Throwable) {
                // Never block sync if log table has schema differences
            }
        }
    }

    private function logConflict(
        int $opportunityId,
        string $sourceType,
        int $sourceId,
        string $newCrmStage,
        string $currentSourceState,
        ?int $userId,
    ): void {
        try {
            DB::table('crm_activities')->insert([
                'opportunity_id' => $opportunityId,
                'type'           => 'conflicto_sync',
                'description'    => "Sync CRM→{$sourceType} bloqueado: kanban ya está en '{$currentSourceState}' "
                    . "(equivalente o superior a '{$newCrmStage}'). El coordinador debe revisar.",
                'user_id'        => $userId,
                'source_id'      => $sourceId,
                'source_type'    => $sourceType,
                'created_at'     => now()->toDateTimeString(),
            ]);
        } catch (\Throwable) {
            // Best-effort logging
        }
    }
}
