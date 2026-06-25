<?php

namespace App\Modules\Whatsapp\Services;

use Carbon\CarbonInterface;

class WhatsappOperationalQueueService
{
    private const SLA_SUPERVISOR_MINUTES = 120;

    private const ASSIGNMENT_ACTIONS = [
        WhatsappOperationalDecisionService::ACTION_ASSIGN_NOW,
    ];

    private const SUPERVISOR_ACTIONS = [
        WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW,
    ];

    private const RESCUE_ACTIONS = [
        WhatsappOperationalDecisionService::ACTION_RESCUE_FOLLOWUP,
        WhatsappOperationalDecisionService::ACTION_SEND_TEMPLATE,
    ];

    public function __construct(
        private readonly WhatsappOperationalDecisionService $decisionService = new WhatsappOperationalDecisionService()
    ) {
    }

    /**
     * Build full queue payload for the given date.
     *
     * @param array{queue?:string,limit?:int} $options
     * @return array<string,mixed>
     */
    public function queues(CarbonInterface $asOf, array $options = []): array
    {
        $result      = $this->decisionService->evaluate($asOf);
        $decisions   = $result['decisions'];
        $queue       = strtolower(trim((string) ($options['queue'] ?? 'all')));
        $limit       = isset($options['limit']) && $options['limit'] > 0 ? (int) $options['limit'] : null;

        $assignmentItems = $this->buildAssignmentQueue($decisions);
        $supervisorItems = $this->buildSupervisorQueue($decisions);
        $rescueItems     = $this->buildRescueQueue($decisions);
        $summary         = $this->buildSummary($decisions, $assignmentItems, $supervisorItems, $rescueItems);

        // Items to return depend on queue filter
        [$returnedAssignment, $returnedSupervisor, $returnedRescue] = match ($queue) {
            'assignment' => [$assignmentItems, [], []],
            'supervisor' => [[], $supervisorItems, []],
            'rescue'     => [[], [], $rescueItems],
            default      => [$assignmentItems, $supervisorItems, $rescueItems],   // 'all'
        };

        $allItems = array_merge($returnedAssignment, $returnedSupervisor, $returnedRescue);

        // Apply limit after computing summary
        if ($limit !== null) {
            $returnedAssignment = array_slice($returnedAssignment, 0, $limit);
            $returnedSupervisor = array_slice($returnedSupervisor, 0, $limit);
            $returnedRescue     = array_slice($returnedRescue, 0, $limit);
            $allItems           = array_slice($allItems, 0, $limit);
        }

        $payload = [
            'date'         => $result['date'],
            'generated_at' => $result['generated_at'],
            'summary'      => $summary,
        ];

        if ($queue === 'assignment') {
            $payload['queue']   = 'assignment';
            $payload['summary'] = $this->assignmentSummary($assignmentItems);
            $payload['items']   = $returnedAssignment;
        } elseif ($queue === 'supervisor') {
            $payload['queue']   = 'supervisor';
            $payload['summary'] = $this->supervisorSummary($supervisorItems);
            $payload['items']   = $returnedSupervisor;
        } elseif ($queue === 'rescue') {
            $payload['queue']   = 'rescue';
            $payload['summary'] = $this->rescueSummary($rescueItems);
            $payload['items']   = $returnedRescue;
        } else {
            $payload['queues'] = [
                'assignment' => $returnedAssignment,
                'supervisor' => $returnedSupervisor,
                'rescue'     => $returnedRescue,
            ];
            $payload['items'] = $allItems;
        }

        return $payload;
    }

    /**
     * @param array<int,array<string,mixed>> $decisions
     * @return array<int,array<string,mixed>>
     */
    public function buildAssignmentQueue(array $decisions): array
    {
        $items = array_values(array_filter(
            $decisions,
            fn (array $d): bool => in_array($d['recommended_action'] ?? '', self::ASSIGNMENT_ACTIONS, true)
        ));

        $items = array_map(fn (array $d): array => $this->enrichAssignmentItem($d), $items);

        usort($items, $this->assignmentSortFn());

        return $items;
    }

    /**
     * @param array<int,array<string,mixed>> $decisions
     * @return array<int,array<string,mixed>>
     */
    public function buildSupervisorQueue(array $decisions): array
    {
        $items = array_values(array_filter(
            $decisions,
            fn (array $d): bool => in_array($d['recommended_action'] ?? '', self::SUPERVISOR_ACTIONS, true)
        ));

        $items = array_map(fn (array $d): array => $this->enrichSupervisorItem($d), $items);

        usort($items, $this->supervisorSortFn());

        return $items;
    }

    /**
     * @param array<int,array<string,mixed>> $decisions
     * @return array<int,array<string,mixed>>
     */
    public function buildRescueQueue(array $decisions): array
    {
        $items = array_values(array_filter(
            $decisions,
            fn (array $d): bool => in_array($d['recommended_action'] ?? '', self::RESCUE_ACTIONS, true)
        ));

        $items = array_map(fn (array $d): array => $this->enrichRescueItem($d), $items);

        usort($items, $this->rescueSortFn());

        return $items;
    }

    /**
     * @param array<int,array<string,mixed>> $decisions
     * @param array<int,array<string,mixed>> $assignmentItems
     * @param array<int,array<string,mixed>> $supervisorItems
     * @param array<int,array<string,mixed>> $rescueItems
     * @return array<string,mixed>
     */
    private function buildSummary(array $decisions, array $assignmentItems, array $supervisorItems, array $rescueItems): array
    {
        $converted      = 0;
        $alreadyHandled = 0;
        $backlog        = 0;
        $lost           = 0;

        foreach ($decisions as $d) {
            match ($d['recommended_action'] ?? '') {
                WhatsappOperationalDecisionService::ACTION_NO_ACTION_CONVERTED  => $converted++,
                WhatsappOperationalDecisionService::ACTION_ALREADY_HANDLED      => $alreadyHandled++,
                WhatsappOperationalDecisionService::ACTION_HOLD_BACKLOG         => $backlog++,
                WhatsappOperationalDecisionService::ACTION_NO_ACTION_LOST       => $lost++,
                default => null,
            };
        }

        return [
            'total_decisions'  => count($decisions),
            'assignment_queue' => $this->assignmentSummary($assignmentItems),
            'supervisor_queue' => $this->supervisorSummary($supervisorItems),
            'rescue_queue'     => $this->rescueSummary($rescueItems),
            'no_action'        => [
                'converted'        => $converted,
                'already_handled'  => $alreadyHandled,
                'backlog'          => $backlog,
                'lost'             => $lost,
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private function assignmentSummary(array $items): array
    {
        $highRisk      = 0;
        $highPriority  = 0;
        $autoAssign    = 0;

        foreach ($items as $item) {
            if (($item['risk_level'] ?? '') === 'high') {
                $highRisk++;
            }
            if (($item['priority'] ?? '') === 'high') {
                $highPriority++;
            }
            if ((bool) ($item['eligible_for_autoassign'] ?? false)) {
                $autoAssign++;
            }
        }

        return [
            'total'                  => count($items),
            'high_risk'              => $highRisk,
            'high_priority'          => $highPriority,
            'eligible_for_autoassign' => $autoAssign,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private function supervisorSummary(array $items): array
    {
        $highRisk   = 0;
        $overSla    = 0;
        $highPriority = 0;
        $alertEligible = 0;

        foreach ($items as $item) {
            if (($item['risk_level'] ?? '') === 'high') {
                $highRisk++;
            }
            if (($item['sla_overdue_minutes'] ?? null) !== null && (int) $item['sla_overdue_minutes'] > 0) {
                $overSla++;
            }
            if (($item['priority'] ?? '') === 'high') {
                $highPriority++;
            }
            if ((bool) ($item['eligible_for_supervisor_alert'] ?? false)) {
                $alertEligible++;
            }
        }

        // When sla_overdue_minutes is not yet computable, fall back to total
        // TODO: populate sla_overdue_minutes from whatsapp_messages/handoff data
        if ($overSla === 0 && count($items) > 0) {
            $overSla = count($items);
        }

        return [
            'total'                      => count($items),
            'high_risk'                  => $highRisk,
            'high_priority'              => $highPriority,
            'over_sla'                   => $overSla,
            'eligible_for_supervisor_alert' => $alertEligible,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private function rescueSummary(array $items): array
    {
        $rescueFollowup      = 0;
        $sendTemplate        = 0;
        $highRisk            = 0;
        $mediumRisk          = 0;

        foreach ($items as $item) {
            if (($item['recommended_action'] ?? '') === WhatsappOperationalDecisionService::ACTION_RESCUE_FOLLOWUP) {
                $rescueFollowup++;
            }
            if (($item['recommended_action'] ?? '') === WhatsappOperationalDecisionService::ACTION_SEND_TEMPLATE) {
                $sendTemplate++;
            }
            match ($item['risk_level'] ?? '') {
                'high'   => $highRisk++,
                'medium' => $mediumRisk++,
                default  => null,
            };
        }

        return [
            'total'                   => count($items),
            'rescue_followup'         => $rescueFollowup,
            'send_template_or_review' => $sendTemplate,
            'high_risk'               => $highRisk,
            'medium_risk'             => $mediumRisk,
        ];
    }

    /**
     * @param array<string,mixed> $d
     * @return array<string,mixed>
     */
    private function enrichAssignmentItem(array $d): array
    {
        return [
            'conversation_id'               => (int) ($d['conversation_id'] ?? 0),
            'bucket'                        => (string) ($d['bucket'] ?? ''),
            'recommended_action'            => (string) ($d['recommended_action'] ?? ''),
            'priority'                      => (string) ($d['priority'] ?? ''),
            'risk_level'                    => (string) ($d['risk_level'] ?? ''),
            'opportunity_level'             => (string) ($d['opportunity_level'] ?? ''),
            'eligible_for_autoassign'       => (bool) ($d['eligible_for_autoassign'] ?? false),
            'eligible_for_rescue'           => (bool) ($d['eligible_for_rescue'] ?? false),
            'eligible_for_supervisor_alert' => (bool) ($d['eligible_for_supervisor_alert'] ?? false),
            // TODO: populate assigned_user_id from whatsapp_conversations join
            'assigned_user_id'              => null,
            // TODO: populate assigned_user_name from users table join
            'assigned_user_name'            => null,
            // TODO: populate waiting_minutes from queued_at → now diff
            'waiting_minutes'               => null,
            // TODO: populate last_human_response_at from whatsapp_messages (outbound, after handoff)
            'last_human_response_at'        => null,
            // TODO: populate last_patient_message_at from whatsapp_messages (inbound latest)
            'last_patient_message_at'       => null,
            'has_attributed_booking'        => (bool) ($d['has_attributed_booking'] ?? false),
            'has_primary_clinical_appointment' => (bool) ($d['has_primary_clinical_appointment'] ?? false),
            'reason'                        => (string) ($d['reason'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $d
     * @return array<string,mixed>
     */
    private function enrichSupervisorItem(array $d): array
    {
        return [
            'conversation_id'              => (int) ($d['conversation_id'] ?? 0),
            'bucket'                       => (string) ($d['bucket'] ?? ''),
            'recommended_action'           => (string) ($d['recommended_action'] ?? ''),
            'priority'                     => (string) ($d['priority'] ?? ''),
            'risk_level'                   => (string) ($d['risk_level'] ?? ''),
            'opportunity_level'            => (string) ($d['opportunity_level'] ?? ''),
            'eligible_for_supervisor_alert' => (bool) ($d['eligible_for_supervisor_alert'] ?? false),
            // TODO: populate assigned_user_id from whatsapp_conversations join
            'assigned_user_id'             => null,
            // TODO: populate assigned_user_name from users table join
            'assigned_user_name'           => null,
            // TODO: populate waiting_minutes from queued_at → now diff via conversation rows
            'waiting_minutes'              => null,
            'sla_minutes'                  => self::SLA_SUPERVISOR_MINUTES,
            // TODO: populate sla_overdue_minutes once waiting_minutes is available
            'sla_overdue_minutes'          => null,
            // TODO: populate last_human_response_at from whatsapp_messages (outbound, after handoff)
            'last_human_response_at'       => null,
            // TODO: populate last_patient_message_at from whatsapp_messages (inbound latest)
            'last_patient_message_at'      => null,
            'has_attributed_booking'       => (bool) ($d['has_attributed_booking'] ?? false),
            'reason'                       => (string) ($d['reason'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $d
     * @return array<string,mixed>
     */
    private function enrichRescueItem(array $d): array
    {
        return [
            'conversation_id'               => (int) ($d['conversation_id'] ?? 0),
            'bucket'                        => (string) ($d['bucket'] ?? ''),
            'recommended_action'            => (string) ($d['recommended_action'] ?? ''),
            'priority'                      => (string) ($d['priority'] ?? ''),
            'risk_level'                    => (string) ($d['risk_level'] ?? ''),
            'opportunity_level'             => (string) ($d['opportunity_level'] ?? ''),
            'eligible_for_rescue'           => (bool) ($d['eligible_for_rescue'] ?? false),
            // TODO: populate waiting_minutes from queued_at → now diff via conversation rows
            'waiting_minutes'               => null,
            // TODO: populate last_patient_message_at from whatsapp_messages (inbound latest)
            'last_patient_message_at'       => null,
            // TODO: populate last_human_response_at from whatsapp_messages (outbound latest)
            'last_human_response_at'        => null,
            'has_attributed_booking'        => (bool) ($d['has_attributed_booking'] ?? false),
            'has_primary_clinical_appointment' => (bool) ($d['has_primary_clinical_appointment'] ?? false),
            'reason'                        => (string) ($d['reason'] ?? ''),
        ];
    }

    /**
     * @return callable(array<string,mixed>,array<string,mixed>):int
     */
    private function assignmentSortFn(): callable
    {
        $riskOrder = ['high' => 0, 'medium' => 1, 'low' => 2, 'closed' => 3];
        $priOrder  = ['high' => 0, 'medium' => 1, 'normal' => 2, 'low' => 3];

        return function (array $a, array $b) use ($riskOrder, $priOrder): int {
            $riskDiff = ($riskOrder[$a['risk_level'] ?? 'low'] ?? 9) <=> ($riskOrder[$b['risk_level'] ?? 'low'] ?? 9);
            if ($riskDiff !== 0) {
                return $riskDiff;
            }
            $priDiff = ($priOrder[$a['priority'] ?? 'low'] ?? 9) <=> ($priOrder[$b['priority'] ?? 'low'] ?? 9);
            if ($priDiff !== 0) {
                return $priDiff;
            }

            return (int) ($a['conversation_id'] ?? 0) <=> (int) ($b['conversation_id'] ?? 0);
        };
    }

    /**
     * @return callable(array<string,mixed>,array<string,mixed>):int
     */
    private function supervisorSortFn(): callable
    {
        $riskOrder = ['high' => 0, 'medium' => 1, 'low' => 2, 'closed' => 3];
        $priOrder  = ['high' => 0, 'medium' => 1, 'normal' => 2, 'low' => 3];

        return function (array $a, array $b) use ($riskOrder, $priOrder): int {
            $riskDiff = ($riskOrder[$a['risk_level'] ?? 'low'] ?? 9) <=> ($riskOrder[$b['risk_level'] ?? 'low'] ?? 9);
            if ($riskDiff !== 0) {
                return $riskDiff;
            }
            $priDiff = ($priOrder[$a['priority'] ?? 'low'] ?? 9) <=> ($priOrder[$b['priority'] ?? 'low'] ?? 9);
            if ($priDiff !== 0) {
                return $priDiff;
            }

            // TODO: sort by sla_overdue_minutes desc once available
            return (int) ($a['conversation_id'] ?? 0) <=> (int) ($b['conversation_id'] ?? 0);
        };
    }

    /**
     * @return callable(array<string,mixed>,array<string,mixed>):int
     */
    private function rescueSortFn(): callable
    {
        $riskOrder = ['high' => 0, 'medium' => 1, 'low' => 2, 'closed' => 3];
        $priOrder  = ['high' => 0, 'medium' => 1, 'normal' => 2, 'low' => 3];

        return function (array $a, array $b) use ($riskOrder, $priOrder): int {
            $riskDiff = ($riskOrder[$a['risk_level'] ?? 'low'] ?? 9) <=> ($riskOrder[$b['risk_level'] ?? 'low'] ?? 9);
            if ($riskDiff !== 0) {
                return $riskDiff;
            }
            $priDiff = ($priOrder[$a['priority'] ?? 'low'] ?? 9) <=> ($priOrder[$b['priority'] ?? 'low'] ?? 9);
            if ($priDiff !== 0) {
                return $priDiff;
            }

            return (int) ($a['conversation_id'] ?? 0) <=> (int) ($b['conversation_id'] ?? 0);
        };
    }
}
