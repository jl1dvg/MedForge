<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Modules\Whatsapp\Services\WhatsappOperationalAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationalAlertsController
{
    private const VALID_SEVERITIES = ['all', 'critical', 'high', 'medium', 'low'];
    private const VALID_CATEGORIES = ['all', 'captacion', 'operacion', 'ambiguo'];
    private const VALID_TYPES = [
        'all',
        WhatsappOperationalAlertService::ALERT_HOT_UNASSIGNED,
        WhatsappOperationalAlertService::ALERT_SUPERVISOR_SLA,
        WhatsappOperationalAlertService::ALERT_RESCUE_AGING,
        WhatsappOperationalAlertService::ALERT_NO_AVAILABILITY,
        WhatsappOperationalAlertService::ALERT_AMBIGUOUS_FAQ,
    ];

    public function __construct(
        private readonly WhatsappOperationalAlertService $alertService = new WhatsappOperationalAlertService()
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $date     = trim((string) $request->query('date', date('Y-m-d')));
        $severity = strtolower(trim((string) $request->query('severity', 'all')));
        $category = strtolower(trim((string) $request->query('category', 'all')));
        $type     = strtolower(trim((string) $request->query('type', 'all')));
        $agent    = trim((string) $request->query('agent', 'all'));
        $limit    = max(1, min(500, (int) $request->query('limit', 500)));
        $summary  = (bool) $request->query('summary', false);

        if (!in_array($severity, self::VALID_SEVERITIES, true)) {
            return response()->json(['ok' => false, 'message' => 'Invalid severity.'], 422);
        }
        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            return response()->json(['ok' => false, 'message' => 'Invalid category.'], 422);
        }
        if (!in_array($type, self::VALID_TYPES, true)) {
            return response()->json(['ok' => false, 'message' => 'Invalid type.'], 422);
        }

        $result = $this->alertService->alerts([
            'date'          => $date,
            'severity'      => $severity,
            'category'      => $category,
            'limit'         => $limit,
            'summary_only'  => $summary,
            'include_items' => true,
        ]);

        // Post-filter by alert type (the service filters by category/severity)
        if ($type !== 'all') {
            $filtered = array_values(array_filter(
                $result['alerts'],
                static fn (array $a): bool => ($a['alert_type'] ?? '') === $type
            ));
            $result['alerts']          = $filtered;
            $result['alerts_total']    = count($filtered);
            $result['alerts_returned'] = count($filtered);
            $result['truncated']       = false;

            $summary2 = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
            $byType   = [];
            foreach ($filtered as $a) {
                $sev = (string) ($a['severity'] ?? 'low');
                $typ = (string) ($a['alert_type'] ?? 'unknown');
                $summary2[$sev] = ($summary2[$sev] ?? 0) + 1;
                $byType[$typ]   = ($byType[$typ] ?? 0) + 1;
            }
            $result['summary'] = $summary2;
            $result['by_type'] = $byType;
        }

        // Post-filter by agent
        if ($agent !== 'all') {
            $filtered = array_values(array_filter(
                $result['alerts'],
                static function (array $a) use ($agent): bool {
                    if ($agent === 'unassigned') {
                        return ($a['assigned_user_id'] ?? null) === null;
                    }
                    return (string) ($a['assigned_user_id'] ?? '') === $agent;
                }
            ));
            $result['alerts']          = $filtered;
            $result['alerts_total']    = count($filtered);
            $result['alerts_returned'] = count($filtered);
            $result['truncated']       = false;

            $summary2 = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
            $byType   = [];
            foreach ($filtered as $a) {
                $sev = (string) ($a['severity'] ?? 'low');
                $typ = (string) ($a['alert_type'] ?? 'unknown');
                $summary2[$sev] = ($summary2[$sev] ?? 0) + 1;
                $byType[$typ]   = ($byType[$typ] ?? 0) + 1;
            }
            $result['summary'] = $summary2;
            $result['by_type'] = $byType;
        }

        $result['filters_applied'] = compact('date', 'severity', 'category', 'type', 'agent', 'limit', 'summary');

        return response()->json($result);
    }
}
