<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmActivity;

class CrmActivityService
{
    public function log(
        int $opportunityId,
        string $type,
        string $description,
        ?int $userId = null,
        ?int $sourceId = null,
        ?string $sourceType = null,
    ): CrmActivity {
        return CrmActivity::query()->create([
            'opportunity_id' => $opportunityId,
            'type'           => $type,
            'description'    => $description,
            'user_id'        => $userId,
            'source_id'      => $sourceId,
            'source_type'    => $sourceType,
        ]);
    }

    public function logStageChange(int $opportunityId, string $fromStage, string $toStage, ?int $userId = null): CrmActivity
    {
        return $this->log(
            opportunityId: $opportunityId,
            type: CrmActivity::TYPE_CAMBIO_ETAPA,
            description: "Etapa cambiada de '{$fromStage}' a '{$toStage}'",
            userId: $userId,
        );
    }

    public function logSystemEvent(int $opportunityId, string $description): CrmActivity
    {
        return $this->log(
            opportunityId: $opportunityId,
            type: CrmActivity::TYPE_NOTA,
            description: $description,
        );
    }

    public function logClinical(
        int $opportunityId,
        string $type,
        string $description,
        int $sourceId,
        string $sourceType,
        ?int $userId = null,
    ): CrmActivity {
        return $this->log(
            opportunityId: $opportunityId,
            type: $type,
            description: $description,
            userId: $userId,
            sourceId: $sourceId,
            sourceType: $sourceType,
        );
    }
}
