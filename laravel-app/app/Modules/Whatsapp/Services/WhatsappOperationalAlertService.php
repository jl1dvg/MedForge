<?php

namespace App\Modules\Whatsapp\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappOperationalAlertService
{
    public const ALERT_HOT_UNASSIGNED   = 'hot_unassigned';
    public const ALERT_SUPERVISOR_SLA   = 'supervisor_sla_breach';
    public const ALERT_RESCUE_AGING     = 'rescue_aging';
    public const ALERT_NO_AVAILABILITY  = 'no_availability_repeated';
    public const ALERT_AMBIGUOUS_FAQ    = 'ambiguous_urgent_faq';

    private const SEVERITY_ORDER = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    public function __construct(
        private readonly WhatsappOperationalDecisionService $decisionService = new WhatsappOperationalDecisionService(),
    ) {
    }

    // Maximum alerts returned in alerts[] by default (avoids noisy payloads)
    private const DEFAULT_ALERTS_PAGE = 50;

    /**
     * @param array{date?:string,category?:string,severity?:string,limit?:int,include_items?:bool,summary_only?:bool} $filters
     * @return array<string,mixed>
     */
    public function alerts(array $filters = []): array
    {
        $date        = (string) ($filters['date'] ?? CarbonImmutable::now()->toDateString());
        $category    = (string) ($filters['category'] ?? 'all');
        $severity    = (string) ($filters['severity'] ?? 'all');
        $limit       = max(1, min(500, (int) ($filters['limit'] ?? 200)));
        $summaryOnly = (bool) ($filters['summary_only'] ?? false);
        $includeAll  = (bool) ($filters['include_items'] ?? false);

        $asOf      = CarbonImmutable::parse($date)->endOfDay();
        $evaluated = $this->decisionService->evaluate($asOf);
        $decisions = $evaluated['decisions'] ?? [];

        $agentNames = $this->loadAgentNames($decisions);
        $repeatMap  = $this->buildRepeatMap();

        $allAlerts = [];
        foreach ($decisions as $decision) {
            $alert = $this->buildAlert($decision, $agentNames, $repeatMap);
            if ($alert === null) {
                continue;
            }

            if ($category !== 'all' && ($decision['category'] ?? '') !== $category) {
                continue;
            }

            if ($severity !== 'all' && $alert['severity'] !== $severity) {
                continue;
            }

            $allAlerts[] = $alert;

            if (count($allAlerts) >= $limit) {
                break;
            }
        }

        usort($allAlerts, static fn (array $a, array $b): int =>
            (self::SEVERITY_ORDER[$a['severity']] ?? 9) <=> (self::SEVERITY_ORDER[$b['severity']] ?? 9)
        );

        $summary = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        $byType  = [];
        foreach ($allAlerts as $a) {
            $sev = (string) ($a['severity'] ?? 'low');
            $typ = (string) ($a['alert_type'] ?? 'unknown');
            $summary[$sev] = ($summary[$sev] ?? 0) + 1;
            $byType[$typ]  = ($byType[$typ] ?? 0) + 1;
        }

        $alertsTotal    = count($allAlerts);
        $pageSize       = $includeAll ? $alertsTotal : self::DEFAULT_ALERTS_PAGE;
        $alertsReturned = $summaryOnly ? [] : array_slice($allAlerts, 0, $pageSize);
        $truncated      = !$summaryOnly && $alertsTotal > $pageSize;

        return [
            'ok'               => true,
            'mode'             => 'read_only',
            'read_only'        => true,
            'db_writes'        => 0,
            'date'             => $date,
            'category'         => $category,
            'severity'         => $severity,
            'evaluated'        => count($decisions),
            'alerts_total'     => $alertsTotal,
            'alerts_returned'  => count($alertsReturned),
            'truncated'        => $truncated,
            'summary'          => $summary,
            'by_type'          => $byType,
            'alerts'           => $alertsReturned,
        ];
    }

    /**
     * @param array<string,mixed> $decision
     * @param array<int,string> $agentNames
     * @param array<int,int> $repeatMap
     * @return array<string,mixed>|null
     */
    private function buildAlert(array $decision, array $agentNames, array $repeatMap): ?array
    {
        $action          = (string) ($decision['recommended_action'] ?? '');
        $bucket          = (string) ($decision['bucket'] ?? '');
        $topic           = (string) ($decision['topic'] ?? '');
        $handoffPriority = (string) ($decision['handoff_priority'] ?? 'normal');
        $waitingMinutes  = (int) ($decision['waiting_minutes'] ?? 0);

        // Global guard: no_action_* decisions are never actionable
        if (str_starts_with($action, 'no_action_')) {
            return null;
        }

        // Rule 1 — hot_unassigned: hot_open + assign_now + no agent
        if ($bucket === 'hot_open' && $action === WhatsappOperationalDecisionService::ACTION_ASSIGN_NOW) {
            $sev = $waitingMinutes >= 60 ? 'critical' : 'high';
            return $this->makeAlert($decision, self::ALERT_HOT_UNASSIGNED, $sev, $agentNames,
                'Conversación HOT activa sin agente asignado.',
                'Asignar agente ahora.');
        }

        // Rule 2 — supervisor_sla_breach: supervisor_review action
        if ($action === WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW) {
            $sev = match (true) {
                $waitingMinutes >= 240 => 'critical',
                $waitingMinutes >= 120 => 'high',
                default               => 'medium',
            };
            return $this->makeAlert($decision, self::ALERT_SUPERVISOR_SLA, $sev, $agentNames,
                'Conversación asignada superó SLA sin respuesta humana.',
                'Supervisor debe revisar y reasignar si aplica.');
        }

        // Rule 3 — rescue_aging: only rescue_followup action (not converted/backlog)
        if ($bucket === 'rescue' && $action === WhatsappOperationalDecisionService::ACTION_RESCUE_FOLLOWUP) {
            $sev = match (true) {
                $waitingMinutes >= 5 * 24 * 60 => 'critical',
                $waitingMinutes >= 3 * 24 * 60 => 'high',
                default                        => 'medium',
            };
            return $this->makeAlert($decision, self::ALERT_RESCUE_AGING, $sev, $agentNames,
                'Conversación en seguimiento pendiente.',
                'Enviar seguimiento o cerrar si ya no aplica.');
        }

        // Rule 4 — no_availability_repeated: only for active buckets, repeat_count >= 2 required
        if ($topic === 'agenda_sin_disponibilidad'
            && in_array($bucket, ['hot_open', 'hot_needs_template', 'rescue'], true)
        ) {
            $convId      = (int) ($decision['conversation_id'] ?? 0);
            $repeatCount = $repeatMap[$convId] ?? 0;
            if ($repeatCount >= 2) {
                return $this->makeAlert($decision, self::ALERT_NO_AVAILABILITY, 'high', $agentNames,
                    'Paciente no encontró disponibilidad (repetido ' . $repeatCount . ' veces).',
                    'Ofrecer fecha, médico o sede alternativa.');
            }
        }

        // Rule 5 — ambiguous_urgent_faq: faq_escalada + urgent/critical handoff priority
        if ($topic === 'faq_escalada' && in_array($handoffPriority, ['urgent', 'critical'], true)) {
            return $this->makeAlert($decision, self::ALERT_AMBIGUOUS_FAQ, 'medium', $agentNames,
                'Consulta escalada urgente requiere revisión de intención.',
                'Clasificar si es captación, soporte o información general.');
        }

        return null;
    }

    /**
     * @param array<string,mixed> $decision
     * @param array<int,string> $agentNames
     * @return array<string,mixed>
     */
    private function makeAlert(array $decision, string $alertType, string $severity, array $agentNames, string $reason, string $suggestedAction): array
    {
        $assignedUserId = ($decision['assigned_user_id'] ?? null) !== null ? (int) $decision['assigned_user_id'] : null;

        return [
            'alert_type'         => $alertType,
            'severity'           => $severity,
            'conversation_id'    => (int) ($decision['conversation_id'] ?? 0),
            'wa_number'          => (string) ($decision['wa_number'] ?? ''),
            'handoff_id'         => (int) ($decision['handoff_id'] ?? 0),
            'topic'              => (string) ($decision['topic'] ?? ''),
            'topic_label'        => (string) ($decision['topic_label'] ?? ''),
            'category'           => (string) ($decision['category'] ?? ''),
            'category_label'     => (string) ($decision['category_label'] ?? ''),
            'bucket'             => (string) ($decision['bucket'] ?? ''),
            'recommended_action' => (string) ($decision['recommended_action'] ?? ''),
            'assigned_user_id'   => $assignedUserId,
            'assigned_user_name' => $assignedUserId !== null ? ($agentNames[$assignedUserId] ?? null) : null,
            'waiting_minutes'    => (int) ($decision['waiting_minutes'] ?? 0),
            'latest_inbound_at'  => $decision['latest_inbound_at'] ?? null,
            'reason'             => $reason,
            'suggested_action'   => $suggestedAction,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $decisions
     * @return array<int,string>
     */
    private function loadAgentNames(array $decisions): array
    {
        $userIds = array_filter(array_unique(array_map(
            static fn (array $d): int => (int) ($d['assigned_user_id'] ?? 0),
            $decisions
        )));

        if ($userIds === [] || !Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->whereIn('id', array_values($userIds))
            ->get(['id', 'nombre', 'first_name', 'last_name'])
            ->mapWithKeys(function (object $u): array {
                $name = trim((string) ($u->nombre ?? ''));
                if ($name === '') {
                    $name = trim(((string) ($u->first_name ?? '')) . ' ' . ((string) ($u->last_name ?? '')));
                }

                return [(int) $u->id => $name !== '' ? $name : 'Agente ' . $u->id];
            })
            ->all();
    }

    /**
     * Count how many times each conversation has had an agenda_sin_disponibilidad handoff in the last 7 days.
     *
     * @return array<int,int>
     */
    private function buildRepeatMap(): array
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return [];
        }

        $map = [];
        $rows = DB::table('whatsapp_handoffs')
            ->selectRaw('conversation_id, COUNT(*) as repeat_count')
            ->where('topic', 'agenda_sin_disponibilidad')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('conversation_id')
            ->get();

        foreach ($rows as $row) {
            $map[(int) $row->conversation_id] = (int) $row->repeat_count;
        }

        return $map;
    }
}
