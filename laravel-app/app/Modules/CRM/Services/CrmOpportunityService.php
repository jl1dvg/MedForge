<?php

namespace App\Modules\CRM\Services;

use App\Events\Crm\CrmStageChanged;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CrmOpportunityService
{
    public function __construct(
        private readonly CrmActivityService $activityService,
    ) {}

    /**
     * Source → initial stage mapping (only for new opportunities).
     */
    private const SOURCE_ENTRY_STAGE = [
        'whatsapp'  => CrmOpportunity::STAGE_NUEVO,
        'solicitud' => CrmOpportunity::STAGE_NUEVO,
        'examen'    => CrmOpportunity::STAGE_NUEVO,
        'manual'    => CrmOpportunity::STAGE_NUEVO,
    ];

    /**
     * Clinical source → activity type mapping.
     */
    private const SOURCE_ACTIVITY_TYPE = [
        'whatsapp'  => CrmActivity::TYPE_WHATSAPP,
        'solicitud' => CrmActivity::TYPE_SOLICITUD,
        'examen'    => CrmActivity::TYPE_EXAMEN,
        'manual'    => CrmActivity::TYPE_NOTA,
    ];

    /**
     * Creates opportunity if contact has none; otherwise adds a clinical activity.
     * This is the main entry point from the listener.
     */
    public function upsertFromEvent(
        CrmContact $contact,
        string $title,
        string $source,
        ?int $sourceId = null,
        ?string $sourceType = null,
        ?int $assignedTo = null,
    ): CrmOpportunity {
        return DB::transaction(function () use ($contact, $title, $source, $sourceId, $sourceType, $assignedTo): CrmOpportunity {
            $existing = CrmOpportunity::query()->where('contact_id', $contact->id)->first();

            if ($existing instanceof CrmOpportunity) {
                // Contact already has an opportunity — add activity and update last_activity_at
                $activityType = self::SOURCE_ACTIVITY_TYPE[$source] ?? CrmActivity::TYPE_NOTA;
                if ($sourceId !== null) {
                    $this->activityService->logClinical(
                        opportunityId: $existing->id,
                        type: $activityType,
                        description: $title,
                        sourceId: $sourceId,
                        sourceType: $sourceType ?? '',
                    );
                } else {
                    $this->activityService->logSystemEvent($existing->id, $title);
                }
                $this->touchLastActivity($existing);
                return $existing;
            }

            // No opportunity yet — create it
            $stage = self::SOURCE_ENTRY_STAGE[$source] ?? CrmOpportunity::STAGE_NUEVO;
            $escalationAt = $this->computeEscalationAt($stage);

            $opp = CrmOpportunity::query()->create([
                'contact_id'       => $contact->id,
                'title'            => $title,
                'stage'            => $stage,
                'phase'            => CrmOpportunity::PHASE_OPERATIONAL,
                'source'           => $source,
                'source_id'        => $sourceId,
                'source_type'      => $sourceType,
                'assigned_to'      => $assignedTo,
                'last_activity_at' => now(),
                'escalation_at'    => $escalationAt,
            ]);

            $activityType = self::SOURCE_ACTIVITY_TYPE[$source] ?? CrmActivity::TYPE_NOTA;
            if ($sourceId !== null) {
                $this->activityService->logClinical(
                    opportunityId: $opp->id,
                    type: $activityType,
                    description: $title,
                    sourceId: $sourceId,
                    sourceType: $sourceType ?? '',
                );
            } else {
                $this->activityService->logSystemEvent($opp->id, "Oportunidad creada desde {$source}");
            }

            return $opp;
        });
    }

    /**
     * Changes stage, handles phase transition, and recalculates escalation_at.
     */
    public function changeStage(
        CrmOpportunity $opportunity,
        string $newStage,
        ?int $userId = null,
        ?string $lostReason = null,
    ): CrmOpportunity {
        if (!in_array($newStage, CrmOpportunity::STAGES, true)) {
            throw new RuntimeException("Etapa inválida: {$newStage}");
        }

        $fromStage = $opportunity->stage;

        DB::transaction(function () use ($opportunity, $newStage, $lostReason, $userId, $fromStage): void {
            $opportunity->stage = $newStage;

            if ($newStage === CrmOpportunity::STAGE_PERDIDO && $lostReason !== null) {
                $opportunity->lost_reason = $lostReason;
            }

            // Auto-transition to commercial phase when reaching propuesta or beyond
            if (in_array($newStage, CrmOpportunity::COMMERCIAL_STAGES, true)) {
                $opportunity->phase        = CrmOpportunity::PHASE_COMMERCIAL;
                $opportunity->escalation_at = null; // No more auto-escalation needed
            } else {
                $opportunity->escalation_at = $this->computeEscalationAt($newStage);
            }

            $opportunity->last_activity_at = now();
            $opportunity->save();

            $this->activityService->logStageChange($opportunity->id, $fromStage, $newStage, $userId);
        });

        $fresh = $opportunity->fresh();

        CrmStageChanged::dispatch($fresh, $fromStage, $newStage, $userId);

        return $fresh;
    }

    /**
     * Assigns the opportunity to an agent and updates last_activity_at.
     */
    public function assign(CrmOpportunity $opportunity, int $userId): CrmOpportunity
    {
        $opportunity->assigned_to      = $userId;
        $opportunity->last_activity_at = now();
        $opportunity->save();
        return $opportunity;
    }

    /**
     * Escalates to commercial phase (called by CrmEscalationService).
     */
    public function escalateToCommercial(CrmOpportunity $opportunity): void
    {
        DB::transaction(function () use ($opportunity): void {
            $opportunity->phase        = CrmOpportunity::PHASE_COMMERCIAL;
            $opportunity->escalation_at = null;
            $opportunity->save();

            $daysSince = (int) ($opportunity->last_activity_at?->diffInDays(now()) ?? 0);
            $this->activityService->logSystemEvent(
                $opportunity->id,
                "Escalado automáticamente a Comercial — sin actividad por {$daysSince} días",
            );
        });
    }

    private function touchLastActivity(CrmOpportunity $opportunity): void
    {
        $opportunity->last_activity_at = now();
        // Only refresh escalation_at for operational opportunities — commercial phase has no escalation timer
        if ($opportunity->phase === CrmOpportunity::PHASE_OPERATIONAL) {
            $opportunity->escalation_at = $this->computeEscalationAt($opportunity->stage);
        }
        $opportunity->save();
    }

    private function computeEscalationAt(string $stage): ?\Carbon\Carbon
    {
        $days = match ($stage) {
            CrmOpportunity::STAGE_CONTACTADO    => (int) config('crm.escalacion.dias_contactado', 7),
            CrmOpportunity::STAGE_EN_EVALUACION => (int) config('crm.escalacion.dias_en_evaluacion', 14),
            default                              => null,
        };

        return $days !== null ? now()->addDays($days) : null;
    }
}
