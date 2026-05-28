<?php

namespace App\Modules\CRM\Services;

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
     * Crea una oportunidad vinculada a un contacto con entrada inteligente por fuente.
     */
    public function createFromEvent(
        CrmContact $contact,
        string $title,
        string $source,
        ?int $sourceId = null,
        ?string $sourceType = null,
        ?int $assignedTo = null,
    ): CrmOpportunity {
        $stage = CrmOpportunity::SOURCE_ENTRY_STAGE[$source] ?? CrmOpportunity::STAGE_NUEVO;

        return DB::transaction(function () use ($contact, $title, $source, $sourceId, $sourceType, $assignedTo, $stage): CrmOpportunity {
            $opportunity = CrmOpportunity::query()->create([
                'contact_id'  => $contact->id,
                'title'       => $title,
                'stage'       => $stage,
                'source'      => $source,
                'source_id'   => $sourceId,
                'source_type' => $sourceType,
                'assigned_to' => $assignedTo,
            ]);

            $this->activityService->logSystemEvent(
                $opportunity->id,
                "Oportunidad creada automáticamente desde {$source}" . ($sourceId ? " #{$sourceId}" : ''),
            );

            return $opportunity;
        });
    }

    /**
     * Avanza la etapa de una oportunidad y registra la actividad.
     */
    public function changeStage(CrmOpportunity $opportunity, string $newStage, ?int $userId = null, ?string $lostReason = null): CrmOpportunity
    {
        if (!in_array($newStage, CrmOpportunity::STAGES, true)) {
            throw new RuntimeException("Etapa inválida: {$newStage}");
        }

        $fromStage = $opportunity->stage;

        DB::transaction(function () use ($opportunity, $newStage, $lostReason, $userId, $fromStage): void {
            $opportunity->stage = $newStage;
            if ($newStage === CrmOpportunity::STAGE_PERDIDO && $lostReason !== null) {
                $opportunity->lost_reason = $lostReason;
            }
            $opportunity->save();

            $this->activityService->logStageChange($opportunity->id, $fromStage, $newStage, $userId);
        });

        return $opportunity->fresh();
    }

    /**
     * Asigna la oportunidad a un agente comercial.
     */
    public function assign(CrmOpportunity $opportunity, int $userId): CrmOpportunity
    {
        $opportunity->assigned_to = $userId;
        $opportunity->save();
        return $opportunity;
    }
}
