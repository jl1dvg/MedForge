<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmOpportunity;
use Illuminate\Support\Facades\DB;

class CrmStatsService
{
    public function panelStats(): array
    {
        $waHours      = (int) config('crm.urgency_threshold_hours.whatsapp', 6);
        $defaultHours = (int) config('crm.urgency_threshold_hours.default', 48);

        $active = CrmOpportunity::query()->active()->count();

        $urgent = CrmOpportunity::query()->urgent($waHours, $defaultHours)->count();

        $wonThisMonth = CrmOpportunity::query()
            ->where('stage', CrmOpportunity::STAGE_GANADO)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->count();

        $avgResponseHours = DB::table('crm_activities')
            ->join('crm_opportunities', 'crm_activities.opportunity_id', '=', 'crm_opportunities.id')
            ->where('crm_activities.type', 'cambio_etapa')
            ->whereRaw("crm_activities.description LIKE '%nuevo%en_contacto%'")
            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, crm_opportunities.created_at, crm_activities.created_at)'));

        $total = CrmOpportunity::query()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $won = CrmOpportunity::query()
            ->where('stage', CrmOpportunity::STAGE_GANADO)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $conversionRate = $total > 0 ? round(($won / $total) * 100) : 0;

        return [
            'urgent'           => $urgent,
            'active'           => $active,
            'won_this_month'   => $wonThisMonth,
            'avg_response_h'   => round((float) ($avgResponseHours ?? 0), 1),
            'conversion_rate'  => $conversionRate,
        ];
    }

    /** @return array<string, int> */
    public function byStage(): array
    {
        return CrmOpportunity::query()
            ->active()
            ->selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->toArray();
    }
}
