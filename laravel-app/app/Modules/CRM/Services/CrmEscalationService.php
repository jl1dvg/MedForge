<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmOpportunity;

class CrmEscalationService
{
    public function __construct(
        private readonly CrmOpportunityService $opportunityService,
    ) {}

    /**
     * Finds all operational opportunities past their escalation_at and promotes them to commercial.
     *
     * @return array{escalated: int, skipped: int}
     */
    public function run(bool $dryRun = false): array
    {
        $pending = CrmOpportunity::query()
            ->pendingEscalation()
            ->with('contact')
            ->get();

        $escalated = 0;
        $skipped   = 0;

        foreach ($pending as $opp) {
            if ($dryRun) {
                $skipped++;
            } else {
                $this->opportunityService->escalateToCommercial($opp);
                $escalated++;
            }
        }

        return ['escalated' => $escalated, 'skipped' => $skipped];
    }
}
