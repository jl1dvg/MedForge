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
    ): CrmActivity {
        return CrmActivity::query()->create([
            'opportunity_id' => $opportunityId,
            'type'           => $type,
            'description'    => $description,
            'user_id'        => $userId,
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
            userId: null,
        );
    }
}
