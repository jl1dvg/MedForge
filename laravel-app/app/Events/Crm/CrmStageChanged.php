<?php

namespace App\Events\Crm;

use App\Models\CrmOpportunity;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a CRM opportunity moves to a new stage.
 * Consumed by CrmSyncToOperationalListener to push the change back
 * to the linked operational source (solicitud / examen).
 */
class CrmStageChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CrmOpportunity $opportunity,
        public readonly string         $fromStage,
        public readonly string         $toStage,
        public readonly ?int           $userId = null,
    ) {}
}
